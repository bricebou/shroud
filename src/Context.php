<?php

declare(strict_types=1);

namespace Shroud;

use Shroud\Declaration\Declaration;
use Shroud\Exception\MissingReferenceException;
use Shroud\Identity\Pseudonym;
use Shroud\Identity\Reference;
use Shroud\Random\Draw;
use Shroud\Strategy\Wrapper\FallbackListenerInterface;

/**
 * Carries what a strategy knows about the value it transforms: its table, its column, its row,
 * the object the row references, the generator and the declaration.
 */
final readonly class Context
{
    /**
     * @param string $key the key of the row, unique within its table
     * @param array<string, mixed> $row the whole row, as read
     * @param ?Reference $reference the object the row references, or null when it references none
     * @param Declaration $declaration the declaration of the base, empty when a strategy runs alone
     * @param ?FallbackListenerInterface $fallbackListener hears the values a fallback takes over, during a dry run
     */
    public function __construct(
        public string $table,
        public string $column,
        public string $key,
        public array $row,
        public ?Reference $reference,
        public Generator $generator,
        public Declaration $declaration = new Declaration([]),
        public ?FallbackListenerInterface $fallbackListener = null,
    ) {
    }

    /**
     * Returns the pseudonym of the object the row references, or refuses when the row references none.
     */
    public function requirePseudonym(): Pseudonym
    {
        if (! $this->reference instanceof Reference) {
            throw new MissingReferenceException($this->table, $this->column);
        }

        return $this->generator->pseudonymFor($this->reference);
    }

    /**
     * Notes, under a key, a fact of shape observed on the object the row references.
     *
     * An observation is what a strategy learns from a value during the first pass over the
     * batch, for another to read back during the second. A fact of shape, never a content.
     * Without a reference nothing is kept: the refusal will come from the transformation,
     * which tells better what is missing.
     */
    public function remember(string $key, mixed $value): void
    {
        if ($this->reference instanceof Reference) {
            $this->generator->remember($this->reference, $key, $value);
        }
    }

    /**
     * Returns the fact noted under this key for the object the row references, or null.
     */
    public function recall(string $key): mixed
    {
        return $this->reference instanceof Reference
            ? $this->generator->recall($this->reference, $key)
            : null;
    }

    /**
     * Returns the draw of this very value: same table, same row, same column, same draw.
     */
    public function draw(): Draw
    {
        return $this->generator->draw($this->table, $this->key, $this->column);
    }
}
