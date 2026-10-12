<?php

declare(strict_types=1);

namespace Shroud\Language;

use Shroud\Identity\Gender;
use Shroud\Random\Draw;

/**
 * Holds the words a language draws a fake identity from, and the ways it composes them.
 *
 * A composition is a pattern whose placeholders are drawn from the lists: `{firstName}`,
 * `{lastName}`, `{streetType}`, `{cityPrefix}`, `{citySuffix}`, `{organizationSuffix}`, and
 * `{number}` between the bounds of the street numbers. Every word is a single word: none holds
 * a blank, so that a name replaces one word by one.
 */
final readonly class Vocabulary
{
    /**
     * @param array{female: non-empty-list<string>, male: non-empty-list<string>} $firstNames
     * @param non-empty-list<string> $lastNames
     * @param array<string, list<string>> $words the lists of the other placeholders, by name
     * @param non-empty-list<string> $streets
     * @param non-empty-list<string> $cities
     * @param non-empty-list<string> $organizations
     * @param array{int, int} $streetNumbers
     * @param string $postalCode a format whose `#` are digits
     */
    public function __construct(
        private array $firstNames,
        private array $lastNames,
        private array $words,
        private array $streets,
        private array $cities,
        private array $organizations,
        private array $streetNumbers,
        private string $postalCode,
    ) {
    }

    /**
     * Reads a vocabulary from a PHP file that returns its lists.
     */
    public static function fromFile(string $file): self
    {
        /** @var array{firstNames: array{female: non-empty-list<string>, male: non-empty-list<string>}, lastNames: non-empty-list<string>, streetTypes: list<string>, cityPrefixes: list<string>, citySuffixes: list<string>, organizationSuffixes: list<string>, streets: non-empty-list<string>, cities: non-empty-list<string>, organizations: non-empty-list<string>, streetNumbers: array{int, int}, postalCode: string} $lists */
        $lists = require $file;

        return new self(
            firstNames: $lists['firstNames'],
            lastNames: $lists['lastNames'],
            words: [
                'streetType' => $lists['streetTypes'],
                'cityPrefix' => $lists['cityPrefixes'],
                'citySuffix' => $lists['citySuffixes'],
                'organizationSuffix' => $lists['organizationSuffixes'],
            ],
            streets: $lists['streets'],
            cities: $lists['cities'],
            organizations: $lists['organizations'],
            streetNumbers: $lists['streetNumbers'],
            postalCode: $lists['postalCode'],
        );
    }

    /**
     * Draws a first name of the gender.
     */
    public function firstName(Gender $gender, Draw $draw): string
    {
        return $draw->pick($this->firstNames[$gender->value]);
    }

    public function lastName(Draw $draw): string
    {
        return $draw->pick($this->lastNames);
    }

    /**
     * Draws a street address: a number, a type of street, a name.
     */
    public function street(Draw $draw): string
    {
        return $this->compose($draw->pick($this->streets), $draw);
    }

    public function city(Draw $draw): string
    {
        return $this->compose($draw->pick($this->cities), $draw);
    }

    public function organization(Draw $draw): string
    {
        return $this->compose($draw->pick($this->organizations), $draw);
    }

    public function postalCode(Draw $draw): string
    {
        return $draw->numerify($this->postalCode);
    }

    /**
     * Fills the placeholders of a composition, each one drawn on its own.
     */
    private function compose(string $pattern, Draw $draw): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $match): string => match ($match[1]) {
                'firstName' => $this->firstName($draw->boolean() ? Gender::Female : Gender::Male, $draw),
                'lastName' => $this->lastName($draw),
                'number' => (string) $draw->int(...$this->streetNumbers),
                default => isset($this->words[$match[1]]) && $this->words[$match[1]] !== [] ? $draw->pick($this->words[$match[1]]) : $match[0],
            },
            $pattern,
        ) ?? $pattern;
    }
}
