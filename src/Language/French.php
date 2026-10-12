<?php

declare(strict_types=1);

namespace Shroud\Language;

use Shroud\Identity\Gender;

/**
 * Follows the conventions of French.
 */
final class French implements LanguageInterface
{
    private static ?Vocabulary $vocabulary = null;

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

    /**
     * The words elided before a vowel, "c'est", "d'Aubigné", "l'Oréal", "qu'importe": ce, de, je,
     * la, le, me, ne, se, te, que and its compounds.
     */
    private const array ELIDED_WORDS = ['c', 'd', 'j', 'l', 'm', 'n', 's', 't', 'qu', 'jusqu', 'lorsqu', 'puisqu', 'quoiqu'];

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

    public function elidedWords(): array
    {
        return self::ELIDED_WORDS;
    }

    public function civility(string $like, Gender $gender): ?string
    {
        $female = $gender === Gender::Female;

        return match (mb_strtolower($like)) {
            // The dot of "M." often goes missing, and "Mr" often stands for it.
            'm' => $female ? 'Mme' : 'M',
            'mr' => $female ? 'Mme' : 'Mr',
            'mr.' => $female ? 'Mme' : 'Mr.',
            'm.', 'mme', 'mme.', 'mlle', 'mlle.' => $female ? 'Mme' : 'M.',
            'monsieur', 'madame', 'mademoiselle' => $female ? 'Madame' : 'Monsieur',
            default => null,
        };
    }

    public function defaultCivility(Gender $gender): string
    {
        return $gender === Gender::Female ? 'Mme' : 'M.';
    }

    public function vocabulary(): Vocabulary
    {
        return self::$vocabulary ??= Vocabulary::fromFile(__DIR__ . '/../../resources/words/fr.php');
    }
}
