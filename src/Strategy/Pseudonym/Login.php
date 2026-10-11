<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Strategy\AbstractTextStrategy;

/**
 * Replaces a login by the fake handle of the referenced object.
 */
final class Login extends AbstractTextStrategy
{
    protected function transform(string $value, Context $context): string
    {
        return $context->requirePseudonym()->handle;
    }
}
