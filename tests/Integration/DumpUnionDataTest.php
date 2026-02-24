<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallAll;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallLive;
use CodyJHeiser\Db2Eloquent\Tests\Fixtures\Integration\ServiceCallHistory;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

class DumpUnionDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    public function test_dump_real_union_data(): void
    {
        // Grab a call number from each individual table
        $liveRecord = ServiceCallLive::unfiltered()->limit(1)->first();
        $historyRecord = ServiceCallHistory::unfiltered()->limit(1)->first();

        $this->assertNotNull($liveRecord, 'No data in SBSCHD (live)');
        $this->assertNotNull($historyRecord, 'No data in SBHSHD (history)');

        $liveCallNumber = $liveRecord->call_number;
        $historyCallNumber = $historyRecord->call_number;

        fwrite(STDERR, "\n\n=== Individual Table Records ===\n");
        fwrite(STDERR, "Live call number (SBSCHD):    {$liveCallNumber}\n");
        fwrite(STDERR, "History call number (SBHSHD): {$historyCallNumber}\n");

        // Dump a few records from the union
        $records = ServiceCallAll::unfiltered()->limit(3)->get();

        fwrite(STDERR, "\n=== ServiceCallAll (UNION ALL of SBSCHD + SBHSHD) ===\n");
        foreach ($records as $i => $record) {
            fwrite(STDERR, "\nRecord " . ($i + 1) . ":\n");
            fwrite(STDERR, print_r($record->toArray(), true));
        }
        fwrite(STDERR, "\nReturned " . $records->count() . " records\n");

        // Query the union model for each specific call number to prove both tables are queryable
        fwrite(STDERR, "\n=== WHERE clause validation ===\n");

        $liveFromUnion = ServiceCallAll::unfiltered()
            ->where('call_number', $liveCallNumber)
            ->limit(1)
            ->first();

        $this->assertNotNull($liveFromUnion, "Live call {$liveCallNumber} not found in union");
        fwrite(STDERR, "Live call {$liveCallNumber} found in union — _source: {$liveFromUnion->_source}\n");

        $historyFromUnion = ServiceCallAll::unfiltered()
            ->where('call_number', $historyCallNumber)
            ->limit(1)
            ->first();

        $this->assertNotNull($historyFromUnion, "History call {$historyCallNumber} not found in union");
        fwrite(STDERR, "History call {$historyCallNumber} found in union — _source: {$historyFromUnion->_source}\n\n");
    }
}
