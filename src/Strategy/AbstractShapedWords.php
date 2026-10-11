<?php

declare(strict_types=1);

namespace Shroud\Strategy;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Words;

/**
 * Replaces the words of a value, rank by rank, by a list of fake words; beyond the list, by
 * fillers drawn for this value. Digits and codes go too, sign by sign: a year or a reference
 * identifies just as much. The references embedded by the platform follow the renumbering.
 */
abstract class AbstractShapedWords extends AbstractTextStrategy
{
    /**
     * Replaces the words, then the codes of the value, keeping its shape.
     */
    protected function transform(string $value, Context $context): string
    {
        $text = $context->generator->text();

        // As many fake words as the original counts: those of the strategy first, then fillers
        // drawn for this very value.
        $words = $this->provideWords($context, $value);
        $missing = $text->countWords($value) - count($words);
        if ($missing > 0) {
            $words = [...$words, ...Words::lorem($context->seedFaker(), $missing)];
        }

        // The codes after the words: a replacement never carries a digit, nothing is rewritten twice.
        $result = $text->replaceWords($value, static fn (int $rank): string => $words[$rank] ?? 'lorem')
            ?? throw new InvalidValueException($context->table, $context->column, 'invalid UTF-8');
        $faker = $context->seedFaker();
        $result = $text->replaceCodes($result, static fn (): string => $faker->randomLetter(), static fn (): int => $faker->randomDigit());

        // The embedded references, left untouched so far, follow the renumbering of their object.
        return $context->generator->platform()->renumberReferences($result, $context);
    }

    /**
     * Lists the fake words to place first, rank by rank; the real value only lends its shape.
     *
     * @return list<string>
     */
    abstract protected function provideWords(Context $context, string $value): array;
}
