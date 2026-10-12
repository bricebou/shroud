<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Generator;
use Shroud\Identity\Permutation;
use Shroud\Identity\Reference;
use Shroud\Language\English;
use Shroud\Language\French;

final class GeneratorTest extends TestCase
{
    private const string SALT = 'test salt';

    public function testThePseudonymOfAnObjectDependsOnlyOnTheSaltAndTheReference(): void
    {
        $generator = new Generator(self::SALT);
        $pseudonym = $generator->pseudonymFor(new Reference('author', 4242));

        self::assertEquals($pseudonym, $generator->pseudonymFor(new Reference('author', '4242')), 'drawn once, kept');
        self::assertEquals($pseudonym, new Generator(self::SALT)->pseudonymFor(new Reference('author', 4242)), 'same across runs');
        self::assertNotEquals($pseudonym->email, new Generator('another salt')->pseudonymFor(new Reference('author', 4242))->email);
        self::assertNotEquals($pseudonym->email, $generator->pseudonymFor(new Reference('contact', 4242))->email);
    }

    /**
     * The email of a pseudonym carries the permuted id of its object: unique within its type, even
     * for two objects drawing the same names, and stable from one run to the next.
     */
    public function testThePseudonymEmailCarriesThePermutedIdInBase36(): void
    {
        $generator = new Generator(self::SALT);
        $suffix = base_convert((string) $generator->permutationFor('author')->apply(4242), 10, 36);

        self::assertStringEndsWith('-' . $suffix . '@' . $this->domainOf($generator->pseudonymFor(new Reference('author', 4242))->email), $generator->pseudonymFor(new Reference('author', 4242))->email);
        self::assertStringContainsString('-' . base_convert('4242', 10, 36) . '@', new Generator(self::SALT, renumbering: false)->pseudonymFor(new Reference('author', 4242))->email, 'without renumbering, the id itself');
    }

    public function testANonNumericIdGetsSixCharactersOfItsDigest(): void
    {
        $generator = new Generator(self::SALT);
        $suffix = substr($generator->digest('pseudonym', 'setting', 'site_name'), 0, 6);

        self::assertMatchesRegularExpression('/-' . $suffix . '@/', $generator->pseudonymFor(new Reference('setting', 'site_name'))->email);
    }

    public function testEachTypeAndEachSaltHasItsOwnPermutation(): void
    {
        $a = new Generator('salt a');
        $b = new Generator('salt b');

        self::assertSame($a->permutationFor('author'), $a->permutationFor('author'));
        self::assertSame($a->permutationFor('author')->apply(12), $a->permutationFor('author')->apply(12));
        self::assertNotSame($a->permutationFor('author')->apply(12), $a->permutationFor('contact')->apply(12));
        self::assertNotSame($a->permutationFor('author')->apply(12), $b->permutationFor('author')->apply(12));
    }

    public function testWithoutRenumberingThePermutationIsTheIdentity(): void
    {
        $permutation = new Generator(self::SALT, renumbering: false)->permutationFor('author');

        self::assertEquals(Permutation::identity(), $permutation);
        self::assertSame(12, $permutation->apply(12));
        self::assertSame(12, $permutation->invert(12));
    }

    public function testTheDrawDependsOnlyOnTheSaltAndTheKeys(): void
    {
        $generator = new Generator(self::SALT);
        $number = static fn (Generator $generator, string|int ...$keys): int => $generator->draw(...$keys)->int(0, 0xFFFFFFFF);

        self::assertSame($number($generator, 'a', 1), $number(new Generator(self::SALT), 'a', '1'));
        self::assertNotSame($number($generator, 'a', 1), $number($generator, 'a1'), 'keys are separated');
        self::assertNotSame($number($generator, 'a', 1), $number(new Generator('another salt'), 'a', 1));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $generator->digest('a'));
    }

    public function testFactsAreRememberedPerObject(): void
    {
        $generator = new Generator(self::SALT);
        $author = new Reference('author', 4242);

        self::assertNull($generator->recall($author, 'words'));
        $generator->remember($author, 'words', 2);
        self::assertSame(2, $generator->recall($author, 'words'));
        self::assertSame(2, $generator->recall(new Reference('author', '4242'), 'words'));
        self::assertNull($generator->recall(new Reference('author', 4243), 'words'));
        self::assertNull($generator->recall($author, 'other'));
    }

    public function testTheLanguageIsEnglishUnlessGiven(): void
    {
        $french = new French();

        self::assertInstanceOf(English::class, new Generator(self::SALT)->language());
        self::assertSame($french, new Generator(self::SALT, language: $french)->language());
    }

    private function domainOf(string $email): string
    {
        return substr($email, (int) strpos($email, '@') + 1);
    }
}
