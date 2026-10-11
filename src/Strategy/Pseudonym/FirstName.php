<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;
use Shroud\Strategy\ObserverInterface;

/**
 * Replaces a first name, compound or not, by the first names of the referenced object.
 *
 * It notes how many words the first name counts, so that the full name of the same object
 * takes exactly as many.
 */
final class FirstName extends AbstractShapedWords implements ObserverInterface
{
    public const string WORD_COUNT = 'firstNameWords';

    /**
     * Notes the number of words of the first name of the object.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (is_string($value)) {
            $context->remember(self::WORD_COUNT, $context->generator->text()->countWords($value));
        }
    }

    protected function provideWords(Context $context, string $value): array
    {
        return $context->requirePseudonym()->firstNames;
    }
}
