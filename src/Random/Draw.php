<?php

declare(strict_types=1);

namespace Shroud\Random;

/**
 * Draws numbers, digits, letters and list items from a seed, and from nothing else.
 *
 * The draws read the SHA-256 blocks of the seed and a counter: no state is shared with any other
 * draw, nor with the random generator of PHP, which a library, a destructor or a garbage
 * collection may reseed at any time. The same seed gives the same draws, on any machine, under
 * any version of PHP.
 */
final class Draw
{
    /**
     * The bytes read so far from the current block and not used yet.
     */
    private string $buffer = '';

    /**
     * The rank of the next block.
     */
    private int $block = 0;

    public function __construct(
        private readonly string $seed,
    ) {
    }

    /**
     * Returns a draw of its own for a part of this one: the draws of one part do not move those of another.
     */
    public function for(string|int ...$keys): self
    {
        return new self(hash('sha256', implode("\x1f", [$this->seed, ...array_map(strval(...), $keys)])));
    }

    /**
     * Draws an integer between the two bounds, both included, every one as likely.
     */
    public function int(int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        // Four bytes give an integer below 2^32; those beyond the last whole multiple of the range
        // are drawn again, so that the modulo favours no number.
        $range = $max - $min + 1;
        $limit = intdiv(0x100000000, $range) * $range;
        do {
            $number = unpack('N', $this->bytes(4))[1] ?? 0;
        } while ($number >= $limit);

        return $min + $number % $range;
    }

    /**
     * Draws one item of a list.
     *
     * @template T
     *
     * @param non-empty-list<T> $items
     *
     * @return T
     */
    public function pick(array $items): mixed
    {
        return $items[$this->int(0, count($items) - 1)];
    }

    public function digit(): int
    {
        return $this->int(0, 9);
    }

    /**
     * Draws a lowercase letter of the Latin alphabet.
     */
    public function letter(): string
    {
        return chr(ord('a') + $this->int(0, 25));
    }

    public function boolean(): bool
    {
        return $this->int(0, 1) === 1;
    }

    /**
     * Replaces each `#` of a format by a digit: "#####" gives a postal code.
     */
    public function numerify(string $format): string
    {
        return preg_replace_callback('/#/', fn (): string => (string) $this->digit(), $format) ?? $format;
    }

    /**
     * Reads bytes from the blocks, the next one computed when the current one runs out.
     */
    private function bytes(int $count): string
    {
        while (strlen($this->buffer) < $count) {
            $this->buffer .= hash('sha256', $this->seed . "\x1f" . $this->block++, binary: true);
        }
        $bytes = substr($this->buffer, 0, $count);
        $this->buffer = substr($this->buffer, $count);

        return $bytes;
    }
}
