<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use CodyJHeiser\Db2Eloquent\Relations\BelongsToMultiple;
use CodyJHeiser\Db2Eloquent\Relations\HasManyMultiple;
use CodyJHeiser\Db2Eloquent\Relations\HasManyThroughMultiple;
use CodyJHeiser\Db2Eloquent\Relations\HasOneMultiple;
use CodyJHeiser\Db2Eloquent\Relations\HasOneThroughMultiple;

/**
 * Trait to support multi-column relationships with DB2-compatible SQL.
 * Automatically detects array keys and uses OR/AND instead of tuple IN syntax.
 * Supports using mapped column names in relationship definitions.
 */
trait HasMultiColumnRelations
{
    /**
     * Translate column name(s) to DB column(s) using a model's maps.
     *
     * @param string|array $columns
     * @param object $model
     * @return string|array
     */
    protected function translateRelationColumns(string|array $columns, object $model): string|array
    {
        if (is_array($columns)) {
            return array_map(fn($col) => $model->getDbColumn($col), $columns);
        }

        return $model->getDbColumn($columns);
    }

    /**
     * Define a belongs-to relationship.
     * Supports array keys for multi-column relationships (DB2-compatible).
     * Supports mapped column names (e.g., 'item_number' instead of 'ICITEM').
     * If ownerKey is omitted, assumes same mapped names as foreignKey.
     *
     * @param string $related
     * @param string|array|null $foreignKey
     * @param string|array|null $ownerKey
     * @param string|null $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo|BelongsToMultiple
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = null, $relation = null)
    {
        $instance = $this->newRelatedInstance($related);

        // If ownerKey not specified, assume same mapped names as foreignKey
        if ($foreignKey !== null && $ownerKey === null) {
            $ownerKey = $foreignKey;
        }

        // Translate mapped column names to DB columns
        if ($foreignKey !== null) {
            $foreignKey = $this->translateRelationColumns($foreignKey, $this);
        }
        if ($ownerKey !== null) {
            $ownerKey = $this->translateRelationColumns($ownerKey, $instance);
        }

        // If arrays are passed, use multi-column relationship
        if (is_array($foreignKey) && is_array($ownerKey)) {
            if (is_null($relation)) {
                $relation = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'];
            }

            return new BelongsToMultiple(
                $instance->newQuery(),
                $this,
                $foreignKey,
                $ownerKey,
                $relation
            );
        }

        // Standard single-column relationship
        return parent::belongsTo($related, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a has-many relationship.
     * Supports array keys for multi-column relationships (DB2-compatible).
     * Supports mapped column names (e.g., 'item_number' instead of 'ICITEM').
     * If localKey is omitted, assumes same mapped names as foreignKey.
     *
     * @param string $related
     * @param string|array|null $foreignKey
     * @param string|array|null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|HasManyMultiple
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        // If localKey not specified, assume same mapped names as foreignKey
        if ($foreignKey !== null && $localKey === null) {
            $localKey = $foreignKey;
        }

        // Translate mapped column names to DB columns
        if ($foreignKey !== null) {
            $foreignKey = $this->translateRelationColumns($foreignKey, $instance);
        }
        if ($localKey !== null) {
            $localKey = $this->translateRelationColumns($localKey, $this);
        }

        // If arrays are passed, use multi-column relationship
        if (is_array($foreignKey) && is_array($localKey)) {
            return new HasManyMultiple(
                $instance->newQuery(),
                $this,
                $foreignKey,
                $localKey
            );
        }

        // Standard single-column relationship
        return parent::hasMany($related, $foreignKey, $localKey);
    }

    /**
     * Define a has-one relationship.
     * Supports array keys for multi-column relationships (DB2-compatible).
     * Supports mapped column names (e.g., 'item_number' instead of 'ICITEM').
     * If localKey is omitted, assumes same mapped names as foreignKey.
     *
     * @param string $related
     * @param string|array|null $foreignKey
     * @param string|array|null $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|HasOneMultiple
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $instance = $this->newRelatedInstance($related);

        // If localKey not specified, assume same mapped names as foreignKey
        if ($foreignKey !== null && $localKey === null) {
            $localKey = $foreignKey;
        }

        // Translate mapped column names to DB columns
        if ($foreignKey !== null) {
            $foreignKey = $this->translateRelationColumns($foreignKey, $instance);
        }
        if ($localKey !== null) {
            $localKey = $this->translateRelationColumns($localKey, $this);
        }

        // If arrays are passed, use multi-column relationship
        if (is_array($foreignKey) && is_array($localKey)) {
            return new HasOneMultiple(
                $instance->newQuery(),
                $this,
                $foreignKey,
                $localKey
            );
        }

        // Standard single-column relationship
        return parent::hasOne($related, $foreignKey, $localKey);
    }

    /**
     * Define a has-one-through relationship.
     * Supports array keys for multi-column relationships (DB2-compatible).
     * Supports mapped column names.
     *
     * @param string $related
     * @param string $through
     * @param string|array|null $firstKey Foreign key on through table
     * @param string|array|null $secondKey Foreign key on related table
     * @param string|array|null $localKey Local key on parent table
     * @param string|array|null $secondLocalKey Local key on through table
     * @return \Illuminate\Database\Eloquent\Relations\HasOneThrough|HasOneThroughMultiple
     */
    public function hasOneThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        $throughInstance = $this->newRelatedInstance($through);
        $relatedInstance = $this->newRelatedInstance($related);

