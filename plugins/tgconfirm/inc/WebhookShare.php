<?php
/**
 * Same Telegram bot token can serve many tgconfirm channels.
 * Owner = lowest channel id; webhook URL is always the owner's.
 */
class TgconfirmWebhook
{
	static public function parseConfig($raw)
	{
		$cfg = is_array($raw) ? $raw : json_decode((string)$raw, true);
		if (!is_array($cfg)) return null;
		$token = (string)($cfg['appkey'] ?? '');
		if ($token === '') return null;
		return [
			'appkey' => $token,
			'appmchid' => (string)($cfg['appmchid'] ?? ''),
			'appsecret' => trim((string)($cfg['appsecret'] ?? '')),
		];
	}

	static public function siblings(array $rows, $token)
	{
		$token = (string)$token;
		$out = [];
		if ($token === '') return $out;
		foreach ($rows as $row) {
			$cfg = self::parseConfig($row['config'] ?? null);
			if (!$cfg || $cfg['appkey'] !== $token) continue;
			$out[] = ['id' => (int)$row['id']] + $cfg;
		}
		usort($out, function ($a, $b) {
			return $a['id'] <=> $b['id'];
		});
		return $out;
	}

	static public function ownerId(array $siblings, $fallback)
	{
		return $siblings ? (int)$siblings[0]['id'] : (int)$fallback;
	}

	static public function secretMatches(array $siblings, $header)
	{
		$header = (string)$header;
		if ($header === '') return false;
		foreach ($siblings as $sib) {
			$s = (string)($sib['appsecret'] ?? '');
			if ($s !== '' && hash_equals($s, $header)) return true;
		}
		return false;
	}

	static public function chatAllowed(array $siblings, $chat_id)
	{
		$chat_id = (string)$chat_id;
		if ($chat_id === '') return false;
		foreach ($siblings as $sib) {
			if ((string)$sib['appmchid'] === $chat_id) return true;
		}
		return false;
	}

	static public function contains(array $siblings, $channelId)
	{
		$channelId = (int)$channelId;
		foreach ($siblings as $sib) {
			if ((int)$sib['id'] === $channelId) return true;
		}
		return false;
	}

	static public function ownerSecret(array $siblings, $fallback)
	{
		if ($siblings) {
			$s = trim((string)($siblings[0]['appsecret'] ?? ''));
			if ($s !== '') return $s;
		}
		return trim((string)$fallback);
	}
}
