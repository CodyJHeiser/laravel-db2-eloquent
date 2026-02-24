<?php

namespace CodyJHeiser\Db2Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as BaseCollection;

/**
 * BelongsToMany relationship with multiple column support for DB2.
 * DB2 doesn't support tuple IN syntax, so we use OR/AND conditions instead.
 */
class BelongsToManyMultiple extends BelongsToMany
{
    /**
     * The foreign pivot keys (local model's keys in pivot table).
     */
    protected array $foreignPivotKeys;

    /**
     * The related pivot keys (related model's keys in pivot table).
     */
    protected array $relatedPivotKeys;

    /**
     * The parent model's local key columns.
     */
    protected array $parentKeys;

    /**
     * The related model's key columns.
     */
    protected array $relatedKeys;

    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(
        Builder $query,
        Model $parent,
        string $table,
        array $foreignPivotKeys,
        array $relatedPivotKeys,
        array $parentKeys,
        array $relatedKeys,
        ?string $relationName = null
    ) {
        $this->foreignPivotKeys = $foreignPivotKeys;
        $this->relatedPivotKeys = $relatedPivotKeys;
        $this->parentKeys = $parentKeys;
        $this->relatedKeys = $relatedKeys;

        // Parent constructor expects single keys, pass first ones
        parent::__construct(
            $query,
            $parent,
            $table,
            $foreignPivotKeys[0],
            $relatedPivotKeys[0],
            $parentKeys[0],
            $relatedKeys[0],
            $relationName
        );
    }

    /**
     * Set the join clause for the relation query.
     * Uses multiple ON conditions for composite keys.
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        $relatedTable = $this->related->getTable();

        $query->join($this->table, function ($join) use ($relatedTable) {
            foreach ($this->relatedKeys as $i => $relatedKey) {
                $join->on(
                    $relatedTable . '.' . $relatedKey,
                    '=',
                    $this->table . '.' . $this->relatedPivotKeys[$i]
                );
            }
        });

        return $this;
    }

    /**
     * Set the where clause for the relation query.
     */
    protected function addWhereConstraints()
    {
        foreach ($this->foreignPivotKeys as $i => $foreignPivotKey) {
            $this->query->where(
                $this->qualifyPivotColumn($foreignPivotKey),
                '=',
                $this->parent->{$this->parentKeys[$i]}
            );
        }

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     * Uses OR/AND conditions instead of tuple IN for DB2 compatibility.
     */
    public function addEagerConstraints(array $models)
    {
        // Collect unique key combinations
        $keyCombinations = [];
        foreach ($models as $model) {
            $keys = [];
            foreach ($this->parentKeys as $parentKey) {
                $keys[] = $model->{$parentKey};
            }
            $keyString = implode('|', $keys);
            $keyCombinations[$keyString] = $keys;
        }

        // Build OR conditions for each key combination
        $this->query->where(function ($query) use ($keyCombinations) {
            $first = true;
            foreach ($keyCombinations as $keys) {
                $method = $first ? 'where' : 'orWhere';
                $first = false;

                $query->{$method}(function ($q) use ($keys) {
                    foreach ($this->foreignPivotKeys as $i => $foreignPivotKey) {
                        $q->where($this->qualifyPivotColumn($foreignPivotKey), '=', $keys[$i]);
                    }
                });
            }
        });
    }

    /**
     * Build model dictionary keyed by the relation's composite foreign key.
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];

        foreach ($results as $result) {
            $keys = [];
            foreach ($this->foreignPivotKeys as $foreignPivotKey) {
                $keys[] = $result->{$this->accessor}->{$foreignPivotKey};
            }
            $keyString = implode('|', $keys);
            $dictionary[$keyString][] = $result;
        }

        return $dictionary;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $keys = [];
            foreach ($this->parentKeys as $parentKey) {
                $keys[] = $this->getDictionaryKey($model->{$parentKey});
            }
            $keyString = implode('|', $keys);

            if (isset($dictionary[$keyString])) {
                $model->setRelation(
                    $relation,
                    $this->related->newCollection($dictionary[$keyString])
                );
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        foreach ($this->parentKeys as $parentKey) {
            if (is_null($this->parent->{$parentKey})) {
                return $this->related->newCollection();
            }
        }

        return $this->get();
    }

    /**
     * Get the pivot columns for the relation.
     * "pivot_" is prefixed at each column for easy removal later.
     */
    protected function aliasedPivotColumns()
    {
        return (new BaseCollection([
            ...$this->foreignPivotKeys,
            ...$this->relatedPivotKeys,
            ...$this->pivotColumns,
        ]))
            ->map(fn ($column) => $this->qualifyPivotColumn($column) . ' as pivot_' . $column)
            ->unique()
            ->all();
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        $this->performJoin($query);

        $query->select($columns);

        $parentTable = $this->parent->getTable();

        foreach ($this->foreignPivotKeys as $i => $foreignPivotKey) {
            $query->whereColumn(
                $parentTable . '.' . $this->parentKeys[$i],
                '=',
                $this->qualifyPivotColumn($foreignPivotKey)
            );
        }

        return $query;
    }
}
