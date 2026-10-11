<?php

declare(strict_types=1);

namespace Shroud\Platform;

use Shroud\Context;

/**
 * Treats the base as a generic relational base: no markup beyond HTML, no embedded reference.
 */
final class Generic implements PlatformInterface
{
    public function name(): string
    {
        return 'generic';
    }

    public function preservedMarkup(): array
    {
        return [];
    }

    public function renumberReferences(string $text, Context $context): string
    {
        return $text;
    }
}
