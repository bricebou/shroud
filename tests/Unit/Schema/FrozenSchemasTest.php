<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use Shroud\Exception\InvalidSchemaException;
use Shroud\Platform\Generic;
use Shroud\Schema\FrozenSchemas;
use Shroud\Tests\Unit\Platform\TestPlatform;

final class FrozenSchemasTest extends TestCase
{
    public function testTheFrozenVersionsAreSortedNaturallyAndTheLatestIsKnown(): void
    {
        $frozen = new TestPlatform()->frozenSchemas();

        self::assertSame(['1.0.0', '2.0.0'], $frozen->versions());
        self::assertSame('2.0.0', $frozen->latest());
        self::assertCount(2, $frozen->load('2.0.0')->tables());
        self::assertStringEndsWith('/fixtures/schema/test-3.0.0.json', $frozen->path('3.0.0'));
    }

    public function testASiteVersionResolvesToTheNearestFrozenVersionBelow(): void
    {
        $frozen = new TestPlatform()->frozenSchemas();

        self::assertSame('1.0.0', $frozen->nearest('1.0.0'));
        self::assertSame('1.0.0', $frozen->nearest('1.9.3'));
        self::assertSame('2.0.0', $frozen->nearest('2.0.0'));
        self::assertSame('2.0.0', $frozen->nearest('9.9.9'), 'a version newer than every frozen schema takes the latest');
        self::assertSame('1.0.0', $frozen->nearest('0.1.0'), 'a version older than every frozen schema takes the oldest');
    }

    public function testTheNaturalOrderComparesTheNumbersOfEachSegment(): void
    {
        $directory = sys_get_temp_dir() . '/shroud-frozen-' . bin2hex(random_bytes(4));
        mkdir($directory);
        foreach (['4.4.9', '4.4.28', '4.3.1'] as $version) {
            file_put_contents(sprintf('%s/x-%s.json', $directory, $version), '{}');
        }

        $frozen = new FrozenSchemas($directory, 'x');
        $versions = $frozen->versions();

        foreach (['4.4.9', '4.4.28', '4.3.1'] as $version) {
            unlink(sprintf('%s/x-%s.json', $directory, $version));
        }
        rmdir($directory);
        self::assertSame(['4.3.1', '4.4.9', '4.4.28'], $versions);
    }

    public function testWithoutFrozenSchemaEverythingIsRefused(): void
    {
        $none = new Generic()->frozenSchemas();

        self::assertSame([], $none->versions());
        try {
            $none->latest();
            self::fail('refused');
        } catch (InvalidSchemaException $exception) {
            self::assertStringStartsWith('no frozen schema in', $exception->reason);
        }

        $this->expectException(InvalidSchemaException::class);
        $none->nearest('1.0.0');
    }
}
