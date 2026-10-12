<?php

declare(strict_types=1);

namespace Shroud\Schema;

use Shroud\Declaration\Declaration;
use Shroud\Declaration\ReferenceColumns;
use Shroud\Declaration\Table;
use Shroud\Platform\PlatformInterface;
use Shroud\Schema\Glossary\GlossaryInterface;
use Shroud\Strategy\Nested\ByColumn;
use Shroud\Strategy\Nested\PerKey;
use Shroud\Strategy\Nested\Serialized;
use Shroud\Strategy\Pseudonym\City;
use Shroud\Strategy\Pseudonym\Email;
use Shroud\Strategy\Pseudonym\FirstName;
use Shroud\Strategy\Pseudonym\FullName;
use Shroud\Strategy\Pseudonym\LastName;
use Shroud\Strategy\Pseudonym\Login;
use Shroud\Strategy\Pseudonym\Organization;
use Shroud\Strategy\Pseudonym\PostalCode;
use Shroud\Strategy\Pseudonym\Street;
use Shroud\Strategy\Pseudonym\Url;
use Shroud\Strategy\Reference\RenumberedId;
use Shroud\Strategy\Value\Clear;
use Shroud\Strategy\Value\Keep;
use Shroud\Strategy\Value\ShapedDigits;
use Shroud\Strategy\Value\ShapedText;
use Shroud\Strategy\Value\ShiftedDate;
use Shroud\Strategy\Wrapper\Fallback;

/**
 * Proposes, as PHP code, the declaration of what a base carries and a declaration ignores.
 *
 * Each column is classified by the rules of the platform, then by its id shape, then by the
 * glossaries that name the columns, then by its SQL type; an uncertain proposal is marked
 * `@todo review`. The draft is written as a
 * PHP file that returns a function: it receives the declaration of the project and returns it
 * completed. It is read before it serves, and never replaces a classification made by hand.
 */
