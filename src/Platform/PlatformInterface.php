<?php

declare(strict_types=1);

namespace Shroud\Platform;

use Shroud\Context;

/**
 * Gathers what the core needs to know about the CMS, or the application, that owns the base.
 *
 * The core knows no CMS: a platform package implements this interface, and a configuration
 * hands it to the generator. Without one, the base is treated as a generic relational base
 * whose texts carry HTML at most.
 */
interface PlatformInterface
{
    /**
     * Names the platform, in lowercase, for messages and file names.
     */
    public function name(): string;

    /**
     * Lists the markup of the platform's texts to leave untouched, besides HTML tags and entities.
     *
     * Each entry is a regular expression fragment without delimiters, compiled with the `u`
     * modifier: the language markers of a multilingual block, the link shortcuts of a wiki
     * syntax, the tags of a template language.
     *
     * @return list<string>
     */
    public function preservedMarkup(): array;

    /**
     * Renumbers the object references that the platform embeds in a text, following the renumbered ids.
     *
     * A link shortcut to the author 12, on a platform whose authors are renumbered, takes the new
     * number of that author. The text comes back unchanged when the platform embeds no reference.
     */
    public function renumberReferences(string $text, Context $context): string;
}
