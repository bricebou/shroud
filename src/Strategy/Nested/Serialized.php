<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\Observation;
use Shroud\Strategy\RelayingObserverInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Transforms a PHP serialized array key by key, and serializes it again.
 *
 * No object is restored. A value that is not a serialization is refused, unless a strategy is
 * given for the plain values: a column where the application may or may not have serialized,
 * as `maybe_serialize()` of WordPress stores its metas. That strategy then transforms a value
 * that is not a serialization, and a serialized string, serialized again so that its length
 * stays right; a serialized number, boolean or object is kept as is, since rewriting it in place
 * would break it.
 */
final class Serialized extends AbstractTextStrategy implements RelayingObserverInterface
{
    /**
     * @param PerKey $content the strategy of a serialized array, key by key
     * @param ?StrategyInterface $plain the strategy of a value that is not a serialized array, or null to refuse what is not a serialization
     */
    public function __construct(
        private readonly PerKey $content,
        private readonly ?StrategyInterface $plain = null,
    ) {
    }

    public function innerStrategies(): iterable
    {
        return $this->plain instanceof StrategyInterface ? [$this->content, $this->plain] : [$this->content];
    }

    /**
     * Relays the observation to the strategy that will transform the value, or to its decoded content.
     */
    public function observe(mixed $value, Context $context): void
    {
        [$serialized, $data] = is_string($value) ? $this->decode($value) : [false, false];
        if (! $this->plain instanceof StrategyInterface || is_array($data)) {
            $this->content->observe($data, $context);
        } elseif (! $serialized || is_string($data)) {
            Observation::relay($this->plain, $serialized ? $data : $value, $context);
        }
    }

    protected function transform(string $value, Context $context): string
    {
        [$serialized, $data] = $this->decode($value);
        if (! $this->plain instanceof StrategyInterface) {
            if (! $serialized) {
                throw new InvalidValueException($context->table, $context->column, 'invalid PHP serialization');
            }

            return serialize($this->content->apply($data, $context));
        }

        if (is_array($data)) {
            return serialize($this->content->apply($data, $context));
        }
        if (! $serialized || is_string($data)) {
            $result = $this->plain->apply($serialized ? $data : $value, $context);
            if (! is_string($result)) {
                throw new InvalidValueException($context->table, $context->column, 'the plain strategy must return a string');
            }

            return $serialized ? serialize($result) : $result;
        }

        return $value;
    }

    /**
     * Decodes a serialization without restoring any object: whether the value is one, and its content.
     *
     * `unserialize()` returns false for an error as for a serialized false: they are told apart.
     *
     * @return array{bool, mixed}
     */
    private function decode(string $value): array
    {
        $data = @unserialize($value, [
            'allowed_classes' => false,
        ]);

        return [$data !== false || $value === serialize(value: false), $data];
    }
}
