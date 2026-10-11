<?php

declare(strict_types=1);

namespace Shroud\Strategy\Wrapper;

use Shroud\Context;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Exception\InvalidValueException;
use Shroud\Strategy\CompositeInterface;
use Shroud\Strategy\StrategyInterface;

/**
 * Replaces a hexadecimal digest by the digest of what another strategy returns.
 *
 * A column that stores the hash of another one, the md5 of an email for instance, keeps both
 * its shape and its relation: `new Hashed(new Email(), 'md5')`. A value that is not a digest of
 * the algorithm is refused, so that `Fallback` can handle columns mixing digests and plain values.
 * Salted password hashes (bcrypt, argon2) cannot keep a relation: declare them `Clear` or `Constant`.
 */
final readonly class Hashed implements CompositeInterface
{
    /**
     * @param string $algorithm any algorithm of `hash()`
     */
    public function __construct(
        private StrategyInterface $source,
        private string $algorithm = 'md5',
    ) {
        if (! in_array($algorithm, hash_algos(), strict: true)) {
            throw new InvalidConfigurationException(sprintf('unknown hash algorithm: %s', $algorithm));
        }
    }

    /**
     * Lets NULL and the empty string through, hashes the result of the source for a digest, refuses the rest.
     */
    public function apply(mixed $value, Context $context): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (! is_string($value) || ! $this->isDigest($value)) {
            throw new InvalidValueException($context->table, $context->column, sprintf('%s digest expected', $this->algorithm));
        }

        $source = $this->source->apply($value, $context);
        if (! is_string($source)) {
            throw new InvalidValueException($context->table, $context->column, 'the hashed strategy returned ' . get_debug_type($source));
        }
        $digest = hash($this->algorithm, $source);

        // An uppercase digest stays uppercase.
        return strtolower($value) !== $value ? strtoupper($digest) : $digest;
    }

    public function innerStrategies(): iterable
    {
        return [$this->source];
    }

    /**
     * Tells whether the value is shaped like a hexadecimal digest of the algorithm.
     */
    private function isDigest(string $value): bool
    {
        return ctype_xdigit($value) && strlen($value) === strlen(hash($this->algorithm, ''));
    }
}
