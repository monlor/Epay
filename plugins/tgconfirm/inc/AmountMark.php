<?php
/**
 * Unique pay-amount marker for concurrent same-amount transfer orders.
 * Integer yuan: random 2-digit cents. Already-decimal: keep first, then +0.01.
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

	/**
	 * @param float|string $baseYuan
	 * @param array $usedYuan already taken amounts in the same window
	 * @param callable|null $pickFn fn(int[] $candidates): int  — inject for tests
	 * @return string amount with 2 decimal places
	 */
	public static function pick($baseYuan, array $usedYuan, $pickFn = null)
	{
		$baseCents = self::toCents($baseYuan);
		$used = [];
		foreach ($usedYuan as $yuan) {
			$used[self::toCents($yuan)] = true;
		}

		if ($baseCents % 100 === 0) {
			$candidates = [];
			for ($i = 0; $i < 100; $i++) {
				$c = $baseCents + $i;
				if (!isset($used[$c])) {
					$candidates[] = $c;
				}
			}
			if (!$candidates) {
				throw new Exception('当前金额档位已满，请稍后再下单');
			}
			if ($pickFn) {
				$chosen = (int)$pickFn($candidates);
			} else {
				$chosen = $candidates[array_rand($candidates)];
			}
			return self::fromCents($chosen);
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
