<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Reference;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Generator;
use Shroud\Strategy\Reference\EmbeddedReferences;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Wrapper\WithoutEmails;
use Shroud\Tests\Unit\Platform\TestPlatform;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class EmbeddedReferencesTest extends StrategyTestCase
{
    public function testOnlyTheReferencesToRenumberedTypesChange(): void
    {
        $generator = new Generator(self::SALT, platform: new TestPlatform());
        $person = $generator->permutationFor('person')->apply(12);
        $context = self::context($generator, declaration: $this->declaration());

        self::assertSame(
            "Written by #person:{$person}, see #article:12 and {{name}}.",
            self::apply(new EmbeddedReferences(), 'Written by #person:12, see #article:12 and {{name}}.', $context),
        );
    }

    public function testTheGenericPlatformKeepsTheText(): void
    {
        self::assertSame('Written by #person:12.', self::apply(new EmbeddedReferences(), 'Written by #person:12.', self::context(declaration: $this->declaration())));
    }

    public function testNullAndTheEmptyStringPassThrough(): void
    {
        self::assertNull(self::apply(new EmbeddedReferences(), value: null));
        self::assertSame('', self::apply(new EmbeddedReferences(), ''));
    }

    public function testItComposesWithoutEmails(): void
    {
        $generator = new Generator(self::SALT, platform: new TestPlatform());
        $person = $generator->permutationFor('person')->apply(12);
        $result = self::applyToString(new WithoutEmails(new EmbeddedReferences()), 'Ask #person:12 at zoe@example-real.test', self::context($generator, declaration: $this->declaration()));

        self::assertStringStartsWith("Ask #person:{$person} at ", $result);
        self::assertStringNotContainsString('example-real', $result);
        self::assertEquals([
            'a' => new EmbeddedReferences(),
            'b' => new EmbeddedReferences(),
        ], EmbeddedReferences::forColumns(['a', 'b']));
    }

    private function declaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id', [
                'id' => new RenumberedId('person'),
            ])]);
    }
}
