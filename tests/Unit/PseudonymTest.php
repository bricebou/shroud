<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use Faker\Factory;
use Faker\Generator as Faker;
use PHPUnit\Framework\TestCase;
use Shroud\Pseudonym;

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

    private function faker(int $seed): Faker
    {
        $faker = Factory::create('en_US');
        $faker->seed($seed);

        return $faker;
    }
}
