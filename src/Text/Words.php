<?php

declare(strict_types=1);

namespace Shroud\Text;

use Shroud\Random\Draw;

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
     * The transliterator to ASCII, built once: building it costs fifteen times its use.
     */
    private static ?\Transliterator $toAscii = null;

    /**
     * The filler words, loaded once.
     *
     * @var ?non-empty-list<string>
     */
    private static ?array $lorem = null;

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
     * Draws filler words, latin-looking, tied to no language.
     *
     * @return list<string>
     */
    public static function lorem(Draw $draw, int $count): array
    {
        /** @var non-empty-list<string> $words */
        $words = self::$lorem ??= require __DIR__ . '/../../resources/words/lorem.php';
        $fillers = [];
        for ($i = 0; $i < $count; ++$i) {
            $fillers[] = $draw->pick($words);
        }

        return $fillers;
    }

    /**
     * Transliterates a text to ASCII, leaving it untouched when the transliteration fails.
     */
    public static function toAscii(string $text): string
    {
        self::$toAscii ??= \Transliterator::create('Any-Latin; Latin-ASCII');
        $transliterated = self::$toAscii?->transliterate($text);

        return is_string($transliterated) ? $transliterated : $text;
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
