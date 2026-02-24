<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use CodyJHeiser\Db2Eloquent\Builders\MappedBuilder;
use CodyJHeiser\Db2Eloquent\Builders\ReadOnlyMappedBuilder;
use CodyJHeiser\Db2Eloquent\Exceptions\ReadOnlyModelException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

/**
 * Trait for combining multiple tables with identical schemas via UNION ALL.
 *
 * Defines a read-only model that queries across multiple source tables
 * (e.g., live and history tables) as a single unified result set.
 *
 * The base class uses this trait and defines shared $maps, $casts, and
 * relationships. Child classes extend it and override $table to query
 * individual tables normally — the trait automatically detects children
 * and skips all union/read-only behavior for them.
 *
 * Usage:
 *   class ServiceCallAll extends Model
 *   {
 *       use HasUnionSources;
 *
 *       protected array $unionSources = [
 *           ServiceCallLive::class,
 *           ServiceCallHistory::class,
 *       ];
 *
 *       protected array $maps = [ ... shared columns ... ];
 *   }
 *
 *   class ServiceCallLive extends ServiceCallAll
 *   {
 *       protected $table = 'SBSCHD';
 *       // Inherits maps, casts, relationships from ServiceCallAll
 *       // Queries its own table normally (no union, fully writable)
 *   }
 */
trait HasUnionSources
{
    /**
     * Original DB_COL => mapped_name maps from the source models.
     * Stored before identity rewrite so the subquery can use them.
     */
    protected array $sourceColumnMap = [];

    /**
     * Check if the current concrete class directly uses the HasUnionSources trait.
     * Child classes that extend a union model do NOT directly use it,
     * so they behave as normal models.
     */
    protected function isUnionModel(): bool
    {
        $rc = new ReflectionClass(static::class);
        $directTraits = array_map(fn($t) => $t->getName(), $rc->getTraits());

        return in_array(HasUnionSources::class, $directTraits);
    }

    /**
     * Initialize on each model instantiation.
     * For union models: stores original maps and rewrites to identity mappings.
     * For child models: no-op, they use normal column mapping.
     */
    public function initializeHasUnionSources(): void
    {
        if (!$this->isUnionModel()) {
            return;
        }

        // Store the original maps (DB_COL => mapped_name) for subquery building
        $this->sourceColumnMap = $this->maps;

        // Rewrite maps to identity (mapped_name => mapped_name)
        // This makes MappedBuilder's translateColumn() a no-op for the outer query,
        // since columns in the UNION ALL subquery are already aliased to mapped names.
        $identityMaps = [];
        foreach ($this->maps as $dbCol => $mappedName) {
            $identityMaps[$mappedName] = $mappedName;
        }
        $this->maps = $identityMaps;

        // Clear reverse maps cache so it's rebuilt from identity maps
        $this->reverseMaps = null;
    }

    /**
     * Boot the trait — registers the unionSources global scope.
     */
    protected static function bootHasUnionSources(): void
    {
        static::addGlobalScope('unionSources', function (Builder $query) {
            $model = $query->getModel();

            // Only apply union behavior to classes that directly use the trait
            if (!$model->isUnionModel()) {
                return;
            }

            if (empty($model->getUnionSources())) {
                return;
            }

            $model->applyUnionScope($query);
        });
    }

