<?php
/**
 * Unique pay-amount marker for concurrent same-amount transfer orders.
 * Integer yuan: start at +0.01. Already-decimal: keep first, then +0.01.
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

		$isInteger = ($baseCents % 100 === 0);
		$start = $isInteger ? $baseCents + 1 : $baseCents;
		$limit = $isInteger ? 99 : 100; // ponytail: integer skips .00, 99 slots
		for ($i = 0; $i < $limit; $i++) {
			$c = $start + $i;
			if (!isset($used[$c])) {
				return self::fromCents($c);
			}
		}
		throw new Exception('当前金额档位已满，请稍后再下单');
	}
}
