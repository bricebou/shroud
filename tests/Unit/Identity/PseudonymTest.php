<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Identity;

use PHPUnit\Framework\TestCase;
use Shroud\Identity\Gender;
use Shroud\Identity\Pseudonym;
use Shroud\Language\English;
use Shroud\Language\French;
use Shroud\Language\Vocabulary;
use Shroud\Random\Draw;

final class PseudonymTest extends TestCase
{
    public function testTheDrawDependsOnlyOnTheSeed(): void
    {
        self::assertEquals($this->draw('42'), $this->draw('42'));
        self::assertNotEquals($this->draw('42')->email, $this->draw('43')->email);
    }

    public function testTheNamesAreReservesOfSingleWords(): void
    {
        $pseudonym = $this->draw('42');

        self::assertCount(5, $pseudonym->firstNames);
        self::assertCount(5, $pseudonym->lastNames);
        foreach ([...$pseudonym->firstNames, ...$pseudonym->lastNames] as $word) {
            self::assertMatchesRegularExpression('/^\p{L}+$/u', $word);
        }
        self::assertSame($pseudonym->lastNames, $pseudonym->listFullNameWords(0));
        self::assertSame($pseudonym->lastNames, $pseudonym->listFullNameWords(-1));
        self::assertSame([$pseudonym->firstNames[0], ...$pseudonym->lastNames], $pseudonym->listFullNameWords());
        self::assertSame([...array_slice($pseudonym->firstNames, 0, 2), ...$pseudonym->lastNames], $pseudonym->listFullNameWords(2));
    }

    public function testTheFirstNamesFollowTheDrawnGender(): void
    {
        /** @var array{firstNames: array{female: list<string>, male: list<string>}} $lists */
        $lists = require __DIR__ . '/../../../resources/words/en.php';
        $genders = [];
        for ($seed = 1; $seed <= 20; ++$seed) {
            $pseudonym = $this->draw((string) $seed);
            foreach ($pseudonym->firstNames as $firstName) {
                self::assertContains($firstName, $lists['firstNames'][$pseudonym->gender->value], "seed {$seed}");
            }
            $genders[$pseudonym->gender->value] = true;
        }

        self::assertCount(2, $genders, 'both genders are drawn');
    }

    /**
     * A field has a draw of its own: another vocabulary changes the names, not the gender.
     */
    public function testEachFieldHasADrawOfItsOwn(): void
    {
        for ($seed = 1; $seed <= 20; ++$seed) {
            self::assertSame($this->draw((string) $seed)->gender, $this->draw((string) $seed, new French()->vocabulary())->gender);
        }
    }

    public function testTheEmailJoinsTheFirstNamesTheSuffixAndAReservedDomain(): void
    {
        $pseudonym = $this->draw('42');

        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.[a-z0-9-]+-x1$/', $pseudonym->handle);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.[a-z0-9-]+-x1@example\.(org|com|net)$/', $pseudonym->email);
        self::assertStringStartsWith($pseudonym->handle . '@', $pseudonym->email);
    }

    public function testTheOrganizationWordsAreNeverEmpty(): void
    {
        $pseudonym = $this->draw('42');

        self::assertGreaterThanOrEqual(8, count($pseudonym->organizationWords));
        self::assertNotContains('', $pseudonym->organizationWords);
        self::assertMatchesRegularExpression('/^[a-z0-9-]+\.example$/', $pseudonym->domain, 'a reserved top-level domain');
        self::assertMatchesRegularExpression('/^\d+ \p{L}+ \p{L}+$/u', $pseudonym->street);
        self::assertMatchesRegularExpression('/^\d{5}$/', $pseudonym->postalCode);
        self::assertNotSame('', $pseudonym->city);
    }

    public function testFrenchComposesItsAddressesTheFrenchWay(): void
    {
        $pseudonym = $this->draw('42', new French()->vocabulary());

        self::assertMatchesRegularExpression('/^\d{1,3}, (rue|chemin|avenue|boulevard|place|impasse) /', $pseudonym->street);
    }

    public function testUnpackingGivesBackThePackedPseudonym(): void
    {
        foreach ([new English(), new French()] as $language) {
            for ($seed = 1; $seed <= 50; ++$seed) {
                $pseudonym = $this->draw((string) $seed, $language->vocabulary());

                self::assertEquals($pseudonym, Pseudonym::unpack($pseudonym->pack()), $language::class . ", seed {$seed}");
            }
        }
    }

    private function draw(string $seed, ?Vocabulary $vocabulary = null): Pseudonym
    {
        return Pseudonym::draw($vocabulary ?? new English()->vocabulary(), new Draw($seed), 'x1');
    }
}
