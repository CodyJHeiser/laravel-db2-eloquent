<?php

namespace CodyJHeiser\Db2Eloquent\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Scope;

/**
 * Trait for automatic filtering by delete_code and company_number.
 * Applies global scopes to filter active records and default company.
 */
trait HasAutoFiltering
{
    /**
     * Whether to automatically filter by delete_code = 'A' (active records).
     * Set to false to include all records regardless of delete code.
     */
    protected bool $filterActiveOnly = true;

    /**
     * The delete code value that indicates an active record.
     * Override in child models if different.
     */
    protected string $activeDeleteCode = 'A';

    /**
     * Whether to automatically filter by company number.
     * Set to false to include all companies.
     */
    protected bool $filterByCompany = true;

    /**
     * The default company number to filter by.
     * Override in child models if different.
     */
    protected string $defaultCompany = '1';

    /**
     * Initialize auto filtering global scopes.
     * Should be called from the model's booted() method.
     */
    protected static function bootHasAutoFiltering(): void
    {
        // Add global scope for active records (delete_code filter)
        static::addGlobalScope('active', function (Builder $query) {
            $model = $query->getModel();

            if ($model->filterActiveOnly && method_exists($model, 'hasMaps') && $model->hasMaps()) {
                $deleteCodeColumn = $model->getDbColumn('delete_code');
                // Only apply if delete_code is actually mapped
                if ($deleteCodeColumn !== 'delete_code') {
                    $query->where($deleteCodeColumn, $model->activeDeleteCode);
                }
            }
        });

        // Add global scope for company filter
        static::addGlobalScope('company', function (Builder $query) {
            $model = $query->getModel();

            if ($model->filterByCompany && method_exists($model, 'hasMaps') && $model->hasMaps()) {
                $companyColumn = $model->getDbColumn('company_number');
                // Only apply if company_number is actually mapped
                if ($companyColumn !== 'company_number') {
                    $query->where($companyColumn, $model->defaultCompany);
                }
            }
        });
    }

    /**
     * Scope to include inactive/deleted records.
     *
     * Usage: Item::withInactive()->get()
     */
    #[Scope]
    protected function withInactive(Builder $query): void
    {
        $query->withoutGlobalScope('active');
    }

    /**
     * Scope to include all companies.
     *
     * Usage: Item::withAllCompanies()->get()
     */
    #[Scope]
    protected function withAllCompanies(Builder $query): void
    {
        $query->withoutGlobalScope('company');
    }

    /**
     * Scope to filter by a specific company.
     *
     * Usage: Item::forCompany('2')->get()
     */
    #[Scope]
    protected function forCompany(Builder $query, string $company): void
    {
        $query->withoutGlobalScope('company');

        if (method_exists($this, 'hasMaps') && $this->hasMaps()) {
            $companyColumn = $this->getDbColumn('company_number');
            if ($companyColumn !== 'company_number') {
                $query->where($companyColumn, $company);
            }
        }
    }

    /**
     * Scope to remove all automatic filters (active + company).
     *
     * Usage: Item::unfiltered()->get()
     */
    #[Scope]
    protected function unfiltered(Builder $query): void
    {
        $query->withoutGlobalScopes(['active', 'company']);
    }
}
