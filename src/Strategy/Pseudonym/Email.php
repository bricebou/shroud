<?php

declare(strict_types=1);

namespace Shroud\Strategy\Pseudonym;

use Shroud\Context;
use Shroud\Identity\Reference;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\ObserverInterface;
use Shroud\Text\Emails;

/**
 * Replaces the value by the fake email of the referenced object, unique within its type.
 *
 * The address is noted on its object during the observation, and `WithoutEmails` gives it the
 * same fake address where a text quotes it.
 */
final class Email extends AbstractTextStrategy implements ObserverInterface
{
    /**
     * Notes that the referenced object holds the address.
     */
    public function observe(mixed $value, Context $context): void
    {
        if (is_string($value) && str_contains($value, '@') && $context->reference instanceof Reference) {
            $context->generator->noteHolder('email', Emails::key($value), $context->reference);
        }
    }

    protected function transform(string $value, Context $context): string
    {
        return $context->requirePseudonym()->email;
    }
}
