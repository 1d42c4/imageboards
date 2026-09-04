<?php
/**
 * Minimal IPv4/IPv6 CIDR helper (replaces lifo/ip for our ban mask needs).
 */
namespace Lifo\IP;

class CIDR {
	private string $mask;
	private string $start;
	private string $end;

	public function __construct(string $mask) {
		$this->mask = trim($mask);
		if (str_contains($this->mask, '/')) {
			[$ip, $bits] = explode('/', $this->mask, 2);
			$bits = (int)$bits;
			$bin = @inet_pton($ip);
			if ($bin === false) {
				throw new \InvalidArgumentException('Invalid IP in CIDR');
			}
			$len = strlen($bin);
			$maxBits = $len * 8;
			if ($bits < 0 || $bits > $maxBits) {
				throw new \InvalidArgumentException('Invalid prefix length');
			}
			$maskBin = self::prefixMask($len, $bits);
			$start = $bin & $maskBin;
			// end = start | ~mask
			$end = '';
			for ($i = 0; $i < $len; $i++) {
				$end .= chr(ord($start[$i]) | (ord($maskBin[$i]) ^ 0xFF));
			}
			$this->start = inet_ntop($start);
			$this->end = inet_ntop($end);
		} else {
			if (@inet_pton($this->mask) === false) {
				throw new \InvalidArgumentException('Invalid IP');
			}
			$this->start = $this->mask;
			$this->end = $this->mask;
		}
	}

	public function getRange(): array {
		return [$this->start, $this->end];
	}

	/**
	 * @return string|false CIDR string or false
	 */
	public static function range_to_cidr(string $start, string $end) {
		$sb = @inet_pton($start);
		$eb = @inet_pton($end);
		if ($sb === false || $eb === false || strlen($sb) !== strlen($eb)) {
			return false;
		}
		if ($sb === $eb) {
			return $start;
		}
		$len = strlen($sb);
		$max = $len * 8;
		// Find common prefix length
		$bits = 0;
		for ($i = 0; $i < $len; $i++) {
			$x = ord($sb[$i]) ^ ord($eb[$i]);
			if ($x === 0) {
				$bits += 8;
				continue;
			}
			for ($b = 7; $b >= 0; $b--) {
				if ($x & (1 << $b)) {
					break 2;
				}
				$bits++;
			}
			break;
		}
		// Verify range matches exact CIDR block
		try {
			$c = new self($start . '/' . $bits);
			$r = $c->getRange();
			if ($r[0] === $start && $r[1] === $end) {
				return $start . '/' . $bits;
			}
		} catch (\Throwable $e) {
			return false;
		}
		return false;
	}

	private static function prefixMask(int $len, int $bits): string {
		$out = '';
		for ($i = 0; $i < $len; $i++) {
			$left = $bits - $i * 8;
			if ($left >= 8) {
				$out .= "\xff";
			} elseif ($left <= 0) {
				$out .= "\x00";
			} else {
				$out .= chr((0xFF << (8 - $left)) & 0xFF);
			}
		}
		return $out;
	}
}
