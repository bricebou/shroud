<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\Value\ShapedDigits;

/**
 * Replaces a phone number by the digits of the referenced object, keeping its shape.
 *
 * The separators stay, and so does what says how to dial rather than whom: an international
 * dialling code, `+33`, or the trunk prefix of a national number, `0`. The other digits come
 * from the pseudonym, the last first: "06 12 34 56 78" and "+33 6 12 34 56 78" end the same,
 * and the person keeps one number in every table. A value with letters is refused, as by
 * `ShapedDigits`.
 */
final class Phone extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        $digits = $context->requirePseudonym()->phoneDigits;
        $letters = preg_match('/\p{L}/u', $value);
        if ($letters !== 0) {
            throw new InvalidValueException($context->table, $context->column, $letters === 1 ? 'letters found' : 'invalid UTF-8');
        }

        $start = preg_match('/^\s*(?:' . ShapedDigits::DIALLING_CODE . '|0)/', $value, $prefix) === 1 ? strlen($prefix[0]) : 0;
        $rank = 0;
        $result = '';
        for ($i = strlen($value) - 1; $i >= $start; --$i) {
            $result = (ctype_digit($value[$i]) ? $digits[$rank++ % strlen($digits)] : $value[$i]) . $result;
        }

        return substr($value, 0, $start) . $result;
    }
}
