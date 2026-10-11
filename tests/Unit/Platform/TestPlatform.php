<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Platform;

use Shroud\Context;
use Shroud\Platform\PlatformInterface;

/**
 * A made-up platform for the tests: template tags `{{name}}` are markup, and `#type:12` references
 * the object 12 of a type, renumbered when the declaration renumbers that type.
 */
final class TestPlatform implements PlatformInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function preservedMarkup(): array
    {
        return ['\{\{[a-z_]+\}\}', '#[a-z]+:\d+'];
    }

    public function renumberReferences(string $text, Context $context): string
    {
        return preg_replace_callback(
            '/#([a-z]+):(\d+)/',
            static fn (array $match): string => $context->declaration->isRenumbered($match[1])
                ? '#' . $match[1] . ':' . $context->generator->permutationFor($match[1])->apply((int) $match[2])
                : $match[0],
            $text,
        ) ?? $text;
    }
}
