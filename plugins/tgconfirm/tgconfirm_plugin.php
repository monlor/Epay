<?php

class tgconfirm_plugin
{
	static public $info = [
		'name'        => 'tgconfirm',
		'showname'    => 'Telegram手动确认',
		'author'      => 'Epay',
		'link'        => '',
		'types'       => ['alipay', 'alipay_manual', 'wxpay', 'wxpay_manual'],
		'inputs' => [
			'appkey' => [
				'name' => 'Telegram Bot Token',
				'type' => 'input',
				'note' => '从 @BotFather 获取，格式 123456:AAH...',
			],
			'appmchid' => [
				'name' => 'Telegram Chat ID',
				'type' => 'input',
				'note' => '管理员私聊或群 ID，机器人需已能给该对话发消息',
			],
			'appsecret' => [
				'name' => 'Webhook 密钥',
				'type' => 'input',
				'note' => '必填随机字符串，Telegram secret_token，建议 16 位以上',
			],
			'paytimeout' => [
				'name' => '支付超时秒数',
				'type' => 'input',
				'note' => '打开支付页后未点「我已支付」则超时。默认 600（10分钟）。超时后金额释放，需重新下单',
			],
			'timeout' => [
				'name' => '确认超时秒数',
				'type' => 'input',
				'note' => '用户点「我已支付」后，超时不可确认。默认 86400（1天），最大 172800（48小时）。超过上限按 48 小时计。此时间内继续占用唯一金额',
			],
			'automin' => [
				'name' => '自动确认分钟',
				'type' => 'input',
				'note' => '用户点「我已支付」后，管理员超时未点确认则自动入账。默认 10，填 0 关闭',
			],
			'autoproof' => [
				'name' => '自动确认需截图',
				'type' => 'select',
				'options' => [1 => '开启（未上传截图不自动确认）', 0 => '关闭（无截图也可自动确认）'],
			],
			'appid' => [
				'name' => '支付宝收款 UID',
				'type' => 'input',
				'note' => '2088 开头 16 位。未填收款码时用来生成二维码。留空则只显示下方收款码',
			],
			'alipayqr' => [
				'name' => '支付宝收款码',
				'type' => 'input',
				'note' => '图片 URL 或二维码内容。以 https://qr.alipay.com 开头时，手机可点按钮直接打开该收款码',
			],
			'appurl' => [
				'name' => '微信收款码',
				'type' => 'input',
				'note' => '图片 URL 或二维码内容。手机页会提示先保存二维码，再打开微信扫码',
			],
		],
		'select' => null,
		'note' => '<p>用户按页面金额转账后点「我已支付」才会给 Telegram 发确认按钮，可选手写说明和上传截图（只转发到 TG，不保存）。确认或自动入账后该金额立即释放。</p><p>微信不跳转付款页，手机提示长按保存二维码再打开微信扫一扫。支付宝收款码为 https://qr.alipay.com/... 时，手机可点按钮直接打开该收款码；其它情况同样是保存二维码再扫。</p><p>支付超时控制开页未申报的等待时间；点「我已支付」后改走确认超时。确认超时应大于自动确认分钟，且不超过 48 小时（系统会清理超过 48 小时的未支付订单）。</p><p>支持调用值：alipay / alipay_manual / wxpay / wxpay_manual。manual 与原方式页面相同，但是独立支付方式，可各绑一条通道，API 用 type 区分。</p><p>同一 Bot Token 的多条 tgconfirm 通道自动共用一个 Webhook（绑到 ID 最小的那条），按订单关联通道。微信/支付宝可共用一个机器人。Webhook 密钥建议填一样。</p><p>默认须上传截图才自动确认。Webhook：<a href="[siteurl]pay/webhook/[channel]/" target="_blank" rel="noopener noreferrer">[siteurl]pay/webhook/[channel]/</a>　绑定：<a href="[siteurl]pay/setwebhook/[channel]/" target="_blank" rel="noopener noreferrer">点击手动绑定</a>　监控：<a href="[siteurl]pay/autocron/[channel]/" target="_blank" rel="noopener noreferrer">[siteurl]pay/autocron/[channel]/</a></p>',
		'bindwxmp' => false,
		'bindwxa' => false,
	];

	static private function payTimeout()
	{
		global $channel;
		$t = intval($channel['paytimeout'] ?? 0);
		return $t > 0 ? $t : 600;
	}

