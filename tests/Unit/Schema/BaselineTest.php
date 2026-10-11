<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Schema\Baseline;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class BaselineTest extends TestCase
{
    public function testTheBaselineOfAPlatformRestsOnTheNearestFrozenSchema(): void
    {
        $baseline = Baseline::of(new TestPlatform(), '1.5.0');

        self::assertSame('test', $baseline->platform);
        self::assertSame('1.0.0', $baseline->version);
        self::assertTrue($baseline->hasTable('people'));
        self::assertFalse($baseline->hasTable('contacts'), 'contacts only appear in 2.0.0');
        self::assertTrue($baseline->hasColumn('people', 'legacy'));
        self::assertFalse($baseline->hasColumn('people', 'phone'));
        self::assertFalse($baseline->hasColumn('contacts', 'id_contact'));
    }

    public function testTheBaselineKnowsWhatTheBareDeclarationDeclares(): void
    {
        $baseline = Baseline::of(new TestPlatform(), '2.0.0');

        self::assertTrue($baseline->declaresTable('people'));
        self::assertFalse($baseline->declaresTable('contacts'));
        self::assertTrue($baseline->declaresColumn('people', 'email'));
        self::assertFalse($baseline->declaresColumn('people', 'legacy'), 'the bare declaration was written for 2.0.0');
        self::assertFalse($baseline->declaresColumn('contacts', 'id_contact'));
    }
}
