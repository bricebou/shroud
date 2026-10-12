<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\Table;
use Shroud\Exception\MissingReferenceException;
use Shroud\Generator;
use Shroud\Identity\Reference;

final class ContextTest extends TestCase
{
    public function testThePseudonymIsThatOfTheReferencedObject(): void
    {
        $generator = new Generator('test salt');

        self::assertEquals(
            $generator->pseudonymFor(new Reference('person', 42)),
            $this->context($generator, new Reference('person', 42))->requirePseudonym(),
        );
    }

    public function testARowWithoutReferenceHasNoPseudonym(): void
    {
        $this->expectException(MissingReferenceException::class);
        $this->expectExceptionMessage('people.value');

        $this->context(new Generator('test salt'), reference: null)->requirePseudonym();
    }

    public function testFactsAreNotedOnTheReferencedObject(): void
    {
        $generator = new Generator('test salt');
        $this->context($generator, new Reference('person', 42))->remember('words', 2);

        self::assertSame(2, $generator->recall(new Reference('person', 42), 'words'));
        self::assertSame(2, $this->context($generator, new Reference('person', '42'))->recall('words'));
        self::assertNull($this->context($generator, new Reference('person', 43))->recall('words'));
    }

    public function testWithoutReferenceNothingIsNoted(): void
    {
        $generator = new Generator('test salt');
        $context = $this->context($generator, reference: null);

        $context->remember('words', 2);

        self::assertNull($context->recall('words'));
    }

    public function testTheDrawIsThatOfTheTableTheRowAndTheColumn(): void
    {
        $generator = new Generator('test salt');
        $draw = fn (string $key, string $column): int => new Context('people', $column, $key, [], reference: null, generator: $generator)->draw()->int(0, PHP_INT_MAX >> 32);

        self::assertSame($generator->draw('people', '42', 'value')->int(0, PHP_INT_MAX >> 32), $draw('42', 'value'));
        self::assertSame($draw('42', 'value'), $draw('42', 'value'));
        self::assertNotSame($draw('42', 'value'), $draw('43', 'value'));
    }

    public function testTheDeclarationIsEmptyUnlessGiven(): void
    {
        $declaration = new Declaration([new Table('people', 'id', [])]);

        self::assertSame([], $this->context(new Generator('test salt'), reference: null)->declaration->tables());
        self::assertSame($declaration, new Context('people', 'value', '42', [], reference: null, generator: new Generator('test salt'), declaration: $declaration)->declaration);
    }

    private function context(Generator $generator, ?Reference $reference): Context
    {
        return new Context('people', 'value', '42', [], $reference, $generator);
    }
}
