<?php

namespace CodyJHeiser\Db2Eloquent\Builders;

use Illuminate\Database\Eloquent\Builder;

class MappedBuilder extends Builder
{
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
        $translated = array_map(fn($col) => $this->translateQualifiedColumn($col), $columns);

        return parent::select($translated);
    }

    /**
     * @inheritdoc
     */
    public function addSelect($column)
    {
        $columns = is_array($column) ? $column : func_get_args();
        $translated = array_map(fn($col) => $this->translateQualifiedColumn($col), $columns);

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
