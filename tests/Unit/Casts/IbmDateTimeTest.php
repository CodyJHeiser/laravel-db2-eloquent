<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Casts;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Casts\IbmDateTime;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class IbmDateTimeTest extends TestCase
{
    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {};
    }

    // ==================== SINGLE COLUMN GET TESTS ====================

    public function test_single_get_returns_carbon_instance(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDTTM', 20251217073733, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(12, $result->month);
        $this->assertEquals(17, $result->day);
        $this->assertEquals(7, $result->hour);
        $this->assertEquals(37, $result->minute);
        $this->assertEquals(33, $result->second);
    }

    public function test_single_get_returns_null_for_null(): void
    {
        $cast = new IbmDateTime();

        $this->assertNull($cast->get($this->model, 'SHDTTM', null, []));
    }

    public function test_single_get_returns_null_for_zero(): void
    {
        $cast = new IbmDateTime();

        $this->assertNull($cast->get($this->model, 'SHDTTM', 0, []));
    }

    public function test_single_get_returns_null_for_empty_string(): void
    {
        $cast = new IbmDateTime();

        $this->assertNull($cast->get($this->model, 'SHDTTM', '', []));
    }

    public function test_single_get_with_format_returns_string(): void
    {
        $cast = new IbmDateTime('Y-m-d H:i:s');

        $result = $cast->get($this->model, 'SHDTTM', 20251217073733, []);

        $this->assertIsString($result);
        $this->assertEquals('2025-12-17 07:37:33', $result);
    }

    public function test_single_get_with_datetime_shortcut(): void
    {
        $cast = new IbmDateTime('datetime');

        $result = $cast->get($this->model, 'SHDTTM', 20251217143052, []);

        $this->assertEquals('2025-12-17 14:30:52', $result);
    }

    public function test_single_get_with_date_shortcut(): void
    {
        $cast = new IbmDateTime('date');

        $result = $cast->get($this->model, 'SHDTTM', 20251217073733, []);

        $this->assertEquals('2025-12-17', $result);
    }

    public function test_single_get_with_us_shortcut(): void
    {
        $cast = new IbmDateTime('us');

        $result = $cast->get($this->model, 'SHDTTM', 20251217143052, []);

        $this->assertEquals('12/17/2025 2:30:52 PM', $result);
    }

    // ==================== COMPOSITE COLUMN GET TESTS ====================

    public function test_composite_get_returns_carbon_instance(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 73733,
        ]);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(12, $result->month);
        $this->assertEquals(17, $result->day);
        $this->assertEquals(7, $result->hour);
        $this->assertEquals(37, $result->minute);
        $this->assertEquals(33, $result->second);
    }

    public function test_composite_get_pads_time_with_leading_zeros(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 73733, // 07:37:33
        ]);

        $this->assertEquals(7, $result->hour);
    }

    public function test_composite_get_returns_null_when_date_is_null(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => null,
            'SHTIME' => 73733,
        ]);

        $this->assertNull($result);
    }

    public function test_composite_get_defaults_time_to_midnight_when_null(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => null,
        ]);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(0, $result->hour);
        $this->assertEquals(0, $result->minute);
        $this->assertEquals(0, $result->second);
    }

    public function test_composite_get_with_format(): void
    {
        $cast = new IbmDateTime('datetime');

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 143052,
        ]);

        $this->assertEquals('2025-12-17 14:30:52', $result);
    }

    public function test_composite_get_with_full_time(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 143052,
        ]);

        $this->assertEquals(14, $result->hour);
        $this->assertEquals(30, $result->minute);
        $this->assertEquals(52, $result->second);
    }

    // ==================== SINGLE COLUMN SET TESTS ====================

    public function test_single_set_from_carbon(): void
    {
        $cast = new IbmDateTime();
        $dt = Carbon::create(2025, 12, 17, 7, 37, 33);

        $result = $cast->set($this->model, 'SHDTTM', $dt, []);

        $this->assertEquals(20251217073733, $result);
    }

    public function test_single_set_from_string(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->set($this->model, 'SHDTTM', '2025-12-17 14:30:52', []);

        $this->assertEquals(20251217143052, $result);
    }

    public function test_single_set_from_integer(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->set($this->model, 'SHDTTM', 20251217073733, []);

        $this->assertEquals(20251217073733, $result);
    }

    public function test_single_set_null_returns_null(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->set($this->model, 'SHDTTM', null, []);

        $this->assertNull($result);
    }

    // ==================== COMPOSITE COLUMN SET TESTS ====================

    public function test_composite_set_from_carbon(): void
    {
        $cast = new IbmDateTime();
        $dt = Carbon::create(2025, 12, 17, 7, 37, 33);

        $result = $cast->set($this->model, 'SHDATE,SHTIME', $dt, []);

        $this->assertIsArray($result);
        $this->assertEquals(20251217, $result['SHDATE']);
        $this->assertEquals(73733, $result['SHTIME']);
    }

    public function test_composite_set_from_string(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->set($this->model, 'SHDATE,SHTIME', '2025-12-17 14:30:52', []);

        $this->assertIsArray($result);
        $this->assertEquals(20251217, $result['SHDATE']);
        $this->assertEquals(143052, $result['SHTIME']);
    }

    public function test_composite_set_null_returns_array_of_nulls(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->set($this->model, 'SHDATE,SHTIME', null, []);

        $this->assertIsArray($result);
        $this->assertNull($result['SHDATE']);
        $this->assertNull($result['SHTIME']);
    }

    // ==================== CUSTOM INPUT FORMAT TESTS ====================

    public function test_custom_input_formats(): void
    {
        // Two args = custom date/time input formats
        $cast = new IbmDateTime('Ymd', 'His');

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 73733,
        ]);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(2025, $result->year);
        $this->assertEquals(7, $result->hour);
    }

    public function test_custom_input_formats_with_output_format(): void
    {
        // Three args = custom date input, time input, output format
        $cast = new IbmDateTime('Ymd', 'His', 'Y-m-d H:i:s');

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 20251217,
            'SHTIME' => 143052,
        ]);

        $this->assertEquals('2025-12-17 14:30:52', $result);
    }

    // ==================== INVALID DATA TESTS ====================

    public function test_composite_invalid_date_returns_null(): void
    {
        $cast = new IbmDateTime();

        $result = $cast->get($this->model, 'SHDATE,SHTIME', null, [
            'SHDATE' => 999,  // Too short
            'SHTIME' => 73733,
        ]);

        $this->assertNull($result);
    }

    public function test_composite_key_must_have_exactly_two_columns(): void
    {
        $cast = new IbmDateTime();

        $this->expectException(\InvalidArgumentException::class);

        $cast->get($this->model, 'A,B,C', null, []);
    }
}
