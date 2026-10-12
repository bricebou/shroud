<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Text;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Generator;
use Shroud\Language\French;
use Shroud\Language\Plain;
use Shroud\Platform\Generic;
use Shroud\Tests\Unit\Platform\TestPlatform;
use Shroud\Text\Text;

final class TextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shapes(): iterable
    {
        yield 'case and separators' => ['Jean-Pierre DUPONT, avocat', 'Alpha-Beta GAMMA, delta'];
        yield 'non-breaking and double space' => ["Jean\u{00A0}Dupont  Fils", "Alpha\u{00A0}Beta  Gamma"];
        yield 'tags and entities untouched' => ['<p class="nom">Jean</p> &amp; Co', '<p class="nom">Alpha</p> &amp; Beta'];
        yield 'digits and codes untouched' => ['Bureau 12, 3e étage, réf. AB12', 'Alpha 12, 3e béta, gàmma. AB12'];
        yield 'platform markup untouched' => ['Hello {{first_name}}, see #person:12', 'Alpha {{first_name}}, beta #person:12'];
        yield 'elisions dropped' => ["Marie d'Aubigné, l’Oréal et qu'importe", 'Alpha Béta, Gàmma delta epsilon'];
        yield 'elision compounds dropped, their capital with them' => ["Lorsqu'elle vient", 'alpha beta'];
        yield 'empty' => ['', ''];
        yield 'no letter' => ['12 - 34', '12 - 34'];
    }

    #[DataProvider('shapes')]
    public function testReplaceWordsKeepsTheShape(string $original, string $expected): void
    {
        $words = ['alpha', 'beta', 'gamma', 'delta', 'epsilon'];

        self::assertSame($expected, $this->french()->replaceWords($original, static fn (int $rank): string => $words[$rank]));
    }

    public function testAnAccentedWordCallsForADiacriticOfTheLanguage(): void
    {
        self::assertSame('Gàmma', $this->french()->replaceWords('Zoé', static fn (): string => 'gamma'));
        self::assertSame('ÀLPHA', $this->french()->replaceWords('ÉLIG', static fn (): string => 'alpha'));
        self::assertSame('Gamma', $this->plain()->replaceWords('Zoé', static fn (): string => 'gamma'), 'a plain language marks nothing');
    }

    public function testAPlainLanguageElidesNothing(): void
    {
        self::assertSame("Alpha beta'Gamma", $this->plain()->replaceWords("Marie d'Aubigné", static fn (int $rank): string => ['alpha', 'beta', 'gamma'][$rank]));
        self::assertSame(3, $this->plain()->countWords("Marie d'Aubigné"));
    }

    public function testTheGenericPlatformOnlyKeepsHtml(): void
    {
        $generic = new Text(new Generic(), new Plain());

        self::assertSame('<b>Alpha</b> beta#gamma:12', $generic->replaceWords('<b>Hello</b> see#person:12', static fn (int $rank): string => ['alpha', 'beta', 'gamma'][$rank]));
    }

    public function testInvalidUtf8IsSignalled(): void
    {
        self::assertNull($this->french()->replaceWords("caf\xE9", static fn (): string => 'x'));
    }

    public function testCountWordsIgnoresMarkupCodesAndElisions(): void
    {
        self::assertSame(2, $this->french()->countWords('<p class="nom">Jean</p> &amp; Dupont 12 3e {{tag}}'));
        self::assertSame(1, $this->french()->countWords("d'Aubigné"));
        self::assertSame(0, $this->french()->countWords('#person:12'));
    }

    public function testReplaceCodesKeepsTheShapeOfCodesAndTheMarkup(): void
    {
        $letters = ['x', 'y', 'z', 'w'];
        $i = 0;
        $result = $this->french()->replaceCodes(
            'Bureau 3e, réf. AB12-c9 #person:12 &#39;',
            static function () use ($letters, &$i): string {
                return $letters[$i++ % 4];
            },
            static fn (): int => 7,
        );

        self::assertSame('Bureau 7x, réf. YZ77-w7 #person:12 &#39;', $result);
    }

    public function testTheGeneratorHandsOutOneTextForItsPlatformAndLanguage(): void
    {
        $generator = new Generator('test salt', language: new French(), platform: new TestPlatform());

        self::assertSame($generator->text(), $generator->text());
        self::assertSame('alpha {{x}}', $generator->text()->replaceWords("l'un {{x}}", static fn (): string => 'alpha'));
    }

    public function testAWordOfACaselessScriptTakesACapitalAndNoDiacritic(): void
    {
        $words = ['alpha', 'beta', 'gamma'];

        self::assertSame('Alpha Beta-Gamma', $this->french()->replaceWords("زها علی\u{200C}آبادی", static fn (int $rank): string => $words[$rank]), 'the joiner of the compound becomes a hyphen');
        self::assertSame('Alpha', $this->french()->replaceWords('北京', static fn (): string => 'alpha'));
        self::assertSame('Àlpha BETA', $this->french()->replaceWords('Zoé DUPONT', static fn (int $rank): string => $words[$rank]), 'a cased script keeps its rules');
    }

    private function french(): Text
    {
        return new Text(new TestPlatform(), new French());
    }

    private function plain(): Text
    {
        return new Text(new TestPlatform(), new Plain());
    }
}
