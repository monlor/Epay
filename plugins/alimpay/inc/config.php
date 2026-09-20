<?php
$alipay_config = [
	'app_id' => $channel['appid'],
	'alipay_public_key' => $channel['appkey'],
	'app_private_key' => $channel['appsecret'],
	'app_auth_token' => $channel['apptoken'],
	'sign_type' => "RSA2",
	'charset' => "UTF-8",
	'gateway_url' => "https://openapi.alipay.com/gateway.do",
	'log_path' => dirname(__FILE__).'/log/',
];

if(file_exists(PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appid'].'/appCertPublicKey_'.$channel['appid'].'.crt')){
	$alipay_config['app_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appid'].'/appCertPublicKey_'.$channel['appid'].'.crt';
	$alipay_config['alipay_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appid'].'/alipayCertPublicKey_RSA2.crt';
	$alipay_config['root_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/'.$channel['appid'].'/alipayRootCert.crt';
}
elseif(file_exists(PLUGIN_ROOT.$channel['plugin'].'/cert/appCertPublicKey_'.$channel['appid'].'.crt')){
	$alipay_config['app_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/appCertPublicKey_'.$channel['appid'].'.crt';
	$alipay_config['alipay_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/alipayCertPublicKey_RSA2.crt';
	$alipay_config['root_cert_path'] = PLUGIN_ROOT.$channel['plugin'].'/cert/alipayRootCert.crt';
}
return $alipay_config;
