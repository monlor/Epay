<?php
/**
 * Mobile "open app" links. Only launches WeChat/Alipay; no in-app pay page.
 * UID transfer URI is QR content only (save then scan), not a jump target.
 */
class TgconfirmOpenApp
{
	public static function isAlipayUid($uid)
	{
		return (bool)preg_match('/^2088\d{12}$/', trim((string)$uid));
	}

	public static function alipayTransferUri($userId, $amount, $memo)
	{
		$params = http_build_query([
			'appId' => '09999988',
			'actionType' => 'toAccount',
			'goBack' => 'NO',
			'amount' => number_format((float)$amount, 2, '.', ''),
			'userId' => trim((string)$userId),
			'memo' => (string)$memo,
		], '', '&', PHP_QUERY_RFC3986);
		$scheme = 'alipays://platformapi/startapp?'.$params;
		return 'https://render.alipay.com/p/s/i?scheme='.rawurlencode($scheme);
	}

	public static function resolve($is_wx, $code_url)
	{
		if (trim((string)$code_url) === '') return '';
		return $is_wx ? 'weixin://' : 'alipays://';
	}
}
