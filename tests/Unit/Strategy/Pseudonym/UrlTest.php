<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Strategy\Pseudonym;

use Shroud\Exception\MissingReferenceException;
use Shroud\Strategy\Pseudonym\Url;
use Shroud\Tests\Unit\Strategy\StrategyTestCase;

final class UrlTest extends StrategyTestCase
{
    public function testTheHostIsKeptAndThePathRewritten(): void
    {
        $url = self::applyToString(new Url(), 'https://www.example.test/in/zo%C3%A9-kowalczyk-1a2b3c/?originalSubdomain=fr');

        self::assertStringStartsWith('https://www.example.test/', $url);
        self::assertStringEndsWith('/', $url);
        self::assertStringNotContainsString('walczyk', rawurldecode($url));
        self::assertStringNotContainsString('?', $url);
        self::assertStringNotContainsString('1a2b3c', rawurldecode($url), 'the profile suffix identifies too');
        self::assertMatchesRegularExpression('#/[^/]+-\d\p{L}\d\p{L}\d\p{L}/$#u', rawurldecode($url), 'but its shape stays');

        $numeric = self::applyToString(new Url(keptSegments: 1), 'https://www.example.test/in/123456789/');
        self::assertMatchesRegularExpression('#^https://www\.example\.test/in/\d{9}/$#', $numeric);
        self::assertNotSame('https://www.example.test/in/123456789/', $numeric);
    }

    /**
     * A path encoded in latin-1, `%E9` for "é", is read instead of stopping the run.
     */
    public function testALatin1EncodedPathIsRead(): void
    {
        $url = self::applyToString(new Url(), 'https://example.test/caf%E9-du-march%E9/');

        self::assertStringStartsWith('https://example.test/', $url);
        self::assertStringNotContainsString('%E9', $url);
        self::assertStringNotContainsString('caf', $url);
        self::assertTrue(mb_check_encoding(rawurldecode($url), 'UTF-8'));
    }

    public function testAMailtoBecomesThePseudonymEmail(): void
    {
        $url = self::applyToString(new Url(keptSegments: 1), 'mailto:zoe.kowalczyk@example-real.test');

        self::assertMatchesRegularExpression('/^mailto:[^@]+@example\.(org|com|net)$/', $url);
        self::assertStringNotContainsString('kowalczyk', $url);
    }

    public function testABareEmailBecomesThePseudonymEmail(): void
    {
        $url = self::applyToString(new Url(keptSegments: 1), 'zoe.kowalczyk@example-real.test');

        self::assertMatchesRegularExpression('/^[^@]+@example\.(org|com|net)$/', $url);
        self::assertStringNotContainsString('kowalczyk', $url);
    }

    /**
     * Two different slugs of the same pseudonym stay different: the suffix comes from the original.
     */
    public function testAUniqueUrlGetsAStableSuffixFromTheOriginal(): void
    {
        $a = self::applyToString(new Url(unique: true), 'Jacques-Dupont');
        $b = self::applyToString(new Url(unique: true), 'Jacques-Dupont-2');
        $c = self::applyToString(new Url(unique: true), 'section/Jacques-Dupont/');

        self::assertMatchesRegularExpression('/^[A-Za-z-]+-[0-9a-f]{10}$/', $a);
        self::assertNotSame($a, $b);
        self::assertSame($a, self::apply(new Url(unique: true), 'Jacques-Dupont'), 'stable');
        self::assertMatchesRegularExpression('#^[a-z]+/[A-Za-z-]+-[0-9a-f]{10}/$#', $c, 'the suffix goes on the last non-empty segment');
    }

    public function testTheFirstSegmentsCanBeKept(): void
    {
        self::assertStringStartsWith('https://www.example.test/in/', self::applyToString(new Url(keptSegments: 1), 'https://www.example.test/in/zoe-kowalczyk/'));

        $path = self::applyToString(new Url(keptSegments: 1), 'author/optima-legal-advice');
        self::assertMatchesRegularExpression('#^author/[a-z-]+$#', $path);
        self::assertStringNotContainsString('optima', $path);
    }

    public function testTheHostCanBeReplacedByTheFakeDomain(): void
    {
        $url = self::applyToString(new Url(keepHost: false), 'https://www.kowalczyk-lawyers.test/team');

        self::assertMatchesRegularExpression('#^https://[a-z0-9-]+\.example/[a-z]+$#', $url);
        self::assertStringNotContainsString('kowalczyk', $url);
    }

    public function testARowWithoutReferenceIsRefused(): void
    {
        $this->expectException(MissingReferenceException::class);

        self::apply(new Url(), 'https://example.test/x', self::context(reference: null));
    }
}
