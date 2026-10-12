<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Identity;

use PHPUnit\Framework\TestCase;
use Shroud\Identity\Reference;

final class ReferenceTest extends TestCase
{
    public function testTheKeyJoinsTheTypeAndTheId(): void
    {
        self::assertSame('author:4242', new Reference('author', 4242)->toKey());
        self::assertSame('author:4242', new Reference('author', '4242')->toKey());
        self::assertSame('setting:site_name', new Reference('setting', 'site_name')->toKey());
    }

    public function testTwoReferencesToTheSameObjectShareTheirKey(): void
    {
        self::assertSame(new Reference('author', 12)->toKey(), new Reference('author', '12')->toKey());
        self::assertNotSame(new Reference('author', 12)->toKey(), new Reference('contact', 12)->toKey());
    }
}
