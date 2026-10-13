<?php

declare(strict_types=1);

namespace Shroud;

use Shroud\Identity\Permutation;
use Shroud\Identity\Pseudonym;
use Shroud\Identity\Reference;
use Shroud\Language\English;
use Shroud\Language\LanguageInterface;
use Shroud\Platform\Generic;
use Shroud\Platform\PlatformInterface;
use Shroud\Random\Draw;
use Shroud\Text\Text;

/**
 * Derives every random-looking draw from the salt alone, so that two runs give the same result.
 */
final class Generator
{
    /**
     * The pseudonyms drawn so far, packed, by reference key.
     *
     * @var array<string, string>
     */
    private array $pseudonyms = [];

    /**
     * @var array<string, Permutation>
     */
    private array $permutations = [];

    private ?Text $text = null;

    /**
     * The facts of shape that the strategies noted on each object during the observation
     * pass, by fact key then by reference key: one array per fact, not one per object,
     * keeps them in a third of the memory.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $memory = [];

    /**
     * @param bool $renumbering false to leave every id untouched
     * @param LanguageInterface $language the words of the fake identities and the conventions that the shape-preserving replacement follows
     * @param PlatformInterface $platform the platform that owns the base, generic by default
     */
    public function __construct(
        private readonly string $salt,
        private readonly bool $renumbering = true,
        private readonly LanguageInterface $language = new English(),
        private readonly PlatformInterface $platform = new Generic(),
    ) {
    }

    /**
     * Returns the language whose conventions the shape-preserving replacement follows.
     */
    public function language(): LanguageInterface
    {
        return $this->language;
    }

    /**
     * Returns the platform that owns the base.
     */
    public function platform(): PlatformInterface
    {
        return $this->platform;
    }

    /**
     * Returns the shape-preserving replacement of texts, set up for the platform and the language.
     */
    public function text(): Text
    {
        return $this->text ??= new Text($this->platform, $this->language);
    }

    /**
     * Returns the draw of the salt and the keys: the same keys give the same draws, nothing else moves them.
     */
    public function draw(string|int ...$keys): Draw
    {
        return new Draw($this->digest(...$keys));
    }

    /**
     * Returns the hexadecimal SHA-256 digest of the salt and the keys, for a unique and stable suffix.
     */
    public function digest(string|int ...$keys): string
    {
        return hash('sha256', implode("\x1f", [$this->salt, ...array_map(strval(...), $keys)]));
    }

    /**
     * Returns the permutation of the ids of an object type, keyed from the salt and the type.
     *
     * Each type has its own: the author 12 and the contact 12 do not receive the same number.
     * It inverts, to find the real id behind a renumbered one. Without renumbering it is the
     * identity: the declaration stays the same, the reference strategies return what they
     * receive, and the batch keeps the ids of the base.
     */
    public function permutationFor(string $type): Permutation
    {
        if (! $this->renumbering) {
            return Permutation::identity();
        }

        return $this->permutations[$type]
            ??= new Permutation(hash('sha256', implode("\x1f", [$this->salt, 'renumber', $type]), binary: true));
    }

    /**
     * Returns a permutation of the given width keyed from the salt and the keys, for a value that is not an id.
     *
     * Unlike `permutationFor()`, it never turns into the identity: an IP address is disguised
     * whether the ids are renumbered or not.
     */
    public function permutation(int $bits, string|int ...$keys): Permutation
    {
        return $this->permutations["\x1e" . implode("\x1f", [$bits, ...$keys])]
            ??= new Permutation(hash('sha256', implode("\x1f", [$this->salt, 'permutation', $bits, ...$keys]), binary: true), bits: $bits);
    }

    /**
     * Notes, under a key, a fact of shape observed on an object.
     */
    public function remember(Reference $reference, string $key, mixed $value): void
    {
        $this->memory[$key][$reference->toKey()] = $value;
    }

    /**
     * Returns the fact noted under this key for an object, or null.
     */
    public function recall(Reference $reference, string $key): mixed
    {
        return $this->memory[$key][$reference->toKey()] ?? null;
    }

    /**
     * Returns the pseudonym of an object, drawn only once.
     *
     * The draw depends only on the salt and the reference: the same object receives the same
     * pseudonym from one run to the next, drawn at its first encounter and kept for the others.
     */
    public function pseudonymFor(Reference $reference): Pseudonym
    {
        return Pseudonym::unpack($this->pseudonyms[$reference->toKey()]
            ??= Pseudonym::draw(
                $this->language->vocabulary(),
                $this->draw('pseudonym', $reference->type, $reference->id),
                $this->pseudonymSuffix($reference),
            )->pack());
    }

    /**
     * Builds the suffix of a pseudonym: the permuted id of the object in base 36, unique within its type.
     *
     * An id that is not a number receives six characters of its digest instead.
     */
    private function pseudonymSuffix(Reference $reference): string
    {
        $id = $reference->id;
        if (is_int($id) || ctype_digit($id)) {
            return base_convert((string) $this->permutationFor($reference->type)->apply((int) $id), 10, 36);
        }

        return substr($this->digest('pseudonym', $reference->type, $id), 0, 6);
    }
}
