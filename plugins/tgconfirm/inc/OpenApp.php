<?php
/**
 * Mobile "open app" links.
 * Alipay qr.alipay.com collection URLs open that URL; other Alipay codes only launch the app.
 * UID transfer URI is QR content only (save then scan), not a jump target.
 */
class TgconfirmOpenApp
{
	public static function isAlipayUid($uid)
	{
		return (bool)preg_match('/^2088\d{12}$/', trim((string)$uid));
	}

	public static function isAlipayQrLink($url)
	{
		$url = trim((string)$url);
		if ($url === '') return false;
		$scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
		$host = strtolower((string)parse_url($url, PHP_URL_HOST));
		return ($scheme === 'https' || $scheme === 'http') && $host === 'qr.alipay.com';
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
		$code_url = trim((string)$code_url);
		if ($code_url === '') return '';
		if ($is_wx) return 'weixin://';
		if (self::isAlipayQrLink($code_url)) return $code_url;
		return 'alipays://';
	}
}
