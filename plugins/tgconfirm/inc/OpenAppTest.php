<?php
require __DIR__.'/OpenApp.php';

$fail = 0;
function check($cond, $msg) {
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL $msg\n";
	}
}

check(TgconfirmOpenApp::isAlipayUid('2088000000000000'), 'valid uid');
check(!TgconfirmOpenApp::isAlipayUid('20880000'), 'short uid rejected');
check(!TgconfirmOpenApp::isAlipayUid(''), 'empty uid rejected');

$uri = TgconfirmOpenApp::alipayTransferUri('2088000000000000', '11.12', '2026010100000111111');
check(strpos($uri, 'https://render.alipay.com/p/s/i?scheme=') === 0, 'https wrapper');
$scheme = rawurldecode(substr($uri, strlen('https://render.alipay.com/p/s/i?scheme=')));
check(strpos($scheme, 'alipays://platformapi/startapp?') === 0, 'inner alipays scheme');
check(strpos($scheme, 'actionType=toAccount') !== false, 'toAccount');

check(TgconfirmOpenApp::isAlipayQrLink('https://qr.alipay.com/fkxabc'), 'https qr.alipay.com prefix');
check(TgconfirmOpenApp::isAlipayQrLink('https://qr.alipay.com/c1x999?t=1'), 'qr link with query');
check(TgconfirmOpenApp::isAlipayQrLink('https://qr.alipay.com/'), 'qr host with slash');
check(TgconfirmOpenApp::isAlipayQrLink('HTTP://QR.ALIPAY.COM/abc'), 'http and case');
check(!TgconfirmOpenApp::isAlipayQrLink('https://qr.alipay.com.evil.example/x'), 'lookalike host rejected');
check(!TgconfirmOpenApp::isAlipayQrLink('https://img.example.com/qr.png'), 'image is not qr link');

$open = TgconfirmOpenApp::resolve(false, 'https://qr.alipay.com/c1xconfigured');
check($open === 'https://qr.alipay.com/c1xconfigured', 'button uses configured qr.alipay.com url');

$open = TgconfirmOpenApp::resolve(false, $uri);
check($open === 'alipays://', 'alipay transfer qr still opens app only');

$open = TgconfirmOpenApp::resolve(false, 'https://img.example.com/qr.png');
check($open === 'alipays://', 'alipay image still opens app');

$open = TgconfirmOpenApp::resolve(false, '');
check($open === '', 'empty alipay qr has no open url');

$open = TgconfirmOpenApp::resolve(true, 'wxp://f2f0abcdef');
check($open === 'weixin://', 'wechat opens app only');

$open = TgconfirmOpenApp::resolve(true, 'https://img.example.com/wx.png');
check($open === 'weixin://', 'wechat image still opens app');

$open = TgconfirmOpenApp::resolve(true, '');
check($open === '', 'empty wechat qr has no open url');

$open = TgconfirmOpenApp::resolve(true, 'weixin://dl/business/?t=1');
check($open === 'weixin://', 'wechat scheme still opens app');

if ($fail) {
	fwrite(STDERR, "OpenAppTest $fail failed\n");
	exit(1);
}
fwrite(STDOUT, "OpenAppTest ok\n");
