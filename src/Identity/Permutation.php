<?php

declare(strict_types=1);

namespace Shroud\Identity;

/**
 * Permutes positive ids reversibly under a key, through a Feistel network.
 *
 * Four rounds of HMAC-SHA256 over two sixteen-bit halves shuffle a 32-bit integer. When the
 * result leaves the id domain, from 1 to 2^31 - 1, the network is applied again until it
 * comes back: this cycle walking keeps the bijection on the domain. Zero and the negatives,
 * which mean "no object", are fixed points.
 */
final readonly class Permutation
{
    private const int ROUNDS = 4;

    /**
     * The largest id admitted, that of a signed 32-bit integer.
     */
    public const int MAX = 2147483647;

    public function __construct(
        private string $key,
        private bool $identity = false,
    ) {
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
        if ($id <= 0 || $this->identity) {
            return $id;
        }
        $result = $id;
        do {
            $result = $this->runNetwork($result, forward: true);
        } while ($result < 1 || $result > self::MAX);

        return $result;
    }

    /**
     * Returns the original id of a permuted id.
     */
    public function invert(int $id): int
    {
        if ($id <= 0 || $this->identity) {
            return $id;
        }
        $result = $id;
        do {
            $result = $this->runNetwork($result, forward: false);
        } while ($result < 1 || $result > self::MAX);

        return $result;
    }

    /**
     * Runs the network once, forwards or backwards.
     *
     * Forwards, each round computes `(L, R) -> (R, L ^ F(R))`; backwards, the rounds are
     * replayed in reverse order as `(L, R) -> (R ^ F(L), L)`, which exactly undoes the forward pass.
     */
    private function runNetwork(int $value, bool $forward): int
    {
        $left = ($value >> 16) & 0xFFFF;
        $right = $value & 0xFFFF;
        $rounds = $forward ? range(0, self::ROUNDS - 1) : range(self::ROUNDS - 1, 0);
        foreach ($rounds as $round) {
            [$left, $right] = $forward
                ? [$right, $left ^ $this->roundFunction($right, $round)]
                : [$right ^ $this->roundFunction($left, $round), $left];
        }

        return ($left << 16) | $right;
    }

    /**
     * Computes the round function: sixteen bits of an HMAC-SHA256 of the half and the round number.
     */
    private function roundFunction(int $half, int $round): int
    {
        return (int) hexdec(substr(hash_hmac('sha256', $round . ':' . $half, $this->key), 0, 4));
    }
}
