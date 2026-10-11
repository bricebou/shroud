<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Declaration;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Exception\UndeclaredTableException;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Strategy\Wrapper\Fallback;

final class DeclarationTest extends TestCase
{
    public function testTheTablesAreListedInDeclarationOrderAndFoundByName(): void
    {
        $declaration = $this->base();

        self::assertSame(['people', 'contacts', 'visits'], array_map(static fn (Table $table): string => $table->name, $declaration->tables()));
        self::assertTrue($declaration->has('people'));
        self::assertFalse($declaration->has('articles'));
        self::assertInstanceOf(FullName::class, $declaration->getTable('people')->getStrategyFor('name'));
    }

    public function testAnUndeclaredTableIsRefused(): void
    {
        $this->expectException(UndeclaredTableException::class);
        $this->expectExceptionMessage('Undeclared table: articles');

        $this->base()->getTable('articles');
    }

    /**
     * Declaring RenumberedId on a column, wherever it sits, declares the type renumbered.
     */
    public function testTheRenumberedTypesAreFoundThroughWrappersAndBlobs(): void
    {
        $declaration = $this->base();

        self::assertTrue($declaration->isRenumbered('person'));
        self::assertTrue($declaration->isRenumbered('contact'), 'inside a Fallback');
        self::assertTrue($declaration->isRenumbered('tag'), 'inside a serialized blob');
        self::assertFalse($declaration->isRenumbered('article'));
        self::assertSame(['person', 'tag', 'contact'], $declaration->renumberedTypes(), 'in the order they are met');
    }

    public function testASiteDeclarationReplacesTablesByNameAndAddsItsOwn(): void
    {
        $site = $this->base()->with(
            $this->base()->getTable('people')->withColumns([
                'institution' => new ShapedText(),
            ]),
            new Table('articles', 'id_article', [
                'id_article' => new RenumberedId('article'),
                'id_person' => new Keep(),
            ]),
        );

        self::assertTrue($site->has('articles'));
        self::assertTrue($site->has('contacts'), 'the other tables remain');
        self::assertInstanceOf(ShapedText::class, $site->getTable('people')->getStrategyFor('institution'));
        self::assertTrue($site->isRenumbered('article'), 'the renumbered types are computed again');
        self::assertTrue($site->isRenumbered('person'));
        self::assertFalse($this->base()->has('articles'), 'the original is untouched');
    }

    public function testALocalDeclarationWinsTableByTable(): void
    {
        $local = new Declaration([
            Table::truncated('people'),
            new Table('visits', 'date', [
                'date' => new Keep(),
                'visits' => new Keep(),
            ]),
        ]);

        $merged = $this->base()->merge($local);

        self::assertTrue($merged->getTable('people')->truncated, 'the local declaration replaces the whole table');
        self::assertFalse($merged->getTable('visits')->truncated, 'and restores a truncated one');
        self::assertTrue($merged->has('contacts'));
        self::assertFalse($merged->isRenumbered('person'), 'no column renumbers persons any more');
        self::assertTrue($merged->isRenumbered('contact'));
    }

    private function base(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
                'name' => new FullName(),
                'settings' => new Serialized(new PerKey([
                    'id_tag' => new RenumberedId('tag'),
                ], new Keep())),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            new Table('contacts', 'id_contact', [
                'id_contact' => new Fallback(new RenumberedId('contact'), new Clear()),
                'id_person' => new Keep(),
            ]),
            Table::truncated('visits'),
        ]);
    }
}
