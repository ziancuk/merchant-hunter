<?php

declare(strict_types=1);

namespace App\Services\Providers;

/** Small deterministic xorshift32 PRNG: no global state (unlike mt_srand), identical on every platform. */
final class SeededRandom
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = ($seed & 0xFFFFFFFF) ?: 0x9E3779B9;
        for ($i = 0; $i < 8; $i++) {
            $this->next();
        }
    }

    public static function forKey(string $key, int $seed): self
    {
        return new self(crc32($key.'|'.$seed));
    }

    private function next(): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        return $this->state = $x & 0xFFFFFFFF;
    }

    /** Float in [0, 1). */
    public function float(): float
    {
        return $this->next() / 4294967296;
    }

    /** Integer in [$min, $max] inclusive. */
    public function int(int $min, int $max): int
    {
        return $min + (int) floor($this->float() * ($max - $min + 1));
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @param  list<int|float>  $weights
     * @return T
     */
    public function weighted(array $items, array $weights): mixed
    {
        $total = array_sum($weights);
        if ($total <= 0) {
            return $this->pick($items);
        }
        $r = $this->float() * $total;
        foreach ($items as $i => $item) {
            $r -= $weights[$i];
            if ($r < 0) {
                return $item;
            }
        }

        return $items[count($items) - 1];
    }
}
