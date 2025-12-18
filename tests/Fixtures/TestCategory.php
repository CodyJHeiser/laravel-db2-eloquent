<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test fixture model for relationship tests.
 * A category can have many items.
 *
 * Scopes from HasAutoFiltering:
 * @method static Builder withInactive()
 * @method static Builder withAllCompanies()
 * @method static Builder forCompany(string $company)
 * @method static Builder unfiltered()
 *
 * Scopes from HasDatabaseSchema:
 * @method static Builder testing()
 *
 * Scopes from HasColumnMapping:
 * @method static Builder selectAll()
 * @method static Builder selectMapped()
 *
 * @method static Collection all($columns = ['*'])
 */
class TestCategory extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_categories';
    protected string $schema = 'R60FILES';
    protected string $schemaPrefixProd = 'R';
    protected string $schemaPrefixTest = 'T';

    protected $guarded = [];

    protected array $maps = [
        'CTCODE' => 'category_code',
        'CTNAME' => 'category_name',
        'CTCOMP' => 'company_number',
        'CTDEPT' => 'department_code',
        'CTDLTC' => 'delete_code',
    ];

    /**
     * Override to use prefixed table names for SQLite testing.
     * In real DB2: R60FILES.test_categories vs T60FILES.test_categories
     * In SQLite:   test_categories vs t60_test_categories
     */
    public function getTable(): string
    {
        if ($this->useTestSchema) {
            return 't60_test_categories';
        }
        return $this->table;
    }

    /**
     * Override testing scope to use SQLite-compatible table naming.
     */
    #[Scope]
    protected function testing(Builder $query): void
    {
        $model = $query->getModel();
        $model->useTestSchema = true;

        // Use the model's getTable() which handles SQLite naming
        $query->from($model->getTable());
    }

    /**
     * A category has many items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(TestItemRelation::class, ['ITCAT', 'ITCOMP'], ['CTCODE', 'CTCOMP']);
    }

    /**
     * A category has many items (single column).
     */
    public function itemsSingle(): HasMany
    {
        return $this->hasMany(TestItemRelation::class, 'ITCAT', 'CTCODE');
    }
}
