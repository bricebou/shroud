<?php

declare(strict_types=1);

namespace Shroud\Language;

/**
 * Follows no convention at all: a replacement keeps the plain word it was given.
 */
final class Plain implements LanguageInterface
{
    public function markDiacritic(string $word): string
    {
        return $word;
    }

    public function elidedWords(): array
    {
        return [];
    }
}
