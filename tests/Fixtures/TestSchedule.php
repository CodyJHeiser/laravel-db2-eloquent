<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures;

use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Casts\IbmDateTime;
use CodyJHeiser\Db2Eloquent\Model;

/**
 * Test fixture model for IbmDateTime and mapped cast tests.
 */
class TestSchedule extends Model
{
    protected $connection = 'testing';
    protected $table = 'test_schedules';
    protected string $schema = '';

    protected $guarded = [];

    protected array $maps = [
        'SHCODE' => 'schedule_code',
        'SHDESC' => 'description',
        'SHDATE' => 'scheduled_date',
        'SHTIME' => 'scheduled_time',
        'SHCOMP' => 'company_number',
        'SHDLTC' => 'delete_code',
    ];

    // Uses MAPPED column names in casts (new feature)
    // Also uses composite cast key with mapped names
    protected $casts = [
        'scheduled_date,scheduled_time' => IbmDateTime::class,
    ];

    /**
     * Override to skip schema prefix for SQLite testing.
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
