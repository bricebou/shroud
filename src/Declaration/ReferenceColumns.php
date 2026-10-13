<?php

declare(strict_types=1);

namespace Shroud\Declaration;

use Shroud\Generator;
use Shroud\Identity\Reference;
use Shroud\Text\Emails;

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

    /**
     * @param bool $fromValue whether the id is drawn from the value of the column rather than read in it
     */
    private function __construct(
        private ?string $type,
        private ?string $typeColumn,
        private string $idColumn,
        private ?self $fallback = null,
        private bool $followingOwner = false,
        private bool $fromValue = false,
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
     * Declares a reference drawn from a value of the row: the rows that carry the same value share an object.
     *
     * The orders of a guest customer carry no user, but the same email: they share one pseudonym.
     * The value is trimmed and lowercased, and gives an object of the type whose id is a salted
     * digest of the value: it lives in memory, never in the dump. When an `Email` column holds
     * the same address for an object, the pseudonym is that of the holder, so that a guest who
     * signs up keeps one pseudonym. An empty value names no object.
     */
    public static function fromValue(string $type, string $column): self
    {
        return new self($type, typeColumn: null, idColumn: $column, fromValue: true);
    }

    /**
     * Adds a fallback reference, used when this one does not resolve.
     */
    public function withFallback(self $fallback): self
    {
        return new self($this->type, $this->typeColumn, $this->idColumn, $fallback, $this->followingOwner, $this->fromValue);
    }

    /**
     * Makes the reference climb to the object that owns the one of the row, when an owner was observed.
     */
    public function followingOwner(): self
    {
        return new self($this->type, $this->typeColumn, $this->idColumn, $this->fallback, followingOwner: true, fromValue: $this->fromValue);
    }

    /**
     * Reads the reference of a row, or that of the fallback, or none; then climbs to the owner if asked.
     *
     * @param array<string, mixed> $row
     */
    public function resolveIn(array $row, ?Generator $generator = null): ?Reference
    {
        $reference = $this->fromValue ? $this->drawnIn($row, $generator) : $this->readIn($row);
        $reference ??= $this->fallback?->resolveIn($row, $generator);
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

    /**
     * Reads the type and the id of the row; an empty type, an id missing, empty or zero name no
     * object, as a foreign key without target reads zero.
     *
     * @param array<string, mixed> $row
     */
    private function readIn(array $row): ?Reference
    {
        $type = $this->type ?? ($row[$this->typeColumn] ?? null);
        $id = $row[$this->idColumn] ?? null;

        return is_string($type) && $type !== '' && (is_int($id) || is_string($id)) && ! in_array($id, ['', '0', 0], strict: true)
            ? new Reference($type, $id)
            : null;
    }

    /**
     * Draws the object of a value, and tells the generator which value it comes from.
     *
     * Without generator, there is no salt to draw from: no object.
     *
     * @param array<string, mixed> $row
     */
    private function drawnIn(array $row, ?Generator $generator): ?Reference
    {
        $value = $row[$this->idColumn] ?? null;
        if (! is_string($value) || trim($value) === '' || ! $generator instanceof Generator || $this->type === null) {
            return null;
        }
        $key = Emails::key($value);
        $reference = new Reference($this->type, substr($generator->digest('reference', $this->type, $key), 0, 16));
        $generator->noteDrawnFrom($reference, 'email', $key);

        return $reference;
    }
}
