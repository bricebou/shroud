<?php

declare(strict_types=1);

namespace Shroud\Language;

use Shroud\Identity\Gender;

/**
 * Follows no convention of its own: a replacement keeps the plain word it was given.
 *
 * Its civilities are those of English, the language of the default Faker locale.
 */
final class Plain implements LanguageInterface
{
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
}