	static private function confirmTimeout()
	{
		global $channel;
		$t = intval($channel['timeout'] ?? 0);
		if ($t <= 0) $t = 86400;
		if ($t > 172800) $t = 172800;
		return $t;
	}

	static private function autoMinutes()
	{
		global $channel;
		if (!isset($channel['automin']) || $channel['automin'] === '') {
			return 10;
		}
		$t = intval($channel['automin']);
		return $t > 0 ? $t : 0;
	}

	static private function autoNeedProof()
	{
		global $channel;
		return !isset($channel['autoproof']) || $channel['autoproof'] === '' || intval($channel['autoproof']) !== 0;
	}

	static private function webhookSecret()
	{
		global $channel;
		return trim((string)($channel['appsecret'] ?? ''));
	}

	static private function validateConfig()
	{
		global $channel;
		if (empty($channel['appkey']) || empty($channel['appmchid']) || self::webhookSecret() === '') {
			throw new Exception('请先配置 Telegram Bot Token、Chat ID 和 Webhook 密钥');
		}
	}

	static private function isClosed($ext)
	{
		return !empty($ext['closed']) || !empty($ext['released']);
	}

	static private function isExpired($ext, $now = null)
	{
		$now = $now === null ? time() : (int)$now;
		return empty($ext['expire']) || (int)$ext['expire'] <= $now;
	}

	static private function loadLib()
	{
		require_once PAY_ROOT.'inc/AmountMark.php';
		require_once PAY_ROOT.'inc/Telegram.php';
		require_once PAY_ROOT.'inc/WebhookShare.php';
		require_once PAY_ROOT.'inc/OpenApp.php';
	}

	static private function siblingChannels($token = null)
	{
		global $DB, $channel;
		if ($token === null) $token = (string)($channel['appkey'] ?? '');
		$rows = $DB->getAll("SELECT id,config FROM pre_channel WHERE plugin='tgconfirm'");
		$sib = TgconfirmWebhook::siblings(is_array($rows) ? $rows : [], $token);
		if ($sib) return $sib;
		if ($token === '' || empty($channel['id'])) return [];
		return [[
			'id' => (int)$channel['id'],
			'appkey' => $token,
			'appmchid' => (string)($channel['appmchid'] ?? ''),
			'appsecret' => self::webhookSecret(),
		]];
	}

	static private function isAlipay($typename)
	{
		return $typename === 'alipay' || $typename === 'alipay_manual';
	}

	static private function isWxpay($typename)
	{
		return $typename === 'wxpay' || $typename === 'wxpay_manual';
	}

	static public function submit()
	{
		return ['type' => 'jump', 'url' => '/pay/qrcode/'.TRADE_NO.'/'];
	}

	static public function mapi()
	{
		return self::submit();
	}

	static public function qrcode()
	{
		global $order, $cdnpublic;

		self::loadLib();
		try {
			$pay = self::prepare();
		} catch (Exception $e) {
			return ['type' => 'error', 'msg' => $e->getMessage()];
		}

		$order['realmoney'] = $pay['amount'];
		$paytime = $pay['expire'] - time();
		$claimed = !empty($pay['claimed']);
		$code_url = self::qrContent($order['typename']);
		$typename = $order['typename'];
		$is_wx = self::isWxpay($typename);
		$open_url = TgconfirmOpenApp::resolve($is_wx, $code_url);
		$open_direct = !$is_wx && TgconfirmOpenApp::isAlipayQrLink($code_url);
		$open_label = $is_wx ? '打开微信扫码付款' : ($open_direct ? '打开支付宝付款' : '打开支付宝扫码付款');

		include PAY_ROOT.'inc/qrcode.page.php';
		exit;
	}

	static public function pay()
	{
		return ['type' => 'jump', 'url' => '/pay/qrcode/'.TRADE_NO.'/'];
	}

