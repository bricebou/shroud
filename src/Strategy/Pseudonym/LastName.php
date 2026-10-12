<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;
use Shroud\Strategy\ObserverInterface;

/**
 * Replaces a last name, compound or not, by the last names of the referenced object.
 *
 * It notes how many words the last name counts, so that the full name of the same object can
 * check that the first and last names it observed account for all its words.
 */
final class LastName extends AbstractShapedWords implements ObserverInterface
{
    public const string WORD_COUNT = 'lastNameWords';

    /**
     * Notes the number of words of the last name of the object.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (is_string($value)) {
            $context->remember(self::WORD_COUNT, $context->generator->text()->countWords($value));
        }
    }

    protected function provideWords(Context $context, string $value): array
    {
        return $context->requirePseudonym()->lastNames;
    }
}
