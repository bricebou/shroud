<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractShapedWords;

/**
 * Replaces an organization name by the organization words of the referenced object.
 */
final class Organization extends AbstractShapedWords
{
    protected function provideWords(Context $context, string $value): array
    {
        return $context->requirePseudonym()->organizationWords;
    }
}
