<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;

/**
 * Trait for extension table functionality.
 * Allows joining and querying related extension tables.
 */
trait HasExtensions
{
    /**
     * Extension table configuration.
     * Override in child models.
     *
     * Format:
     *   'EXTENSION_TABLE_NAME' => [
     *       'join' => [
     *           'EXT_COLUMN' => 'BASE_COLUMN',
     *           'EXT_COMPANY' => 'BASE_COMPANY',
     *       ],
     *       'columns' => ['*'],  // Optional: specific columns
     *       'maps' => [],        // Optional: column mappings
     *   ]
     */
    protected array $extensions = [];

    /**
     * Extension data storage.
     */
    protected array $extensionData = [];

    /**
     * Get the effective extension table name, respecting testing mode.
     * Transforms schema prefix (R→T) when in testing mode.
     *
     * @param string $extTable The extension table (e.g., 'R60FSDTA.VARCUSTXT')
     * @param bool $isTestingMode Whether testing mode is active
     */
    protected function getEffectiveExtensionTable(string $extTable, bool $isTestingMode = false): string
    {
        if (!$isTestingMode) {
            return $extTable;
        }

        // Parse schema.table format
        if (str_contains($extTable, '.')) {
            [$schema, $table] = explode('.', $extTable, 2);
            // Swap R to T at start of schema (R60FILES → T60FILES, R60FSDTA → T60FSDTA)
            $testSchema = 'T' . substr($schema, 1);
            return "{$testSchema}.{$table}";
        }

        return $extTable;
    }

    /**
     * Detect if testing mode is active by checking the query's FROM clause.
     * This allows testing() to be called in any order relative to withExtensions().
     */
    protected function isTestingModeFromQuery(Builder $query): bool
    {
        $fromTable = $query->getQuery()->from;

        if ($fromTable && str_contains($fromTable, '.')) {
            $schema = explode('.', $fromTable)[0];
            // Testing schemas start with 'T' (T60FILES, T60FSDTA)
            return str_starts_with($schema, 'T');
        }

        // Fall back to model property
        return property_exists($this, 'useTestSchema') && $this->useTestSchema;
    }

    /**
     * Scope to automatically join extension tables.
     *
     * Note: If using testing(), call it BEFORE withExtensions() for correct behavior:
     *   Customer::testing()->withExtensions()->get()  // Correct
     *   Customer::withExtensions()->testing()->get()  // Extension tables won't use test schema
     */
    #[Scope]
    protected function withExtensions(Builder $query, ?array $only = null): void
    {
        $baseTable = $this->getTable();

        // Check if testing mode (set by testing() scope called before this)
        $isTestingMode = property_exists($this, 'useTestSchema') && $this->useTestSchema;

        // Check if user explicitly specified columns
        $hasUserColumns = !empty($query->getQuery()->columns);

        // Check if auto-select mapped is enabled
        $useAutoSelectMapped = property_exists($this, 'autoSelectMapped') && $this->autoSelectMapped;

        foreach ($this->extensions as $extTable => $config) {
            if ($only !== null && !in_array($extTable, $only)) {
                continue;
            }

            // Transform extension table name for testing mode
            $effectiveExtTable = $this->getEffectiveExtensionTable($extTable, $isTestingMode);

            $joinConditions = $config['join'] ?? [];
            $columns = $config['columns'] ?? ['*'];
            $extMaps = $config['maps'] ?? [];

            $query->leftJoin($effectiveExtTable, function ($join) use ($baseTable, $effectiveExtTable, $joinConditions) {
                foreach ($joinConditions as $extColumn => $baseColumn) {
                    $join->on("{$effectiveExtTable}.{$extColumn}", '=', "{$baseTable}.{$baseColumn}");
                }
            });

            // Add extension columns
            if ($hasUserColumns || $useAutoSelectMapped) {
                // User specified columns OR auto-select enabled - add only mapped extension columns
                if (!empty($extMaps)) {
                    $selectColumns = array_map(fn($col) => "{$effectiveExtTable}.{$col}", array_keys($extMaps));
                    $query->addSelect($selectColumns);
                }
            } else {
                // No auto-select, no user columns - use configured columns or *
                if ($columns === ['*']) {
                    $query->addSelect("{$effectiveExtTable}.*");
                } else {
                    $selectColumns = array_map(fn($col) => "{$effectiveExtTable}.{$col}", $columns);
                    $query->addSelect($selectColumns);
                }
            }
        }

        // Add base table columns
        if (!$hasUserColumns) {
            if ($useAutoSelectMapped) {
                // Auto-select enabled - add only mapped base columns
                if (!empty($this->maps)) {
                    $baseColumns = array_map(fn($col) => "{$baseTable}.{$col}", array_keys($this->maps));
                    $query->addSelect($baseColumns);
                }
            } else {
                // Auto-select disabled - add all base columns
                $query->addSelect("{$baseTable}.*");
            }
        }
    }

