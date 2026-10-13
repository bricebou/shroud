<?php

declare(strict_types=1);

namespace Shroud\Identity;

/**
 * Permutes positive ids reversibly under a key, through a Feistel network.
 *
 * Four rounds of HMAC-SHA256 over two halves shuffle an integer of the width of the network,
 * 32 bits by default. When the result leaves the domain, the ids from 1 to 2^31 - 1 or any
 * subset the caller names, the network is applied again until it comes back: this cycle
 * walking keeps the bijection on the domain. Zero and the negatives, which mean "no object",
 * are fixed points of the ids.
 */
final readonly class Permutation
{
    private const int ROUNDS = 4;

    /**
     * The largest id admitted, that of a signed 32-bit integer.
     */
    public const int MAX = 2147483647;

    /**
     * @param int $bits the width of the network, even, from 2 to 32
     */
    public function __construct(
        private string $key,
        private bool $identity = false,
        private int $bits = 32,
    ) {
        if ($bits < 2 || $bits > 32 || $bits % 2 !== 0) {
            throw new \InvalidArgumentException(sprintf('the width of a permutation is even, from 2 to 32 bits, %d given', $bits));
        }
    }

    /**
     * Returns the permutation that leaves every id untouched, for a pass without renumbering.
     */
    public static function identity(): self
    {
        return new self('', identity: true);
    }

    /**
     * Returns the permuted id, within the id domain.
     */
    public function apply(int $id): int
    {
        return $id <= 0 ? $id : $this->applyWithin($id, $this->isId(...));
    }

    /**
     * Returns the original id of a permuted id.
     */
    public function invert(int $id): int
    {
        return $id <= 0 ? $id : $this->invertWithin($id, $this->isId(...));
    }

    /**
     * Returns the permuted value within a domain of the network, the value being in it.
     *
     * @param callable(int): bool $inDomain tells whether a value of the width of the network belongs to the domain
     */
    public function applyWithin(int $value, callable $inDomain): int
    {
        return $this->walk($value, $inDomain, forward: true);
    }

    /**
     * Returns the original value of a value permuted within a domain.
     *
     * @param callable(int): bool $inDomain
     */
    public function invertWithin(int $value, callable $inDomain): int
    {
        return $this->walk($value, $inDomain, forward: false);
    }

    /**
     * Runs the network until the result is back in the domain.
     *
     * @param callable(int): bool $inDomain
     */
    private function walk(int $value, callable $inDomain, bool $forward): int
    {
        if ($this->identity) {
            return $value;
        }
        $result = $value;
        do {
            $result = $this->runNetwork($result, $forward);
        } while (! $inDomain($result));

        return $result;
    }

    /**
     * Tells whether a value is an id the permutation admits: from 1 to the largest the width allows.
     */
    private function isId(int $value): bool
    {
        return $value >= 1 && $value <= min(self::MAX, (1 << $this->bits) - 1);
    }

    /**
     * Runs the network once, forwards or backwards.
     *
     * Forwards, each round computes `(L, R) -> (R, L ^ F(R))`; backwards, the rounds are
     * replayed in reverse order as `(L, R) -> (R ^ F(L), L)`, which exactly undoes the forward pass.
     */
    private function runNetwork(int $value, bool $forward): int
    {
        $half = intdiv($this->bits, 2);
        $mask = (1 << $half) - 1;
        $left = ($value >> $half) & $mask;
        $right = $value & $mask;
        $rounds = $forward ? range(0, self::ROUNDS - 1) : range(self::ROUNDS - 1, 0);
        foreach ($rounds as $round) {
            [$left, $right] = $forward
                ? [$right, $left ^ $this->roundFunction($right, $round)]
                : [$right ^ $this->roundFunction($left, $round), $left];
        }

        return ($left << $half) | $right;
    }

    /**
     * Computes the round function: a half width of the first sixteen bits of an HMAC-SHA256 of the half and the round number.
     */
    private function roundFunction(int $half, int $round): int
    {
        return (int) hexdec(substr(hash_hmac('sha256', $round . ':' . $half, $this->key), 0, 4)) & ((1 << intdiv($this->bits, 2)) - 1);
    }
}