	static public function claimed()
	{
		global $DB, $order, $channel;

		self::loadLib();
		try {
			self::validateConfig();
		} catch (Exception $e) {
			return ['type' => 'json', 'data' => ['code' => -1, 'msg' => $e->getMessage()]];
		}
		if (function_exists('checkRefererHost') && !checkRefererHost()) {
			return ['type' => 'json', 'data' => ['code' => 403, 'msg' => 'forbidden']];
		}

		$trade_no = TRADE_NO;
		try {
			$note = self::readClaimNote();
			$photo = self::readClaimPhoto();
		} catch (Exception $e) {
			return ['type' => 'json', 'data' => ['code' => -1, 'msg' => $e->getMessage()]];
		}
		$transactionStarted = false;
		try {
			$transactionStarted = $DB->beginTransaction();
			if (!$transactionStarted) {
				throw new Exception('订单处理失败');
			}
			$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.trade_no=:trade_no FOR UPDATE", [':trade_no' => $trade_no]);
			if (!$row) {
				throw new Exception('订单不存在');
			}
			if ($row['status'] > 0) {
				if (!$DB->commit()) throw new Exception('订单处理失败');
				$transactionStarted = false;
				return ['type' => 'json', 'data' => ['code' => 1, 'msg' => 'ok']];
			}
			$ext = self::decodeExt($row['ext']);
			if (self::isClosed($ext)) {
				throw new Exception('订单已关闭');
			}
			if (empty($ext['amount']) || empty($ext['expire'])) {
				throw new Exception('请先打开支付页面');
			}
			if (self::isExpired($ext)) {
				throw new Exception('订单已超时，请重新下单');
			}
			$need_notify = empty($ext['claimed']) || empty($ext['tg']);
			if (empty($ext['claimed'])) {
				$ext['claimed'] = time();
				$ext['expire'] = $ext['claimed'] + self::confirmTimeout();
			}
			if ($photo) {
				$ext['proof'] = 1;
			}
			if ($DB->update('order', ['ext' => serialize($ext)], ['trade_no' => $trade_no]) === false) {
				throw new Exception('订单更新失败');
			}
			if ($need_notify) {
				self::notifyTelegram($row, $ext, $trade_no, $note, $photo);
			} elseif ($note !== '' || $photo) {
				self::sendProofOnly($row, $ext, $note, $photo);
			}
			if (!$DB->commit()) throw new Exception('订单处理失败');
			$transactionStarted = false;
		} catch (Exception $e) {
			if ($transactionStarted && $DB->inTransaction()) $DB->rollBack();
			return ['type' => 'json', 'data' => ['code' => -1, 'msg' => $e->getMessage() === '订单处理失败' ? '提交失败，请稍后重试' : $e->getMessage()]];
		}
		$paytime = !empty($ext['expire']) ? max(0, (int)$ext['expire'] - time()) : 0;
		return ['type' => 'json', 'data' => ['code' => 0, 'msg' => 'ok', 'paytime' => $paytime]];
	}

	static public function status()
	{
		global $DB;

		self::loadLib();
		$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.trade_no=:trade_no LIMIT 1", [':trade_no' => TRADE_NO]);
		if (!$row) {
			return ['type' => 'json', 'data' => ['code' => -1, 'msg' => '订单不存在']];
		}
		$ext = self::decodeExt($row['ext']);
		if ($row['status'] == 0 && self::isClosed($ext)) {
			return ['type' => 'json', 'data' => ['code' => -4, 'msg' => 'closed']];
		}
		if ($row['status'] == 0) {
			self::maybeAutoConfirm($row, $ext);
			if ($row['status'] > 0) {
				return ['type' => 'json', 'data' => ['code' => 1, 'msg' => 'ok']];
			}
			$fresh = $DB->getRow("SELECT status,ext FROM pre_order WHERE trade_no=:trade_no LIMIT 1", [':trade_no' => TRADE_NO]);
			if ($fresh) {
				$row['status'] = $fresh['status'];
				$ext = self::decodeExt($fresh['ext']);
			}
			if ($row['status'] == 0 && self::isClosed($ext)) {
				return ['type' => 'json', 'data' => ['code' => -4, 'msg' => 'closed']];
			}
		}
		if ($row['status'] > 0) {
			return ['type' => 'json', 'data' => ['code' => 1, 'msg' => 'ok']];
		}
		if (!empty($ext['expire']) && self::isExpired($ext)) {
			return ['type' => 'json', 'data' => ['code' => -2, 'msg' => 'expired']];
		}
		if (!empty($ext['claimed'])) {
			return ['type' => 'json', 'data' => ['code' => 0, 'msg' => 'waiting']];
		}
		return ['type' => 'json', 'data' => ['code' => -3, 'msg' => 'unpaid']];
	}

