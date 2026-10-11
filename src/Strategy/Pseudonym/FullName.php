<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;

/**
 * Replaces a full name, "First Last", by the names of the referenced object.
 *
 * As many first names as the first name observed on the same object counts, then the last
 * names. Without observation, the first group before a space stands for the first name.
 */
final class FullName extends AbstractShapedWords
{
    protected function provideWords(Context $context, string $value): array
    {
        $firstNameWords = $context->recall(FirstName::WORD_COUNT);
        // Outside a batch, no first name was observed: the first group before a space stands for it.
        if (! is_int($firstNameWords)) {
            $firstGroup = preg_split('/[\s\x{00A0}]+/u', trim($value), 2)[0] ?? '';
            $firstNameWords = $context->generator->text()->countWords($firstGroup);
        }

        return $context->requirePseudonym()->listFullNameWords($firstNameWords);
    }
}
