<?php

namespace CodyJHeiser\Db2Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsTo relationship with multiple column support for DB2.
 * DB2 doesn't support tuple IN syntax, so we use OR/AND conditions instead.
 */
class BelongsToMultiple extends BelongsTo
{
    /**
     * The foreign keys of the relationship.
     */
    protected array $foreignKeys;

    /**
     * The owner keys of the relationship.
     */
    protected array $ownerKeys;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(Builder $query, Model $child, array $foreignKeys, array $ownerKeys, string $relationName)
    {
        $this->foreignKeys = $foreignKeys;
        $this->ownerKeys = $ownerKeys;

        // Parent constructor expects single keys, pass first ones
        parent::__construct($query, $child, $foreignKeys[0], $ownerKeys[0], $relationName);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            foreach ($this->ownerKeys as $i => $ownerKey) {
                $this->query->where($table . '.' . $ownerKey, '=', $this->child->{$this->foreignKeys[$i]});
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     * Uses OR/AND conditions instead of tuple IN for DB2 compatibility.
     */
    public function addEagerConstraints(array $models)
    {
        $table = $this->related->getTable();

        // Collect unique key combinations
        $keyCombinations = [];
        foreach ($models as $model) {
            $keys = [];
            foreach ($this->foreignKeys as $foreignKey) {
                $keys[] = $model->{$foreignKey};
            }
            $keyString = implode('|', $keys);
            $keyCombinations[$keyString] = $keys;
        }

        // Build OR conditions for each key combination
        $this->query->where(function ($query) use ($keyCombinations, $table) {
            $first = true;
            foreach ($keyCombinations as $keys) {
                $method = $first ? 'where' : 'orWhere';
                $first = false;

                $query->{$method}(function ($q) use ($keys, $table) {
                    foreach ($this->ownerKeys as $i => $ownerKey) {
                        $q->where($table . '.' . $ownerKey, '=', $keys[$i]);
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
        // Build a dictionary keyed by owner key combination
        $dictionary = [];
        foreach ($results as $result) {
            $keys = [];
            foreach ($this->ownerKeys as $ownerKey) {
                $keys[] = $result->{$ownerKey};
            }
            $dictionary[implode('|', $keys)] = $result;
        }

        // Match to parents
        foreach ($models as $model) {
            $keys = [];
            foreach ($this->foreignKeys as $foreignKey) {
                $keys[] = $model->{$foreignKey};
            }
            $keyString = implode('|', $keys);

            if (isset($dictionary[$keyString])) {
                $model->setRelation($relation, $dictionary[$keyString]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        // Check if any foreign key is null
        foreach ($this->foreignKeys as $foreignKey) {
            if (is_null($this->child->{$foreignKey})) {
                return $this->getDefaultFor($this->child);
            }
        }

        return $this->query->first() ?: $this->getDefaultFor($this->child);
    }

    /**
     * Add the constraints for a relationship query.
     * Used by whereHas/has to build the EXISTS subquery.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->select($columns);

        $parentTable = $this->child->getTable();
        $relatedTable = $this->related->getTable();

        // Add all key constraints for multi-column relationship
        foreach ($this->foreignKeys as $i => $foreignKey) {
            $query->whereColumn(
                $parentTable . '.' . $foreignKey,
                '=',
                $relatedTable . '.' . $this->ownerKeys[$i]
            );
        }

        return $query;
    }
}
