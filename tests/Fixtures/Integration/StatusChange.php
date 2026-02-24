<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Casts\IbmDate;
use CodyJHeiser\Db2Eloquent\Casts\IbmTime;
use CodyJHeiser\Db2Eloquent\Model;

/**
 * Integration fixture: StatusChange (read-only)
 * Mirrors a live model from a consuming codebase.
 *
 * Table: R60FSDTA.FSZUPSCLOG
 * Uses separate IbmDate and IbmTime casts with mapped column names.
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class StatusChange extends Model
{
    protected $connection = 'vai';
    protected string $schema = 'R60FSDTA';
    protected $table = 'FSZUPSCLOG';

    protected $casts = [
        'change_date' => IbmDate::class . ':date',
        'change_time' => IbmTime::class . ':time',
    ];

    protected array $maps = [
        'ZSCZUPUID' => 'zuper_user_id',
        'ZSCZUPJID' => 'zuper_job_id',
        'ZSCZUPCID' => 'zuper_customer_id',
        'ZSCCLNO'   => 'call_number',
        'ZSCSLSM'   => 'tech_id',
        'ZSCCUST'   => 'customer_number',
        'ZSCDATE'   => 'change_date',
        'ZSCTIME'   => 'change_time',
        'ZSCSTATUS' => 'status_code',
    ];

    public function serviceCallLive()
    {
        return $this->belongsTo(ServiceCallLive::class, ['call_number']);
    }

    public function serviceCall()
    {
        return $this->belongsTo(ServiceCallAll::class, ['call_number']);
    }

    public function customer()
    {
        return $this->hasOneThrough(
            Customer::class,
            ServiceCallLive::class,
            ['call_number'],
            ['customer_number'],
            ['call_number'],
            ['customer_number']
        );
    }
}
