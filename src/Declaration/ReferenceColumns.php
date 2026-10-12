<?php

declare(strict_types=1);

namespace Shroud\Declaration;

use Shroud\Generator;
use Shroud\Identity\Reference;

/**
 * Tells where a row carries the reference to its object: an id column, and a type either fixed
 * or read from another column, as in a polymorphic link table.
 *
 * A fallback takes over when the id is missing or zero. Following the owner, the reference
 * climbs the ownership links that the observation pass has learnt.
 */
final readonly class ReferenceColumns
{
    /**
     * The key under which the owner of an object is noted on it, as a `Reference`.
     */
    public const string OWNER = 'owner';

    /**
     * How many successive owners a reference climbs at most: an email, a contact, a person.
     */
    private const int MAX_HOPS = 5;

    private function __construct(
        private ?string $type,
        private ?string $typeColumn,
        private string $idColumn,
        private ?self $fallback = null,
        private bool $followingOwner = false,
    ) {
    }

    /**
     * Declares a reference whose type is fixed, the id read from a column.
     */
    public static function fromFixedType(string $type, string $idColumn): self
    {
        return new self($type, typeColumn: null, idColumn: $idColumn);
    }

    /**
     * Declares a reference whose type and id are each read from a column.
     */
    public static function fromTypeColumn(string $typeColumn, string $idColumn): self
    {
        return new self(type: null, typeColumn: $typeColumn, idColumn: $idColumn);
    }

    /**
     * Adds a fallback reference, used when this one does not resolve.
     */
    public function withFallback(self $fallback): self
    {
        return new self($this->type, $this->typeColumn, $this->idColumn, $fallback, $this->followingOwner);
    }

    /**
     * Makes the reference climb to the object that owns the one of the row, when an owner was observed.
     */
    public function followingOwner(): self
    {
        return new self($this->type, $this->typeColumn, $this->idColumn, $this->fallback, followingOwner: true);
    }

    /**
     * Reads the reference of a row, or that of the fallback, or none; then climbs to the owner if asked.
     *
     * @param array<string, mixed> $row
     */
    public function resolveIn(array $row, ?Generator $generator = null): ?Reference
    {
        $type = $this->type ?? ($row[$this->typeColumn] ?? null);
        $id = $row[$this->idColumn] ?? null;
        // An empty type, an id missing, empty or zero: no object, as a foreign key without target reads zero.
        if (is_string($type) && $type !== '' && (is_int($id) || is_string($id)) && ! in_array($id, ['', '0', 0], strict: true)) {
            $reference = new Reference($type, $id);
        } else {
            $reference = $this->fallback?->resolveIn($row, $generator);
        }
        if (! $this->followingOwner || ! $generator instanceof Generator) {
            return $reference;
        }
        for ($hops = 0; $reference instanceof Reference && $hops < self::MAX_HOPS; ++$hops) {
            $owner = $generator->recall($reference, self::OWNER);
            if (! $owner instanceof Reference) {
                break;
            }
            $reference = $owner;
        }

        return $reference;
    }
}
