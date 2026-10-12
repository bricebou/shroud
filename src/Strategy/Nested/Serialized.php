<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\RelayingObserverInterface;

/**
 * Transforms a PHP serialized array key by key, and serializes it again.
 *
 * No object is restored. A value that is not a serialization is refused: an old base that carries
 * some declares a `Fallback`.
 */
final class Serialized extends AbstractTextStrategy implements RelayingObserverInterface
{
    public function __construct(
        private readonly PerKey $content,
    ) {
    }

    public function innerStrategies(): iterable
    {
        return [$this->content];
    }

    /**
     * Relays the observation to the content of the decoded blob; an unreadable blob is not observed.
     */
    public function observe(mixed $value, Context $context): void
    {
        $data = is_string($value) ? @unserialize($value, [
            'allowed_classes' => false,
        ]) : false;
        $this->content->observe($data, $context);
    }

    protected function transform(string $value, Context $context): string
    {
        // `unserialize()` returns false for an error as for a serialized false: tell them apart.
        $data = @unserialize($value, [
            'allowed_classes' => false,
        ]);
        if ($data === false && $value !== serialize(value: false)) {
            throw new InvalidValueException($context->table, $context->column, 'invalid PHP serialization');
        }

        return serialize($this->content->apply($data, $context));
    }
}
