<?php

namespace CodyJHeiser\Db2Eloquent;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use CodyJHeiser\Db2Eloquent\Concerns\HasColumnMapping;
use CodyJHeiser\Db2Eloquent\Concerns\HasQueryLogging;
use CodyJHeiser\Db2Eloquent\Concerns\HasExtensions;
use CodyJHeiser\Db2Eloquent\Concerns\HasAutoFiltering;
use CodyJHeiser\Db2Eloquent\Concerns\HasMultiColumnRelations;
use CodyJHeiser\Db2Eloquent\Concerns\HasDatabaseSchema;

/**
 * Base model for IBM DB2 database tables.
 *
 * Features:
 * - Column mapping (DB columns to human-readable names)
 * - Query logging with formatted SQL output
 * - Extension table joins
 * - Auto filtering by delete_code and company_number
 * - Multi-column relationships (DB2-compatible)
 */
abstract class Model extends EloquentModel
{
    use HasColumnMapping;
    use HasQueryLogging;
    use HasExtensions;
    use HasAutoFiltering;
    use HasMultiColumnRelations;
    use HasDatabaseSchema;

    protected $connection = 'db2';

    public $timestamps = false;

    public $incrementing = false;

    protected static function booted(): void
    {
        static::preventLazyLoading();
    }

    /**
     * Set the keys for a save update query.
     * Uses all original attributes as WHERE conditions since IBM tables lack primary keys.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function setKeysForSaveQuery($query)
    {
        // Use all original attributes as WHERE conditions
        foreach ($this->original as $key => $value) {
            $query->where($key, '=', $value);
        }

        return $query;
    }

    /**
     * Perform a model update operation.
     * Overridden to add FETCH FIRST 1 ROW ONLY for safety on IBM tables without primary keys.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return bool
     */
    protected function performUpdate($query)
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            // Build the update query with all original values as WHERE conditions
            $updateQuery = $this->setKeysForSaveQuery($query);

            // Get the SQL and bindings
            $sql = $updateQuery->getQuery()->grammar->compileUpdate(
                $updateQuery->getQuery(),
                $dirty
            );

            // Append FETCH FIRST 1 ROW ONLY for safety
            $sql .= ' FETCH FIRST 1 ROW ONLY';

            // Get bindings (update values + where values)
            $bindings = array_merge(array_values($dirty), $updateQuery->getQuery()->getBindings());

            // Execute the raw query
            $this->getConnection()->update($sql, $bindings);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

}
