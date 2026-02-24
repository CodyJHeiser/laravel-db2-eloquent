<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Builders;

use CodyJHeiser\Db2Eloquent\Tests\Fixtures\TestItem;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;

class MappedBuilderExistsTest extends TestCase
{
    protected bool $useDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfNoDatabaseDriver();
    }

    public function test_exists_returns_true_when_rows_exist(): void
    {
        TestItem::unfiltered()->insert([
            'ICITEM' => 'EXISTS-TEST-1',
            'ICDESC' => 'Exists Test',
            'ICCOMP' => '1',
            'ICDLTC' => 'A',
            'ICCOST' => 100,
            'ICDATE' => 20251217,
        ]);

        $result = TestItem::where('item_number', 'EXISTS-TEST-1')->exists();

        $this->assertTrue($result);
    }

    public function test_exists_returns_false_when_no_rows(): void
    {
        $result = TestItem::where('item_number', 'NONEXISTENT-ITEM')->exists();

        $this->assertFalse($result);
    }

    public function test_doesnt_exist_returns_true_when_no_rows(): void
    {
        $result = TestItem::where('item_number', 'NONEXISTENT-ITEM')->doesntExist();

        $this->assertTrue($result);
    }

    public function test_doesnt_exist_returns_false_when_rows_exist(): void
    {
        TestItem::unfiltered()->insert([
            'ICITEM' => 'EXISTS-TEST-2',
            'ICDESC' => 'Exists Test 2',
            'ICCOMP' => '1',
            'ICDLTC' => 'A',
            'ICCOST' => 200,
            'ICDATE' => 20251217,
        ]);

        $result = TestItem::where('item_number', 'EXISTS-TEST-2')->doesntExist();

        $this->assertFalse($result);
    }
}
