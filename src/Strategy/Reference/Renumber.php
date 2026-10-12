<?php

declare(strict_types=1);

namespace Shroud\Strategy\Reference;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Identity\Permutation;

/**
 * Permutes an id for its object type, keeping the type of the value: what the reference strategies share.
 */
final class Renumber
{
    /**
     * Returns the id permuted for this type; NULL, the empty string, zero and the negatives pass through.
     *
     * An id beyond `Permutation::MAX` is refused: it cannot be permuted reversibly. A string id
     * written with leading zeros, `'0042'`, keeps its width; a longer permuted id cannot.
     */
    public static function value(mixed $value, string $type, Context $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (is_int($value)) {
            return $context->generator->permutationFor($type)->apply(self::inDomain($value, $context));
        }
        if (! is_string($value) || preg_match('/^-?\d+$/', $value) !== 1) {
            throw new InvalidValueException($context->table, $context->column, 'integer id expected, ' . get_debug_type($value) . ' given');
        }
        // A negative id means no object: it stays as written, whatever its size.
        if (str_starts_with($value, '-')) {
            return $value;
        }
        // Too many digits for an integer: beyond the domain, and beyond what a cast would keep.
        $digits = ltrim($value, '0');
        if (strlen($digits) > strlen((string) Permutation::MAX)) {
            throw self::beyond($context);
        }

        $permuted = (string) $context->generator->permutationFor($type)->apply(self::inDomain((int) $digits, $context));

        return $value[0] === '0' ? str_pad($permuted, strlen($value), '0', STR_PAD_LEFT) : $permuted;
    }

    /**
     * Returns the id when the permutation can take it, refuses it otherwise.
     */
    private static function inDomain(int $id, Context $context): int
    {
        if ($id > Permutation::MAX) {
            throw self::beyond($context);
        }

        return $id;
    }

    private static function beyond(Context $context): InvalidValueException
    {
        return new InvalidValueException($context->table, $context->column, 'id beyond ' . Permutation::MAX);
    }
}
