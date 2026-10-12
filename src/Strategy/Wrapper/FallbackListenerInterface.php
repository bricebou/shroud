<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;

/**
 * Hears each value that a `Fallback` hands to its second strategy, to count what the first refused.
 */
interface FallbackListenerInterface
{
    /**
     * Notes that the first strategy refused the value of this context, for this reason.
     */
    public function fellBack(Context $context, InvalidValueException $refusal): void;
}
