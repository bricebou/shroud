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
 * An empty object stays an object and a float keeps its fraction. A value that is not JSON is
 * refused: an old base that carries some declares a `Fallback`.
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
        // Decoded as an array, an empty object would come back as `[]`.
        if ($result === [] && str_starts_with(ltrim($value), '{')) {
            return '{}';
        }

        try {
            return json_encode($result, self::ENCODING);
        } catch (\JsonException $exception) {
            throw new InvalidValueException($context->table, $context->column, 'unencodable JSON: ' . $exception->getMessage(), $exception);
        }
    }
}