	static public function autocron()
	{
		global $DB, $channel;

		self::loadLib();
		$list = $DB->getAll("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.channel=:channel AND A.status=0 AND A.ext IS NOT NULL", [
			':channel' => $channel['id'],
		]);
		$n = 0;
		if (is_array($list)) {
			foreach ($list as $row) {
				$ext = self::decodeExt($row['ext']);
				if (self::maybeAutoConfirm($row, $ext)) {
					$n++;
				}
			}
		}
		return ['type' => 'html', 'data' => 'ok '.$n];
	}

	static public function setwebhook()
	{
		self::loadLib();
		try {
			self::bindWebhook();
		} catch (Exception $e) {
			return ['type' => 'html', 'data' => '绑定失败：'.htmlspecialchars($e->getMessage())];
		}
		return ['type' => 'html', 'data' => 'Webhook 已绑定'];
	}

	static public function webhook()
	{
		global $channel, $DB;

		self::loadLib();
		$token = isset($channel['appkey']) ? $channel['appkey'] : '';

		try {
			self::validateConfig();
		} catch (Exception $e) {
			return ['type' => 'html', 'data' => 'forbidden'];
		}
		$siblings = self::siblingChannels();
		$header = Telegram::secretHeader();
		if (!TgconfirmWebhook::secretMatches($siblings, $header)) {
			return ['type' => 'html', 'data' => 'forbidden'];
		}

		$update = json_decode((string)file_get_contents('php://input'), true);
		$cq = (is_array($update) && !empty($update['callback_query'])) ? $update['callback_query'] : null;
		$cb_id = ($cq && isset($cq['id'])) ? (string)$cq['id'] : '';
		if (!$cq) {
			return ['type' => 'html', 'data' => 'ok'];
		}

		$data = isset($cq['data']) ? $cq['data'] : '';
		$msg = [];
		if (!empty($cq['message']) && is_array($cq['message'])) {
			$msg = $cq['message'];
		} elseif (!empty($cq['maybe_inaccessible_message']) && is_array($cq['maybe_inaccessible_message'])) {
			$msg = $cq['maybe_inaccessible_message'];
		}
		$chat_id = isset($msg['chat']['id']) ? (string)$msg['chat']['id'] : '';
		$message_id = isset($msg['message_id']) ? $msg['message_id'] : 0;
		if ($cb_id === '') {
			return ['type' => 'html', 'data' => 'ok'];
		}

		if (!TgconfirmWebhook::chatAllowed($siblings, $chat_id)) {
			try {
				Telegram::answer($token, $cb_id, '未授权的对话', true);
			} catch (Exception $e) {
			}
			return ['type' => 'html', 'data' => 'ok'];
		}
		if (!is_numeric($message_id) || intval($message_id) <= 0) {
			try {
				Telegram::answer($token, $cb_id, '按钮已失效', true);
			} catch (Exception $e) {
			}
			return ['type' => 'html', 'data' => 'ok'];
		}

		if (!preg_match('/^(ok|no):([0-9]{10,32})$/', $data, $m)) {
			try {
				Telegram::answer($token, $cb_id, '无效按钮', true);
			} catch (Exception $e) {
			}
			return ['type' => 'html', 'data' => 'ok'];
		}

		$action = $m[1];
		$trade_no = $m[2];
		$who = isset($cq['from']['username']) ? '@'.$cq['from']['username'] : (string)($cq['from']['id'] ?? 'unknown');
		$row = null;
		$ext = [];
		$reply = null;
		$transactionStarted = false;

		try {
			$transactionStarted = $DB->beginTransaction();
			if (!$transactionStarted) {
				throw new Exception('订单处理失败');
			}
			$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.trade_no=:trade_no FOR UPDATE", [':trade_no' => $trade_no]);
			if (!$row) {
				$reply = ['toast' => '订单不存在', 'alert' => true, 'text' => '订单 '.$trade_no.' 不存在'];
			} elseif (!TgconfirmWebhook::contains($siblings, $row['channel'])) {
				$reply = ['toast' => '订单通道不匹配', 'alert' => true, 'text' => '订单不属于当前机器人'];
			} else {
				if ((int)$row['channel'] !== (int)$channel['id']) {
					$orderChannel = \lib\Channel::get($row['channel']);
					if ($orderChannel) {
						$channel = $orderChannel;
						$channel['apptype'] = explode(',', $channel['apptype']);
					}
				}
				$ext = self::decodeExt($row['ext']);
				if (!isset($ext['tg']) || (string)$ext['tg'] !== (string)$message_id) {
					$reply = ['toast' => '按钮已失效', 'alert' => true, 'text' => '该确认按钮已失效'];
				} elseif ($row['status'] > 0) {
					$reply = ['toast' => '已入账', 'alert' => false, 'text' => self::orderText($row, $ext, '已入账')."\n操作人: ".Telegram::h($who)];
				} elseif (self::isClosed($ext)) {
					$reply = ['toast' => '已关闭', 'alert' => false, 'text' => self::orderText($row, $ext, '已关闭（未入账）')."\n操作人: ".Telegram::h($who)];
				} elseif (empty($ext['claimed'])) {
					$reply = ['toast' => '尚未申报', 'alert' => true, 'text' => self::orderText($row, $ext, '尚未申报，不能确认')];
				} elseif (self::isExpired($ext)) {
					$reply = ['toast' => '已超时，不能确认', 'alert' => true, 'text' => self::orderText($row, $ext, '已超时，未入账')];
				} elseif ($action === 'no') {
					self::releaseAmount($trade_no, $ext);
					$reply = ['toast' => '已关闭', 'alert' => false, 'text' => self::orderText($row, $ext, '已关闭（未入账）')."\n操作人: ".Telegram::h($who)];
				} else {
					processNotify($row, $trade_no, $who);
					if ((int)$DB->getColumn("SELECT status FROM pre_order WHERE trade_no=:trade_no", [':trade_no' => $trade_no]) !== 1) {
						throw new Exception('订单入账失败');
					}
					$row['status'] = 1;
					$reply = ['toast' => '已确认入账', 'alert' => false, 'text' => self::orderText($row, $ext, '已确认入账')."\n操作人: ".Telegram::h($who)];
				}
			}
			if (!$DB->commit()) throw new Exception('订单处理失败');
			$transactionStarted = false;
		} catch (Exception $e) {
			if ($transactionStarted && $DB->inTransaction()) {
				$DB->rollBack();
			}
			$reply = ['toast' => '处理失败，请重试', 'alert' => true, 'text' => '订单处理失败，请稍后重试'];
		}

		if ($reply) {
			self::tgReply($token, $cb_id, $chat_id, $message_id, $reply['toast'], $reply['alert'], $reply['text']);
		}
		return ['type' => 'html', 'data' => 'ok'];
	}

