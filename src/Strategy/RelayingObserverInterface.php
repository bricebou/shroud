<?php

declare(strict_types=1);

namespace Shroud\Strategy;

/**
 * Marks a composite that observes only through the strategies it carries.
 *
 * Its `observe()` relays the value, or the part of it, to the inner strategy that will transform it:
 * the case `ByColumn` picks, the strategy of a key of `PerKey`, the content of a decoded blob.
 * It observes something only when an observer sits somewhere among the strategies it carries.
 */
interface RelayingObserverInterface extends ObserverInterface, CompositeInterface
{
}
