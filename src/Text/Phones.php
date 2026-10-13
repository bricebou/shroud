<?php

declare(strict_types=1);

namespace Shroud\Text;

/**
 * Finds the phone numbers of a text, and lays fake digits over a number while keeping its shape.
 *
 * A number is a run of 8 to 15 digits written in groups, `06 12 34 56 78`, `+33 1 23 45 67 89`,
 * `(555) 123-4567`, `08-61 99 10`, or glued after a `+`, `+33612345678`. A run of digits
 * without separator nor `+` is an id or a timestamp, never a number. The rules below set
 * aside what looks like one without being one; they were measured on real bases, where they
 * keep the numbers of the phone columns and drop dates, amounts, addresses and versions.
 */
final class Phones
{
    /**
     * A separator of groups: a blank, a non-breaking space, a dot or a hyphen.
     */
    private const string SEPARATOR = '[ .\-\x{00A0}]';

    /**
     * A candidate: groups, after an optional dialling code, `+33` or `(+33)`, and an optional group in parentheses; or a `+` and digits glued.
     */
    private const string CANDIDATE = '/(?<![\p{L}\p{N}])(?:(?:\(\+\d{1,3}\)|\+\d{1,3})' . self::SEPARATOR . '?)?(?:\(\d{1,4}\)' . self::SEPARATOR . '?)?\d{1,4}(?:' . self::SEPARATOR . '\d{1,4})+(?![\p{L}\p{N}])|(?<![\p{L}\p{N}])\+\d{8,15}(?![\p{L}\p{N}])/u';

    /**
     * A date written in groups: a year first or last.
     */
    private const string DATE = '/\d{4}[.\-\/]\d{1,2}[.\-\/]\d{1,2}|\d{1,2}[.\-\/]\d{1,2}[.\-\/]\d{4}/';

    /**
     * What a number keeps of its head, telling how to dial rather than whom: a dialling code, or the trunk prefix of a national number.
     */
    private const string PREFIX = '/^\s*(?:\(\+\d{1,3}\)|\+\d{1,3}|0)/';

    /**
     * Replaces each phone number of a text by what the callable returns for it.
     *
     * @param callable(string): string $replace
     */
    public static function replaceIn(string $text, callable $replace): string
    {
        return preg_replace_callback(
            self::CANDIDATE,
            static function (array $match) use ($text, $replace): string {
                [$number, $offset] = $match[0];

                return self::isNumber($number, substr($text, max(0, $offset - 20), min(20, $offset)), substr($text, $offset + strlen($number), 2))
                    ? $replace($number)
                    : $number;
            },
            $text,
            flags: PREG_OFFSET_CAPTURE,
        ) ?? $text;
    }

    /**
     * Returns what tells one number from another however it is written: its last nine digits.
     *
     * "06 12 34 56 78" and "+33 6 12 34 56 78" give the same key.
     */
    public static function key(string $number): string
    {
        return substr((string) preg_replace('/\D/', '', $number), -9);
    }

    /**
     * Counts the digits of a value.
     */
    public static function countDigits(string $value): int
    {
        return (int) preg_match_all('/\d/', $value);
    }

    /**
     * Lays digits over a number, the last first, keeping its separators and its prefix.
     *
     * The digits beyond those given start over from the first.
     */
    public static function lay(string $number, string $digits): string
    {
        $start = preg_match(self::PREFIX, $number, $prefix) === 1 ? strlen($prefix[0]) : 0;
        $rank = 0;
        $result = '';
        for ($i = strlen($number) - 1; $i >= $start; --$i) {
            $result = (ctype_digit($number[$i]) ? $digits[$rank++ % strlen($digits)] : $number[$i]) . $result;
        }

        return substr($number, 0, $start) . $result;
    }

    /**
     * Tells whether a candidate is a phone number, from its shape and what surrounds it.
     */
    private static function isNumber(string $number, string $before, string $after): bool
    {
        $digits = self::countDigits($number);
        if ($digits < 8 || $digits > 15) {
            return false;
        }
        // A part of a word, "order-oct-12-2026-0800-am", or the arguments of a function, "rgb(255 255 255)".
        if (preg_match('/\p{L}-$/u', $before) === 1 || preg_match('/^-\p{L}/u', $after) === 1 || preg_match('/[A-Za-z-]+\(\s*$/', $before) === 1) {
            return false;
        }
        if (preg_match(self::DATE, $number) === 1 || self::isDateWithBlanks($number) || preg_match('/^(19|20)\d\d-(19|20)\d\d$/', $number) === 1) {
            return false;
        }

        // The dialling code and the group in parentheses, set apart with the separator after them.
        $head = preg_match('/^(?:(?:\(\+\d{1,3}\)|\+\d{1,3})' . self::SEPARATOR . '?(?=\d|\())?(?:\(\d{1,4}\)' . self::SEPARATOR . '?)?/u', $number, $match) === 1 ? $match[0] : '';
        $parts = preg_split('/(' . self::SEPARATOR . ')/u', substr($number, strlen($head)), -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return false;
        }
        $groups = [];
        $separators = [];
        foreach ($parts as $rank => $part) {
            if ($rank % 2 === 0) {
                $groups[] = $part;
            } else {
                $separators[] = $part;
            }
        }
        // More than six groups: a version, a list of ids. Six at most, dialling code included.
        if (count($groups) + ($head === '' ? 0 : 1) > 6) {
            return false;
        }
        if ($head !== '') {
            return true;
        }
        // Without dialling code nor parentheses: one separator throughout, but after an area code.
        if (count(array_unique(array_slice($separators, 1))) > 1) {
            return false;
        }

        return ! self::isThousands($number, $groups, $separators) && ! self::isIpv4($groups, $separators);
    }

    /**
     * Tells whether a value is a date written with blanks, its day, month and year plausible: "12 03 2026".
     */
    private static function isDateWithBlanks(string $value): bool
    {
        return preg_match('/^(\d{1,2})[ \x{00A0}](\d{1,2})[ \x{00A0}](\d{4})$/u', $value, $date) === 1
            && (int) $date[1] >= 1 && (int) $date[1] <= 31
            && (int) $date[2] >= 1 && (int) $date[2] <= 12
            && (int) $date[3] >= 1900 && (int) $date[3] <= 2099;
    }

    /**
     * Tells whether groups read as an amount: one to three digits, then groups of three, after blanks or dots.
     *
     * A national number keeps its leading zero, "0 800 123 456", unless dots separate it, a version then.
     *
     * @param list<string> $groups
     * @param list<string> $separators
     */
    private static function isThousands(string $number, array $groups, array $separators): bool
    {
        if (in_array('-', $separators, strict: true) || (str_starts_with($number, '0') && array_unique($separators) !== ['.'])) {
            return false;
        }

        return strlen($groups[0]) <= 3 && array_filter(array_slice($groups, 1), static fn (string $group): bool => strlen($group) !== 3) === [];
    }

    /**
     * Tells whether groups read as an IPv4 address: four groups after dots, none beyond 255.
     *
     * @param list<string> $groups
     * @param list<string> $separators
     */
    private static function isIpv4(array $groups, array $separators): bool
    {
        return count($groups) === 4 && array_unique($separators) === ['.']
            && array_filter($groups, static fn (string $group): bool => strlen($group) > 3 || (int) $group > 255) === [];
    }
}
