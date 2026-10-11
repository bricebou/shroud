<?php

declare(strict_types=1);

namespace Shroud;

/**
 * Designates one object of the base, the author 4242 for instance: a type and an id.
 */
final readonly class Reference
{
    public function __construct(
        public string $type,
        public int|string $id,
    ) {
    }

    /**
     * Composes the key of the reference, type and id, for indexing.
     */
    public function toKey(): string
    {
        return $this->type . ':' . $this->id;
    }
}
