<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Test fixture model for unit tests.
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
 * Scopes from HasQueryLogging:
 * @method static Builder logQuery(string|array|null $channels = 'stderr')
 *
 * Scopes from HasExtensions:
 * @method static Builder withExtensions(?array $only = null)
 * @method static Builder withExtension(string $extTable)
 * @method static Builder whereHasExtension(string $extTable, string $operator = '>=', int $count = 1)
 * @method static Builder whereDoesntHaveExtension(string $extTable)
 * @method static Builder withWhereHasExtension(string $extTable, string $operator = '>=', int $count = 1)
 *
 * @method static Collection all($columns = ['*'])
 */
class TestItem extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_items';
    protected string $schema = '';

    protected $guarded = [];

    protected array $maps = [
        'ICITEM' => 'item_number',
        'ICDESC' => 'description',
        'ICCOMP' => 'company_number',
        'ICDLTC' => 'delete_code',
        'ICCOST' => 'cost',
        'ICDATE' => 'created_date',
    ];

    protected $casts = [
        'ICDATE' => IbmDate::class,
    ];

    /**
     * Override to skip schema prefix for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Override to use standard Eloquent update for SQLite testing.
     * The parent's performUpdate() uses DB2-specific FETCH FIRST 1 ROW ONLY syntax.
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
            $this->setKeysForSaveQuery($query)->update($dirty);
            $this->syncChanges();
            $this->fireModelEvent('updated', false);
        }

        return true;
    }
}
