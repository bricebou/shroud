<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Language;

use PHPUnit\Framework\TestCase;
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
}
