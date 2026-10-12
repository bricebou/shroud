<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shroud\Identity\Gender;
use Shroud\Language\English;
use Shroud\Language\French;
use Shroud\Language\LanguageInterface;
use Shroud\Random\Draw;

final class VocabularyTest extends TestCase
{
    /**
     * @return iterable<string, array{LanguageInterface}>
     */
    public static function languages(): iterable
    {
        yield 'english' => [new English()];
        yield 'french' => [new French()];
    }

    #[DataProvider('languages')]
    public function testEveryNameIsASingleWord(LanguageInterface $language): void
    {
        $vocabulary = $language->vocabulary();
        $draw = new Draw('words');
        for ($i = 0; $i < 200; ++$i) {
            self::assertMatchesRegularExpression('/^\p{L}+$/u', $vocabulary->firstName(Gender::Female, $draw));
            self::assertMatchesRegularExpression('/^\p{L}+$/u', $vocabulary->firstName(Gender::Male, $draw));
            self::assertMatchesRegularExpression('/^\p{L}+$/u', $vocabulary->lastName($draw));
        }
    }

    #[DataProvider('languages')]
    public function testEveryPlaceholderIsFilled(LanguageInterface $language): void
    {
        $vocabulary = $language->vocabulary();
        $draw = new Draw('compositions');
        for ($i = 0; $i < 200; ++$i) {
            foreach ([$vocabulary->street($draw), $vocabulary->city($draw), $vocabulary->organization($draw), $vocabulary->postalCode($draw)] as $value) {
                self::assertStringNotContainsString('{', $value);
                self::assertStringNotContainsString('#', $value);
                self::assertNotSame('', $value);
            }
        }
    }

    public function testTheVocabularyIsLoadedOnce(): void
    {
        self::assertSame(new French()->vocabulary(), new French()->vocabulary());
    }
}
