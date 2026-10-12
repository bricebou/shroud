<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Identity;

use Faker\Factory;
use Faker\Generator as Faker;
use PHPUnit\Framework\TestCase;
use Shroud\Identity\Pseudonym;

final class PseudonymTest extends TestCase
{
    public function testTheDrawDependsOnlyOnTheSeed(): void
    {
        self::assertEquals(Pseudonym::draw($this->faker(42), 'x1'), Pseudonym::draw($this->faker(42), 'x1'));
        self::assertNotEquals(Pseudonym::draw($this->faker(42), 'x1')->email, Pseudonym::draw($this->faker(43), 'x1')->email);
    }

    public function testTheNamesAreReservesOfSingleWords(): void
    {
        $pseudonym = Pseudonym::draw($this->faker(42), 'x1');

        self::assertCount(5, $pseudonym->firstNames);
        self::assertCount(5, $pseudonym->lastNames);
        foreach ([...$pseudonym->firstNames, ...$pseudonym->lastNames] as $word) {
            self::assertStringNotContainsString(' ', $word);
        }
        self::assertSame($pseudonym->lastNames, $pseudonym->listFullNameWords(0));
        self::assertSame($pseudonym->lastNames, $pseudonym->listFullNameWords(-1));
        self::assertSame([$pseudonym->firstNames[0], ...$pseudonym->lastNames], $pseudonym->listFullNameWords());
        self::assertSame([...array_slice($pseudonym->firstNames, 0, 2), ...$pseudonym->lastNames], $pseudonym->listFullNameWords(2));
    }

    public function testTheEmailJoinsTheFirstNamesTheSuffixAndASafeDomain(): void
    {
        $pseudonym = Pseudonym::draw($this->faker(42), 'x1');

        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.[a-z0-9-]+-x1$/', $pseudonym->handle);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.[a-z0-9-]+-x1@example\.(org|com|net)$/', $pseudonym->email);
        self::assertStringStartsWith($pseudonym->handle . '@', $pseudonym->email);
    }

    public function testTheOrganizationWordsAreNeverEmpty(): void
    {
        $pseudonym = Pseudonym::draw($this->faker(42), 'x1');

        self::assertGreaterThanOrEqual(8, count($pseudonym->organizationWords));
        self::assertNotContains('', $pseudonym->organizationWords);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.example$/', $pseudonym->domain, 'a reserved top-level domain');
        self::assertNotSame('', $pseudonym->street);
        self::assertNotSame('', $pseudonym->postalCode);
        self::assertNotSame('', $pseudonym->city);
    }

    public function testUnpackingGivesBackThePackedPseudonym(): void
    {
        foreach (['en_US', 'fr_FR', 'de_DE', 'ja_JP'] as $locale) {
            for ($seed = 1; $seed <= 50; ++$seed) {
                $pseudonym = Pseudonym::draw($this->faker($seed, $locale), 'x' . $seed);

                self::assertEquals($pseudonym, Pseudonym::unpack($pseudonym->pack()), "{$locale}, seed {$seed}");
            }
        }
    }

    private function faker(int $seed, string $locale = 'en_US'): Faker
    {
        $faker = Factory::create($locale);
        $faker->seed($seed);

        return $faker;
    }
}