    /**
     * Apply the UNION ALL subquery scope to the given query.
     */
    protected function applyUnionScope(Builder $query): void
    {
        $sourceMap = $this->sourceColumnMap;
        $sourceModels = $this->getUnionSources();

        // Detect whether auto-filters have been removed (via unfiltered(), withInactive(), etc.)
        $removedScopes = $query->removedScopes();
        $applyActiveFilter = !in_array('active', $removedScopes);
        $applyCompanyFilter = !in_array('company', $removedScopes);

        // Build individual SELECT queries for each source.
        // We build raw SQL because the DB2 grammar's compileSelect does not
        // compile unions when offsetCompatibilityMode is enabled (the default).
        $unionParts = [];
        $allBindings = [];

        foreach ($sourceModels as $sourceClass) {
            /** @var \CodyJHeiser\Db2Eloquent\Model $sourceInstance */
            $sourceInstance = new $sourceClass;

            // Build SELECT with aliases: DB_COL AS mapped_name
            $selectColumns = [];
            foreach ($sourceMap as $dbCol => $mappedName) {
                $selectColumns[] = "{$dbCol} AS {$mappedName}";
            }

            // Add _source column identifying which table this row came from
            $sourceTable = $sourceInstance->getTable();
            $selectColumns[] = "'{$sourceTable}' AS \"_source\"";

            $sourceQuery = DB::connection($sourceInstance->getConnectionName())
                ->table($sourceInstance->getTable())
                ->selectRaw(implode(', ', $selectColumns));

            // Apply auto-filters to each source individually
            if ($applyActiveFilter && $sourceInstance->filterActiveOnly) {
                $dbCol = array_search('delete_code', $sourceMap);
                if ($dbCol !== false) {
                    $sourceQuery->where($dbCol, $sourceInstance->activeDeleteCode);
                }
            }

            if ($applyCompanyFilter && $sourceInstance->filterByCompany) {
                $dbCol = array_search('company_number', $sourceMap);
                if ($dbCol !== false) {
                    $sourceQuery->where($dbCol, $sourceInstance->defaultCompany);
                }
            }

            $unionParts[] = $sourceQuery->toSql();
            $allBindings = array_merge($allBindings, $sourceQuery->getBindings());
        }

        // Replace the outer query's FROM with the UNION ALL subquery
        $unionAlias = $this->unionAlias ?? 'union_view';
        $unionSql = implode(' UNION ALL ', $unionParts);
        $query->getQuery()->fromRaw("({$unionSql}) as {$unionAlias}", $allBindings);

        // Remove scopes that are now handled inside the subquery
        $query->withoutGlobalScopes(['active', 'company', 'autoSelectMapped']);

        // Set outer SELECT to the mapped column names + _source
        $mappedColumns = array_values($sourceMap);
        $query->select($mappedColumns);
        $query->addSelect(DB::raw('"_source"'));

        // Track mapped names as aliases for DB2 uppercase normalization.
        // DB2 uppercases all unquoted aliases, so "company_number" comes back
        // as "COMPANY_NUMBER". This lets normalizeAttributeKeys() restore them.
        foreach ($mappedColumns as $col) {
            $query->trackAlias($col);
        }
        $query->trackAlias('_source');
    }

    /**
     * Override to return ReadOnlyMappedBuilder for union models,
     * or regular MappedBuilder for child classes.
     */
    public function newEloquentBuilder($query): MappedBuilder
    {
        if ($this->isUnionModel()) {
            return new ReadOnlyMappedBuilder($query);
        }

        return new MappedBuilder($query);
    }

    /**
     * Get the union source model classes.
     * Define `protected array $unionSources = [...]` on the using class.
     */
    public function getUnionSources(): array
    {
        return $this->unionSources ?? [];
    }

    /**
     * Get the original source column map (DB_COL => mapped_name).
     */
    public function getSourceColumnMap(): array
    {
        return $this->sourceColumnMap;
    }

    // ==================== WRITE PREVENTION ====================
    // Only blocks writes on the union model itself.
    // Child classes that extend it are fully writable.

    public function save(array $options = [])
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::save($options);
    }

    public function delete()
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::delete();
    }

    public function forceDelete()
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::forceDelete();
    }

    protected function performInsert($query)
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::performInsert($query);
    }

    protected function performUpdate($query)
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::performUpdate($query);
    }

    protected function performDeleteOnModel()
    {
        if ($this->isUnionModel()) {
            throw ReadOnlyModelException::forModel(static::class);
        }

        return parent::performDeleteOnModel();
    }
}
