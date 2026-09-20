<?php
/**
 * Mobile "open app" links for the checkout page.
 * Alipay can prefill amount via toAccount. WeChat has no equivalent.
 */
class TgconfirmOpenApp
{
	public static function isImageSource($value)
	{
		$value = trim((string)$value);
		if ($value === '') return false;
		if (strpos($value, 'data:image/') === 0) return true;
		if (preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $value)) return true;
		return false;
	}

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

	public static function alipayWrapUrl($code_url)
	{
		$code_url = trim((string)$code_url);
		if ($code_url === '') return '';
		if (stripos($code_url, 'alipays:') === 0) return $code_url;
		if (stripos($code_url, 'https://render.alipay.com/') === 0) return $code_url;
		if (preg_match('#/pay/(pay|qrcode)/#', $code_url)) return '';
		return 'alipays://platformapi/startapp?appId=20000067&url='.rawurlencode($code_url);
	}

	public static function resolve($is_wx, $uid, $code_url, $amount, $memo)
	{
		$code_url = trim((string)$code_url);
		if ($is_wx) {
			return $code_url !== '' ? 'weixin://' : '';
		}
		if (self::isAlipayUid($uid)) {
			return self::alipayTransferUri($uid, $amount, $memo);
		}
		if ($code_url === '' || self::isImageSource($code_url)) {
			return '';
		}
		return self::alipayWrapUrl($code_url);
	}
}
