<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Identity\Reference;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\ObserverInterface;
use Shroud\Text\Phones;

/**
 * Replaces a phone number by the digits of the referenced object, keeping its shape.
 *
 * The separators stay, and so does what says how to dial rather than whom: an international
 * dialling code, `+33`, or the trunk prefix of a national number, `0`. The other digits come
 * from the pseudonym, the last first: "06 12 34 56 78" and "+33 6 12 34 56 78" end the same,
 * and the person keeps one number in every table. The number is noted on its object during
 * the observation, and `WithoutPhones` gives it the same digits where a text quotes it. A
 * value with letters is refused, as by `ShapedDigits`.
 */
final class Phone extends AbstractTextStrategy implements ObserverInterface
{
    protected function transform(string $value, Context $context): string
    {
        $digits = $context->requirePseudonym()->phoneDigits;
        $letters = preg_match('/\p{L}/u', $value);
        if ($letters !== 0) {
            throw new InvalidValueException($context->table, $context->column, $letters === 1 ? 'letters found' : 'invalid UTF-8');
        }

        return Phones::lay($value, $digits);
    }

    /**
     * Notes that the referenced object holds the number, so that `WithoutPhones` gives it the same digits in the texts.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (is_string($value) && $context->reference instanceof Reference && Phones::countDigits($value) >= 8) {
            $context->generator->noteHolder('phone', Phones::key($value), $context->reference);
        }
    }
}
