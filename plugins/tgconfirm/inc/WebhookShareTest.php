<?php
require __DIR__.'/WebhookShare.php';

$fail = 0;
function check($cond, $msg) {
	global $fail;
	if (!$cond) {
		$fail++;
		echo "FAIL $msg\n";
	}
}

$rows = [
	['id' => 12, 'config' => json_encode(['appkey' => 'tokA', 'appmchid' => '111', 'appsecret' => 'secA'])],
	['id' => 5, 'config' => json_encode(['appkey' => 'tokA', 'appmchid' => '222', 'appsecret' => 'secB'])],
	['id' => 9, 'config' => json_encode(['appkey' => 'tokB', 'appmchid' => '333', 'appsecret' => 'secC'])],
	['id' => 7, 'config' => ''],
];
$sib = TgconfirmWebhook::siblings($rows, 'tokA');
check(count($sib) === 2, 'same token yields two siblings');
check($sib[0]['id'] === 5 && $sib[1]['id'] === 12, 'owner is lowest id');
check(TgconfirmWebhook::ownerId($sib, 12) === 5, 'ownerId uses lowest');
check(TgconfirmWebhook::contains($sib, 12) && TgconfirmWebhook::contains($sib, 5), 'both channels associated');
check(!TgconfirmWebhook::contains($sib, 9), 'other bot not associated');
check(TgconfirmWebhook::secretMatches($sib, 'secA') && TgconfirmWebhook::secretMatches($sib, 'secB'), 'any sibling secret accepted');
check(!TgconfirmWebhook::secretMatches($sib, 'secC'), 'other bot secret rejected');
check(TgconfirmWebhook::chatAllowed($sib, '111') && TgconfirmWebhook::chatAllowed($sib, '222'), 'any sibling chat allowed');
check(!TgconfirmWebhook::chatAllowed($sib, '333'), 'other bot chat rejected');
check(TgconfirmWebhook::ownerSecret($sib, 'fallback') === 'secB', 'bind uses owner secret');
check(TgconfirmWebhook::siblings($rows, '') === [], 'empty token has no siblings');
check(TgconfirmWebhook::ownerId([], 12) === 12, 'no siblings falls back');

if ($fail) {
	echo "$fail failed\n";
	exit(1);
}
echo "ok\n";
