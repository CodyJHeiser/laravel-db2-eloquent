<?php

namespace CodyJHeiser\Db2Eloquent\Tests\Unit\Casts;

use Carbon\Carbon;
use CodyJHeiser\Db2Eloquent\Casts\IbmTime;
use CodyJHeiser\Db2Eloquent\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class IbmTimeTest extends TestCase
{
    protected Model $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new class extends Model {};
    }

    public function test_get_returns_carbon_instance_by_default(): void
    {
        $cast = new IbmTime();

        $result = $cast->get($this->model, 'time', 73733, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(7, $result->hour);
        $this->assertEquals(37, $result->minute);
        $this->assertEquals(33, $result->second);
    }

    public function test_get_pads_leading_zeros(): void
    {
        $cast = new IbmTime();

        // 73733 => 07:37:33
        $result = $cast->get($this->model, 'time', 73733, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(7, $result->hour);
        $this->assertEquals(37, $result->minute);
        $this->assertEquals(33, $result->second);
    }

    public function test_get_handles_full_six_digit_time(): void
    {
        $cast = new IbmTime();

        // 143052 => 14:30:52
        $result = $cast->get($this->model, 'time', 143052, []);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals(14, $result->hour);
        $this->assertEquals(30, $result->minute);
        $this->assertEquals(52, $result->second);
    }

    public function test_get_handles_midnight(): void
    {
        $cast = new IbmTime();

        $result = $cast->get($this->model, 'time', 0, []);

        $this->assertNull($result);
    }

    public function test_get_returns_null_for_null_value(): void
    {
        $cast = new IbmTime();

        $this->assertNull($cast->get($this->model, 'time', null, []));
    }

    public function test_get_returns_null_for_empty_string(): void
    {
        $cast = new IbmTime();

        $this->assertNull($cast->get($this->model, 'time', '', []));
    }

    public function test_get_returns_null_for_invalid_hour(): void
    {
        $cast = new IbmTime();

        $this->assertNull($cast->get($this->model, 'time', 250000, []));
    }

    public function test_get_returns_null_for_invalid_minute(): void
    {
        $cast = new IbmTime();

        $this->assertNull($cast->get($this->model, 'time', 126000, []));
    }

    public function test_get_returns_null_for_invalid_second(): void
    {
        $cast = new IbmTime();

        $this->assertNull($cast->get($this->model, 'time', 120060, []));
    }

    public function test_get_with_format_returns_string(): void
    {
        $cast = new IbmTime('H:i:s');

        $result = $cast->get($this->model, 'time', 73733, []);

        $this->assertIsString($result);
        $this->assertEquals('07:37:33', $result);
    }

    public function test_get_with_time_shortcut(): void
    {
        $cast = new IbmTime('time');

        $result = $cast->get($this->model, 'time', 143052, []);

        $this->assertEquals('14:30:52', $result);
    }

    public function test_get_with_12h_shortcut(): void
    {
        $cast = new IbmTime('12h');

        $result = $cast->get($this->model, 'time', 143052, []);

        $this->assertEquals('2:30:52 PM', $result);
    }

    public function test_get_with_short_shortcut(): void
    {
        $cast = new IbmTime('short');

        $result = $cast->get($this->model, 'time', 73733, []);

        $this->assertEquals('07:37', $result);
    }

    public function test_get_with_custom_format(): void
    {
        $cast = new IbmTime('g:i A');

        $result = $cast->get($this->model, 'time', 143052, []);

        $this->assertEquals('2:30 PM', $result);
    }

    public function test_set_from_carbon_instance(): void
    {
        $cast = new IbmTime();
        $time = Carbon::createFromTime(7, 37, 33);

        $result = $cast->set($this->model, 'time', $time, []);

        $this->assertEquals(73733, $result);
    }

    public function test_set_from_carbon_with_leading_hour(): void
    {
        $cast = new IbmTime();
        $time = Carbon::createFromTime(14, 30, 52);

        $result = $cast->set($this->model, 'time', $time, []);

        $this->assertEquals(143052, $result);
    }

    public function test_set_from_integer(): void
    {
        $cast = new IbmTime();

        $result = $cast->set($this->model, 'time', 73733, []);

        $this->assertEquals(73733, $result);
    }

    public function test_set_from_string(): void
    {
        $cast = new IbmTime();

        $result = $cast->set($this->model, 'time', '07:37:33', []);

        $this->assertEquals(73733, $result);
    }

    public function test_set_from_null_returns_null(): void
    {
        $cast = new IbmTime();

        $result = $cast->set($this->model, 'time', null, []);

        $this->assertNull($result);
    }
}
