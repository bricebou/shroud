<?php

declare(strict_types=1);

namespace Shroud;

use Shroud\Language\LanguageInterface;
use Shroud\Platform\PlatformInterface;

/**
 * Replaces the content of a text while keeping its shape.
 *
 * A word is replaced by a word, a digit by a digit, a letter of a code by a letter; everything
 * else stays in place: blanks, non-breaking spaces, punctuation, HTML tags and entities, and the
 * markup of the platform. A replacement takes the case of the original and, when the original
 * carried a diacritic, one of the language. The elisions of the language are dropped.
 */
final readonly class Text
{
    /**
     * A word: a run of letters touching no digit. `3e` or `1a2b3c` are codes.
     */
    private const string WORD = '(?<![\p{L}\p{N}])\p{L}+(?![\p{L}\p{N}])';

    /**
     * What is never a word: HTML tags and entities, before the markup of the platform.
     */
    private const string HTML = '<[^>]*>|&[a-zA-Z0-9#]+;';

    /**
     * The markup to leave untouched: HTML, then that of the platform.
     */
    private string $markup;

    /**
     * An elision before a word, "d'Aubigné", "qu'importe"; matches nothing when the language elides nothing.
     */
    private string $elision;

    /**
     * The same elision, once cut from its surroundings.
     */
    private string $isolatedElision;

    public function __construct(
        PlatformInterface $platform,
        private LanguageInterface $language,
    ) {
        $this->markup = implode('|', [self::HTML, ...$platform->preservedMarkup()]);
        $elided = array_map(static fn (string $word): string => preg_quote($word, '/'), $language->elidedWords());
        // Longest first, so that `lorsqu'` is not read as `l` followed by letters.
        usort($elided, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $words = $elided === [] ? '(?!)' : '(?:' . implode('|', $elided) . ')';
        $this->elision = '(?<!\p{L})' . $words . '[\'’](?=\p{L})';
        $this->isolatedElision = '^' . $words . '[\'’]$';
    }

    /**
     * Replaces each word by the one of the same rank provided, and keeps everything else.
     *
     * Blanks, punctuation, digits, codes and markup stay in place; the elisions are dropped.
     * Returns null when the text is not valid UTF-8.
     *
     * @param callable(int): string $word
     */
    public function replaceWords(string $text, callable $word): ?string
    {
        // The text is split into pieces of four kinds. The captures keep the markup, the
        // elisions and the words; what lies between them, blanks, punctuation, digits, codes,
        // is a separator. A split that fails signals invalid UTF-8.
        $parts = preg_split('/(' . $this->markup . '|' . $this->elision . '|' . self::WORD . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return null;
        }

        $rank = 0;
        $result = '';
        foreach ($parts as $part) {
            // An elision cut from its surroundings is no longer recognised by its lookarounds: bare form.
            if (preg_match('/' . $this->isolatedElision . '/iu', $part) === 1) {
                continue;
            }
            // Separator or markup: as is. Only a piece made of letters is a word.
            if (preg_match('/^\p{L}+$/u', $part) !== 1) {
                $result .= $part;
                continue;
            }
            $result .= $this->shapeLike($part, $word($rank++));
        }

        return $result;
    }

    /**
     * Counts the words of a text, markup, codes and elisions left aside.
     */
    public function countWords(string $text): int
    {
        $bare = preg_replace('/' . $this->markup . '|' . $this->elision . '/iu', ' ', $text) ?? $text;

        return (int) preg_match_all('/' . self::WORD . '/u', $bare);
    }

    /**
     * Replaces each digit and each letter of the codes, keeping the case of the letters and the markup.
     *
     * A code is any run of letters and digits that carries a digit: a year, a reference, `3e`.
     *
     * @param callable(): string $letter
     * @param callable(): int $digit
     */
    public function replaceCodes(string $text, callable $letter, callable $digit): string
    {
        return preg_replace_callback(
            '/(' . $this->markup . ')|[\p{L}\p{N}]*\p{N}[\p{L}\p{N}]*/u',
            static function (array $match) use ($letter, $digit): string {
                if (($match[1] ?? '') !== '') {
                    return $match[0];
                }
                $result = '';
                foreach (mb_str_split($match[0]) as $char) {
                    if (ctype_digit($char)) {
                        $result .= (string) $digit();
                    } elseif (preg_match('/\p{L}/u', $char) === 1) {
                        // The case of each letter of the code is carried over to the drawn letter.
                        $result .= mb_strtoupper($char) === $char ? mb_strtoupper($letter()) : mb_strtolower($letter());
                    } else {
                        $result .= $char;
                    }
                }

                return $result;
            },
            $text,
        ) ?? $text;
    }

    /**
     * Gives the replacement the case of the original, and a diacritic when the original carried one.
     */
    private function shapeLike(string $original, string $replacement): string
    {
        $word = mb_strtolower($replacement);
        $initial = mb_substr($original, 0, 1);
        if (mb_strlen($original) > 1 && mb_strtoupper($original) === $original) {
            $word = mb_strtoupper($word);
        } elseif (mb_strtoupper($initial) === $initial && mb_strtolower($initial) !== $initial) {
            // An initial that changes case is a capital; a digit or a letter of a caseless script does not change.
            $word = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
        }
        // A diacritic in the original, none in the replacement: the language puts one, if it has any.
        if (preg_match('/[^\x00-\x7F]/', $original) === 1 && preg_match('/[^\x00-\x7F]/', $word) !== 1) {
            return $this->language->markDiacritic($word);
        }

        return $word;
    }
}
