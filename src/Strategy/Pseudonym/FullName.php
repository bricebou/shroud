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
 * words as the full name, or a first name alone was observed, or a last name alone as many words
 * as the full name. Otherwise, an empty first name beside a full name of two words for instance,
 * the first group before a space stands for the first name, and a single group for the last name.
 *
 * A civility of the language at the head, "Mme", "Mr.", gives way to the one of the gender the
 * object draws, which agrees with its first names.
 */
final class FullName extends AbstractShapedWords
{
    protected function transform(string $value, Context $context): string
    {
        [$civility, $name] = $context->generator->text()->splitCivility($value, $context->requirePseudonym()->gender);

        return $civility . parent::transform($name, $context);
    }

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
        } elseif (is_int($lastNameWords) && $lastNameWords === $text->countWords($value)) {
            return 0;
        }

        // No observation, or one the value contradicts: the first group before a space stands for
        // the first name, and a single group, "Dupont", for the last name.
        $groups = preg_split('/[\s\x{00A0}]+/u', trim($value), 2);

        return is_array($groups) && count($groups) > 1 ? $text->countWords($groups[0]) : 0;
    }
}
