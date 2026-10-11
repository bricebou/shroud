<?php

declare(strict_types=1);

namespace Shroud\Platform;

use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Schema\DraftRule;
use Shroud\Schema\FrozenSchemas;
use Shroud\Schema\Glossary\GlossaryInterface;

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

    /**
     * Returns the declaration of the bare platform: its own tables, before any extension or site column.
     */
    public function bareDeclaration(): Declaration;

    /**
     * Returns the frozen schemas of the bare platform, one per version whose schema changed.
     */
    public function frozenSchemas(): FrozenSchemas;

    /**
     * Reads the version of the platform installed on an open base, or null when the base does not tell.
     */
    public function readVersion(\PDO $pdo): ?string;

    /**
     * Lists the rules of the platform for drafting a declaration, tried before the generic ones.
     *
     * The secrets of the platform to clear, the columns of its polymorphic links.
     *
     * @return list<DraftRule>
     */
    public function draftRules(): array;

    /**
     * Lists the glossaries that name the columns of the platform, in order of trial.
     *
     * The language of a schema is that of the platform, not of the site: a platform written in
     * French names its columns in French, with some English loans.
     *
     * @return list<GlossaryInterface>
     */
    public function glossaries(): array;
}