final readonly class Draft
{
    private const string REVIEW = '@todo review';

    /**
     * The classes the draft may name, to write its imports.
     *
     * @var array<string, class-string>
     */
    private const array CLASSES = [
        'Clear' => Clear::class,
        'Keep' => Keep::class,
        'ShapedDigits' => ShapedDigits::class,
        'ShapedText' => ShapedText::class,
        'ShiftedDate' => ShiftedDate::class,
        'City' => City::class,
        'Email' => Email::class,
        'FirstName' => FirstName::class,
        'FullName' => FullName::class,
        'LastName' => LastName::class,
        'Login' => Login::class,
        'Organization' => Organization::class,
        'PostalCode' => PostalCode::class,
        'Street' => Street::class,
        'Url' => Url::class,
        'RenumberedId' => RenumberedId::class,
        'ByColumn' => ByColumn::class,
        'PerKey' => PerKey::class,
        'Serialized' => Serialized::class,
        'Fallback' => Fallback::class,
        'Declaration' => Declaration::class,
        'ReferenceColumns' => ReferenceColumns::class,
        'Table' => Table::class,
    ];

    /**
     * The strategies that draw no pseudonym: a table made of them only needs no reference.
     */
    private const array IMPERSONAL = ['Keep', 'Clear', 'Constant', 'ShapedText', 'ShapedDigits', 'ShiftedDate', 'RenumberedId', 'RenumberedPolymorphicId', 'EmbeddedReferences', 'ByColumn', 'Fallback'];

    /**
     * The SQL types of numbers, which take no text strategy.
     */
    private const array NUMERIC_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'decimal', 'numeric', 'float', 'double', 'real', 'bit', 'boolean', 'bool', 'year'];

    /**
     * The SQL types of dates, which take no text strategy but a shift.
     */
    private const array DATE_TYPES = ['date', 'datetime', 'timestamp', 'time'];

    /**
     * @var list<GlossaryInterface>
     */
    private array $glossaries;

    /**
     * @param GlossaryInterface ...$glossaries the glossaries tried in order, those of the platform when none is given
     */
    public function __construct(
        private PlatformInterface $platform,
        GlossaryInterface ...$glossaries,
    ) {
        $this->glossaries = $glossaries === [] ? $platform->glossaries() : array_values($glossaries);
    }

    /**
     * Returns the PHP file of the draft: a function that completes the declaration it receives.
     *
     * The columns specific to the site and those the bare declaration does not declare for this
     * version of the platform join table by table, since `Declaration::with()` replaces a table
     * by its name: two completions of the same table would overwrite each other.
     */
    public function file(Coverage $coverage, Schema $schema, Declaration $declaration, string $date): string
    {
        $baseline = $coverage->baseline;
        $platformNote = $baseline instanceof Baseline
            ? sprintf('%s %s, not declared by the bare declaration', $baseline->platform, $baseline->version)
            : 'not declared by the bare declaration';

        $parts = [];
        foreach ($coverage->undeclaredTables as $table) {
            $parts[] = $this->table($schema->getTable($table), $declaration, 4);
        }
        foreach ($coverage->platformUndeclaredTables as $table) {
            $parts[] = '    // ' . $platformNote . "\n" . $this->table($schema->getTable($table), $declaration, 4);
        }
        $byTable = [];
        foreach ($coverage->undeclaredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $byTable[$table][$column] = '';
            }
        }
        foreach ($coverage->platformUndeclaredColumns as $table => $columns) {
            foreach ($columns as $column) {
                $byTable[$table][$column] = $platformNote;
            }
        }
        foreach ($byTable as $table => $notes) {
            $parts[] = sprintf(
                "    \$declaration->getTable(%s)->withColumns([\n%s\n    ]),",
                $this->quote($table),
                $this->columns($schema->getTable($table), array_keys($notes), $declaration, 8, $notes),
            );
        }
        $body = implode("\n", $parts);

        $uses = [];
        foreach (self::CLASSES as $class => $fqcn) {
            if ($class === 'Declaration' || preg_match('/\b' . $class . '\b/', $body) === 1) {
                $uses[] = $fqcn;
            }
        }
        foreach ($this->platform->draftRules() as $rule) {
            if (str_contains($body, $rule->strategy)) {
                array_push($uses, ...$rule->imports);
            }
        }
        $uses = array_unique($uses);
        sort($uses);

        $removals = [];
        foreach ($coverage->missingTables as $table) {
            $removals[] = sprintf(' *     %s (whole table)', $table);
        }
        foreach ($coverage->missingColumns as $table => $columns) {
            $removals[] = sprintf(' *     %s: %s', $table, implode(', ', $columns));
        }

        return sprintf(
            "<?php\n\n/**\n * Declaration draft suggested by shroud-diff on %s%s.\n *\n"
            . " * This file returns a function that receives the declaration of the site and returns it completed.\n"
            . " * Read it column by column, the todos first, then fold it into shroud.php: either by copying\n"
            . " * the tables and columns below into it, or by having it apply this file:\n"
            . " *     \$declaration = (require __DIR__ . '/shroud.suggestions.php')(\$declaration);\n%s */\n\n"
            . "declare(strict_types=1);\n\n%s\n\nreturn static fn (Declaration \$declaration): Declaration => \$declaration->with(\n%s\n);\n",
            $date,
            $baseline instanceof Baseline ? sprintf(' for %s %s', $baseline->platform, $baseline->version) : '',
            $removals === [] ? '' : " *\n * @todo Remove from the declaration, named without existing in the base:\n" . implode("\n", $removals) . "\n",
            implode("\n", array_map(static fn (string $use): string => 'use ' . $use . ';', $uses)),
            $body,
        );
    }

    /**
     * Returns the code of a whole table, ready to paste into a declaration.
     */
    public function table(TableSchema $table, Declaration $declaration, int $indent = 0): string
    {
        $pad = str_repeat(' ', $indent);
        $lines = [];
        $personal = false;
        foreach ($table->columns as $column => $type) {
            $lines[] = $pad . '    ' . $this->columnLine($table, $column, $type, $declaration, $personal);
        }
        $primaryKey = count($table->primaryKey) === 1
            ? $this->quote($table->primaryKey[0])
            : '[' . implode(', ', array_map($this->quote(...), $table->primaryKey)) . ']';
        $code = sprintf("%snew Table(%s, %s, [\n%s\n%s]", $pad, $this->quote($table->name), $primaryKey, implode("\n", $lines), $pad);

        // A reference only makes sense when a pseudonym is drawn; the type is the one a simple
        // primary key `id_xxx` or `xxx_id` names.
        $reference = $this->reference($table);
        if ($personal && $reference !== null) {
            $code .= ', ' . $reference;
        }

        return $code . '),';
    }

    /**
     * Returns the code of the given columns of a table only, one line each, to insert into its declaration.
     *
     * @param list<string> $columns
     * @param array<string, string> $notes a comment to attach to some columns
     */
    public function columns(TableSchema $table, array $columns, Declaration $declaration, int $indent = 4, array $notes = []): string
    {
        $pad = str_repeat(' ', $indent);
        $lines = [];
        $personal = false;
        foreach ($columns as $column) {
            $lines[] = $pad . $this->columnLine($table, $column, $table->columns[$column] ?? '', $declaration, $personal, $notes[$column] ?? '');
        }

        return implode("\n", $lines);
    }

    /**
     * Returns the line of code of a column, and notes whether its strategy draws a pseudonym.
     */
    private function columnLine(TableSchema $table, string $column, string $type, Declaration $declaration, bool &$personal, string $note = ''): string
    {
        [$strategy, $review] = $this->propose($table, $column, $type, $declaration);
        $class = (string) preg_replace('/^new |\(.*$/', '', $strategy);
        $personal = $personal || ! in_array($class, self::IMPERSONAL, strict: true);

        $comments = array_filter([$review ? self::REVIEW : '', $note], static fn (string $comment): bool => $comment !== '');

        return sprintf('%s => %s,%s', $this->quote($column), $strategy, $comments === [] ? '' : ' // ' . implode('; ', $comments));
    }

    /**
     * Proposes a strategy for a column: the rules of the platform, then the ids, the key/value pairs,
     * the glossaries, then the type; the SQL type of a number or a date always has the last word.
     *
     * @return array{string, bool} the code of the strategy, and whether it is to review
     */
    private function propose(TableSchema $table, string $column, string $sqlType, Declaration $declaration): array
    {
        foreach ($this->platform->draftRules() as $rule) {
            if ($rule->matches($column, $table)) {
                return [$rule->strategy, $rule->review];
            }
        }

        $name = strtolower($column);
        if (preg_match('/^id_(.+)$|^(.+)_id$/', $name, $match) === 1) {
            $type = $match[1] !== '' ? $match[1] : $match[2];

            return $declaration->isRenumbered($type) ? [sprintf('new RenumberedId(%s)', self::quote($type)), false] : ['new Keep()', false];
        }

        // The value of a key/value table, `meta_value` beside `meta_key`: its strategy depends on the key.
        if (preg_match('/^(.*)value$/', $name, $match) === 1) {
            foreach (['key', 'name'] as $suffix) {
                foreach ([$match[1] . $suffix, $match[1] . '_' . $suffix] as $key) {
                    if ($key !== $suffix && $table->hasColumn($key)) {
                        return [sprintf('new ByColumn(%s, [], new Fallback(new Serialized(new PerKey([], new Keep())), new Keep()))', self::quote($key)), true];
                    }
                }
            }
        }

        $base = strtolower((string) preg_replace('/\(.*$/', '', $sqlType));
        foreach ($this->namesToTry($table, $name) as $candidate) {
            foreach ($this->glossaries as $glossary) {
                foreach ($glossary->rules() as $rule) {
                    if ($rule->matches($candidate, $table)) {
                        return $this->fitType($rule->strategy, $rule->review, $base);
                    }
                }
            }
        }

        // Then the type: a number or a date is kept, a free text is to review.
        if (in_array($base, [...self::NUMERIC_TYPES, ...self::DATE_TYPES, 'enum', 'set'], strict: true)) {
            return ['new Keep()', false];
        }
        if (in_array($base, ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'], strict: true)) {
            return ['new ShapedText()', true];
        }

        return ['new Keep()', true];
    }

    /**
     * Lists the names the glossaries try: the name without the prefix that repeats the table, the surer hint, then the column name.
     *
     * `user_email` in `wp_users` is tried as `email` first, `comment_author` in `wp_comments` as `author`;
     * `post_name` in `wp_posts` is not tried as `name`.
     *
     * @return list<string>
     */
    private function namesToTry(TableSchema $table, string $name): array
    {
        $segments = explode('_', strtolower($table->name));
        $singular = $this->singular(end($segments));
        $prefix = $singular . '_';
        // `post_name` is the name of the post, its slug, not that of a person: a bare `name` is not tried.
        if ($singular !== '' && str_starts_with($name, $prefix) && strlen($name) > strlen($prefix) && $name !== $prefix . 'name') {
            return [substr($name, strlen($prefix)), $name];
        }

        return [$name];
    }

    /**
     * Keeps a proposal that fits the SQL type, or falls back to Keep: a number takes no text strategy, a date only a shift.
     *
     * @return array{string, bool}
     */
    private function fitType(string $strategy, bool $review, string $sqlType): array
    {
        $class = (string) preg_replace('/^new |\(.*$/', '', $strategy);
        $fits = match (true) {
            in_array($sqlType, self::NUMERIC_TYPES, strict: true) => in_array($class, ['Keep', 'Clear', 'RenumberedId'], strict: true),
            in_array($sqlType, self::DATE_TYPES, strict: true) => in_array($class, ['Keep', 'Clear', 'ShiftedDate'], strict: true),
            default => true,
        };

        return $fits ? [$strategy, $review] : ['new Keep()', false];
    }

    /**
     * Turns the last segment of a table name into a singular: `users` into `user`, `addresses` into `address`.
     */
    private function singular(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'ies') => substr($word, 0, -3) . 'y',
            str_ends_with($word, 'sses') => substr($word, 0, -2),
            str_ends_with($word, 's') && ! str_ends_with($word, 'ss') => substr($word, 0, -1),
            default => $word,
        };
    }

    /**
     * Returns the code of the reference of a table with a simple primary key, or nothing.
     *
     * The type is the one `id_xxx` or `xxx_id` names; for a key named `id`, the singular of the last
     * segment of the table name, `user` for `wp_users`.
     */
    private function reference(TableSchema $table): ?string
    {
        if (count($table->primaryKey) !== 1) {
            return null;
        }
        $key = strtolower($table->primaryKey[0]);
        if (preg_match('/^id_(.+)$|^(.+)_id$/', $key, $match) === 1) {
            $type = $match[1] !== '' ? $match[1] : $match[2];
        } elseif ($key === 'id') {
            $segments = explode('_', strtolower($table->name));
            $type = $this->singular(end($segments));
        } else {
            return null;
        }

        return sprintf('ReferenceColumns::fromFixedType(%s, %s)', $this->quote($type), $this->quote($table->primaryKey[0]));
    }

    private function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
