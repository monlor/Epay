<?php
if (substr(php_sapi_name(), 0, 3) != 'cli') {
	die("This Programe can only be run in CLI mode");
}
@chdir(dirname(__FILE__));
$nosession = true;
include("../../includes/common.php");
require_once PLUGIN_ROOT.'alimpay/inc/AlimpayService.php';

$onlyId = isset($argv[1]) ? intval($argv[1]) : 0;
if(isset($argv[1]) && $onlyId <= 0){
	exit('支付通道ID不正确');
}

while(true){
	$now = time();
	$targets = AlimpayService::listScanTargets($onlyId);
	if(empty($targets)){
		echo '暂无需要扫账的 AliMPay 通道'.PHP_EOL;
	}
	foreach($targets as $channel){
		$label = $channel['name'].'#'.$channel['id'];
		if(!empty($channel['subid'])) $label .= '/'.$channel['subid'];
		try{
			$n = AlimpayService::scanOnce($channel);
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
