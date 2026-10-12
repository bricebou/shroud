<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Language;

use PHPUnit\Framework\TestCase;
use Shroud\Identity\Gender;
use Shroud\Language\English;
use Shroud\Language\French;

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

    public function testEnglishMarksNothingAndElidesNothing(): void
    {
        $english = new English();

        self::assertSame('ete', $english->markDiacritic('ete'));
        self::assertSame('', $english->markDiacritic(''));
        self::assertSame([], $english->elidedWords());
    }

    public function testACivilityKeepsItsFormAndTakesTheGender(): void
    {
        $french = new French();
        $english = new English();

        self::assertSame('M.', $french->civility('Mme', Gender::Male));
        self::assertSame('Mme', $french->civility('Mlle', Gender::Female));
        self::assertSame('Monsieur', $french->civility('MADEMOISELLE', Gender::Male));
        self::assertSame('Mrs.', $english->civility('Ms.', Gender::Female));
        self::assertSame('Mr', $english->civility('Miss', Gender::Male));
        self::assertNull($french->civility('Miss', Gender::Male), 'a civility of another language');
        self::assertSame('Mme', $french->civility('M', Gender::Female), 'the dot often goes missing');
        self::assertSame('M', $french->civility('M', Gender::Male));
        self::assertSame('Mr', $french->civility('Mr', Gender::Male), 'and Mr often stands for M.');
        self::assertNull($english->civility('Mister', Gender::Female), 'no feminine counterpart');
        self::assertSame('Sir', $english->civility('Madam', Gender::Male));
        self::assertSame('Madam', $english->civility('Sir', Gender::Female));
        self::assertSame('Mr', $english->civility('Mrs', Gender::Male), 'British, without the dot');
        self::assertNull($english->civility('Dr', Gender::Male));
        self::assertSame('Mme', $french->defaultCivility(Gender::Female));
        self::assertSame('Mr', $english->defaultCivility(Gender::Male));
    }
}