	static private function prepare()
	{
		global $DB, $order, $channel;

		self::validateConfig();

		$timeout = self::payTimeout();
		$trade_no = TRADE_NO;

		$transactionStarted = false;
		try {
			$transactionStarted = $DB->beginTransaction();
			if (!$transactionStarted) {
				throw new Exception('订单处理失败');
			}
			$row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no=:trade_no FOR UPDATE", [':trade_no' => $trade_no]);
			if (!$row) {
				throw new Exception('订单不存在');
			}
			if ($row['status'] > 0) {
				throw new Exception('订单已支付');
			}

			$ext = self::decodeExt($row['ext']);
			if (self::isClosed($ext)) {
				throw new Exception('订单已关闭');
			}
			if (!empty($ext['amount']) && !empty($ext['expire'])) {
				if (self::isExpired($ext)) {
					throw new Exception('订单已超时，请重新下单');
				}
				if (!$DB->commit()) throw new Exception('订单处理失败');
				$transactionStarted = false;
				self::maybeAutoConfirm($row, $ext);
				return $ext;
			}

			$channelRow = $DB->getRow("SELECT id FROM pre_channel WHERE id=:channel FOR UPDATE", [
				':channel' => $row['channel'],
			]);
			if (!$channelRow) {
				throw new Exception('支付通道不存在');
			}

			$now = time();
			$used = $DB->getAll("SELECT realmoney,ext FROM pre_order WHERE channel=:channel AND type=:type AND trade_no<>:trade_no AND status=0 AND realmoney IS NOT NULL AND ext IS NOT NULL FOR UPDATE", [
				':channel' => $row['channel'],
				':type' => $row['type'],
				':trade_no' => $trade_no,
			]);
			$usedYuan = [];
			if (is_array($used)) {
				foreach ($used as $u) {
					$uext = self::decodeExt($u['ext']);
					if (!AmountMark::isReserved($uext, $now)) continue;
					$usedYuan[] = $uext['amount'];
				}
			}

			$base = $row['realmoney'] > 0 ? $row['realmoney'] : $row['money'];
			$amount = AmountMark::pick($base, $usedYuan);
			$expire = $now + $timeout;
			$ext = [
				'amount' => $amount,
				'base' => number_format((float)$base, 2, '.', ''),
				'expire' => $expire,
				'tg' => 0,
			];

			if ($DB->update('order', [
				'realmoney' => $amount,
				'ext' => serialize($ext),
			], ['trade_no' => $trade_no]) === false) {
				throw new Exception('订单更新失败');
			}
			if (!$DB->commit()) throw new Exception('订单处理失败');
			$transactionStarted = false;
		} catch (Exception $e) {
			if ($transactionStarted && $DB->inTransaction()) $DB->rollBack();
			throw $e;
		}

		$order['realmoney'] = $amount;
		return $ext;
	}

