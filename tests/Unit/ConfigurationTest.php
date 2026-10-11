<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Configuration;
use Shroud\Declaration\Declaration;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Language\French;
use Shroud\Language\Plain;
use Shroud\Platform\Generic;
use Shroud\Schema\TableSchema;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class ConfigurationTest extends TestCase
{
    public function testTheProjectFileLoads(): void
    {
        $configuration = Configuration::fromFile(__DIR__ . '/../fixtures/project/shroud.php');

        self::assertSame('fr_FR', $configuration->locale);
        self::assertInstanceOf(French::class, $configuration->language);
        self::assertInstanceOf(TestPlatform::class, $configuration->platform);
        self::assertTrue($configuration->declaration->has('visits'));
        self::assertTrue($configuration->declaration->isRenumbered('person'));
        self::assertCount(2, $configuration->glossaries);
    }

    public function testTheGeneratorAndTheDisguiserFollowTheConfiguration(): void
    {
        $configuration = Configuration::fromFile(__DIR__ . '/../fixtures/project/shroud.php');

        self::assertInstanceOf(French::class, $configuration->generator()->language());
        self::assertInstanceOf(TestPlatform::class, $configuration->generator()->platform());
        self::assertSame(12, $configuration->generator(renumbering: false)->permutationFor('person')->apply(12));
        self::assertNotSame(12, $configuration->generator()->permutationFor('person')->apply(12));
        self::assertNotSame('Zoé Kowalczyk', $configuration->disguiser()->disguiseRow('people', [
            'id_person' => '1',
            'name' => 'Zoé Kowalczyk',
            'email' => '',
            'institution' => '',
        ])['name']);
    }

    public function testTheDraftUsesTheGlossariesOfTheProjectOrOfThePlatform(): void
    {
        $table = new TableSchema('t', [], [
            'nom' => 'text',
        ]);
        $declaration = new Declaration([]);

        self::assertStringContainsString("'nom' => new FullName(), // @todo review", Configuration::fromFile(__DIR__ . '/../fixtures/project/shroud.php')->draft()->table($table, $declaration));
        self::assertStringContainsString("'nom' => new ShapedText(), // @todo review", new Configuration($declaration, 'salt', platform: new TestPlatform())->draft()->table($table, $declaration), 'the platform glossaries, English alone');
    }

    public function testTheDefaultsAreGenericAndPlain(): void
    {
        $configuration = new Configuration(new Declaration([]), 'salt');

        self::assertSame('en_US', $configuration->locale);
        self::assertInstanceOf(Plain::class, $configuration->language);
        self::assertInstanceOf(Generic::class, $configuration->platform);
        self::assertSame([], $configuration->glossaries);
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('file not found');

        Configuration::fromFile(__DIR__ . '/absent.php');
    }

    public function testAFileThatReturnsSomethingElseIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('must return a Shroud\Configuration object');

        Configuration::fromFile(__DIR__ . '/../fixtures/schema/test-1.0.0.json');
    }

    public function testAnEmptySaltIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('the salt is empty');

        new Configuration(new Declaration([]), '');
    }
}
