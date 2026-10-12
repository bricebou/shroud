<?php

declare(strict_types=1);

namespace Shroud;

use Faker\Factory;
use Faker\Generator as Faker;
use Shroud\Identity\Permutation;
use Shroud\Identity\Pseudonym;
use Shroud\Identity\Reference;
use Shroud\Language\LanguageInterface;
use Shroud\Language\Plain;
use Shroud\Platform\Generic;
use Shroud\Platform\PlatformInterface;
use Shroud\Text\Text;

/**
 * Derives every random-looking draw from the salt alone, so that two runs give the same result.
 */
final class Generator
{
    private readonly Faker $faker;

    /**
     * The pseudonyms drawn so far, by reference key.
     *
     * @var array<string, Pseudonym>
     */
    private array $pseudonyms = [];

    /**
     * @var array<string, Permutation>
     */
    private array $permutations = [];

    private ?Text $text = null;

    /**
     * The facts of shape that the strategies noted on each object during the observation
     * pass, by reference key then by fact key.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $memory = [];

    /**
     * @param string $locale the Faker locale, which picks the lists of names and cities
     * @param bool $renumbering false to leave every id untouched
     * @param LanguageInterface $language the conventions that the shape-preserving replacement follows
     * @param PlatformInterface $platform the platform that owns the base, generic by default
     */
    public function __construct(
        private readonly string $salt,
        string $locale = Factory::DEFAULT_LOCALE,
        private readonly bool $renumbering = true,
        private readonly LanguageInterface $language = new Plain(),
        private readonly PlatformInterface $platform = new Generic(),
    ) {
        $this->faker = Factory::create($locale);
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
     * Reseeds Faker from the salt and the keys: any sequence of draws depends on them alone.
     *
     * Faker seeds the global generator of PHP. The draws must follow at once, before any other
     * call to `seedFaker()`, or they mix with another sequence.
     */
    public function seedFaker(string|int ...$keys): Faker
    {
        $this->faker->seed($this->computeSeed(...$keys));

        return $this->faker;
    }

    /**
     * Computes the integer seed of the salt and the keys.
     *
     * SHA-256 of the salt and the keys, separated by a control character so that no
     * concatenation equals another; the first eight hexadecimal digits give the 32-bit
     * integer that `mt_srand()` expects.
     */
    public function computeSeed(string|int ...$keys): int
    {
        return (int) hexdec(substr($this->digest(...$keys), 0, 8));
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
     * Notes, under a key, a fact of shape observed on an object.
     */
    public function remember(Reference $reference, string $key, mixed $value): void
    {
        $this->memory[$reference->toKey()][$key] = $value;
    }

    /**
     * Returns the fact noted under this key for an object, or null.
     */
    public function recall(Reference $reference, string $key): mixed
    {
        return $this->memory[$reference->toKey()][$key] ?? null;
    }

    /**
     * Returns the pseudonym of an object, drawn only once.
     *
     * The seed depends only on the salt and the reference: the same object receives the same
     * pseudonym from one run to the next, drawn at its first encounter and kept for the others.
     */
    public function pseudonymFor(Reference $reference): Pseudonym
    {
        return $this->pseudonyms[$reference->toKey()]
            ??= Pseudonym::draw(
                $this->seedFaker('pseudonym', $reference->type, $reference->id),
                $this->pseudonymSuffix($reference),
            );
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
