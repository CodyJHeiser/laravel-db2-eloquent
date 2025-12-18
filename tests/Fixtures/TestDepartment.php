<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Test fixture model for hasOneThrough/hasManyThrough relationship tests.
 * A department has categories, and through categories has items.
 *
 * @method static Builder testing()
 * @method static Builder unfiltered()
 * @method static Collection all($columns = ['*'])
 */
class TestDepartment extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_departments';
    protected string $schema = 'R60FILES';
    protected string $schemaPrefixProd = 'R';
    protected string $schemaPrefixTest = 'T';

    protected $guarded = [];

    protected array $maps = [
        'DPCODE' => 'department_code',
        'DPNAME' => 'department_name',
        'DPCOMP' => 'company_number',
        'DPDLTC' => 'delete_code',
    ];

    /**
     * Override to use prefixed table names for SQLite testing.
     */
    public function getTable(): string
    {
        if ($this->useTestSchema) {
            return 't60_test_departments';
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
        $query->from($model->getTable());
    }

    /**
     * A department has many categories (multi-column).
     */
    public function categories(): HasMany
    {
        return $this->hasMany(TestCategory::class, ['CTDEPT', 'CTCOMP'], ['DPCODE', 'DPCOMP']);
    }

    /**
     * A department has one item through category (multi-column).
     * Department -> Category -> Item
     */
    public function firstItem(): HasOneThrough
    {
        return $this->hasOneThrough(
            TestItemRelation::class,
            TestCategory::class,
            ['CTDEPT', 'CTCOMP'],      // Foreign keys on categories (match department)
            ['ITCAT', 'ITCOMP'],        // Foreign keys on items (match category)
            ['DPCODE', 'DPCOMP'],       // Local keys on department
            ['CTCODE', 'CTCOMP']        // Local keys on category (match items)
        );
    }

    /**
     * A department has many items through category (multi-column).
     * Department -> Category -> Items
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            TestItemRelation::class,
            TestCategory::class,
            ['CTDEPT', 'CTCOMP'],      // Foreign keys on categories (match department)
            ['ITCAT', 'ITCOMP'],        // Foreign keys on items (match category)
            ['DPCODE', 'DPCOMP'],       // Local keys on department
            ['CTCODE', 'CTCOMP']        // Local keys on category (match items)
        );
    }

    /**
     * Single column version for comparison.
     */
    public function firstItemSingle(): HasOneThrough
    {
        return $this->hasOneThrough(
            TestItemRelation::class,
            TestCategory::class,
            'CTDEPT',
            'ITCAT',
            'DPCODE',
            'CTCODE'
        );
    }

    /**
     * Single column version for comparison.
     */
    public function itemsSingle(): HasManyThrough
    {
        return $this->hasManyThrough(
            TestItemRelation::class,
            TestCategory::class,
            'CTDEPT',
            'ITCAT',
            'DPCODE',
            'CTCODE'
        );
    }
}
