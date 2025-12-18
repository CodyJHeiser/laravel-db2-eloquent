<?php

namespace CodyJHeiser\Db2Eloquent\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HasMany relationship with multiple column support for DB2.
 * DB2 doesn't support tuple IN syntax, so we use OR/AND conditions instead.
 */
class HasManyMultiple extends HasMany
{
    /**
     * The foreign keys of the relationship.
     */
    protected array $foreignKeys;

    /**
     * The local keys of the relationship.
     */
    protected array $localKeys;

    /**
     * Create a new has many relationship instance.
     */
    public function __construct(Builder $query, Model $parent, array $foreignKeys, array $localKeys)
    {
        $this->foreignKeys = $foreignKeys;
        $this->localKeys = $localKeys;

        // Parent constructor expects single keys, pass first ones
        parent::__construct($query, $parent, $foreignKeys[0], $localKeys[0]);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            $table = $this->related->getTable();

            foreach ($this->foreignKeys as $i => $foreignKey) {
                $this->query->where($table . '.' . $foreignKey, '=', $this->parent->{$this->localKeys[$i]});
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
            foreach ($this->localKeys as $localKey) {
                $keys[] = $model->{$localKey};
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
                    foreach ($this->foreignKeys as $i => $foreignKey) {
                        $q->where($table . '.' . $foreignKey, '=', $keys[$i]);
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
        // Build a dictionary keyed by foreign key combination
        $dictionary = [];
        foreach ($results as $result) {
            $keys = [];
            foreach ($this->foreignKeys as $foreignKey) {
                $keys[] = $result->{$foreignKey};
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

        $parentTable = $this->parent->getTable();
        $relatedTable = $this->related->getTable();

        // Add all key constraints for multi-column relationship
        foreach ($this->foreignKeys as $i => $foreignKey) {
            $query->whereColumn(
                $parentTable . '.' . $this->localKeys[$i],
                '=',
                $relatedTable . '.' . $foreignKey
            );
        }

        return $query;
    }
}
