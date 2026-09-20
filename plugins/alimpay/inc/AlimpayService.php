<?php

class AlimpayService
{
	const CHECKOUT_SECONDS = 300;
	const MONITOR_SECONDS = 600;
	const BILL_LAG_SECONDS = 60;
	const MAX_OFFSET_CENTS = 99;

	public static function occupySeconds(){
		return self::MONITOR_SECONDS + self::BILL_LAG_SECONDS;
	}

	public static function moneyToCents($money){
		$raw = trim((string)$money);
		if(!preg_match('/^(0|[1-9]\d*)(\.\d{1,2})?$/', $raw)){
			throw new Exception('金额格式错误');
		}
		[$yuan, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
		return intval($yuan) * 100 + intval(str_pad($fraction, 2, '0'));
	}

	public static function centsToMoney($cents){
		return number_format(intval($cents) / 100, 2, '.', '');
	}

	public static function parseExt($raw){
		if(empty($raw)) return null;
		$data = is_array($raw) ? $raw : @unserialize($raw);
		if(!is_array($data) || empty($data['mode']) || empty($data['payable'])) return null;
		return $data;
	}

	public static function collectionMode($channel){
		return (!empty($channel['appswitch']) && $channel['appswitch'] == 1) ? 'transfer' : 'business_qr';
	}

	public static function isImageSource($value){
		$value = trim((string)$value);
		if($value === '') return false;
		if(strpos($value, 'data:image/') === 0) return true;
		if(stripos($value, 'alipays:') === 0 || stripos($value, 'qr.alipay.com') !== false) return false;
		return (bool)preg_match('/^https?:\/\//i', $value);
	}

	public static function createTransferUri($userId, $amount, $memo, $layer = 2){
		$params = http_build_query([
			'appId' => '09999988',
			'actionType' => 'toAccount',
			'goBack' => 'NO',
			'amount' => self::centsToMoney(self::moneyToCents($amount)),
			'userId' => $userId,
			'memo' => $memo,
		], '', '&', PHP_QUERY_RFC3986);
		$first = 'alipays://platformapi/startapp?'.$params;
		$second = 'https://render.alipay.com/p/s/i?scheme='.rawurlencode($first);
		$third = 'alipays://platformapi/startapp?'.http_build_query([
			'appId' => '20000218',
			'url' => $second,
		], '', '&', PHP_QUERY_RFC3986);
		$layer = intval($layer);
		if($layer === 1) return $first;
		if($layer === 3) return $third;
		return $second;
	}

	public static function matchEvent(array $event, array $orderPay){
		$direction = trim((string)($event['direction'] ?? ''));
		if($direction !== '收入') return false;
		if(self::moneyToCents($event['amount'] ?? '0') !== self::moneyToCents($orderPay['payable'])) return false;
		$occurred = strtotime((string)($event['occurred_at'] ?? ''));
		$created = strtotime((string)($orderPay['addtime'] ?? ''));
		if($occurred <= 0 || $created <= 0) return false;
		if($occurred < $created || $occurred > $created + self::MONITOR_SECONDS) return false;
		if(($orderPay['mode'] ?? '') === 'transfer'){
			return trim((string)($event['memo'] ?? '')) === (string)$orderPay['trade_no'];
		}
		return true;
	}

	public static function normalizeEvent(array $item){
		$logId = trim((string)($item['account_log_id'] ?? $item['accountLogId'] ?? ''));
		$amount = str_replace(',', '', (string)($item['trans_amount'] ?? $item['transAmount'] ?? ''));
		if($amount === '' || !isset($amount[0])) return null;
		if($amount[0] === '-') return null;
		try{
			self::moneyToCents($amount);
		}catch(Exception $e){
			return null;
		}
		$occurred = trim((string)($item['trans_dt'] ?? $item['transDt'] ?? ''));
		return [
			'account_log_id' => $logId,
			'amount' => $amount,
			'memo' => trim((string)($item['trans_memo'] ?? $item['transMemo'] ?? '')),
			'direction' => trim((string)($item['direction'] ?? '')),
			'occurred_at' => $occurred,
			'alipay_order_no' => trim((string)($item['alipay_order_no'] ?? $item['alipayOrderNo'] ?? '')),
			'other_account' => trim((string)($item['other_account'] ?? $item['otherAccount'] ?? '')),
		];
	}

	public static function allocatePayable($channelId, $subid, $requestedMoney, $maxOffset = self::MAX_OFFSET_CENTS){
		global $DB;
		$requested = self::moneyToCents($requestedMoney);
		$maxOffset = max(1, min(self::MAX_OFFSET_CENTS, intval($maxOffset)));
		$occupy = self::occupySeconds();
		$sql = "SELECT ext FROM pre_order WHERE channel=:channel AND status=0 AND addtime>=DATE_SUB(NOW(), INTERVAL {$occupy} SECOND)";
		$bind = [':channel'=>intval($channelId)];
		if($subid > 0){
			$sql .= " AND subchannel=:subchannel";
			$bind[':subchannel'] = intval($subid);
		}
		$rows = $DB->getAll($sql, $bind) ?: [];
		$occupied = [];
		foreach($rows as $row){
			$ext = self::parseExt($row['ext'] ?? null);
			if($ext) $occupied[self::moneyToCents($ext['payable'])] = true;
		}
		for($offset = 1; $offset <= $maxOffset; $offset++){
			$candidate = $requested + $offset;
			if(empty($occupied[$candidate])){
				return self::centsToMoney($candidate);
			}
		}
		throw new Exception('当前相同金额的待支付订单过多，请稍后再试');
	}

	public static function buildExt($channel, $order){
		$mode = self::collectionMode($channel);
		if($mode === 'transfer'){
			if(empty($channel['appmchid'])){
				throw new Exception('转账模式尚未配置支付宝用户ID');
			}
			return [
				'mode' => 'transfer',
				'payable' => self::centsToMoney(self::moneyToCents($order['realmoney'])),
			];
		}
		if(empty($channel['appurl'])){
			throw new Exception('经营码尚未配置');
		}
		$subid = intval($order['subchannel'] ?? 0);
		return [
			'mode' => 'business_qr',
			'payable' => self::allocatePayable($channel['id'], $subid, $order['realmoney']),
		];
	}

	public static function ensurePayData($order, $channel){
		global $DB;
		$created = strtotime((string)($order['addtime'] ?? ''));
		if($created <= 0 || time() > $created + self::MONITOR_SECONDS){
			throw new Exception('订单已超时，请重新发起支付');
		}
		$ext = self::parseExt($order['ext'] ?? null);
		if($ext) return $ext;
		$subid = intval($order['subchannel'] ?? 0);
		$lock = 'alimpay_amt_'.intval($channel['id']).'_'.$subid;
		$got = $DB->getColumn("SELECT GET_LOCK('{$lock}', 10)");
		if($got != 1){
			throw new Exception('金额分配繁忙，请稍后重试');
		}
		try{
			$raw = $DB->getColumn("SELECT ext FROM pre_order WHERE trade_no=:trade_no", [':trade_no'=>$order['trade_no']]);
			$ext = self::parseExt($raw);
			if($ext) return $ext;
			return \lib\Payment::lockPayData($order['trade_no'], function() use($order, $channel){
				return self::buildExt($channel, $order);
			});
		}finally{
			$DB->getColumn("SELECT RELEASE_LOCK('{$lock}')");
		}
	}

	public static function displayData($order, $channel, $ext){
		$mode = $ext['mode'];
		$payable = $ext['payable'];
		if($mode === 'transfer'){
			$uri = self::createTransferUri($channel['appmchid'], $payable, $order['trade_no'], 2);
			return [
				'mode' => $mode,
				'payable' => $payable,
				'requested' => self::centsToMoney(self::moneyToCents($order['realmoney'])),
				'qr_type' => 'content',
				'qr_value' => $uri,
				'open_url' => $uri,
			];
		}
		$source = trim((string)$channel['appurl']);
		$image = self::isImageSource($source);
		return [
			'mode' => $mode,
			'payable' => $payable,
			'requested' => self::centsToMoney(self::moneyToCents($order['realmoney'])),
			'qr_type' => $image ? 'image' : 'content',
			'qr_value' => $source,
			'open_url' => $image ? '' : $source,
		];
	}

	public static function scanFilter($channel){
		$subid = intval($channel['subid'] ?? 0);
		$sql = $subid > 0 ? " AND subchannel='{$subid}'" : '';
		return [$sql, $subid];
	}

	public static function hasInflight($channelId, $subid = 0){
		global $DB;
		$occupy = self::occupySeconds();
		$sql = "SELECT 1 FROM pre_order WHERE channel=:channel AND status=0 AND addtime>=DATE_SUB(NOW(), INTERVAL {$occupy} SECOND)";
		$bind = [':channel' => intval($channelId)];
		if($subid > 0){
			$sql .= " AND subchannel=:subchannel";
			$bind[':subchannel'] = intval($subid);
		}
		return (bool)$DB->getColumn($sql.' LIMIT 1', $bind);
	}

	public static function listScanTargets($onlyId = 0){
		global $DB;
		$sql = "SELECT id,status FROM pre_channel WHERE plugin='alimpay'";
		$bind = [];
		if($onlyId > 0){
			$sql .= " AND id=:id";
			$bind[':id'] = $onlyId;
		}
		$rows = $DB->getAll($sql, $bind) ?: [];
		$targets = [];
		foreach($rows as $row){
			$channelId = intval($row['id']);
			$enabled = intval($row['status']) === 1;
			$channel = \lib\Channel::get($channelId);
			if(!$channel) continue;
			if(!empty($channel['apptoken']) && substr($channel['apptoken'], 0, 1) == '['){
				$subs = $DB->getAll("SELECT id,status FROM pre_subchannel WHERE channel=:channel", [':channel'=>$channelId]) ?: [];
				foreach($subs as $sub){
					$subid = intval($sub['id']);
					$subOn = intval($sub['status']) === 1;
					if(!((($enabled || $onlyId > 0) && $subOn) || self::hasInflight($channelId, $subid))) continue;
					$subch = \lib\Channel::getSub($subid);
					if($subch) $targets[] = $subch;
				}
				continue;
			}
			if($enabled || $onlyId > 0 || self::hasInflight($channelId)){
				$targets[] = $channel;
			}
		}
		return $targets;
	}

	public static function paidJumpUrl($row){
		if($row['status'] == 2 || !empty($row['black'])){
			return '/payerr.html';
		}
		if(!empty($row['endtime']) && time() - strtotime($row['endtime']) > 300){
			return '/payok.html';
		}
		$url = creat_callback($row);
		return $url['return'];
	}

	public static function scanOnce($channel){
		global $DB;
		[$sql, $subid] = self::scanFilter($channel);
		$lock = 'amps_'.intval($channel['id']).'_'.$subid;
		$got = $DB->getColumn("SELECT GET_LOCK('{$lock}', 0)");
		if($got != 1) return 0;
		try{
			return self::scanChannel($channel, $sql);
		}finally{
			$DB->getColumn("SELECT RELEASE_LOCK('{$lock}')");
		}
	}

	public static function usedTradeNos($channelId, $sql = ''){
		global $DB;
		$window = self::occupySeconds();
		$rows = $DB->getAll("SELECT api_trade_no FROM pre_order WHERE channel=:channel{$sql} AND status>=1 AND api_trade_no<>'' AND addtime>=DATE_SUB(NOW(), INTERVAL {$window} SECOND)", [
			':channel' => intval($channelId),
		]) ?: [];
		$used = [];
		foreach($rows as $row){
			$id = trim((string)($row['api_trade_no'] ?? ''));
			if($id !== '') $used[$id] = true;
		}
		return $used;
	}

	public static function eventAlreadyUsed(array $event, array $used){
		foreach([$event['account_log_id'] ?? '', $event['alipay_order_no'] ?? ''] as $id){
			$id = trim((string)$id);
			if($id !== '' && isset($used[$id])) return true;
		}
		return false;
	}

	public static function scanChannel($channel, $sql = ''){
		global $DB;
		$occupy = self::occupySeconds();
		$list = $DB->getAll("SELECT trade_no,realmoney,addtime,ext,buyer FROM pre_order WHERE channel=:channel{$sql} AND status=0 AND addtime>=DATE_SUB(NOW(), INTERVAL {$occupy} SECOND)", [
			':channel' => intval($channel['id']),
		]);
		if(empty($list)) return 0;

		$candidates = [];
		$earliest = time();
		foreach($list as $row){
			$ext = self::parseExt($row['ext'] ?? null);
			if(!$ext) continue;
			$created = strtotime($row['addtime']);
			if($created > 0 && $created < $earliest) $earliest = $created;
			$candidates[] = [
				'trade_no' => $row['trade_no'],
				'addtime' => $row['addtime'],
				'mode' => $ext['mode'],
				'payable' => $ext['payable'],
			];
		}
		if(empty($candidates)) return 0;

		$used = self::usedTradeNos($channel['id'], $sql);
		$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
		$aop = new \Alipay\AlipayBillService($alipay_config);
		$start_time = date('Y-m-d H:i:s', $earliest);
		$end_time = date('Y-m-d H:i:s', time() + self::BILL_LAG_SECONDS);
		$page_no = 1;
		$page_size = 2000;
		$matched = 0;
		$matched_logs = [];
		while(true){
			$result = $aop->accountlogQuery($start_time, $end_time, $page_no, $page_size);
			$details = $result['detail_list'] ?? [];
			if(empty($details)) break;
			foreach($details as $item){
				$event = self::normalizeEvent($item);
				if(!$event) continue;
				if(self::eventAlreadyUsed($event, $used)) continue;
				$logKey = $event['account_log_id'] !== '' ? $event['account_log_id'] : ($event['alipay_order_no'].'|'.$event['amount'].'|'.$event['occurred_at']);
				if(isset($matched_logs[$logKey])) continue;
				foreach($candidates as $index => $orderPay){
					if(!self::matchEvent($event, $orderPay)) continue;
					$order = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE trade_no=:trade_no limit 1", [':trade_no'=>$orderPay['trade_no']]);
					if(!$order || $order['status'] != 0) continue;
					$order['plugin'] = $channel['plugin'];
					$buyer = empty($order['buyer']) ? ($event['other_account'] ?: null) : null;
					$api_trade_no = $event['alipay_order_no'] !== '' ? $event['alipay_order_no'] : $event['account_log_id'];
					processNotify($order, $api_trade_no, $buyer);
					$matched_logs[$logKey] = true;
					if($api_trade_no !== '') $used[$api_trade_no] = true;
					if($event['account_log_id'] !== '') $used[$event['account_log_id']] = true;
					unset($candidates[$index]);
					$matched++;
					break;
				}
				if(empty($candidates)) break;
			}
			if(empty($candidates) || count($details) < $page_size) break;
			$page_no++;
		}
		return $matched;
	}
}
