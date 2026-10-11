<?php

declare(strict_types=1);

namespace Shroud\Language;

/**
 * Follows the conventions of French.
 */
final class French implements LanguageInterface
{
    /**
     * The diacritic that French puts on a letter, by letter, the first markable letter of a word taking it.
     *
     * @var array<string, string>
     */
    private const array DIACRITICS = [
        'e' => 'é',
        'a' => 'à',
        'u' => 'ù',
        'o' => 'ô',
        'i' => 'î',
        'c' => 'ç',
        'E' => 'É',
        'A' => 'À',
        'U' => 'Ù',
        'O' => 'Ô',
        'I' => 'Î',
        'C' => 'Ç',
    ];

    public function markDiacritic(string $word): string
    {
        $length = strlen($word);
        for ($i = 0; $i < $length; ++$i) {
            if (isset(self::DIACRITICS[$word[$i]])) {
                return substr($word, 0, $i) . self::DIACRITICS[$word[$i]] . substr($word, $i + 1);
            }
        }

        return $word;
    }
}
