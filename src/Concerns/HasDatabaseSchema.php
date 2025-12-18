<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;

/**
 * Trait for handling database schema prefixes.
 * Allows models to specify just the table name while the schema is handled separately.
 *
 * Usage:
 *   protected string $schema = 'PROD_SCHEMA';
 *   protected $table = 'MYTABLE';
 *   // Results in: PROD_SCHEMA.MYTABLE
 *
 * Testing (Option 1 - explicit test schema):
 *   protected string $schema = 'PROD_SCHEMA';
 *   protected string $testSchema = 'TEST_SCHEMA';
 *   Model::testing()->get()  // Uses TEST_SCHEMA.MYTABLE
 *
 * Testing (Option 2 - prefix replacement):
 *   protected string $schema = 'R60FILES';
 *   protected string $schemaPrefixProd = 'R';
 *   protected string $schemaPrefixTest = 'T';
 *   Model::testing()->get()  // Uses T60FILES.MYTABLE
 */
trait HasDatabaseSchema
{
    /**
     * The database schema for this model (production).
     * Override in child models.
     * @var string
     */
    protected string $schema = '';

    /**
     * The test database schema for this model.
     * If set, this is used directly when testing() scope is applied.
     * If not set, falls back to prefix replacement logic.
     * @var string|null
     */
    protected ?string $testSchema = null;

    /**
     * The prefix to replace in schema name for testing.
     * Used when $testSchema is not explicitly set.
     * @var string
     */
    protected string $schemaPrefixProd = 'R';

    /**
     * The prefix to use for testing schema.
     * Used when $testSchema is not explicitly set.
     * @var string
     */
    protected string $schemaPrefixTest = 'T';

    /**
     * Whether to use the testing schema.
     * @var bool
     */
    protected bool $useTestSchema = false;

    /**
     * Get the table associated with the model.
     * Automatically prepends the schema.
     *
     * @return string
     */
    public function getTable(): string
    {
        $table = parent::getTable();

        // If table already has a dot (schema.table), return as-is
        if (str_contains($table, '.')) {
            return $table;
        }

        $schema = $this->getEffectiveSchema();

        return $schema . '.' . $table;
    }

    /**
     * Get the effective schema (production or testing).
     *
     * @return string
     */
    protected function getEffectiveSchema(): string
    {
        if ($this->useTestSchema) {
            return $this->getTestSchemaName();
        }

        return $this->schema;
    }

    /**
     * Get the test schema name.
     * Override this method for custom test schema logic.
     *
     * @return string
     */
    protected function getTestSchemaName(): string
    {
        // Option 1: Explicit test schema is set
        if ($this->testSchema !== null) {
            return $this->testSchema;
        }

        // Option 2: Use prefix replacement (e.g., R60FILES -> T60FILES)
        if (str_starts_with($this->schema, $this->schemaPrefixProd)) {
            return $this->schemaPrefixTest . substr($this->schema, strlen($this->schemaPrefixProd));
        }

        // Fallback: return schema as-is if no transformation applies
        return $this->schema;
    }

    /**
     * Get the schema for this model.
     *
     * @return string
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Scope to use the testing database schema.
     * Uses $testSchema if set, otherwise applies prefix replacement.
     *
     * Usage: Model::testing()->get()
     */
    #[Scope]
    protected function testing(Builder $query): void
    {
        $model = $query->getModel();
        $model->useTestSchema = true;

        $table = parent::getTable();
        $testSchema = $model->getTestSchemaName();

        $query->from($testSchema . '.' . $table);
    }

    /**
     * Create a new instance of the related model.
     * Propagates testing mode and selectAll to related models for whereHas, with, etc.
     *
     * @param string $class
     * @return mixed
     */
    protected function newRelatedInstance($class)
    {
        $instance = parent::newRelatedInstance($class);

        // Propagate testing mode to related models
        if ($this->useTestSchema && property_exists($instance, 'useTestSchema')) {
            $instance->useTestSchema = true;
        }

        // Propagate selectAll to related models
        if ($this->useSelectAll && property_exists($instance, 'useSelectAll')) {
            $instance->useSelectAll = true;
        }

        return $instance;
    }

    /**
     * Create a new instance of the given model.
     * Propagates testing mode and selectAll to hydrated models during query result processing.
     *
     * @param array $attributes
     * @param bool $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = parent::newInstance($attributes, $exists);

        // Propagate testing mode to new instances (e.g., hydrated query results)
        if ($this->useTestSchema) {
            $model->useTestSchema = true;
        }

        // Propagate selectAll to new instances
        if ($this->useSelectAll) {
            $model->useSelectAll = true;
        }

        return $model;
    }
}
