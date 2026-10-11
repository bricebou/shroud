<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Generator;
use Shroud\Platform\Generic;
use Shroud\Schema\Glossary\English;
use Shroud\Strategy\Reference\RenumberedId;

final class PlatformTest extends TestCase
{
    public function testTheGenericPlatformKnowsNoMarkupAndNoReference(): void
    {
        $generic = new Generic();
        $context = new Context('people', 'bio', '42', [], reference: null, generator: new Generator('test salt'));

        self::assertSame('generic', $generic->name());
        self::assertSame([], $generic->preservedMarkup());
        self::assertSame('See #person:12 and {{name}}', $generic->renumberReferences('See #person:12 and {{name}}', $context));
        self::assertSame([], $generic->bareDeclaration()->tables());
        self::assertSame([], $generic->frozenSchemas()->versions());
        self::assertNull($generic->readVersion(new \PDO('sqlite::memory:')));
        self::assertSame([], $generic->draftRules());
        self::assertEquals([new English()], $generic->glossaries());
    }

    public function testTheGeneratorIsGenericUnlessGivenAPlatform(): void
    {
        $platform = new TestPlatform();

        self::assertInstanceOf(Generic::class, new Generator('test salt')->platform());
        self::assertSame($platform, new Generator('test salt', platform: $platform)->platform());
    }

    public function testTheTestPlatformRenumbersTheReferencesToRenumberedTypes(): void
    {
        $generator = new Generator('test salt');
        $declaration = new Declaration([
            new Table('people', 'id', [
                'id' => new RenumberedId('person'),
            ])]);
        $context = new Context('people', 'bio', '42', [], reference: null, generator: $generator, declaration: $declaration);
        $person = $generator->permutationFor('person')->apply(12);

        self::assertSame("See #person:{$person}, #article:12 and {{name}}", new TestPlatform()->renumberReferences('See #person:12, #article:12 and {{name}}', $context));
        self::assertSame('See #person:12', new TestPlatform()->renumberReferences('See #person:12', new Context('people', 'bio', '42', [], reference: null, generator: $generator)), 'nothing is renumbered in an empty declaration');
    }
}
