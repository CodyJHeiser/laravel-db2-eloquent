<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;
use CodyJHeiser\Db2Eloquent\Builders\MappedBuilder;

/**
 * Trait for column name mapping functionality.
 * Maps DB column names to human-readable names for queries and output.
 */
trait HasColumnMapping
{
    /**
     * Column name mappings for output.
     * Override in child models to map DB columns to human-readable names.
     *
     * Format:
     *   'DB_COLUMN_NAME' => 'humanReadableName'
     */
    protected array $maps = [];

    /**
     * Reverse maps cache (humanReadableName => DB_COLUMN_NAME).
     */
    protected ?array $reverseMaps = null;

    /**
     * Whether to automatically apply maps when converting to array/JSON.
     * Set to false to disable auto-mapping.
     */
    protected bool $applyMapsOnOutput = true;

    /**
     * Whether to automatically select only mapped columns.
     * Set to false to select all columns by default.
     * @var bool
     */
    protected bool $autoSelectMapped = true;

    /**
     * Whether selectAll() was called - propagates to relationships.
     * @var bool
     */
    protected bool $useSelectAll = false;

    /**
     * Flag to skip mapping in getAttributes() when called from attributesToArray().
     * This ensures casts are applied correctly before mapping.
     * @var bool
     */
    protected bool $skipMappingInGetAttributes = false;

    /**
     * Default casts for common mapped column names.
     * Maps human-readable names to cast types.
     * @var array
     */
    protected static array $defaultMappedCasts = [
        'company_number' => 'integer',
    ];

    /**
     * Initialize the trait on model instantiation.
     * Adds default casts for mapped columns.
     */
    public function initializeHasColumnMapping(): void
    {
        // Add default casts for mapped columns
        foreach (static::$defaultMappedCasts as $mappedName => $castType) {
            $dbColumn = $this->getDbColumn($mappedName);
            // Only add if the column is actually mapped (not returned as-is)
            if ($dbColumn !== $mappedName && !isset($this->casts[$dbColumn])) {
                $this->casts[$dbColumn] = $castType;
            }
        }
    }

    /**
     * Initialize column mapping - registers auto-select global scope.
     */
    protected static function bootHasColumnMapping(): void
    {
        static::addGlobalScope('autoSelectMapped', function (Builder $query) {
            $model = $query->getModel();

            // Skip if selectAll() was called (propagated from parent)
            if ($model->useSelectAll) {
                $query->select('*');
                return;
            }

            // Skip if auto-select is disabled
            if (!$model->autoSelectMapped) {
                return;
            }

            // Skip if model has no maps
            if (!$model->hasMaps()) {
                return;
            }

            // Skip if columns already selected (user called select())
            $columns = $query->getQuery()->columns;
            if (!empty($columns)) {
                return;
            }

            // Apply selectMapped
            $query->selectMapped();
        });
    }

    /**
     * Create a new Eloquent query builder with column mapping support.
     */
    public function newEloquentBuilder($query): MappedBuilder
    {
        return new MappedBuilder($query);
    }

    /**
     * Scope to select all columns, bypassing auto-selectMapped.
     * Propagates to relationships via newInstance/newRelatedInstance.
     */
    #[Scope]
    protected function selectAll(Builder $query): void
    {
        $model = $query->getModel();
        $model->useSelectAll = true;

        $query->withoutGlobalScope('autoSelectMapped');
        $query->select('*');
    }

    /**
     * Scope to select only the columns defined in $maps.
     * Also includes extension mapped columns if extensions are joined.
     * Automatically applies to eager-loaded relations that extend Model.
     */
    #[Scope]
    protected function selectMapped(Builder $query): void
    {
        $columns = [];

        // Add base table mapped columns
        if (!empty($this->maps)) {
            $columns = array_keys($this->maps);
        }

        // Check if any extensions are joined and add their mapped columns
        $joins = $query->getQuery()->joins ?? [];
        foreach ($joins as $join) {
            $joinTable = $join->table;
            if (isset($this->extensions[$joinTable]['maps'])) {
                $extMaps = $this->extensions[$joinTable]['maps'];
                $columns = array_merge($columns, array_keys($extMaps));
            }
        }

        if (!empty($columns)) {
            $query->select($columns);
        }

        // Apply selectMapped to eager-loaded relations
        $eagerLoads = $query->getEagerLoads();
        foreach ($eagerLoads as $name => $constraints) {
            $query->with([$name => function ($q) use ($constraints) {
                // Apply existing constraints first
                if (is_callable($constraints)) {
                    $constraints($q);
                }
                // Apply selectMapped if the related model is an IBM model
                if (method_exists($q->getModel(), 'hasMaps') && $q->getModel()->hasMaps()) {
                    $q->selectMapped();
                }
            }]);
        }
    }

    /**
     * Check if model has column mappings defined.
     */
    public function hasMaps(): bool
    {
        return !empty($this->maps);
    }

    /**
     * Get the base table column mappings (DB => human).
     */
    public function getMaps(): array
    {
        return $this->maps;
    }

