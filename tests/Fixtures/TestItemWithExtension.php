<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Test fixture model with extension tables for unit tests.
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
class TestItemWithExtension extends Model
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
    ];

    protected array $extensions = [
        'test_item_extensions' => [
            'join' => [
                'EXITEM' => 'ICITEM',
                'EXCOMP' => 'ICCOMP',
            ],
            'columns' => ['*'],
            'maps' => [
                'EXITEM' => 'ext_item_number',
                'EXCOMP' => 'ext_company',
                'EXDATA' => 'ext_data',
                'EXNOTE' => 'ext_note',
            ],
        ],
        'test_item_details' => [
            'join' => [
                'DTITEM' => 'ICITEM',
            ],
            'columns' => ['DTITEM', 'DTINFO'],
            'maps' => [
                'DTITEM' => 'detail_item',
                'DTINFO' => 'detail_info',
            ],
        ],
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
