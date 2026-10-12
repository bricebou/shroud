<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Replaces a civility by the one of the gender the referenced object draws, in the same form.
 *
 * "Mme" gives "M." or "Mme": the gender of the pseudonym, which agrees with its first names,
 * not the real one. A value that is no civility of the language, a code or a title, gives way
 * to the civility the language writes.
 */
final class Civility extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        $gender = $context->requirePseudonym()->gender;

        return $context->generator->text()->replaceCivility($value, $gender)
            ?? $context->generator->language()->defaultCivility($gender);
    }
}
