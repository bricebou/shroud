<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Replaces the value by the fake postal code of the referenced object.
 */
final class PostalCode extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        return $context->requirePseudonym()->postalCode;
    }
}
