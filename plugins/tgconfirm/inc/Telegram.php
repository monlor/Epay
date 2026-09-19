<?php

class Telegram
{
	static public function api($token, $method, $params)
	{
		return self::request($token, $method, $params, true);
	}

	static public function setWebhook($token, $url, $secret = '')
	{
		if (trim((string)$secret) === '') {
			throw new Exception('Webhook 密钥不能为空');
		}
		$params = [
			'url' => $url,
			'allowed_updates' => ['callback_query'],
			'drop_pending_updates' => false,
		];
		$params['secret_token'] = $secret;
		return self::api($token, 'setWebhook', $params);
	}

	static public function send($token, $chat_id, $text)
	{
		$result = self::api($token, 'sendMessage', [
			'chat_id' => $chat_id,
			'text' => $text,
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
		]);
		return $result['result']['message_id'];
	}

	static public function confirmKeyboard($trade_no, $amount)
	{
		return [
			'inline_keyboard' => [[
				['text' => '确认 ¥'.$amount, 'callback_data' => 'ok:'.$trade_no],
				['text' => '关闭订单', 'callback_data' => 'no:'.$trade_no],
			]],
		];
	}

	static public function sendConfirm($token, $chat_id, $text, $trade_no, $amount)
	{
		$params = [
			'chat_id' => $chat_id,
			'text' => $text,
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
			'reply_markup' => self::confirmKeyboard($trade_no, $amount),
		];
		$result = self::api($token, 'sendMessage', $params);
		return $result['result']['message_id'];
	}

	static public function sendPhoto($token, $chat_id, $tmpfile, $caption, $trade_no = '', $amount = '')
	{
		$mime = function_exists('mime_content_type') ? @mime_content_type($tmpfile) : 'image/jpeg';
		if (!$mime) $mime = 'image/jpeg';
		$ext = 'jpg';
		if (strpos($mime, 'png') !== false) $ext = 'png';
		elseif (strpos($mime, 'webp') !== false) $ext = 'webp';
		elseif (strpos($mime, 'gif') !== false) $ext = 'gif';

		$params = [
			'chat_id' => $chat_id,
			'caption' => $caption,
			'parse_mode' => 'HTML',
			'photo' => new CURLFile($tmpfile, $mime, 'proof.'.$ext),
		];
		if ($trade_no !== '') {
			$params['reply_markup'] = json_encode(self::confirmKeyboard($trade_no, $amount), JSON_UNESCAPED_UNICODE);
		}
		return self::upload($token, 'sendPhoto', $params);
	}

	static private function upload($token, $method, $params)
	{
		return self::request($token, $method, $params, false)['result']['message_id'];
	}

	static private function request($token, $method, $params, $json)
	{
		if ((string)$token === '') {
			throw new Exception('Telegram Bot Token 不能为空');
		}
		$payload = $json ? json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $params;
		if ($json && $payload === false) {
			throw new Exception('Telegram 请求参数无效');
		}

		$ch = curl_init();
		if ($ch === false) {
			throw new Exception('Telegram 请求初始化失败');
		}
		curl_setopt_array($ch, [
			CURLOPT_URL => 'https://api.telegram.org/bot'.$token.'/'.$method,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => false,
		]);
		if ($json) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		}
		$resp = curl_exec($ch);
		$error = curl_error($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		if ($resp === false) {
			throw new Exception($error !== '' ? 'Telegram 网络请求失败' : 'Telegram 接口无响应');
		}
		$result = json_decode($resp, true);
		if (!is_array($result)) {
			throw new Exception($http_code >= 400 ? 'Telegram 接口请求失败' : 'Telegram 接口无响应');
		}
		if (empty($result['ok'])) {
			$msg = isset($result['description']) ? $result['description'] : 'Telegram 接口调用失败';
			throw new Exception($msg);
		}
		return $result;
	}

	static public function answer($token, $callback_id, $text, $alert = false)
	{
		self::api($token, 'answerCallbackQuery', [
			'callback_query_id' => $callback_id,
			'text' => $text,
			'show_alert' => $alert ? true : false,
		]);
	}

	static public function edit($token, $chat_id, $message_id, $text)
	{
		$params = [
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'parse_mode' => 'HTML',
		];
		try {
			self::api($token, 'editMessageText', $params + [
				'text' => $text,
				'disable_web_page_preview' => true,
			]);
		} catch (Exception $e) {
			self::api($token, 'editMessageCaption', $params + ['caption' => $text]);
		}
	}

	static public function h($s)
	{
		return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
