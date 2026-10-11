<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Platform;

use PHPUnit\Framework\TestCase;
use Shroud\Context;
use Shroud\Generator;
use Shroud\Platform\Generic;

final class PlatformTest extends TestCase
{
    public function testTheGenericPlatformKnowsNoMarkupAndNoReference(): void
    {
        $generic = new Generic();
        $context = new Context('people', 'bio', '42', [], reference: null, generator: new Generator('test salt'));

        self::assertSame('generic', $generic->name());
        self::assertSame([], $generic->preservedMarkup());
        self::assertSame('See #person:12 and {{name}}', $generic->renumberReferences('See #person:12 and {{name}}', $context));
    }

    public function testTheGeneratorIsGenericUnlessGivenAPlatform(): void
    {
        $platform = new TestPlatform();

        self::assertInstanceOf(Generic::class, new Generator('test salt')->platform());
        self::assertSame($platform, new Generator('test salt', platform: $platform)->platform());
    }

    public function testTheTestPlatformRenumbersItsReferences(): void
    {
        $generator = new Generator('test salt');
        $context = new Context('people', 'bio', '42', [], reference: null, generator: $generator);
        $person = $generator->permutationFor('person')->apply(12);

        self::assertSame("See #person:{$person} and {{name}}", new TestPlatform()->renumberReferences('See #person:12 and {{name}}', $context));
    }
}
