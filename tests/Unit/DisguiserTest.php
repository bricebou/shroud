<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\Exception\MissingReferenceException;
use Shroud\Exception\TruncatedTableException;
use Shroud\Exception\UndeclaredColumnException;
use Shroud\Exception\UndeclaredTableException;
use Shroud\Generator;
use Shroud\Language\English;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\LastName;
use Shroud\Strategy\Value\Keep;

final class DisguiserTest extends TestCase
{
    /**
     * @var array<string, list<array<string, mixed>>>
     */
    private const array TABLES = [
        'people' => [
            [
                'id_person' => '4242',
                'name' => 'Zoé Kowalczyk',
                'email' => 'zoe.kowalczyk@example-real.test',
            ],
            [
                'id_person' => '4343',
                'name' => 'OPTIMA Legal-Advice',
                'email' => '',
            ],
        ],
        'contacts' => [
            [
                'id_contact' => '777',
                'id_person' => '4242',
                'first_name' => 'Zoé',
                'last_name' => 'Kowalczyk',
            ],
        ],
        'visits' => [
            [
                'date' => '2026-01-01',
                'visits' => '12',
            ],
        ],
    ];

    public function testTwoRunsGiveTheSameResult(): void
    {
        self::assertSame(Fixture::disguiser()->disguiseTables(self::TABLES), Fixture::disguiser()->disguiseTables(self::TABLES));
    }

    public function testTheOrderOfTheRowsDoesNotChangeARow(): void
    {
        $tables = self::TABLES;
        $tables['people'] = array_reverse($tables['people']);

        $direct = Fixture::disguiser()->disguiseTables(self::TABLES);
        $reversed = Fixture::disguiser()->disguiseTables($tables);

        self::assertSame($direct['people'][0], $reversed['people'][1]);
    }

    public function testAnotherSaltGivesAnotherResult(): void
    {
        $a = Fixture::disguiser('salt a')->disguiseTables(self::TABLES);
        $b = Fixture::disguiser('salt b')->disguiseTables(self::TABLES);

        self::assertNotSame($a['people'][0]['name'], $b['people'][0]['name']);
        self::assertNotSame($a['people'][0]['email'], $b['people'][0]['email']);
    }

    public function testNothingRealSurvives(): void
    {
        $person = Fixture::disguiser()->disguiseTables(self::TABLES)['people'][0];

        self::assertIsString($person['name']);
        self::assertIsString($person['email']);
        self::assertStringNotContainsString('Kowalczyk', $person['name']);
        self::assertStringNotContainsString('Zoé', $person['name']);
        self::assertStringNotContainsString('example-real', $person['email']);
        self::assertMatchesRegularExpression('/@example\.(org|com|net)$/', $person['email']);
    }

