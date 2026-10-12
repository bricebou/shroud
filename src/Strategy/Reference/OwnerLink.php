<?php

declare(strict_types=1);

namespace Shroud\Strategy\Reference;

use Shroud\Context;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Identity\Reference;
use Shroud\Strategy\CompositeInterface;
use Shroud\Strategy\Observation;
use Shroud\Strategy\ObserverInterface;
use Shroud\Strategy\StrategyInterface;
use Shroud\Strategy\Value\Keep;

/**
 * Learns, during the observation pass, that the object of the row belongs to another one: an
 * email to a contact, a contact to a person.
 *
 * A reference declared `followingOwner()` then climbs that chain, so that the email of a person
 * carries the same pseudonym as their name. The strategy wraps the one of the column it sits
 * on, and lets it transform the value.
 */
final readonly class OwnerLink implements ObserverInterface, CompositeInterface
{
    /**
     * @param ReferenceColumns $owned where the row names the owned object
     * @param ReferenceColumns $owner where the row names its owner
     * @param StrategyInterface $inner the strategy of the column
     */
    public function __construct(
        private ReferenceColumns $owned,
        private ReferenceColumns $owner,
        private StrategyInterface $inner = new Keep(),
    ) {
    }

    /**
     * Notes the owner of the object of the row, when both are named and differ, then lets the wrapped strategy observe.
     */
    public function observe(mixed $value, Context $context): void
    {
        $owned = $this->owned->resolveIn($context->row);
        $owner = $this->owner->resolveIn($context->row);
        if ($owned instanceof Reference && $owner instanceof Reference && $owned->toKey() !== $owner->toKey()) {
            $context->generator->remember($owned, ReferenceColumns::OWNER, $owner);
        }
        Observation::relay($this->inner, $value, $context);
    }

    /**
     * Lets the wrapped strategy transform the value.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        return $this->inner->apply($value, $context);
    }

    public function innerStrategies(): iterable
    {
        return [$this->inner];
    }
}
