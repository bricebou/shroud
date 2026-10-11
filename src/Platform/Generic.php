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
 * Treats the base as a generic relational base: no markup beyond HTML, no embedded reference,
 * no bare declaration, no frozen schema and no version.
 */
final class Generic implements PlatformInterface
{
    public function name(): string
    {
        return 'generic';
    }

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
        return new FrozenSchemas(__DIR__ . '/none', 'generic');
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
        throw new InvalidConfigurationException('the generic platform has no connection file');
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
