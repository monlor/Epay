<?php
/**
 * Unique pay-amount marker for concurrent same-amount transfer orders.
 * First order keeps the original amount; later collisions add +0.01.
 */
class AmountMark
{
	public static function toCents($yuan)
	{
		return (int)round((float)$yuan * 100);
	}

	public static function fromCents($cents)
	{
		return number_format(((int)$cents) / 100, 2, '.', '');
	}

	public static function isReserved(array $ext, $now)
	{
		return isset($ext['amount'], $ext['expire'])
			&& $ext['amount'] !== ''
			&& (int)$ext['expire'] > (int)$now
			&& empty($ext['released'])
			&& empty($ext['closed']);
	}

	/**
	 * @param float|string $baseYuan
	 * @param array $usedYuan already taken amounts in the same window
	 * @return string amount with 2 decimal places
	 */
	public static function pick($baseYuan, array $usedYuan)
	{
		$baseCents = self::toCents($baseYuan);
		$used = [];
		foreach ($usedYuan as $yuan) {
			$used[self::toCents($yuan)] = true;
		}

		for ($i = 0; $i < 100; $i++) {
			$c = $baseCents + $i;
			if (!isset($used[$c])) {
				return self::fromCents($c);
			}
		}
		throw new Exception('当前金额档位已满，请稍后再下单');
	}
}
