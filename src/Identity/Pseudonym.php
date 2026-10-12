<?php

declare(strict_types=1);

namespace Shroud\Identity;

use Faker\Generator as Faker;
use Shroud\Text\Words;

/**
 * Holds the complete fake identity of one object, the author 4242 for instance, not of a type.
 *
 * It is drawn once and reused in every row that references the object.
 * The first names and the last names are reserves: a word-by-word replacement consumes
 * as many as the original counts, in order.
 * The first of each list is the first name, the last name, of the object.
 * The gender, drawn, never observed, gives the first names and the civility.
 */
final readonly class Pseudonym
{
    private const string FIELD_SEPARATOR = "\x1E";

    private const string ITEM_SEPARATOR = "\x1F";

    /**
     * @param list<string> $firstNames
     * @param list<string> $lastNames
     * @param list<string> $organizationWords
     */
    private function __construct(
        public Gender $gender,
        public array $firstNames,
        public array $lastNames,
        public array $organizationWords,
        public string $domain,
        public string $handle,
        public string $email,
        public string $street,
        public string $postalCode,
        public string $city,
    ) {
    }

    /**
     * Draws a complete pseudonym from a seeded Faker; the suffix, specific to the object, makes its email unique.
     *
     * The order of the draws is part of the contract: changing it changes every fixture.
     * A new field is drawn last; the gender comes first, for the first names follow it.
     */
    public static function draw(Faker $faker, string $suffix): self
    {
        $gender = $faker->boolean() ? Gender::Female : Gender::Male;
        $firstNames = [];
        $lastNames = [];
        // A name with a space ("Da Costa") would replace one word by two: draw again.
        for ($i = 0; $i < 5; ++$i) {
            $firstNames[] = self::drawWord(static fn (): string => $faker->firstName($gender->value));
            $lastNames[] = self::drawWord($faker->lastName(...));
        }
        // The words of the company name, then fillers for longer organization names.
        $organization = $faker->company();
        $organizationWords = array_values(array_filter(
            [...Words::extract($organization), ...Words::lorem($faker, 8)],
            static fn (string $word): bool => $word !== '',
        ));
        // A reserved top-level domain: a fake host must not point at a real site.
        $domain = Words::toSlug($faker->domainWord()) . '.example';
        // The suffix tells homonyms apart: two objects drawing the same names keep distinct
        // emails, as a unique key on the column requires.
        $handle = Words::toSlug($firstNames[0]) . '.' . Words::toSlug($lastNames[0]) . '-' . $suffix;
        $email = $handle . '@' . $faker->safeEmailDomain();

        return new self(
            gender: $gender,
            firstNames: $firstNames,
            lastNames: $lastNames,
            organizationWords: $organizationWords,
            domain: $domain,
            handle: $handle,
            email: $email,
            street: $faker->streetAddress(),
            postalCode: $faker->postcode(),
            city: $faker->city(),
        );
    }

    /**
     * Packs the pseudonym into one string, four times smaller in memory than the object.
     *
     * The separators are control characters, which no drawn word, street or city contains.
     */
    public function pack(): string
    {
        return implode(self::FIELD_SEPARATOR, [
            $this->gender->value,
            implode(self::ITEM_SEPARATOR, $this->firstNames),
            implode(self::ITEM_SEPARATOR, $this->lastNames),
            implode(self::ITEM_SEPARATOR, $this->organizationWords),
            $this->domain,
            $this->handle,
            $this->email,
            $this->street,
            $this->postalCode,
            $this->city,
        ]);
    }

    /**
     * Rebuilds a pseudonym from the string that `pack()` made.
     */
    public static function unpack(string $packed): self
    {
        [$gender, $firstNames, $lastNames, $organizationWords, $domain, $handle, $email, $street, $postalCode, $city] = explode(self::FIELD_SEPARATOR, $packed);

        return new self(
            gender: Gender::from($gender),
            firstNames: explode(self::ITEM_SEPARATOR, $firstNames),
            lastNames: explode(self::ITEM_SEPARATOR, $lastNames),
            organizationWords: explode(self::ITEM_SEPARATOR, $organizationWords),
            domain: $domain,
            handle: $handle,
            email: $email,
            street: $street,
            postalCode: $postalCode,
            city: $city,
        );
    }

    /**
     * Draws a one-word first name or last name.
     *
     * @param callable(): string $draw
     */
    private static function drawWord(callable $draw): string
    {
        do {
            $word = $draw();
        } while (str_contains($word, ' '));

        return $word;
    }

    /**
     * Lists the words of a full name: as many first names as asked, then the last names.
     *
     * Zero first names gives the last names alone.
     *
     * @return list<string>
     */
    public function listFullNameWords(int $firstNameWords = 1): array
    {
        return [...array_slice($this->firstNames, 0, max(0, $firstNameWords)), ...$this->lastNames];
    }
}
