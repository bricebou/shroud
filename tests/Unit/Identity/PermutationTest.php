<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Identity;

use PHPUnit\Framework\TestCase;
use Shroud\Identity\Permutation;

final class PermutationTest extends TestCase
{
    public function testThePermutationInvertsAndStaysInRange(): void
    {
        $permutation = new Permutation('test key');

        foreach ([1, 2, 7, 4242, 149841, 65536, 1000000, Permutation::MAX] as $id) {
            $permuted = $permutation->apply($id);
            self::assertGreaterThanOrEqual(1, $permuted, "id {$id}");
            self::assertLessThanOrEqual(Permutation::MAX, $permuted, "id {$id}");
            self::assertNotSame($id, $permuted, "id {$id}");
            self::assertSame($id, $permutation->invert($permuted), "id {$id}");
        }
    }

    public function testZeroAndNegativesAreFixedPoints(): void
    {
        $permutation = new Permutation('test key');

        self::assertSame(0, $permutation->apply(0));
        self::assertSame(-1, $permutation->apply(-1));
        self::assertSame(0, $permutation->invert(0));
        self::assertSame(-1, $permutation->invert(-1));
    }

    public function testThePermutationIsABijectionOnASample(): void
    {
        $permutation = new Permutation('test key');
        $images = array_map($permutation->apply(...), range(1, 3000));

        self::assertCount(3000, array_unique($images));
    }

    public function testEachKeyHasItsOwnPermutation(): void
    {
        self::assertSame(new Permutation('test key')->apply(4242), new Permutation('test key')->apply(4242));
        self::assertNotSame(new Permutation('test key')->apply(4242), new Permutation('another key')->apply(4242));
    }

    /**
     * A renumbered base must stay invertible by a later release: the algorithm is pinned by known values.
     */
    public function testThePermutationIsStableAcrossReleases(): void
    {
        $permutation = new Permutation('test key');

        self::assertSame([
            1 => 1656770907,
            2 => 1178048864,
            7 => 1894664206,
            4242 => 1890650980,
            65536 => 626560409,
            1000000 => 76505665,
            Permutation::MAX => 1215067762,
        ], array_combine($ids = [1, 2, 7, 4242, 65536, 1000000, Permutation::MAX], array_map($permutation->apply(...), $ids)));
    }

    public function testTheIdentityLeavesEveryIdUntouched(): void
    {
        $identity = Permutation::identity();

        foreach ([0, 1, 4242, Permutation::MAX] as $id) {
            self::assertSame($id, $identity->apply($id));
            self::assertSame($id, $identity->invert($id));
        }
    }

    public function testANarrowerNetworkStaysWithinItsWidth(): void
    {
        $permutation = new Permutation('test key', bits: 8);
        $seen = [];
        for ($id = 1; $id <= 255; ++$id) {
            $permuted = $permutation->apply($id);
            self::assertGreaterThanOrEqual(1, $permuted);
            self::assertLessThanOrEqual(255, $permuted);
            self::assertSame($id, $permutation->invert($permuted));
            $seen[$permuted] = true;
        }

        self::assertCount(255, $seen, 'a bijection on 1 to 255');
    }

    public function testADomainNamedByTheCallerIsKept(): void
    {
        $permutation = new Permutation('test key', bits: 8);
        $even = static fn (int $value): bool => $value % 2 === 0;
        $seen = [];
        for ($value = 0; $value <= 254; $value += 2) {
            $permuted = $permutation->applyWithin($value, $even);
            self::assertTrue($even($permuted));
            self::assertSame($value, $permutation->invertWithin($permuted, $even));
            $seen[$permuted] = true;
        }

        self::assertCount(128, $seen, 'a bijection on the even values');
    }

    public function testTheWidthIsEvenAndAtMostThirtyTwoBits(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Permutation('test key', bits: 27);
    }
}
