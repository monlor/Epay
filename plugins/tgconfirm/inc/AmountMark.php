<?php
/**
 * Unique pay-amount marker for concurrent same-amount transfer orders.
 * First order keeps the original amount; later collisions keep adding +0.01.
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
		$c = self::toCents($baseYuan);
		$used = [];
		foreach ($usedYuan as $yuan) {
			$used[self::toCents($yuan)] = true;
		}
		while (isset($used[$c])) {
			$c++;
		}
		return self::fromCents($c);
	}
}
