<?php

declare(strict_types=1);

namespace Shroud\Text;

use Shroud\Generator;
use Shroud\Identity\Gender;
use Shroud\Identity\Pseudonym;

/**
 * Finds the email addresses of a text and draws the fake ones.
 *
 * A fake address joins names of the language on a reserved domain: the same for the same
 * address, case ignored, whatever the table.
 */
final class Emails
{
    /**
     * An address, internationalised ones included: Unicode letters in the local part and the domain.
     * A missed address leaks, while a false positive only replaces a string that looked like one.
     */
    private const string PATTERN = '/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}-]+(?:\.[\p{L}\p{N}-]+)*\.\p{L}{2,}/u';

    /**
     * Replaces each address of a text by what the callable returns for it.
     *
     * @param callable(string): string $replace
     */
    public static function replaceIn(string $text, callable $replace): string
    {
        if (! str_contains($text, '@')) {
            return $text;
        }

        return preg_replace_callback(self::PATTERN, static fn (array $match): string => $replace($match[0]), $text) ?? $text;
    }

    /**
     * Draws the fake address of a real one: names of the language, a reserved domain.
     */
    public static function disguise(string $address, Generator $generator): string
    {
        $draw = $generator->draw('email', mb_strtolower($address));
        $vocabulary = $generator->language()->vocabulary();
        $firstName = $vocabulary->firstName($draw->boolean() ? Gender::Female : Gender::Male, $draw);

        return Words::toSlug($firstName) . '.' . Words::toSlug($vocabulary->lastName($draw)) . '@' . $draw->pick(Pseudonym::EMAIL_DOMAINS);
    }
}
