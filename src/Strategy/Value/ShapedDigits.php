<?php

declare(strict_types=1);

namespace Shroud\Strategy\Value;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Replaces each digit and keeps the separators.
 *
 * With `keepDiallingCode`, a leading `+` and the one to three digits after it stay as they are.
 * A value with letters is refused: keeping them would leak, and replacing them is a decision for
 * the caller, through `Fallback`.
 */
final class ShapedDigits extends AbstractTextStrategy
{
    public function __construct(
        private readonly bool $keepDiallingCode = false,
    ) {
    }

    protected function transform(string $value, Context $context): string
    {
        $letters = preg_match('/\p{L}/u', $value);
        if ($letters !== 0) {
            throw new InvalidValueException($context->table, $context->column, $letters === 1 ? 'letters found' : 'invalid UTF-8');
        }

        // An international dialling code: a `+` followed by one to three digits.
        $start = 0;
        if ($this->keepDiallingCode && preg_match('/^\+\d{1,3}/', $value, $diallingCode) === 1) {
            $start = strlen($diallingCode[0]);
        }

        $draw = $context->draw();
        $result = substr($value, 0, $start);
        $length = strlen($value);
        for ($i = $start; $i < $length; ++$i) {
            $result .= ctype_digit($value[$i]) ? (string) $draw->digit() : $value[$i];
        }

        return $result;
    }
}
