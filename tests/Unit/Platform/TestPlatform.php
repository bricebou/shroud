<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Platform;

use Shroud\Adapter\Pdo\ConnectionSettings;
use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Exception\InvalidConfigurationException;
use Shroud\Platform\PlatformInterface;
use Shroud\Schema\DraftRule;
use Shroud\Schema\FrozenSchemas;
use Shroud\Schema\Glossary\English;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Reference\RenumberedPolymorphicId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;

/**
 * A made-up platform for the tests: template tags `{{name}}` are markup, and `#type:12` references
 * the object 12 of a type, renumbered when the declaration renumbers that type. Its connection
 * file is `config/database.json`. Its bare
 * distribution is a table of people, frozen at versions 1.0.0 and 2.0.0 in `tests/fixtures/schema`.
 */
final class TestPlatform implements PlatformInterface
{
    public function name(): string
    {
        return 'test';
    }

    public function preservedMarkup(): array
    {
        return ['\{\{[a-z_]+\}\}', '#[a-z]+:\d+'];
    }

    public function renumberReferences(string $text, Context $context): string
    {
        return preg_replace_callback(
            '/#([a-z]+):(\d+)/',
            static fn (array $match): string => $context->declaration->isRenumbered($match[1])
                ? '#' . $match[1] . ':' . $context->generator->permutationFor($match[1])->apply((int) $match[2])
                : $match[0],
            $text,
        ) ?? $text;
    }

    public function bareDeclaration(): Declaration
    {
        return new Declaration([
            new Table('people', 'id_person', [
                'id_person' => new RenumberedId('person'),
                'name' => new FullName(),
                'email' => new Email(),
            ], ReferenceColumns::fromFixedType('person', 'id_person')),
        ]);
    }

    public function frozenSchemas(): FrozenSchemas
    {
        return new FrozenSchemas(__DIR__ . '/../../fixtures/schema', 'test');
    }

    public function readVersion(\PDO $pdo): ?string
    {
        return null;
    }

    public function connectionFile(): string
    {
        return 'config/database.json';
    }

    public function readConnection(string $path): ConnectionSettings
    {
        if (! is_file($path)) {
            throw new InvalidConfigurationException(sprintf('connection file not found: %s', $path));
        }
        $settings = json_decode((string) file_get_contents($path), associative: true);
        if (! is_array($settings) || ! is_string($settings['host'] ?? null) || ! is_string($settings['database'] ?? null) || ! is_string($settings['user'] ?? null)) {
            throw new InvalidConfigurationException(sprintf('%s does not hold the host, the database and the user', $path));
        }

        return new ConnectionSettings(
            $settings['host'],
            $settings['database'],
            $settings['user'],
            is_string($settings['password'] ?? null) ? $settings['password'] : null,
            is_string($settings['port'] ?? null) ? $settings['port'] : '',
        );
    }

    public function draftRules(): array
    {
        return [
            new DraftRule('/^object_id$/', "new RenumberedPolymorphicId('object_type')", sibling: 'object_type', imports: [RenumberedPolymorphicId::class]),
            new DraftRule('/^object_type$/', 'new Keep()', imports: [Keep::class]),
            new DraftRule('/^(remember_token|recovery_code)$/', 'new Clear()', imports: [Clear::class]),
        ];
    }

    public function glossaries(): array
    {
        return [new English()];
    }
}
