<?php

declare(strict_types=1);

namespace Shroud\Platform;

/**
 * Treats the base as a generic relational base: the neutral answers of every question, under the name `generic`.
 */
final readonly class Generic extends AbstractPlatform
{
    public function name(): string
    {
        return 'generic';
    }
}
