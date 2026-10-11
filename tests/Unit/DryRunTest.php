<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Disguiser;
use Shroud\DryRun;
use Shroud\Exception\InvalidValueException;
use Shroud\Exception\MissingReferenceException;
use Shroud\Exception\TruncatedTableException;
use Shroud\Exception\UndeclaredColumnException;
use Shroud\Generator;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;

final class DryRunTest extends TestCase
{
    public function testCheckingARowCollectsEveryRefusalInsteadOfStoppingAtTheFirst(): void
    {
        $refusals = $this->disguiser()->checkRow('contacts', [
            'id_contact' => '1',
            'id_person' => '0',
            'phone' => 'call me',
            'email' => 'x@example-real.test',
            'extra' => 'x',
        ]);

        self::assertSame(['phone', 'email', 'extra'], array_keys($refusals));
        self::assertInstanceOf(InvalidValueException::class, $refusals['phone']);
        self::assertInstanceOf(MissingReferenceException::class, $refusals['email']);
        self::assertInstanceOf(UndeclaredColumnException::class, $refusals['extra']);
        self::assertSame([], $this->disguiser()->checkRow('contacts', [
            'id_contact' => '1',
            'id_person' => '12',
            'phone' => '06 12',
            'email' => 'x@example-real.test',
        ]));
    }

    public function testCheckingARowOfATruncatedTableStillRefuses(): void
    {
        $this->expectException(TruncatedTableException::class);

        $this->disguiser()->checkRow('visits', [
            'date' => '2026-01-01',
        ]);
    }

    public function testTheDryRunTalliesByTableColumnAndReason(): void
    {
        $dryRun = new DryRun();
        $disguiser = $this->disguiser();
        $rows = [
            [
                'id_contact' => '1',
                'id_person' => '12',
                'phone' => 'call me',
                'email' => 'a@example-real.test',
            ],
            [
                'id_contact' => '2',
                'id_person' => '0',
                'phone' => 'call me',
                'email' => 'b@example-real.test',
            ],
            [
                'id_contact' => '3',
                'id_person' => '12',
                'phone' => '06 12',
                'email' => '',
            ],
        ];
        foreach ($rows as $row) {
            $dryRun->countRow('contacts');
            foreach ($disguiser->checkRow('contacts', $row) as $column => $refusal) {
                $dryRun->noteRefusal('contacts', $column, $refusal);
            }
        }
        $dryRun->noteUndeclaredTable('newsletters');

        self::assertFalse($dryRun->isClean());
        self::assertSame(3, $dryRun->rows());
        self::assertSame(3, $dryRun->rows('contacts'));
        self::assertSame(0, $dryRun->rows('people'));
        self::assertSame([
            'contacts' => [
                'phone' => [
                    'letters found' => 2,
                ],
                'email' => [
                    'The strategy of contacts.email needs an object reference, but the row carries none' => 1,
                ],
            ],
        ], $dryRun->refusals());
        self::assertSame([
            'newsletters: undeclared table',
            'contacts.phone: letters found (2 rows)',
            'contacts.email: The strategy of contacts.email needs an object reference, but the row carries none (1 row)',
            'Dry run: 3 rows in 1 tables, 3 refusals in 2 columns, 1 undeclared table.',
        ], $dryRun->report());
    }

    public function testACleanDryRunSaysSo(): void
    {
        $dryRun = new DryRun();
        $dryRun->countRow('people');
        $dryRun->countRow('people');
        $dryRun->countRow('contacts');

        self::assertTrue($dryRun->isClean());
        self::assertSame(['Dry run: 3 rows in 2 tables, every one passes.'], $dryRun->report());
    }

    private function disguiser(): Disguiser
    {
        return new Disguiser(new Declaration([
            new Table('contacts', 'id_contact', [
                'id_contact' => new Keep(),
                'id_person' => new Keep(),
                'phone' => new ShapedDigits(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
            Table::truncated('visits'),
        ]), new Generator(Fixture::SALT));
    }
}
