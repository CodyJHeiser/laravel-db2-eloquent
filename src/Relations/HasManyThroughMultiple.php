<?php

namespace CodyJHeiser\Db2Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * HasManyThrough relationship with multiple column support for DB2.
 * DB2 doesn't support tuple IN syntax, so we use OR/AND conditions instead.
 */
class HasManyThroughMultiple extends HasManyThrough
{
    /**
     * The foreign keys on the through table.
     */
    protected array $firstKeys;

    /**
     * The foreign keys on the related table.
     */
    protected array $secondKeys;

    /**
     * The local keys on the parent table.
     */
    protected array $localKeys;

    /**
     * The local keys on the through table.
     */
    protected array $secondLocalKeys;

    /**
     * Columns to select from the related table.
     */
    protected ?array $selectColumns = null;

    /**
     * Create a new has many through relationship instance.
     */
    public function __construct(
        Builder $query,
        Model $farParent,
        Model $throughParent,
        array $firstKeys,
        array $secondKeys,
        array $localKeys,
        array $secondLocalKeys
    ) {
        $this->firstKeys = $firstKeys;
        $this->secondKeys = $secondKeys;
        $this->localKeys = $localKeys;
        $this->secondLocalKeys = $secondLocalKeys;

        // Parent constructor expects single keys, pass first ones
        parent::__construct(
            $query,
            $farParent,
            $throughParent,
            $firstKeys[0],
            $secondKeys[0],
            $localKeys[0],
            $secondLocalKeys[0]
        );
    }

    /**
     * Prepare the query builder for query execution.
     * Override to use select() instead of addSelect() so we can limit columns.
     */
    protected function prepareQueryBuilder($columns = ['*'])
    {
        $builder = $this->query->applyScopes();

        // Use select() instead of addSelect() to replace columns entirely
        return $builder->select(
            $this->shouldSelect($this->selectColumns ?? $builder->getQuery()->columns ?? $columns)
        );
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        $localValues = [];
        foreach ($this->localKeys as $localKey) {
            $localValues[] = $this->farParent->{$localKey};
        }

        $this->performJoin();

        if (static::$constraints) {
            $throughTable = $this->throughParent->getTable();

            foreach ($this->firstKeys as $i => $firstKey) {
                $this->query->where($throughTable . '.' . $firstKey, '=', $localValues[$i]);
            }
        }
    }

    /**
     * Set the join clause on the query.
     */
    protected function performJoin(?Builder $query = null)
    {
        $query = $query ?: $this->query;

        $throughTable = $this->throughParent->getTable();
        $relatedTable = $this->related->getTable();

        $query->join($throughTable, function ($join) use ($throughTable, $relatedTable) {
            foreach ($this->secondLocalKeys as $i => $secondLocalKey) {
                $join->on(
                    $relatedTable . '.' . $this->secondKeys[$i],
                    '=',
                    $throughTable . '.' . $secondLocalKey
                );
            }
        });
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models)
    {
        $throughTable = $this->throughParent->getTable();

        // Collect unique key combinations
        $keyCombinations = [];
        foreach ($models as $model) {
            $keys = [];
            foreach ($this->localKeys as $localKey) {
                $keys[] = $model->{$localKey};
            }
            $keyString = implode('|', $keys);
            $keyCombinations[$keyString] = $keys;
        }

        // Build OR conditions for each key combination
        $this->query->where(function ($query) use ($keyCombinations, $throughTable) {
            $first = true;
            foreach ($keyCombinations as $keys) {
                $method = $first ? 'where' : 'orWhere';
                $first = false;

                $query->{$method}(function ($q) use ($keys, $throughTable) {
                    foreach ($this->firstKeys as $i => $firstKey) {
                        $q->where($throughTable . '.' . $firstKey, '=', $keys[$i]);
                    }
                });
            }
        });
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, $relation)
    {
        // Build a dictionary keyed by through table's first keys (using aliased names)
        $dictionary = [];
        foreach ($results as $result) {
            $keys = [];
            foreach ($this->firstKeys as $firstKey) {
                // Try uppercase first (DB2), then lowercase (other databases)
                $keys[] = $result->{'LARAVEL_THROUGH_KEY_' . $firstKey}
                    ?? $result->{'laravel_through_key_' . $firstKey};
            }
            $keyString = implode('|', $keys);
            $dictionary[$keyString][] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $keys = [];
            foreach ($this->localKeys as $localKey) {
                $keys[] = $model->{$localKey};
            }
            $keyString = implode('|', $keys);

            $model->setRelation($relation, new Collection($dictionary[$keyString] ?? []));
        }

        return $models;
    }

    /**
     * Get the fully qualified first keys.
     */
    public function getQualifiedFirstKeyNames(): array
    {
        $throughTable = $this->throughParent->getTable();
        return array_map(fn($key) => $throughTable . '.' . $key, $this->firstKeys);
    }

    /**
     * Specify columns to select from the related table.
     * These will be preserved during eager loading.
     */
    public function withColumns(array $columns): static
    {
        $this->selectColumns = $columns;

        return $this;
    }

    /**
     * Get the columns that should be selected during eager loading.
     * Overrides parent to include all first keys with aliases.
     */
    protected function shouldSelect(array $columns = ['*']): array
    {
        $relatedTable = $this->related->getTable();

        if ($columns === ['*']) {
            $columns = [$relatedTable . '.*'];
        } else {
            // Qualify columns with table name if not already
            $columns = array_map(function ($col) use ($relatedTable) {
                if (str_contains($col, '.')) {
                    return $col;
                }
                return $relatedTable . '.' . $col;
            }, $columns);
        }

        $throughTable = $this->throughParent->getTable();

        // Add all first keys with aliases for matching
        foreach ($this->firstKeys as $key) {
            $columns[] = $throughTable . '.' . $key . ' as laravel_through_key_' . $key;
        }

        return array_unique($columns);
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        $query->select($columns);

        $farParentTable = $this->farParent->getTable();
        $throughTable = $this->throughParent->getTable();

        foreach ($this->firstKeys as $i => $firstKey) {
            $query->whereColumn(
                $farParentTable . '.' . $this->localKeys[$i],
                '=',
                $throughTable . '.' . $firstKey
            );
        }

        return $query;
    }
}
