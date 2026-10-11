<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Replaces the value by the fake email of the referenced object, unique within its type.
 */
final class Email extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        return $context->requirePseudonym()->email;
    }
}
