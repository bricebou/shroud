<?php

declare(strict_types=1);

namespace Shroud;

use Faker\Generator as Faker;

/**
 * Handles words as units: extracting them from a text, drawing fillers, slugging.
 */
final class Words
{
    /**
     * A word: letters only, not glued to a letter or a digit on either side.
     */
    public const string PATTERN = '(?<![\p{L}\p{N}])\p{L}+(?![\p{L}\p{N}])';

    /**
     * Lists the words of a text, in order.
     *
     * @return list<string>
     */
    public static function extract(string $text): array
    {
        preg_match_all('/' . self::PATTERN . '/u', $text, $found);

        return $found[0];
    }

    /**
     * Draws filler words from a seeded Faker.
     *
     * @return list<string>
     */
    public static function lorem(Faker $faker, int $count): array
    {
        if ($count <= 0) {
            return [];
        }
        // Faker returns an array of words, or a string when asked for text: normalise.
        $words = $faker->words($count);

        return is_array($words) ? array_values(array_map(strval(...), $words)) : [$words];
    }

    /**
     * Transliterates a text to ASCII, leaving it untouched when the transliteration fails.
     */
    public static function toAscii(string $text): string
    {
        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);

        return $transliterated === false ? $text : $transliterated;
    }

    /**
     * Reduces a text to a lowercase ASCII slug, words joined by hyphens.
     */
    public static function toSlug(string $text): string
    {
        $ascii = strtolower(self::toAscii($text));

        return trim(preg_replace('/[^a-z0-9]+/', '-', $ascii) ?? $ascii, '-');
    }
}
