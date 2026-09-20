<?php
if (substr(php_sapi_name(), 0, 3) != 'cli') {
	die("This Programe can only be run in CLI mode");
}
require __DIR__.'/AlimpayService.php';

assert(AlimpayService::moneyToCents('1') === 100);
assert(AlimpayService::moneyToCents('1.2') === 120);
assert(AlimpayService::moneyToCents('1.20') === 120);
assert(AlimpayService::centsToMoney(101) === '1.01');

assert(AlimpayService::nextPayable('11.00', []) === '11.00', 'first keeps original');
assert(AlimpayService::nextPayable('11.00', ['11.00']) === '11.01', 'second +0.01');
assert(AlimpayService::nextPayable('11.00', ['11.00', '11.02']) === '11.01', 'fills first gap');
$occupied = [];
for($i = 0; $i < 100; $i++){
	$occupied[] = AlimpayService::centsToMoney(1100 + $i);
}
assert(AlimpayService::nextPayable('11.00', $occupied) === '12.00', 'rolls past 11.99 to 12.00');
$occupied[] = '12.00';
assert(AlimpayService::nextPayable('11.00', $occupied) === '12.01', 'keeps +0.01 after 12.00');

$order = [
	'trade_no' => '2026010100000111111',
	'realmoney' => '10.00',
	'addtime' => '2026-01-01 12:00:00',
	'mode' => 'business_qr',
	'payable' => '10.01',
];
$ok = AlimpayService::normalizeEvent([
	'direction' => '收入',
	'trans_amount' => '10.01',
	'trans_dt' => '2026-01-01 12:00:10',
	'trans_memo' => '',
	'account_log_id' => 'log1',
	'alipay_order_no' => 'ALI1',
	'other_account' => 'buyer',
]);
assert($ok && AlimpayService::matchEvent($ok, $order), 'business_qr match failed');

$wrongAmount = $ok;
$wrongAmount['amount'] = '10.00';
assert(!AlimpayService::matchEvent($wrongAmount, $order), 'business_qr must require unique payable');

$out = $ok;
$out['direction'] = '支出';
assert(!AlimpayService::matchEvent($out, $order), 'expense must not match');

$emptyDir = $ok;
$emptyDir['direction'] = '';
assert(!AlimpayService::matchEvent($emptyDir, $order), 'empty direction must not match');

assert(AlimpayService::normalizeEvent(['direction'=>'收入','trans_amount'=>'-10.01','trans_dt'=>'2026-01-01 12:00:10']) === null, 'signed outbound must be dropped');

$early = $ok;
$early['occurred_at'] = '2026-01-01 11:59:00';
assert(!AlimpayService::matchEvent($early, $order), 'lookbehind must not match');

$atCreated = $ok;
$atCreated['occurred_at'] = '2026-01-01 12:00:00';
assert(AlimpayService::matchEvent($atCreated, $order), 'payment at created must match');

$transfer = $order;
$transfer['mode'] = 'transfer';
$transfer['payable'] = '10.00';
$transferEvent = AlimpayService::normalizeEvent([
	'direction' => '收入',
	'trans_amount' => '10.00',
	'trans_dt' => '2026-01-01 12:00:10',
	'trans_memo' => '2026010100000111111',
	'account_log_id' => 'log2',
	'alipay_order_no' => 'ALI2',
]);
assert($transferEvent && AlimpayService::matchEvent($transferEvent, $transfer), 'transfer memo match failed');
$transferEvent['memo'] = 'wrong';
assert(!AlimpayService::matchEvent($transferEvent, $transfer), 'transfer must require memo');

$uri = AlimpayService::createTransferUri('2088000000000000', '10.00', '2026010100000111111', 1);
assert(strpos($uri, 'alipays://platformapi/startapp?') === 0);
assert(strpos($uri, 'actionType=toAccount') !== false);
assert(strpos($uri, 'userId=2088000000000000') !== false);
assert(strpos($uri, 'memo=2026010100000111111') !== false);

assert(AlimpayService::occupySeconds() === AlimpayService::MONITOR_SECONDS + AlimpayService::BILL_LAG_SECONDS);
assert(AlimpayService::eventAlreadyUsed(['account_log_id'=>'log1','alipay_order_no'=>'ALI1'], ['ALI1'=>true]) === true);
assert(AlimpayService::eventAlreadyUsed(['account_log_id'=>'log9','alipay_order_no'=>'ALI9'], ['ALI1'=>true]) === false);

assert(AlimpayService::isImageSource('https://example.com/qr.png') === true);
assert(AlimpayService::isImageSource('data:image/png;base64,AAA') === true);
assert(AlimpayService::isImageSource('https://qr.alipay.com/fkx123') === false);
assert(AlimpayService::isImageSource('alipays://platformapi/startapp') === false);

fwrite(STDOUT, "alimpay selfcheck ok\n");
