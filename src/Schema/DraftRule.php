<?php

declare(strict_types=1);

namespace Shroud\Schema;

/**
 * Proposes a strategy for the columns whose name matches a pattern, when a draft is written.
 *
 * A platform adds its own rules to the generic ones of the draft: its secrets to clear, its
 * polymorphic link columns. The strategy is PHP code, as the draft writes it.
 */
final readonly class DraftRule
{
    /**
     * @param string $pattern a regular expression tried on the lowercased column name
     * @param string $strategy the code of the strategy, `new Clear()`
     * @param bool $review whether the proposal is uncertain and marked for review
     * @param ?string $sibling a column that the table must also carry for the rule to apply
     * @param list<class-string> $imports the classes the code names, to import in the draft file
     */
    public function __construct(
        public string $pattern,
        public string $strategy,
        public bool $review = false,
        public ?string $sibling = null,
        public array $imports = [],
    ) {
    }

    /**
     * Tells whether the rule applies to a column of a table.
     */
    public function matches(string $column, TableSchema $table): bool
    {
        return preg_match($this->pattern, strtolower($column)) === 1
            && ($this->sibling === null || $table->hasColumn($this->sibling));
    }
}
