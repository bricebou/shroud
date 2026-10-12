<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Random;

use PHPUnit\Framework\TestCase;
use Shroud\Random\Draw;

final class DrawTest extends TestCase
{
    public function testTheSameSeedGivesTheSameDraws(): void
    {
        $sequence = (static fn (Draw $draw): array => [$draw->int(1, 1000), $draw->digit(), $draw->letter(), $draw->boolean(), $draw->pick(['a', 'b', 'c']), $draw->numerify('##-##')]);

        self::assertSame($sequence(new Draw('seed')), $sequence(new Draw('seed')));
        self::assertNotSame($sequence(new Draw('seed')), $sequence(new Draw('another seed')));
    }

    /**
     * The values are part of the contract: they must not move with a version of PHP.
     */
    public function testTheDrawsAreFrozen(): void
    {
        $draw = new Draw('seed');

        self::assertSame([1, 8, 5, 7, 7], array_map($draw->digit(...), range(1, 5)));
    }

    public function testTheBoundsAreIncludedAndRespected(): void
    {
        $draw = new Draw('bounds');
        $seen = [];
        for ($i = 0; $i < 2000; ++$i) {
            $number = $draw->int(-2, 2);
            $seen[$number] = true;
        }
        ksort($seen);

        self::assertSame([-2, -1, 0, 1, 2], array_keys($seen));
        self::assertSame(7, $draw->int(7, 7));
        self::assertSame(7, $draw->int(7, 3), 'an empty range gives its lower bound');
        self::assertMatchesRegularExpression('/^[a-z]$/', $draw->letter());
        self::assertMatchesRegularExpression('/^\d\d-\d$/', $draw->numerify('##-#'));
    }

    public function testAPartHasADrawOfItsOwn(): void
    {
        $draw = new Draw('seed');
        $before = new Draw('seed')->for('names')->int(0, 1 << 30);
        $draw->int(0, 9);

        self::assertSame($before, $draw->for('names')->int(0, 1 << 30), 'what the whole drew does not move a part');
        self::assertNotSame($draw->for('names')->int(0, 1 << 30), $draw->for('streets')->int(0, 1 << 30));
    }
}
