<?php

class alimpay_plugin
{
	static public $info = [
		'name'        => 'alimpay',
		'showname'    => 'AliMPay支付宝免签约',
		'author'      => 'AliMPay',
		'link'        => 'https://github.com/MiaM1ku/AliMPay',
		'types'       => ['alipay'],
		'inputs' => [
			'appid' => [
				'name' => '应用APPID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '支付宝公钥',
				'type' => 'textarea',
				'note' => '填错也可以支付成功但无法扫账确认；公钥证书模式此处留空',
			],
			'appsecret' => [
				'name' => '应用私钥',
				'type' => 'textarea',
				'note' => '',
			],
			'apptoken' => [
				'name' => '商户授权token',
				'type' => 'input',
				'note' => '只有第三方应用需要填写，非第三方应用必须留空',
			],
			'appswitch' => [
				'name' => '收款方式',
				'type' => 'select',
				'options' => [0 => '经营码', 1 => '转账'],
			],
			'appurl' => [
				'name' => '经营码',
				'type' => 'input',
				'note' => '经营码图片URL，或经营码内容。转账模式可留空',
			],
			'appmchid' => [
				'name' => '支付宝UID',
				'type' => 'input',
				'note' => '2088开头的16位纯数字，转账模式必填',
			],
		],
		'select' => null,
		'note' => '<p>参考 AliMPay：经营码按唯一分位金额匹配，转账按备注=平台订单号匹配。直连支付宝 <code>alipay.data.bill.accountlog.query</code>，不签约支付产品。应用需已上线，并开通账务明细查询，不能开启余额宝自动转入。</p><p>收银台展示 5 分钟，页面继续轮询至下单后 10 分钟；扫账监控同样到 10 分钟。经营码会把实付金额设为订单金额 +0.01～+0.99，商户到账仍按原订单金额。</p><p>手机端显示「打开支付宝继续付款」，需用户点按钮后跳转，页面打开时不会自动跳走。</p><p>需添加守护进程统一扫账，运行目录：<u>[basedir]plugins/alimpay/</u> 启动命令：<u>php server.php</u>。默认扫描全部已启用通道（含刚关闭但仍有监控窗口内未支付订单的通道）。也可在命令后加通道 ID 只扫一条。</p>',
		'bindwxmp' => false,
		'bindwxa' => false,
	];

	static public function submit(){
		return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
	}

	static public function mapi(){
		return ['type'=>'jump','url'=>'/pay/qrcode/'.TRADE_NO.'/'];
	}

	static public function qrcode(){
		global $order, $channel, $cdnpublic;

		require_once PAY_ROOT.'inc/AlimpayService.php';
		try{
			$ext = AlimpayService::ensurePayData($order, $channel);
			$pay_data = AlimpayService::displayData($order, $channel, $ext);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>$ex->getMessage()];
		}

		$created = strtotime($order['addtime']);
		$paytime = $created + AlimpayService::CHECKOUT_SECONDS - time();
		$code_url = $pay_data['qr_value'];

		include PAY_ROOT.'inc/qrcode.page.php';
		exit;
	}

	static public function status(){
		global $DB;

		require_once PAY_ROOT.'inc/AlimpayService.php';
		$row = $DB->getRow("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_order A left join pre_type B on A.type=B.id WHERE A.trade_no=:trade_no LIMIT 1", [':trade_no' => TRADE_NO]);
		if(!$row){
			return ['type'=>'json','data'=>['code'=>-1,'msg'=>'订单不存在']];
		}
		if($row['status'] >= 1){
			return ['type'=>'json','data'=>['code'=>1,'msg'=>'ok','backurl'=>AlimpayService::paidJumpUrl($row)]];
		}
		$created = strtotime($row['addtime']);
		if(time() > $created + AlimpayService::MONITOR_SECONDS){
			return ['type'=>'json','data'=>['code'=>-4,'msg'=>'closed']];
		}
		return ['type'=>'json','data'=>['code'=>0,'msg'=>'unpaid']];
	}
}
