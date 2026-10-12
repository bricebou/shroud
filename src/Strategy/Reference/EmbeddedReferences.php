<?php

declare(strict_types=1);

namespace Shroud\Strategy\Reference;

use Shroud\Context;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Keeps a text as it is, except the references that the platform embeds in it, which follow the renumbering.
 *
 * For a public editorial text that links to renumbered objects: a SPIP link shortcut `[->aut12]`,
 * a WordPress block `<!-- wp:image {"id":12} -->` or shortcode `[gallery ids="12,15"]` take the new
 * numbers of their objects, as the platform reads them, and the rest of the text does not change.
 */
final class EmbeddedReferences extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        return $context->generator->platform()->renumberReferences($value, $context);
    }

    /**
     * Declares every listed column kept with its embedded references renumbered.
     *
     * @param list<string> $columns
     *
     * @return array<string, self>
     */
    public static function forColumns(array $columns): array
    {
        return array_fill_keys($columns, new self());
    }
}
