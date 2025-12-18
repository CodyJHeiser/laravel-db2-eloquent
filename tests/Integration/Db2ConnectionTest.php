<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Integration;

use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests that require a real DB2 connection.
 *
 * To run these tests, configure your .env file with IBM DB2 credentials:
 *   IBM_DB_HOST=your-host
 *   IBM_DB_DATABASE=your-database
 *   IBM_DB_USERNAME=your-user
 *   IBM_DB_PASSWORD=your-password
 *
 * Then run: composer test-integration
 */
class Db2ConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDb2();
    }

    public function test_can_connect_to_db2(): void
    {
        $result = DB::connection('vai')->select('SELECT 1 AS test FROM SYSIBM.SYSDUMMY1');

        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result[0]->test ?? $result[0]->TEST);
    }

    public function test_can_query_with_schema_prefix(): void
    {
        // This tests that the schema prefix is working
        $schema = config('database.connections.vai.schema');

        // Try to get table info - this validates the connection and schema
        $result = DB::connection('vai')
            ->select("SELECT COUNT(*) AS cnt FROM QSYS2.SYSTABLES WHERE TABLE_SCHEMA = ?", [$schema]);

        $this->assertIsArray($result);
    }

    public function test_fetch_first_syntax_works(): void
    {
        // Test DB2-specific FETCH FIRST syntax that the Model uses
        $sql = "SELECT 1 AS test FROM SYSIBM.SYSDUMMY1 FETCH FIRST 1 ROW ONLY";
        $result = DB::connection('vai')->select($sql);

        $this->assertCount(1, $result);
    }
}
