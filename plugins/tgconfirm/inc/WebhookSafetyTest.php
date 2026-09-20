<?php
$source = file_get_contents(dirname(__DIR__).'/tgconfirm_plugin.php');
$fail = 0;

function check($cond, $msg) {
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL $msg\n";
	}
}

preg_match('/static public function claimed\(\).*?(?=\n\tstatic public function|\z)/s', $source, $claimed);
check(!empty($claimed[0]), 'claimed handler found');
check(strpos($claimed[0] ?? '', 'bindWebhook(') === false, 'claimed handler does not rebind webhook');

$start = strpos($source, 'if (!TgconfirmWebhook::secretMatches($siblings, $header))');
$end = strpos($source, 'if (!$cq)', $start);
$rejected = $start === false || $end === false ? '' : substr($source, $start, $end - $start);
check($rejected !== '', 'rejected webhook branch found');
check(strpos(substr($source, 0, $start === false ? 0 : $start), 'answerQuiet(') === false, 'webhook does not call Telegram before secret verification');
check(strpos($rejected, 'answerQuiet(') === false, 'rejected webhook does not call Telegram');
check(strpos($rejected, 'bindWebhook(') === false, 'rejected webhook does not rebind');

if ($fail) exit(1);
echo "ok\n";