        // If keys not specified, assume same mapped names
        if ($firstKey !== null && $localKey === null) {
            $localKey = $firstKey;
        }
        if ($secondKey !== null && $secondLocalKey === null) {
            $secondLocalKey = $secondKey;
        }

        // Translate mapped column names to DB columns
        if ($firstKey !== null) {
            $firstKey = $this->translateRelationColumns($firstKey, $throughInstance);
        }
        if ($secondKey !== null) {
            $secondKey = $this->translateRelationColumns($secondKey, $relatedInstance);
        }
        if ($localKey !== null) {
            $localKey = $this->translateRelationColumns($localKey, $this);
        }
        if ($secondLocalKey !== null) {
            $secondLocalKey = $this->translateRelationColumns($secondLocalKey, $throughInstance);
        }

        // If arrays are passed, use multi-column relationship
        if (is_array($firstKey) && is_array($secondKey)) {
            return new HasOneThroughMultiple(
                $relatedInstance->newQuery(),
                $this,
                $throughInstance,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey
            );
        }

        // Standard single-column relationship
        return parent::hasOneThrough($related, $through, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }

    /**
     * Define a has-many-through relationship.
     * Supports array keys for multi-column relationships (DB2-compatible).
     * Supports mapped column names.
     *
     * @param string $related
     * @param string $through
     * @param string|array|null $firstKey Foreign key on through table
     * @param string|array|null $secondKey Foreign key on related table
     * @param string|array|null $localKey Local key on parent table
     * @param string|array|null $secondLocalKey Local key on through table
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough|HasManyThroughMultiple
     */
    public function hasManyThrough($related, $through, $firstKey = null, $secondKey = null, $localKey = null, $secondLocalKey = null)
    {
        $throughInstance = $this->newRelatedInstance($through);
        $relatedInstance = $this->newRelatedInstance($related);

        // If keys not specified, assume same mapped names
        if ($firstKey !== null && $localKey === null) {
            $localKey = $firstKey;
        }
        if ($secondKey !== null && $secondLocalKey === null) {
            $secondLocalKey = $secondKey;
        }

        // Translate mapped column names to DB columns
        if ($firstKey !== null) {
            $firstKey = $this->translateRelationColumns($firstKey, $throughInstance);
        }
        if ($secondKey !== null) {
            $secondKey = $this->translateRelationColumns($secondKey, $relatedInstance);
        }
        if ($localKey !== null) {
            $localKey = $this->translateRelationColumns($localKey, $this);
        }
        if ($secondLocalKey !== null) {
            $secondLocalKey = $this->translateRelationColumns($secondLocalKey, $throughInstance);
        }

        // If arrays are passed, use multi-column relationship
        if (is_array($firstKey) && is_array($secondKey)) {
            return new HasManyThroughMultiple(
                $relatedInstance->newQuery(),
                $this,
                $throughInstance,
                $firstKey,
                $secondKey,
                $localKey,
                $secondLocalKey
            );
        }

        // Standard single-column relationship
        return parent::hasManyThrough($related, $through, $firstKey, $secondKey, $localKey, $secondLocalKey);
    }
}
