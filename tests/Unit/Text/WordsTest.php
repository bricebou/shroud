<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Text;

use PHPUnit\Framework\TestCase;
use Shroud\Random\Draw;
use Shroud\Text\Words;

final class WordsTest extends TestCase
{
    public function testWordsAreLettersOnlyAndNotGluedToDigits(): void
    {
        self::assertSame(['Zoé', 'Kowalczyk', 'née', 'en', 'ref'], Words::extract('Zoé Kowalczyk, née en 1984 (ref. A12b)'));
        self::assertSame([], Words::extract('12 34-56'));
        self::assertSame(['Œuvre', 'd', 'été'], Words::extract("Œuvre d'été !"));
    }

    public function testFillersAreDrawnFromTheDraw(): void
    {
        $first = Words::lorem(new Draw('seed'), 3);

        self::assertCount(3, $first);
        self::assertSame($first, Words::lorem(new Draw('seed'), 3));
        self::assertNotSame($first, Words::lorem(new Draw('another seed'), 3));
        self::assertMatchesRegularExpression('/^\p{Ll}+$/u', $first[0]);
        self::assertSame([], Words::lorem(new Draw('seed'), 0));
        self::assertSame([], Words::lorem(new Draw('seed'), -1));
    }

    public function testAsciiDropsTheDiacriticsAndLigatures(): void
    {
        self::assertSame('Zoe Kowalczyk', Words::toAscii('Zoé Kowalczyk'));
        self::assertSame('OEuvre d\'ete', Words::toAscii("Œuvre d'été"));
        self::assertSame('plain', Words::toAscii('plain'));
    }

    public function testTheSlugIsLowercaseAsciiJoinedByHyphens(): void
    {
        self::assertSame('zoe-kowalczyk', Words::toSlug('Zoé Kowalczyk'));
        self::assertSame('oeuvre-d-ete', Words::toSlug("Œuvre d'été !"));
        self::assertSame('', Words::toSlug(' - '));
    }
}