    public function testTheSamePersonGetsTheSamePseudonymInBothTables(): void
    {
        $result = Fixture::disguiser()->disguiseTables(self::TABLES);
        $contact = $result['contacts'][0];

        self::assertIsString($contact['first_name']);
        self::assertIsString($contact['last_name']);
        self::assertSame($contact['first_name'] . ' ' . $contact['last_name'], $result['people'][0]['name']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function composedNames(): iterable
    {
        yield 'hyphenated first name' => ['Jean-Pierre', 'Léonard-Kowalczyk'];
        yield 'first name with a space' => ['Jean Pierre', 'Léonard Kowalczyk'];
        yield 'first name with a non-breaking space' => ["Jean\u{00A0}Pierre", 'Léonard'];
        yield 'no first name' => ['', 'Léonard'];
        yield 'particle' => ['Jean', 'de La Tour'];
    }

    /**
     * The people come before the contacts, as in the corpora: the observation pass must be enough.
     */
    #[DataProvider('composedNames')]
    public function testTheFullNameTakesEveryFirstNameThenEveryLastName(string $firstName, string $lastName): void
    {
        $tables = [
            'people' => [[
                'id_person' => '7',
                'name' => trim($firstName . ' ' . $lastName),
                'email' => '',
            ]],
            'contacts' => [[
                'id_contact' => '1',
                'id_person' => '7',
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]],
        ];

        $result = Fixture::disguiser()->disguiseTables($tables);
        $contact = $result['contacts'][0];

        self::assertIsString($contact['first_name']);
        self::assertIsString($contact['last_name']);
        self::assertSame(trim($contact['first_name'] . ' ' . $contact['last_name']), $result['people'][0]['name']);
        if ($firstName !== '') {
            self::assertNotSame($firstName, $contact['first_name']);
        }
    }

    public function testTheShapeIsKept(): void
    {
        $result = Fixture::disguiser()->disguiseTables(self::TABLES);

        $name = $result['people'][0]['name'];
        self::assertIsString($name);
        self::assertMatchesRegularExpression('/^\p{Lu}\p{Ll}+ \p{Lu}\p{Ll}+$/u', $name);
        self::assertMatchesRegularExpression('/[^\x00-\x7F]/', $name, 'the diacritics of the original call for a diacritic');

        $organization = $result['people'][1]['name'];
        self::assertIsString($organization);
        self::assertMatchesRegularExpression('/^\p{Lu}+ \p{Lu}\p{Ll}+-\p{Lu}\p{Ll}+$/u', $organization);
        self::assertSame('', $result['people'][1]['email']);
    }

    public function testATruncatedTableComesOutEmpty(): void
    {
        self::assertSame([], Fixture::disguiser()->disguiseTables(self::TABLES)['visits']);
    }

    public function testARowOfATruncatedTableIsRefused(): void
    {
        $this->expectException(TruncatedTableException::class);
        $this->expectExceptionMessage('visits');

        Fixture::disguiser()->disguiseRow('visits', [
            'date' => '2026-01-01',
        ]);
    }

    /**
     * An adapter reading in a stream observes the whole batch, then disguises row by row.
     */
    public function testObservingThenDisguisingRowByRowEqualsTheBatch(): void
    {
        $batch = Fixture::disguiser()->disguiseTables(self::TABLES);

        $disguiser = Fixture::disguiser();
        foreach (self::TABLES as $tableName => $rows) {
            foreach ($rows as $row) {
                $disguiser->observeRow($tableName, $row);
            }
        }
        $streamed = [];
        foreach (['people', 'contacts'] as $tableName) {
            foreach (self::TABLES[$tableName] as $row) {
                $streamed[$tableName][] = $disguiser->disguiseRow($tableName, $row);
            }
        }

        self::assertSame($batch['people'], $streamed['people']);
        self::assertSame($batch['contacts'], $streamed['contacts']);
    }

    public function testTheFallbackReferenceTakesOverWhenThePersonIsMissing(): void
    {
        $declaration = new Declaration([
            new Table('contacts', 'id_contact', [
                'id_contact' => new Keep(),
                'id_person' => new Keep(),
                'last_name' => new LastName(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')->withFallback(ReferenceColumns::fromFixedType('contact', 'id_contact'))),
        ]);
        $disguiser = new Disguiser($declaration, new Generator(Fixture::SALT));

        $withoutPerson = $disguiser->disguiseRow('contacts', [
            'id_contact' => '7',
            'id_person' => '0',
            'last_name' => 'Dupont',
        ]);
        $withPerson = $disguiser->disguiseRow('contacts', [
            'id_contact' => '7',
            'id_person' => '4242',
            'last_name' => 'Dupont',
        ]);

        self::assertNotSame('Dupont', $withoutPerson['last_name']);
        self::assertNotSame($withoutPerson['last_name'], $withPerson['last_name']);
    }

    public function testEnglishDrawsEnglishNamesWithoutDiacritics(): void
    {
        $result = Fixture::disguiser(language: new English())->disguiseTables(self::TABLES);

        self::assertIsString($result['people'][0]['name']);
        self::assertMatchesRegularExpression('/^[A-Z][a-z]+ [A-Z][a-z]+$/', $result['people'][0]['name']);
    }

    public function testAnUndeclaredColumnIsAnError(): void
    {
        $this->expectException(UndeclaredColumnException::class);
        $this->expectExceptionMessage('people.password');

        Fixture::disguiser()->disguiseRow('people', [
            'id_person' => '1',
            'password' => 'x',
        ]);
    }

    public function testAnUndeclaredTableIsAnError(): void
    {
        $this->expectException(UndeclaredTableException::class);

        Fixture::disguiser()->disguiseRow('articles', [
            'id' => '1',
        ]);
    }

    public function testAStrategyNeedingAReferenceFailsWithoutOne(): void
    {
        $declaration = new Declaration([
            new Table('settings', 'name', [
                'name' => new Keep(),
                'value' => new Email(),
            ])]);

        $this->expectException(MissingReferenceException::class);
        $this->expectExceptionMessage('settings.value');

        new Disguiser($declaration, new Generator(Fixture::SALT))->disguiseRow('settings', [
            'name' => 'admin_email',
            'value' => 'x@example-real.test',
        ]);
    }
}
