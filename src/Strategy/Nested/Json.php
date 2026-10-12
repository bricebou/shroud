<?php

declare(strict_types=1);

namespace Shroud\Strategy\Nested;

use Shroud\Context;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\AbstractTextStrategy;
use Shroud\Strategy\RelayingObserverInterface;

/**
 * Transforms a JSON document key by key, and encodes it again.
 *
 * Every object stays an object, even empty or keyed 0, 1, 2…, at any depth, and a float keeps its
 * fraction. A value that is not JSON is refused: an old base that carries some declares a `Fallback`.
 */
final class Json extends AbstractTextStrategy implements RelayingObserverInterface
{
    private const int ENCODING = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

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
        try {
            $data = is_string($value) ? json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            return;
        }
        $this->content->observe($data, $context);
    }

    protected function transform(string $value, Context $context): string
    {
        try {
            $data = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidValueException($context->table, $context->column, 'invalid JSON: ' . $exception->getMessage(), $exception);
        }

        $result = $this->content->apply($data, $context);

        try {
            return json_encode(self::restoreObjects(json_decode($value, flags: JSON_THROW_ON_ERROR), $result), self::ENCODING);
        } catch (\JsonException $exception) {
            throw new InvalidValueException($context->table, $context->column, 'unencodable JSON: ' . $exception->getMessage(), $exception);
        }
    }

    /**
     * Gives the transformed data back the objects of the original document: decoded as arrays, an
     * empty object or one keyed 0, 1, 2… would be encoded again as a list.
     */
    private static function restoreObjects(mixed $original, mixed $result): mixed
    {
        if (! is_array($result)) {
            return $result;
        }
        $items = $original instanceof \stdClass ? get_object_vars($original) : (is_array($original) ? $original : []);
        foreach ($result as $key => $item) {
            $result[$key] = self::restoreObjects($items[$key] ?? null, $item);
        }

        return $original instanceof \stdClass ? (object) $result : $result;
    }
}
