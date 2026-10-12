<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Language;

use PHPUnit\Framework\TestCase;
use Shroud\Identity\Gender;
use Shroud\Language\French;
use Shroud\Language\Plain;

final class LanguagesTest extends TestCase
{
    public function testFrenchMarksTheFirstLetterThatTakesADiacritic(): void
    {
        $french = new French();

        self::assertSame('éte', $french->markDiacritic('ete'));
        self::assertSame('Éte', $french->markDiacritic('Ete'));
        self::assertSame('çlaude', $french->markDiacritic('claude'));
        self::assertSame('brôwn', $french->markDiacritic('brown'));
        self::assertSame('xyz', $french->markDiacritic('xyz'), 'no letter takes a diacritic');
        self::assertSame('', $french->markDiacritic(''));
    }

    public function testFrenchElidesTheArticlesThePronounsAndQue(): void
    {
        $elided = new French()->elidedWords();

        self::assertContains('l', $elided);
        self::assertContains('d', $elided);
        self::assertContains('qu', $elided);
        self::assertContains('lorsqu', $elided);
        self::assertNotContains('a', $elided);
    }

    public function testPlainMarksNothingAndElidesNothing(): void
    {
        $plain = new Plain();

        self::assertSame('ete', $plain->markDiacritic('ete'));
        self::assertSame('', $plain->markDiacritic(''));
        self::assertSame([], $plain->elidedWords());
    }

    public function testACivilityKeepsItsFormAndTakesTheGender(): void
    {
        $french = new French();
        $plain = new Plain();

        self::assertSame('M.', $french->civility('Mme', Gender::Male));
        self::assertSame('Mme', $french->civility('Mlle', Gender::Female));
        self::assertSame('Monsieur', $french->civility('MADEMOISELLE', Gender::Male));
        self::assertSame('Mrs.', $plain->civility('Ms.', Gender::Female));
        self::assertSame('Mr', $plain->civility('Miss', Gender::Male));
        self::assertNull($french->civility('Miss', Gender::Male), 'a civility of another language');
        self::assertSame('Mme', $french->civility('M', Gender::Female), 'the dot often goes missing');
        self::assertSame('M', $french->civility('M', Gender::Male));
        self::assertSame('Mr', $french->civility('Mr', Gender::Male), 'and Mr often stands for M.');
        self::assertNull($plain->civility('Mister', Gender::Female), 'no feminine counterpart');
        self::assertSame('Sir', $plain->civility('Madam', Gender::Male));
        self::assertSame('Madam', $plain->civility('Sir', Gender::Female));
        self::assertSame('Mr', $plain->civility('Mrs', Gender::Male), 'British, without the dot');
        self::assertNull($plain->civility('Dr', Gender::Male));
        self::assertSame('Mme', $french->defaultCivility(Gender::Female));
        self::assertSame('Mr', $plain->defaultCivility(Gender::Male));
    }
}
