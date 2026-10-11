<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy;

use PHPUnit\Framework\TestCase;
use Shroud\Strategy\Nested\ByColumn;
use Shroud\Strategy\Nested\Json;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Wrapper\Fallback;
use Shroud\Strategy\Wrapper\Hashed;
use Shroud\Strategy\Wrapper\WithoutEmails;

final class CompositeTest extends TestCase
{
    public function testEveryCompositeListsTheStrategiesItCarries(): void
    {
        $email = new Email();
        $clear = new Clear();
        $keep = new Keep();
        $perKey = new PerKey([
            'a' => $email,
        ], $clear);

        self::assertSame([$email, $clear], [...new Fallback($email, $clear)->innerStrategies()]);
        self::assertSame([$email], [...new WithoutEmails($email)->innerStrategies()]);
        self::assertSame([$email], [...new Hashed($email)->innerStrategies()]);
        self::assertSame([$email, $clear], [...$perKey->innerStrategies()]);
        self::assertSame([$perKey], [...new Serialized($perKey)->innerStrategies()]);
        self::assertSame([$perKey], [...new Json($perKey)->innerStrategies()]);
        self::assertSame([$email, $keep], [...new ByColumn('name', [
            'x' => $email,
        ], $keep)->innerStrategies()]);
    }
}
