<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;

/**
 * Replaces a last name, compound or not, by the last names of the referenced object.
 */
final class LastName extends AbstractShapedWords
{
    protected function provideWords(Context $context, string $value): array
    {
        return $context->requirePseudonym()->lastNames;
    }
}
