<?php

declare(strict_types=1);

namespace Shroud\Language;

use Shroud\Identity\Gender;

/**
 * Gathers the conventions of a language that a shape-preserving replacement follows.
 */
interface LanguageInterface
{
    /**
     * Puts one diacritic on a word that carries none, as the language would; returns it unchanged otherwise.
     *
     * The replacement of a word that carried a diacritic is drawn from Faker, whose lorem fillers
     * and many locales carry none: marking it keeps the look of the original.
     */
    public function markDiacritic(string $word): string;

    /**
     * Lists the words that the language elides before a vowel, without their apostrophe: `l`, `qu`.
     *
     * An elision is a fragment of the real word, "d'Aubigné": a shape-preserving replacement
     * drops it, and does not count it as a word. Empty when the language elides nothing.
     *
     * @return list<string>
     */
    public function elidedWords(): array;

    /**
     * Returns the civility of the gender in the form of the one given, or null when it is no civility of the language.
     *
     * "Mme" or "Mlle" give "M." or "Mme", "Madame" gives "Monsieur" or "Madame": the form stays,
     * the gender is the one drawn. The case of the original is left to the caller.
     */
    public function civility(string $like, Gender $gender): ?string;

    /**
     * Returns the civility the language writes for the gender, for a value that names none.
     */
    public function defaultCivility(Gender $gender): string;
}