    /**
     * Scope to join a specific extension by name.
     */
    #[Scope]
    protected function withExtension(Builder $query, string $extTable): void
    {
        $this->withExtensions($query, [$extTable]);
    }

    /**
     * Load extension data separately (without join).
     */
    public function loadExtension(string $extTable): ?object
    {
        if (!isset($this->extensions[$extTable])) {
            return null;
        }

        // Transform extension table name for testing mode
        $isTestingMode = property_exists($this, 'useTestSchema') && $this->useTestSchema;
        $effectiveExtTable = $this->getEffectiveExtensionTable($extTable, $isTestingMode);

        $config = $this->extensions[$extTable];
        $joinConditions = $config['join'] ?? [];

        $query = $this->getConnection()->table($effectiveExtTable);

        foreach ($joinConditions as $extColumn => $baseColumn) {
            $query->where($extColumn, $this->getAttribute($baseColumn));
        }

        $this->extensionData[$extTable] = $query->first();

        return $this->extensionData[$extTable];
    }

    /**
     * Get loaded extension data.
     */
    public function getExtensionData(string $extTable): ?object
    {
        return $this->extensionData[$extTable] ?? null;
    }

    /**
     * Check if model has extensions defined.
     */
    public function hasExtensions(): bool
    {
        return !empty($this->extensions);
    }

    /**
     * Count extension records in the database for this model instance.
     */
    public function countExtensionRecords(string $extTable): int
    {
        if (!isset($this->extensions[$extTable])) {
            return 0;
        }

        // Transform extension table name for testing mode
        $isTestingMode = property_exists($this, 'useTestSchema') && $this->useTestSchema;
        $effectiveExtTable = $this->getEffectiveExtensionTable($extTable, $isTestingMode);

        $config = $this->extensions[$extTable];
        $joinConditions = $config['join'] ?? [];

        $query = $this->getConnection()->table($effectiveExtTable);

        foreach ($joinConditions as $extColumn => $baseColumn) {
            $rawAttributes = method_exists($this, 'getRawAttributes') ? $this->getRawAttributes() : $this->attributes;
            $query->where($extColumn, $rawAttributes[$baseColumn] ?? null);
        }

        return $query->count();
    }

    /**
     * Check if extension has any records in the database for this model instance.
     */
    public function hasExtensionRecords(string $extTable): bool
    {
        return $this->countExtensionRecords($extTable) > 0;
    }

    /**
     * Check if extension has multiple records (many-to-one relationship).
     */
    public function hasMultipleExtensionRecords(string $extTable): bool
    {
        return $this->countExtensionRecords($extTable) > 1;
    }

    /**
     * Scope to filter by extension record count.
     *
     * Usage:
     *   Item::whereHasExtension('R60FSDTA.VINITEMX')->get()           // has at least 1
     *   Item::whereHasExtension('R60FSDTA.VINITEMX', '>', 1)->get()   // has more than 1
     *   Item::whereHasExtension('R60FSDTA.VINITEMX', '=', 0)->get()   // has none
     */
    #[Scope]
    protected function whereHasExtension(Builder $query, string $extTable, string $operator = '>=', int $count = 1): void
    {
        if (!isset($this->extensions[$extTable])) {
            return;
        }

        // Transform extension table name for testing mode
        $isTestingMode = $this->isTestingModeFromQuery($query);
        $effectiveExtTable = $this->getEffectiveExtensionTable($extTable, $isTestingMode);

        $baseTable = $this->getTable();
        $config = $this->extensions[$extTable];
        $joinConditions = $config['join'] ?? [];

        $subquery = $this->getConnection()->table($effectiveExtTable)
            ->selectRaw('COUNT(*)');

        foreach ($joinConditions as $extColumn => $baseColumn) {
            $subquery->whereColumn("{$effectiveExtTable}.{$extColumn}", "{$baseTable}.{$baseColumn}");
        }

        $query->where($subquery, $operator, $count);
    }

    /**
     * Scope to filter models that have no extension records.
     */
    #[Scope]
    protected function whereDoesntHaveExtension(Builder $query, string $extTable): void
    {
        $this->whereHasExtension($query, $extTable, '=', 0);
    }

    /**
     * Scope to filter by extension and join it in one call.
     *
     * Usage:
     *   Item::withWhereHasExtension('R60FSDTA.VINITEMX')->get()
     *   Item::withWhereHasExtension('R60FSDTA.VINITEMX', '>', 1)->get()
     */
    #[Scope]
    protected function withWhereHasExtension(Builder $query, string $extTable, string $operator = '>=', int $count = 1): void
    {
        $this->whereHasExtension($query, $extTable, $operator, $count);
        $this->withExtension($query, $extTable);
    }
}
