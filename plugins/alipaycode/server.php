<?php
if (substr(php_sapi_name(), 0, 3) != 'cli') {
	die("This Programe can only be run in CLI mode");
}
@chdir(dirname(__FILE__));
$nosession = true;
include("../../includes/common.php");

$onlyId = isset($argv[1]) ? intval($argv[1]) : 0;
if(isset($argv[1]) && $onlyId <= 0){
	exit('支付通道ID不正确');
}

function alipaycode_inflight($channelId, $subid = 0){
	global $DB;
	$sql = "SELECT 1 FROM pre_order WHERE channel=:channel AND status=0 AND addtime>=DATE_SUB(NOW(), INTERVAL 8 MINUTE)";
	$bind = [':channel' => intval($channelId)];
	if($subid > 0){
		$sql .= " AND subchannel=:subchannel";
		$bind[':subchannel'] = intval($subid);
	}
	return (bool)$DB->getColumn($sql.' LIMIT 1', $bind);
}

function alipaycode_targets($onlyId = 0){
	global $DB;
	$sql = "SELECT id,status FROM pre_channel WHERE plugin='alipaycode'";
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
				if(!((($enabled || $onlyId > 0) && $subOn) || alipaycode_inflight($channelId, $subid))) continue;
				$subch = \lib\Channel::getSub($subid);
				if($subch) $targets[] = $subch;
			}
			continue;
		}
		if($enabled || $onlyId > 0 || alipaycode_inflight($channelId)){
			$targets[] = $channel;
		}
	}
	return $targets;
}

function alipaycode_scan($channel){
	global $DB;
	$subid = intval($channel['subid'] ?? 0);
	$sql = $subid > 0 ? " AND subchannel='{$subid}'" : '';
	$lock = 'apcs_'.intval($channel['id']).'_'.$subid;
	$got = $DB->getColumn("SELECT GET_LOCK('{$lock}', 0)");
	if($got != 1) return 0;
	try{
		$list = $DB->getAll("SELECT trade_no,realmoney FROM pre_order WHERE channel=:channel{$sql} AND status=0 AND addtime>=DATE_SUB(NOW(), INTERVAL 8 MINUTE)", [
			':channel' => intval($channel['id']),
		]);
		if(empty($list)) return 0;
		$alipay_config = require(PLUGIN_ROOT.$channel['plugin'].'/inc/config.php');
		$aop = new \Alipay\AlipayBillService($alipay_config);
		$start_time = date('Y-m-d H:i:s', time()-180);
		$end_time = date('Y-m-d H:i:s', time()+60);
		$result = $aop->accountlogQuery($start_time, $end_time, 1, 2000);
		$details = $result['detail_list'] ?? [];
		if(empty($details)) return 0;
		$matched = 0;
		foreach($details as $item){
			if(!isset($item['trans_memo']) || !isset($item['trans_amount'])) continue;
			$trade_no = str_replace('请勿添加备注-', '', $item['trans_memo']);
			$money = $item['trans_amount'];
			$orders = array_filter($list, function($v) use($trade_no, $money){
				return $v['trade_no'] == $trade_no && $v['realmoney'] == $money;
			});
			if(empty($orders)) continue;
			$order = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE trade_no=:trade_no limit 1", [':trade_no'=>$trade_no]);
			if(!$order || $order['status'] != 0) continue;
			$order['plugin'] = $channel['plugin'];
			$buyer = empty($order['buyer']) ? ($item['other_account'] ?? null) : null;
			processNotify($order, $item['alipay_order_no'], $buyer);
			$matched++;
		}
		return $matched;
	}finally{
		$DB->getColumn("SELECT RELEASE_LOCK('{$lock}')");
	}
}

while(true){
	$now = time();
	$targets = alipaycode_targets($onlyId);
	if(empty($targets)){
		echo '暂无需要扫账的 alipaycode 通道'.PHP_EOL;
	}
	foreach($targets as $channel){
		$label = $channel['name'].'#'.$channel['id'];
		if(!empty($channel['subid'])) $label .= '/'.$channel['subid'];
		try{
			$n = alipaycode_scan($channel);
			if($n > 0) echo $label.' 本轮确认'.$n.'笔订单'.PHP_EOL;
		}catch(Exception $e){
			echo $label.' 查询账务明细失败，'.$e->getMessage().PHP_EOL;
		}
	}
	$cost = time() - $now;
	if($cost < 5){
		sleep(5 - $cost);
	}
}
echo 'stop!';
