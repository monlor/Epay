<?php
require __DIR__.'/AmountMark.php';

$fail = 0;
function check($cond, $msg) {
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL $msg\n";
	}
}

$a = AmountMark::pick('11.11', []);
check($a === '11.11', "first decimal keeps original, got $a");

$a = AmountMark::pick('11.11', ['11.11']);
check($a === '11.12', "second decimal +0.01, got $a");

$a = AmountMark::pick(11.11, ['11.11', '11.12']);
check($a === '11.13', "third decimal +0.02, got $a");

$a = AmountMark::pick('11.00', []);
check($a === '11.00', "integer first keeps original, got $a");

$a = AmountMark::pick(11, ['11.00']);
check($a === '11.01', "integer second +0.01, got $a");

$a = AmountMark::pick(11, ['11.00', '11.01']);
check($a === '11.02', "integer third +0.02, got $a");

$used = [];
for ($i = 0; $i < 100; $i++) {
	$used[] = AmountMark::fromCents(1100 + $i);
}
$a = AmountMark::pick(11, $used);
check($a === '12.00', "integer rolls past 11.99 to 12.00, got $a");

$used[] = '12.00';
$a = AmountMark::pick(11, $used);
check($a === '12.01', "integer keeps +0.01 after 12.00, got $a");

$used = [];
for ($i = 0; $i < 100; $i++) {
	$used[] = AmountMark::fromCents(1111 + $i);
}
$a = AmountMark::pick('11.11', $used);
check($a === '12.11', "decimal rolls past +0.99 to 12.11, got $a");

$a = AmountMark::pick('11.00', ['11.00', '11.02']);
check($a === '11.01', "fills the first free gap, got $a");

check(AmountMark::isReserved(['amount' => '11.37', 'expire' => 200], 100), 'future reservation is active');
check(!AmountMark::isReserved(['amount' => '11.37', 'expire' => 100], 100), 'expired reservation is inactive');
check(!AmountMark::isReserved(['amount' => '11.37', 'expire' => 200, 'released' => 1], 100), 'released reservation is inactive');
check(!AmountMark::isReserved(['amount' => '11.37', 'expire' => 200, 'closed' => 1], 100), 'closed reservation is inactive');

if ($fail) {
	echo "$fail failed\n";
	exit(1);
}
echo "ok\n";
