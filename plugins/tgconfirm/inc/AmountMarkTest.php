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

$a = AmountMark::pick('11.00', [], function ($cands) { return $cands[0]; });
check($a === '11.00', "integer can pick .00, got $a");

$a = AmountMark::pick(11, ['11.00'], function ($cands) { return $cands[0]; });
check($a === '11.01', "integer skips used .00, got $a");

$used = [];
for ($i = 0; $i < 99; $i++) {
	$used[] = AmountMark::fromCents(1100 + $i);
}
$a = AmountMark::pick(11, $used, function ($cands) { return $cands[0]; });
check($a === '11.99', "integer last slot 11.99, got $a");

$threw = false;
try {
	$used[] = '11.99';
	AmountMark::pick(11, $used);
} catch (Exception $e) {
	$threw = true;
}
check($threw, 'integer full window throws');

$threw = false;
try {
	$used = [];
	for ($i = 0; $i < 100; $i++) {
		$used[] = AmountMark::fromCents(1111 + $i);
	}
	AmountMark::pick('11.11', $used);
} catch (Exception $e) {
	$threw = true;
}
check($threw, 'decimal full window throws');

$a = AmountMark::pick(11, [], function ($cands) { return $cands[37]; });
check($a === '11.37', "integer random index 37, got $a");

if ($fail) {
	echo "$fail failed\n";
	exit(1);
}
echo "ok\n";
