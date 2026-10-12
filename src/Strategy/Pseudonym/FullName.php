<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;

/**
 * Replaces a full name, "First Last", by the names of the referenced object.
 *
 * As many first names as the first name observed on the same object counts, then the last names,
 * when the observation accounts for the value: the observed first and last names count as many
 * words as the full name, or a first name alone was observed. Otherwise, an empty first name
 * beside a full name of two words for instance, the first group before a space stands for the first name.
 */
final class FullName extends AbstractShapedWords
{
    protected function provideWords(Context $context, string $value): array
    {
        return $context->requirePseudonym()->listFullNameWords($this->firstNameWords($context, $value));
    }

    /**
     * Returns how many first names the full name takes: the observed count when it accounts for the value, else the first group.
     */
    private function firstNameWords(Context $context, string $value): int
    {
        $text = $context->generator->text();
        $firstNameWords = $context->recall(FirstName::WORD_COUNT);
        $lastNameWords = $context->recall(LastName::WORD_COUNT);
        if (is_int($firstNameWords) && is_int($lastNameWords)) {
            if ($firstNameWords + $lastNameWords === $text->countWords($value)) {
                return $firstNameWords;
            }
        } elseif (is_int($firstNameWords) && $firstNameWords > 0) {
            return $firstNameWords;
        }

        // No observation, or one the value contradicts: the first group before a space stands for the first name.
        $firstGroup = preg_split('/[\s\x{00A0}]+/u', trim($value), 2)[0] ?? '';

        return $text->countWords($firstGroup);
    }
}