	static private function maybeAutoConfirm(&$row, &$ext)
	{
		global $DB, $channel;
		if ($row['status'] > 0) return false;
		if (empty($ext['claimed'])) return false;
		$mins = self::autoMinutes();
		if ($mins <= 0) return false;
		if (self::autoNeedProof() && empty($ext['proof'])) return false;
		if (time() < intval($ext['claimed']) + $mins * 60) return false;
		if (self::isExpired($ext)) return false;
		if (self::isClosed($ext)) return false;

		$trade_no = $row['trade_no'];
		$transactionStarted = false;
		try {
			$transactionStarted = $DB->beginTransaction();
			if (!$transactionStarted) {
				return false;
			}
			$locked = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.trade_no=:trade_no FOR UPDATE", [':trade_no' => $trade_no]);
			if (!$locked) {
				if (!$DB->commit()) throw new Exception('订单处理失败');
				$transactionStarted = false;
				return false;
			}
			$row = $locked;
			$ext = self::decodeExt($locked['ext']);
			$now = time();
			$eligible = (int)$locked['status'] === 0
				&& !empty($ext['claimed'])
				&& (!self::autoNeedProof() || !empty($ext['proof']))
				&& $now >= intval($ext['claimed']) + $mins * 60
				&& !self::isExpired($ext, $now)
				&& !self::isClosed($ext);
			if ($eligible) {
				processNotify($row, $trade_no);
				if ((int)$DB->getColumn("SELECT status FROM pre_order WHERE trade_no=:trade_no", [':trade_no' => $trade_no]) !== 1) {
					throw new Exception('订单入账失败');
				}
				$ext['auto'] = 1;
				self::saveExt($trade_no, $ext);
			}
			if (!$DB->commit()) throw new Exception('订单处理失败');
			$transactionStarted = false;
			if (!$eligible) return false;
			$row['status'] = 1;
		} catch (Exception $e) {
			if ($transactionStarted && $DB->inTransaction()) $DB->rollBack();
			return false;
		}

		try {
			$text = self::orderText($row, $ext, '已自动确认入账');
			if (!empty($ext['tg'])) {
				Telegram::edit($channel['appkey'], $channel['appmchid'], $ext['tg'], $text);
			}
			Telegram::send($channel['appkey'], $channel['appmchid'], "订单 <code>".Telegram::h($row['trade_no'])."</code> 已自动确认入账，实付 <b>".$ext['amount']."</b>");
		} catch (Exception $e) {
		}
		return true;
	}

	static private function saveExt($trade_no, $ext)
	{
		global $DB;
		if ($DB->update('order', ['ext' => serialize($ext)], ['trade_no' => $trade_no]) === false) {
			throw new Exception('订单更新失败');
		}
	}

	static private function releaseAmount($trade_no, &$ext)
	{
		$ext['released'] = 1;
		$ext['closed'] = 1;
		self::saveExt($trade_no, $ext);
	}

