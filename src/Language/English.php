<?php

declare(strict_types=1);

namespace Shroud\Language;

use Shroud\Identity\Gender;

/**
 * Follows the conventions of English: no diacritic, no elision, the civilities and the words of English.
 */
final class English implements LanguageInterface
{
    private static ?Vocabulary $vocabulary = null;

    public function markDiacritic(string $word): string
    {
        return $word;
    }

    public function elidedWords(): array
    {
        return [];
    }

    public function civility(string $like, Gender $gender): ?string
    {
        $female = $gender === Gender::Female;

        return match (mb_strtolower($like)) {
            // British English drops the dot, American English keeps it: the form of the original stays.
            'mr', 'mrs', 'ms', 'miss' => $female ? 'Mrs' : 'Mr',
            'mr.', 'mrs.', 'ms.' => $female ? 'Mrs.' : 'Mr.',
            'sir', 'madam' => $female ? 'Madam' : 'Sir',
            default => null,
        };
    }

    public function defaultCivility(Gender $gender): string
    {
        return $gender === Gender::Female ? 'Mrs' : 'Mr';
    }

    public function vocabulary(): Vocabulary
    {
        return self::$vocabulary ??= Vocabulary::fromFile(__DIR__ . '/../../resources/words/en.php');
    }
}
