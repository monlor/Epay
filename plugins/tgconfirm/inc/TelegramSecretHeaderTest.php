<?php
require __DIR__.'/Telegram.php';

$fail = 0;
function check($cond, $msg) {
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL $msg\n";
	}
}

$got = Telegram::secretHeader(['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'abc'], []);
check($got === 'abc', "primary CGI header, got '$got'");

$got = Telegram::secretHeader(['REDIRECT_HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'xyz'], []);
check($got === 'xyz', "rewrite redirect header, got '$got'");

$got = Telegram::secretHeader([], ['X-Telegram-Bot-Api-Secret-Token' => 'from-headers']);
check($got === 'from-headers', "getallheaders original name, got '$got'");

$got = Telegram::secretHeader([], ['x-telegram-bot-api-secret-token' => 'lower']);
check($got === 'lower', "lowercase header name, got '$got'");

$got = Telegram::secretHeader([
	'HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => ' first ',
], []);
check($got === 'first', "trimmed primary header, got '$got'");

$got = Telegram::secretHeader([], []);
check($got === '', "missing header should be empty, got '$got'");

if ($fail) {
	echo "$fail failed\n";
	exit(1);
}
echo "ok\n";
