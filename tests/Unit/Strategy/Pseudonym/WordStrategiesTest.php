<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Pseudonym;

use Shroud\Generator;
use Shroud\Language\French;
use Shroud\Reference;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\LastName;
use Shroud\Strategy\Pseudonym\Organization;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class WordStrategiesTest extends StrategyTestCase
{
    public function testTheNamesComeFromThePseudonymOfTheReferencedObject(): void
    {
        $generator = new Generator(self::SALT);
        $pseudonym = $generator->pseudonymFor(new Reference('person', 42));
        $context = self::context($generator);

        self::assertSame(ucfirst($pseudonym->firstNames[0]), self::apply(new FirstName(), 'Zoé', $context));
        self::assertSame(mb_strtoupper($pseudonym->lastNames[0]), self::apply(new LastName(), 'KOWALCZYK', $context));
        self::assertSame(
            ucfirst($pseudonym->firstNames[0]) . ' ' . ucfirst($pseudonym->lastNames[0]),
            self::apply(new FullName(), 'Zoé Kowalczyk', $context),
        );
    }

    public function testACompoundNameTakesAsManyWords(): void
    {
        $pseudonym = new Generator(self::SALT)->pseudonymFor(new Reference('person', 42));

        self::assertSame(
            ucfirst($pseudonym->firstNames[0]) . '-' . ucfirst($pseudonym->firstNames[1]),
            self::apply(new FirstName(), 'Jean-Pierre'),
        );
        self::assertSame(
            ucfirst($pseudonym->lastNames[0]) . ' ' . ucfirst($pseudonym->lastNames[1]),
            self::apply(new LastName(), 'Da Costa'),
        );
    }

    /**
     * The first name observed on the object tells the full name how many first names to take.
     */
    public function testTheFullNameTakesAsManyFirstNamesAsObserved(): void
    {
        $generator = new Generator(self::SALT);
        $pseudonym = $generator->pseudonymFor(new Reference('person', 42));
        $authors = self::context($generator, column: 'first_name', key: '42');
        $contacts = self::context($generator, column: 'name', key: '7');

        new FirstName()->observe('Jean Pierre', $authors);

        self::assertSame(2, $generator->recall(new Reference('person', 42), FirstName::WORD_COUNT));
        self::assertSame(
            ucfirst($pseudonym->firstNames[0]) . ' ' . ucfirst($pseudonym->firstNames[1]) . ' ' . ucfirst($pseudonym->lastNames[0]),
            self::apply(new FullName(), 'Jean Pierre Dupont', $contacts),
        );
    }

    public function testWithoutObservationTheFirstGroupStandsForTheFirstName(): void
    {
        $pseudonym = new Generator(self::SALT)->pseudonymFor(new Reference('person', 42));

        self::assertSame(
            ucfirst($pseudonym->firstNames[0]) . '-' . ucfirst($pseudonym->firstNames[1]) . ' ' . ucfirst($pseudonym->lastNames[0]) . ' ' . ucfirst($pseudonym->lastNames[1]),
            self::apply(new FullName(), 'Jean-Pierre Da Costa'),
        );
        self::assertSame(ucfirst($pseudonym->firstNames[0]), self::apply(new FullName(), 'Dupont'), 'one word: taken for a first name');
    }

    public function testTheObservationIgnoresWhatIsNotAString(): void
    {
        $generator = new Generator(self::SALT);

        new FirstName()->observe(value: null, context: self::context($generator));
        new FirstName()->observe(42, self::context($generator));

        self::assertNull($generator->recall(new Reference('person', 42), FirstName::WORD_COUNT));
    }

    public function testWordsAlsoDropYears(): void
    {
        $name = self::applyToString(new FullName(), 'Gaspard Loiseau (1931-2019)');

        self::assertMatchesRegularExpression('/^\p{Lu}\p{Ll}+ \p{Lu}\p{Ll}+ \(\d{4}-\d{4}\)$/u', $name);
        self::assertStringNotContainsString('1931', $name);
    }

    public function testTheOrganizationKeepsItsShape(): void
    {
        $organization = self::applyToString(new Organization(), 'OPTIMA Kowalczyk Conseils-en-Droit (Lyon)');

        self::assertMatchesRegularExpression('/^\p{Lu}+ \p{Lu}\p{Ll}+ \p{Lu}\p{Ll}+-\p{Ll}+-\p{Lu}\p{Ll}+ \(\p{Lu}\p{Ll}+\)$/u', $organization);
        self::assertStringNotContainsString('Kowalczyk', $organization);
    }

    public function testAnAccentedNameGetsADiacriticOfTheLanguage(): void
    {
        $french = self::context(new Generator(self::SALT, language: new French()));

        self::assertMatchesRegularExpression('/[àâçéèêëîïôùûü]/u', self::applyToString(new FirstName(), 'Zoé', $french));
        self::assertMatchesRegularExpression('/^[a-zA-Z]+$/', self::applyToString(new FirstName(), 'Zoé'), 'plain: no diacritic');
    }

    /**
     * Every column of one object speaks of the same fake person, whatever the table.
     */
    public function testTwoTablesReferencingTheSameObjectAgree(): void
    {
        $generator = new Generator(self::SALT);

        self::assertSame(
            self::apply(new LastName(), 'Kowalczyk', self::context($generator, key: '42')),
            self::apply(new LastName(), 'Kowalczyk', self::context($generator, column: 'surname', key: '7', reference: new Reference('person', '42'))),
        );
    }
}
