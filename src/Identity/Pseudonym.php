<?php

declare(strict_types=1);

namespace Shroud\Identity;

use Shroud\Language\Vocabulary;
use Shroud\Random\Draw;
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
     * The domains of the fake emails, reserved for documentation (RFC 2606): no mail reaches anyone.
     *
     * @var non-empty-list<string>
     */
    public const array EMAIL_DOMAINS = ['example.com', 'example.net', 'example.org'];

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
        public string $phoneDigits,
    ) {
    }

    /**
     * Draws a complete pseudonym from the words of a language; the suffix, specific to the object, makes its email unique.
     *
     * Each field has a draw of its own: a new field moves none of the others. The first names
     * follow the gender.
     */
    public static function draw(Vocabulary $vocabulary, Draw $draw, string $suffix): self
    {
        $gender = $draw->for('gender')->boolean() ? Gender::Female : Gender::Male;
        $firstNames = [];
        $lastNames = [];
        $firstNameDraw = $draw->for('firstNames');
        $lastNameDraw = $draw->for('lastNames');
        for ($i = 0; $i < 5; ++$i) {
            $firstNames[] = $vocabulary->firstName($gender, $firstNameDraw);
            $lastNames[] = $vocabulary->lastName($lastNameDraw);
        }
        // The words of the organization name, then fillers for longer organization names.
        $organizationDraw = $draw->for('organization');
        $organizationWords = [...Words::extract($vocabulary->organization($organizationDraw)), ...Words::lorem($organizationDraw, 8)];
        // A reserved top-level domain: a fake host must not point at a real site.
        $domain = Words::toSlug($vocabulary->lastName($draw->for('domain'))) . '.example';
        // The suffix tells homonyms apart: two objects drawing the same names keep distinct
        // emails, as a unique key on the column requires.
        $handle = Words::toSlug($firstNames[0]) . '.' . Words::toSlug($lastNames[0]) . '-' . $suffix;

        return new self(
            gender: $gender,
            firstNames: $firstNames,
            lastNames: $lastNames,
            organizationWords: $organizationWords,
            domain: $domain,
            handle: $handle,
            email: $handle . '@' . $draw->for('email')->pick(self::EMAIL_DOMAINS),
            street: $vocabulary->street($draw->for('street')),
            postalCode: $vocabulary->postalCode($draw->for('postalCode')),
            city: $vocabulary->city($draw->for('city')),
            // Enough digits for any number: E.164 counts fifteen at most.
            phoneDigits: $draw->for('phone')->numerify(str_repeat('#', 15)),
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
            $this->phoneDigits,
        ]);
    }

    /**
     * Rebuilds a pseudonym from the string that `pack()` made.
     */
    public static function unpack(string $packed): self
    {
        [$gender, $firstNames, $lastNames, $organizationWords, $domain, $handle, $email, $street, $postalCode, $city, $phoneDigits] = explode(self::FIELD_SEPARATOR, $packed);

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
            phoneDigits: $phoneDigits,
        );
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
