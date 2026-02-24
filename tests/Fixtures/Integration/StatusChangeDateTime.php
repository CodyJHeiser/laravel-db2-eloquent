<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration;

use CodyJHeiser\Db2Eloquent\Casts\IbmDateTime;
use CodyJHeiser\Db2Eloquent\Model;

/**
 * Integration fixture: StatusChange with composite IbmDateTime cast.
 * Uses composite cast key to combine date + time columns into a single Carbon instance.
 *
 * Table: R60FSDTA.FSZUPSCLOG
 *
 * IMPORTANT: READ-ONLY. Must NEVER insert, update, or delete data.
 */
class StatusChangeDateTime extends Model
{
    protected $connection = 'vai';
    protected string $schema = 'R60FSDTA';
    protected $table = 'FSZUPSCLOG';

    protected $casts = [
        'change_date,change_time' => IbmDateTime::class,
    ];

    protected array $maps = [
        'ZSCZUPUID' => 'zuper_user_id',
        'ZSCZUPJID' => 'zuper_job_id',
        'ZSCZUPCID' => 'zuper_customer_id',
        'ZSCCLNO'   => 'call_number',
        'ZSCSLSM'   => 'salesrep',
        'ZSCCUST'   => 'customer',
        'ZSCDATE'   => 'change_date',
        'ZSCTIME'   => 'change_time',
        'ZSCSTATUS' => 'status_code',
    ];
}
