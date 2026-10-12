<?php

declare(strict_types=1);

namespace Shroud\Platform;

use Shroud\Adapter\Pdo\ConnectionSettings;
use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Schema\FrozenSchemas;
use Shroud\Schema\Glossary\English;

/**
 * Grounds a platform on neutral answers, so that a platform package writes only what it knows.
 *
 * No markup beyond HTML, no embedded reference, an empty bare declaration, no frozen schema, no
 * version, no connection file, no draft rule, and the English glossary. A method added to the
 * contract later receives a neutral answer here, and the packages built on this class keep working.
 */
abstract readonly class AbstractPlatform implements PlatformInterface
{
    public function preservedMarkup(): array
    {
        return [];
    }

    public function renumberReferences(string $text, Context $context): string
    {
        return $text;
    }

    public function bareDeclaration(): Declaration
    {
        return new Declaration([]);
    }

    public function frozenSchemas(): FrozenSchemas
    {
        return new FrozenSchemas(__DIR__ . '/none', $this->name());
    }

    public function readVersion(\PDO $pdo): ?string
    {
        return null;
    }

    public function connectionFile(): ?string
    {
        return null;
    }

    public function readConnection(string $path): ConnectionSettings
    {
        throw new InvalidConfigurationException(sprintf('the %s platform has no connection file', $this->name()));
    }

    public function draftRules(): array
    {
        return [];
    }

    public function glossaries(): array
    {
        return [new English()];
    }
}
