<?php

namespace CodyJHeiser\Db2Eloquent\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Database\Query\Expression;

class MappedBuilder extends Builder
{
    /**
     * Track select aliases for case normalization.
     * Maps UPPERCASE => original_case for aliases used in select/addSelect.
     */
    protected array $selectAliases = [];

    /**
     * Check if a value is a subquery or expression (should not be translated).
     */
    protected function isSubqueryOrExpression($value): bool
    {
        return $value instanceof Builder
            || $value instanceof \Illuminate\Database\Query\Builder
            || $value instanceof Expression;
    }

    /**
     * Track an alias for case normalization.
     */
    protected function trackAlias(string $alias): void
    {
        $this->selectAliases[strtoupper($alias)] = $alias;
    }

    /**
     * Get the tracked select aliases.
     */
    public function getSelectAliases(): array
    {
        return $this->selectAliases;
    }

    /**
     * Normalize attribute keys from DB2 uppercase to original aliases.
     */
    protected function normalizeAttributeKeys(array $attributes): array
    {
        if (empty($this->selectAliases)) {
            return $attributes;
        }

        $normalized = [];
        foreach ($attributes as $key => $value) {
            $upperKey = strtoupper($key);
            if (isset($this->selectAliases[$upperKey])) {
                $normalized[$this->selectAliases[$upperKey]] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Get the hydrated models without eager loading.
     * Overridden to normalize attribute keys from DB2 uppercase.
     */
    public function getModels($columns = ['*'])
    {
        $results = $this->query->get($columns);

        // Normalize attribute keys if we have tracked aliases
        if (!empty($this->selectAliases)) {
            $results = $results->map(function ($item) {
                return (object) $this->normalizeAttributeKeys((array) $item);
            });
        }

        return $this->model->hydrate($results->all())->all();
    }

    /**
     * Get the reverse maps from the model.
     */
    protected function getReverseMaps(): array
    {
        return $this->model->getReverseMaps();
    }

    /**
     * Translate a column name using the model's maps.
     */
    protected function translateColumn(string $column): string
    {
        return $this->getReverseMaps()[$column] ?? $column;
    }

    /**
     * Translate column in a potentially qualified column name (table.column).
     * Handles arrays for multi-column relationships.
     */
    protected function translateQualifiedColumn(string|array $column): string|array
    {
        // Handle arrays (from multi-column relationships)
        if (is_array($column)) {
            return array_map(fn($col) => $this->translateQualifiedColumn($col), $column);
        }

        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $parts[count($parts) - 1] = $this->translateColumn($parts[count($parts) - 1]);
            return implode('.', $parts);
        }

        return $this->translateColumn($column);
    }

    /**
     * @inheritdoc
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            $column = $this->translateQualifiedColumn($column);
        }

        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * @inheritdoc
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (is_string($column)) {
            $column = $this->translateQualifiedColumn($column);
        }

        return parent::orWhere($column, $operator, $value);
    }

    /**
     * @inheritdoc
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        return parent::whereIn($this->translateQualifiedColumn($column), $values, $boolean, $not);
    }

    /**
     * @inheritdoc
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return parent::whereNotIn($this->translateQualifiedColumn($column), $values, $boolean);
    }

    /**
     * @inheritdoc
     */
    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        if (is_string($columns)) {
            $columns = $this->translateQualifiedColumn($columns);
        } elseif (is_array($columns)) {
            $columns = array_map(fn($col) => $this->translateQualifiedColumn($col), $columns);
        }

        return parent::whereNull($columns, $boolean, $not);
    }

    /**
     * @inheritdoc
     */
    public function whereNotNull($columns, $boolean = 'and')
    {
        if (is_string($columns)) {
            $columns = $this->translateQualifiedColumn($columns);
        } elseif (is_array($columns)) {
            $columns = array_map(fn($col) => $this->translateQualifiedColumn($col), $columns);
        }

        return parent::whereNotNull($columns, $boolean);
    }

    /**
     * @inheritdoc
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        return parent::whereBetween($this->translateQualifiedColumn($column), $values, $boolean, $not);
    }

    /**
     * @inheritdoc
     */
    public function orderBy($column, $direction = 'asc')
    {
        return parent::orderBy($this->translateQualifiedColumn($column), $direction);
    }

    /**
     * @inheritdoc
     */
    public function orderByDesc($column)
    {
        return parent::orderByDesc($this->translateQualifiedColumn($column));
    }

    /**
     * @inheritdoc
     */
    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $translated = [];

        foreach ($columns as $key => $col) {
            // Track string keys as aliases for case normalization
            if (is_string($key)) {
                $this->trackAlias($key);
            }

            if ($this->isSubqueryOrExpression($col)) {
                // Subqueries and expressions pass through unchanged
                $translated[$key] = $col;
            } elseif (is_string($col)) {
                // Translate string column names
                $translated[$key] = $this->translateQualifiedColumn($col);
            } else {
                // Unknown type, pass through
                $translated[$key] = $col;
            }
        }

        return parent::select($translated);
    }

    /**
     * @inheritdoc
     */
    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();
        $translated = [];

        foreach ($columns as $key => $col) {
            // Track string keys as aliases for case normalization
            if (is_string($key)) {
                $this->trackAlias($key);
            }

            if ($this->isSubqueryOrExpression($col)) {
                // Subqueries and expressions pass through unchanged
                $translated[$key] = $col;
            } elseif (is_string($col)) {
                // Translate string column names
                $translated[$key] = $this->translateQualifiedColumn($col);
            } else {
                // Unknown type, pass through
                $translated[$key] = $col;
            }
        }

        return parent::addSelect($translated);
    }

    /**
     * @inheritdoc
     */
    public function groupBy(...$groups)
    {
        $translated = array_map(fn($col) => $this->translateQualifiedColumn($col), $groups);

        return parent::groupBy(...$translated);
    }

    /**
     * @inheritdoc
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        return parent::having($this->translateQualifiedColumn($column), $operator, $value, $boolean);
    }

    /**
     * @inheritdoc
     */
    public function pluck($column, $key = null)
    {
        $column = $this->translateQualifiedColumn($column);
        $key = $key ? $this->translateQualifiedColumn($key) : null;

        return parent::pluck($column, $key);
    }

    /**
     * @inheritdoc
     */
    public function value($column)
    {
        return parent::value($this->translateQualifiedColumn($column));
    }

    /**
     * @inheritdoc
     */
    public function update(array $values)
    {
        $translated = [];
        foreach ($values as $column => $value) {
            $translated[$this->translateColumn($column)] = $value;
        }

        return parent::update($translated);
    }

    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        // Handle both single insert and batch insert
        if (isset($values[0]) && is_array($values[0])) {
            // Batch insert
            $translated = [];
            foreach ($values as $row) {
                $translatedRow = [];
                foreach ($row as $column => $value) {
                    $translatedRow[$this->translateColumn($column)] = $value;
                }
                $translated[] = $translatedRow;
            }
            return parent::insert($translated);
        }

        // Single insert
        $translated = [];
        foreach ($values as $column => $value) {
            $translated[$this->translateColumn($column)] = $value;
        }

        return parent::insert($translated);
    }
}
