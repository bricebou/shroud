<?php

declare(strict_types=1);

namespace Shroud\Tests\Unit\Platform;

use Shroud\Context;
use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Platform\PlatformInterface;
use Shroud\Schema\FrozenSchemas;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Reference\RenumberedId;

/**
 * A made-up platform for the tests: template tags `{{name}}` are markup, and `#type:12` references
 * the object 12 of a type, renumbered when the declaration renumbers that type. Its bare
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
}