    /**
     * Get all column mappings including extensions (DB => human).
     */
    public function getAllMaps(): array
    {
        $allMaps = $this->maps;

        if (property_exists($this, 'extensions')) {
            foreach ($this->extensions as $config) {
                if (isset($config['maps'])) {
                    $allMaps = array_merge($allMaps, $config['maps']);
                }
            }
        }

        return $allMaps;
    }

    /**
     * Get reverse mappings (human => DB).
     * Uses all maps including extensions.
     */
    public function getReverseMaps(): array
    {
        if ($this->reverseMaps === null) {
            // Build reverse maps, keeping only the first DB column for each mapped name
            $this->reverseMaps = [];
            foreach ($this->getAllMaps() as $dbCol => $mappedName) {
                if (!isset($this->reverseMaps[$mappedName])) {
                    $this->reverseMaps[$mappedName] = $dbCol;
                }
            }
        }

        return $this->reverseMaps;
    }

    /**
     * Translate a mapped column name to its DB column name.
     * Returns the original name if no mapping exists.
     */
    public function getDbColumn(string $column): string
    {
        return $this->getReverseMaps()[$column] ?? $column;
    }

    /**
     * Translate a DB column name to its mapped name.
     * Returns the original name if no mapping exists.
     * Uses all maps including extensions.
     */
    public function getMappedColumn(string $column): string
    {
        return $this->getAllMaps()[$column] ?? $column;
    }

    /**
     * Apply column mappings to an array.
     * First mapping takes priority for duplicate mapped names.
     * Uses all maps including extensions.
     */
    protected function applyMaps(array $data): array
    {
        $allMaps = $this->getAllMaps();

        if (empty($allMaps)) {
            return $data;
        }

        $mapped = [];
        $usedMappedNames = [];

        foreach ($data as $key => $value) {
            $mappedKey = $allMaps[$key] ?? $key;

            // Skip if this mapped name was already used (first one wins)
            if (isset($usedMappedNames[$mappedKey])) {
                continue;
            }

            $mapped[$mappedKey] = $value;
            $usedMappedNames[$mappedKey] = true;
        }

        return $mapped;
    }

    /**
     * Convert attributes to array with mapped column names.
     */
    public function attributesToArray(): array
    {
        // Skip mapping in getAttributes() while parent processes casts
        $this->skipMappingInGetAttributes = true;
        $attributes = parent::attributesToArray();
        $this->skipMappingInGetAttributes = false;

        if ($this->applyMapsOnOutput && !empty($this->getAllMaps())) {
            return $this->applyMaps($attributes);
        }

        return $attributes;
    }

    /**
     * Get the raw attributes (unmapped) for internal use.
     */
    public function getRawAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get all attributes with mapping applied for display.
     * Mapping is skipped when called from attributesToArray() to preserve casts.
     */
    public function getAttributes(): array
    {
        $attrs = parent::getAttributes();

        // Apply mapping for direct calls (e.g., tinker display)
        // Skip when called from attributesToArray() to let casts work first
        if (!$this->skipMappingInGetAttributes && $this->applyMapsOnOutput && !empty($this->getAllMaps())) {
            return $this->applyMaps($attrs);
        }

        return $attrs;
    }

    /**
     * Get an attribute from the raw attributes array.
     * Override to use raw attributes, not mapped ones.
     */
    protected function getAttributeFromArray($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get an attribute - supports both mapped and DB column names.
     */
    public function getAttribute($key)
    {
        // Check if key exists directly in attributes
        if (array_key_exists($key, $this->attributes)) {
            return parent::getAttribute($key);
        }

        // If key is a mapped name, translate to DB column
        $dbKey = $this->getDbColumn($key);
        if (array_key_exists($dbKey, $this->attributes)) {
            return parent::getAttribute($dbKey);
        }

        // If key is a DB column, translate to mapped name
        $mappedKey = $this->getMappedColumn($key);
        if (array_key_exists($mappedKey, $this->attributes)) {
            return parent::getAttribute($mappedKey);
        }

        // Fall back to parent for accessors, relations, etc.
        return parent::getAttribute($key);
    }

    /**
     * Set an attribute - always stores with DB column name.
     */
    public function setAttribute($key, $value)
    {
        // Translate mapped name to DB column for storage
        $dbKey = $this->getDbColumn($key);
        return parent::setAttribute($dbKey, $value);
    }

    /**
     * Get the attributes that have been changed since last sync.
     * Uses raw DB column names for database operations.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!$this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync the original attributes with the current.
     * Uses raw attributes to ensure DB column names are preserved.
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Sync a single original attribute with its current value.
     * Uses raw attribute value to ensure DB column names are preserved.
     */
    public function syncOriginalAttribute($attribute)
    {
        $this->original[$attribute] = $this->attributes[$attribute] ?? null;

        return $this;
    }

    /**
     * Sync multiple original attributes with their current values.
     */
    public function syncOriginalAttributes($attributes)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        foreach ($attributes as $attribute) {
            $this->syncOriginalAttribute($attribute);
        }

        return $this;
    }

}