	static private function readClaimNote()
	{
		$note = isset($_POST['note']) ? trim($_POST['note']) : '';
		$note = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $note);
		if ($note === '') return '';
		if (mb_strlen($note, 'UTF-8') > 200) {
			$note = mb_substr($note, 0, 200, 'UTF-8');
		}
		return $note;
	}

	static private function readClaimPhoto()
	{
		if (empty($_FILES['proof']['tmp_name']) || !is_uploaded_file($_FILES['proof']['tmp_name'])) {
			return null;
		}
		if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK) return null;
		if ($_FILES['proof']['size'] > 5 * 1024 * 1024) {
			throw new Exception('图片不能超过 5MB');
		}
		$mime = '';
		if (function_exists('finfo_open')) {
			$f = finfo_open(FILEINFO_MIME_TYPE);
			$mime = finfo_file($f, $_FILES['proof']['tmp_name']);
			finfo_close($f);
		}
		if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
			throw new Exception('只支持 jpg/png/webp/gif 图片');
		}
		return $_FILES['proof']['tmp_name'];
	}

	static private function withNote($text, $note)
	{
		if ($note === '') return $text;
		return $text."\n说明: ".Telegram::h($note);
	}

	static private function notifyTelegram($row, $ext, $trade_no, $note = '', $photo = null)
	{
		global $channel;
		$text = self::withNote(self::orderText($row, $ext, '待确认'), $note);
		if ($photo) {
			$mid = Telegram::sendPhoto($channel['appkey'], $channel['appmchid'], $photo, $text, $trade_no, $ext['amount']);
		} else {
			$mid = Telegram::sendConfirm($channel['appkey'], $channel['appmchid'], $text, $trade_no, $ext['amount']);
		}
		$ext['tg'] = $mid;
		self::saveExt($trade_no, $ext);
		return $ext;
	}

	static private function sendProofOnly($row, $ext, $note, $photo)
	{
		global $channel;
		$text = self::withNote(self::orderText($row, $ext, '补充凭证'), $note);
		if ($photo) {
			Telegram::sendPhoto($channel['appkey'], $channel['appmchid'], $photo, $text);
		} else {
			Telegram::send($channel['appkey'], $channel['appmchid'], $text);
		}
	}

	static private function qrContent($typename)
	{
		global $channel, $order;
		if (self::isAlipay($typename)) {
			if (!empty($channel['alipayqr'])) {
				return $channel['alipayqr'];
			}
			if (class_exists('TgconfirmOpenApp') && TgconfirmOpenApp::isAlipayUid($channel['appid'] ?? '')) {
				return TgconfirmOpenApp::alipayTransferUri($channel['appid'], $order['realmoney'], $order['trade_no']);
			}
			return '';
		}
		return isset($channel['appurl']) ? $channel['appurl'] : '';
	}

	static private function bindWebhook()
	{
		global $channel, $conf;
		self::validateConfig();
		$token = $channel['appkey'];
		$siblings = self::siblingChannels($token);
		$ownerId = TgconfirmWebhook::ownerId($siblings, $channel['id']);
		$secret = TgconfirmWebhook::ownerSecret($siblings, self::webhookSecret());
		$url = $conf['localurl'].'pay/webhook/'.$ownerId.'/';
		Telegram::setWebhook($token, $url, $secret);
	}

	static private function decodeExt($raw)
	{
		if (empty($raw)) return [];
		$ext = @unserialize($raw);
		return is_array($ext) ? $ext : [];
	}

	static private function orderText($row, $ext, $status)
	{
		$base = isset($ext['base']) ? $ext['base'] : $row['money'];
		$amount = isset($ext['amount']) ? $ext['amount'] : $row['realmoney'];
		$expire = isset($ext['expire']) ? date('Y-m-d H:i:s', $ext['expire']) : '-';
		$type = isset($row['typeshowname']) ? $row['typeshowname'] : $row['typename'];
		return "<b>转账".$status."</b>\n"
			."方式: ".Telegram::h($type)."\n"
			."订单: <code>".Telegram::h($row['trade_no'])."</code>\n"
			."商品: ".Telegram::h($row['name'])."\n"
			."应付: ".$base."\n"
			."实付: <b>".$amount."</b>\n"
			."超时: ".$expire;
	}

	static private function answerQuiet($token, $cb_id, $text, $alert = true)
	{
		if ($token === '' || $cb_id === '') return;
		try {
			Telegram::answer($token, $cb_id, $text, $alert);
		} catch (Exception $e) {
		}
	}

	static private function tgReply($token, $cb_id, $chat_id, $message_id, $toast, $alert, $text)
	{
		self::answerQuiet($token, $cb_id, $toast, $alert);
		if ($message_id) {
			try {
				Telegram::edit($token, $chat_id, $message_id, $text);
			} catch (Exception $e) {
			}
		}
	}
}
