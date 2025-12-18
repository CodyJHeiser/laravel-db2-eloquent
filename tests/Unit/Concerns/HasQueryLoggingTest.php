<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Concerns;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Tests for the HasQueryLogging trait.
 */
class HasQueryLoggingTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();

        // Reset query log state before each test
        TestItem::disableQueryLog();
        TestItem::clearQueryLog();
        TestItem::setSqlFormatter(null);

        // Seed test data
        $this->app['db']->connection('testing')->table('test_items')->insert([
            ['ICITEM' => 'ITEM1', 'ICDESC' => 'Test Item', 'ICCOMP' => '1', 'ICDLTC' => 'A', 'ICCOST' => 100, 'ICDATE' => 20251217],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        TestItem::disableQueryLog();
        TestItem::clearQueryLog();
        TestItem::setSqlFormatter(null);

        parent::tearDown();
    }

    // ==================== ENABLE/DISABLE TESTS ====================

    public function test_enable_query_log_returns_channel_info(): void
    {
        $result = TestItem::enableQueryLog('stderr');

        $this->assertStringContainsString('stderr', $result);
    }

    public function test_enable_query_log_with_default_channel(): void
    {
        $result = TestItem::enableQueryLog(null);

        $this->assertStringContainsString('default', $result);
    }

    public function test_enable_query_log_with_array_of_channels(): void
    {
        $result = TestItem::enableQueryLog(['stderr', 'default']);

        $this->assertStringContainsString('stderr', $result);
        $this->assertStringContainsString('default', $result);
    }

    public function test_disable_query_log_can_be_called(): void
    {
        TestItem::enableQueryLog();
        TestItem::disableQueryLog();

        // Just verifying no exception is thrown
        $this->assertTrue(true);
    }

    // ==================== QUERY LOG TESTS ====================

    public function test_get_query_log_returns_array(): void
    {
        $log = TestItem::getQueryLog();

        $this->assertIsArray($log);
    }

    public function test_clear_query_log_empties_log(): void
    {
        // Manually add something to the log to test clearing
        TestItem::enableQueryLog();

        // Force a query via DB facade that we know will be logged
        DB::connection('testing')->select('SELECT 1');

        // Clear and verify empty
        TestItem::clearQueryLog();
        $this->assertEmpty(TestItem::getQueryLog());
    }

    // ==================== SQL FORMATTER TESTS ====================

    public function test_set_sql_formatter_accepts_callable(): void
    {
        TestItem::setSqlFormatter(function ($sql, $bindings) {
            return 'CUSTOM: ' . $sql;
        });

        // Just verifying no exception is thrown
        $this->assertTrue(true);
    }

    public function test_set_sql_formatter_accepts_null(): void
    {
        // Set a formatter first
        TestItem::setSqlFormatter(function ($sql, $bindings) {
            return 'CUSTOM: ' . $sql;
        });

        // Reset to null
        TestItem::setSqlFormatter(null);

        // Just verifying no exception is thrown
        $this->assertTrue(true);
    }

    // ==================== LOG QUERY SCOPE TESTS ====================

    public function test_log_query_scope_returns_builder(): void
    {
        $query = TestItem::logQuery()->unfiltered();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function test_log_query_scope_can_specify_channel(): void
    {
        // Just verify it doesn't throw - we can't easily test actual output
        $query = TestItem::logQuery('default')->unfiltered();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function test_log_query_scope_can_specify_multiple_channels(): void
    {
        $query = TestItem::logQuery(['stderr', 'default'])->unfiltered();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function test_log_query_scope_can_chain_with_query_methods(): void
    {
        $query = TestItem::logQuery()
            ->unfiltered()
            ->where('item_number', 'TEST')
            ->orderBy('description');

        $sql = $query->toSql();

        $this->assertStringContainsString('test_items', $sql);
    }

    // ==================== FORMAT SQL TESTS ====================

    public function test_format_sql_with_bindings_default_handles_strings(): void
    {
        // Test via reflection since formatSqlWithBindings is protected
        $reflection = new \ReflectionClass(TestItem::class);
        $method = $reflection->getMethod('formatSqlWithBindings');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM test WHERE name = ?';
        $bindings = ['John'];

        $result = $method->invoke(null, $sql, $bindings);

        $this->assertStringContainsString("'John'", $result);
        $this->assertStringNotContainsString('?', $result);
    }

    public function test_format_sql_with_bindings_default_handles_numbers(): void
    {
        $reflection = new \ReflectionClass(TestItem::class);
        $method = $reflection->getMethod('formatSqlWithBindings');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM test WHERE id = ?';
        $bindings = [123];

        $result = $method->invoke(null, $sql, $bindings);

        $this->assertStringContainsString('123', $result);
        $this->assertStringNotContainsString('?', $result);
    }

    public function test_format_sql_with_bindings_handles_multiple_bindings(): void
    {
        $reflection = new \ReflectionClass(TestItem::class);
        $method = $reflection->getMethod('formatSqlWithBindings');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM test WHERE name = ? AND age = ? AND active = ?';
        $bindings = ['John', 30, 1];

        $result = $method->invoke(null, $sql, $bindings);

        $this->assertStringContainsString("'John'", $result);
        $this->assertStringContainsString('30', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringNotContainsString('?', $result);
    }

    public function test_format_sql_with_custom_formatter(): void
    {
        TestItem::setSqlFormatter(function ($sql, $bindings) {
            return 'FORMATTED: ' . $sql . ' [' . count($bindings) . ' bindings]';
        });

        $reflection = new \ReflectionClass(TestItem::class);
        $method = $reflection->getMethod('formatSqlWithBindings');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM test WHERE id = ?';
        $bindings = [123];

        $result = $method->invoke(null, $sql, $bindings);

        $this->assertStringStartsWith('FORMATTED:', $result);
        $this->assertStringContainsString('[1 bindings]', $result);
    }

    public function test_format_sql_escapes_special_characters(): void
    {
        $reflection = new \ReflectionClass(TestItem::class);
        $method = $reflection->getMethod('formatSqlWithBindings');
        $method->setAccessible(true);

        $sql = 'SELECT * FROM test WHERE name = ?';
        $bindings = ["O'Brien"];

        $result = $method->invoke(null, $sql, $bindings);

        // Should escape the apostrophe
        $this->assertStringContainsString("O\\'Brien", $result);
    }

    // ==================== STATIC STATE TESTS ====================

    public function test_query_log_is_static_across_instances(): void
    {
        TestItem::clearQueryLog();

        $item1 = new TestItem();
        $item2 = new TestItem();

        // Both instances should see the same empty log
        $this->assertEquals($item1::getQueryLog(), $item2::getQueryLog());
    }

    public function test_enable_disable_affects_all_instances(): void
    {
        TestItem::disableQueryLog();

        $item1 = new TestItem();
        TestItem::enableQueryLog();

        // New instance should see logging as enabled (returns channel info)
        $item2 = new TestItem();
        $result = $item2::enableQueryLog('stderr');

        $this->assertStringContainsString('stderr', $result);
    }
}
