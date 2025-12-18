<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Test fixture model for relationship tests.
 * An item belongs to a category.
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
class TestItemRelation extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_items_rel';
    protected string $schema = 'R60FILES';
    protected string $schemaPrefixProd = 'R';
    protected string $schemaPrefixTest = 'T';

    protected $guarded = [];

    protected array $maps = [
        'ITCODE' => 'item_code',
        'ITNAME' => 'item_name',
        'ITCAT' => 'category_code',
        'ITCOMP' => 'company_number',
        'ITDLTC' => 'delete_code',
    ];

    /**
     * Override to use prefixed table names for SQLite testing.
     * In real DB2: R60FILES.test_items_rel vs T60FILES.test_items_rel
     * In SQLite:   test_items_rel vs t60_test_items_rel
     */
    public function getTable(): string
    {
        if ($this->useTestSchema) {
            return 't60_test_items_rel';
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
     * An item belongs to a category (multi-column).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, ['ITCAT', 'ITCOMP'], ['CTCODE', 'CTCOMP']);
    }

    /**
     * An item belongs to a category (single column).
     */
    public function categorySingle(): BelongsTo
    {
        return $this->belongsTo(TestCategory::class, 'ITCAT', 'CTCODE');
    }
}
