<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections\Utility;

use AndyDefer\BestPractices\Collections\Utility\FloatTypedRecords;
use PHPUnit\Framework\TestCase;

final class FloatTypedRecordsTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new FloatTypedRecords;

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(['float'], $collection->getAllowedTypes());
    }

    public function test_add_floats(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.5)->add(2.7)->add(3.9);

        $this->assertCount(3, $collection);
        $this->assertSame([1.5, 2.7, 3.9], $collection->toArray());
    }

    public function test_add_throws_exception_for_non_float(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $collection = new FloatTypedRecords;
        $collection->add('hello');
    }

    public function test_round_rounds_numbers_to_specified_precision(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.234)->add(2.567)->add(3.891);

        $result = $collection->round(2);

        $this->assertNotSame($collection, $result);
        $this->assertSame([1.23, 2.57, 3.89], $result->toArray());
    }

    public function test_round_without_precision_rounds_to_integer(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.4)->add(2.6)->add(3.5);

        $result = $collection->round();

        $this->assertSame([1.0, 3.0, 4.0], $result->toArray());
    }

    public function test_ceil_rounds_up_to_nearest_integer(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.2)->add(2.7)->add(3.1);

        $result = $collection->ceil();

        $this->assertSame([2.0, 3.0, 4.0], $result->toArray());
    }

    public function test_floor_rounds_down_to_nearest_integer(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.2)->add(2.7)->add(3.1);

        $result = $collection->floor();

        $this->assertSame([1.0, 2.0, 3.0], $result->toArray());
    }

    public function test_format_formats_numbers_with_decimals(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.234)->add(2.567)->add(3.891);

        $result = $collection->format(2);

        $this->assertSame([1.23, 2.57, 3.89], $result->toArray());
    }

    public function test_positive_returns_only_positive_numbers(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(-1.5)->add(2.7)->add(-3.9)->add(4.2)->add(0.0);

        $result = $collection->positive();

        $this->assertSame([2.7, 4.2], $result->toArray());
    }

    public function test_negative_returns_only_negative_numbers(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(-1.5)->add(2.7)->add(-3.9)->add(4.2)->add(0.0);

        $result = $collection->negative();

        $this->assertSame([-1.5, -3.9], $result->toArray());
    }

    public function test_between_returns_numbers_in_range(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.5)->add(2.7)->add(3.9)->add(5.1)->add(6.3);

        $result = $collection->between(2.0, 5.0);

        $this->assertSame([2.7, 3.9], $result->toArray());
    }

    public function test_average_calculates_mean(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(10.5)->add(20.3)->add(30.2);

        $this->assertEquals(20.333333333333332, $collection->average());
    }

    public function test_range_generates_sequence(): void
    {
        $collection = FloatTypedRecords::range(1.0, 5.0, 1.0);

        $this->assertCount(5, $collection);
        $this->assertSame([1.0, 2.0, 3.0, 4.0, 5.0], $collection->toArray());
    }

    public function test_range_with_negative_step(): void
    {
        $collection = FloatTypedRecords::range(5.0, 1.0, -1.0);

        $this->assertCount(5, $collection);
        $this->assertSame([5.0, 4.0, 3.0, 2.0, 1.0], $collection->toArray());
    }

    public function test_chained_operations(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(-1.5)->add(2.7)->add(-3.9)->add(4.2)->add(5.5);

        $result = $collection
            ->positive()           // [2.7, 4.2, 5.5]
            ->between(3.0, 5.0)    // [4.2] (5.5 > 5.0, donc exclu)
            ->round(0);            // [4.0]

        $this->assertSame([4.0], $result->toArray());
    }

    public function test_empty_collection_operations_return_empty(): void
    {
        $collection = new FloatTypedRecords;

        $this->assertTrue($collection->positive()->isEmpty());
        $this->assertTrue($collection->negative()->isEmpty());
        $this->assertTrue($collection->round()->isEmpty());
        $this->assertTrue($collection->ceil()->isEmpty());
        $this->assertTrue($collection->floor()->isEmpty());
        $this->assertTrue($collection->format()->isEmpty());
        $this->assertSame(0.0, $collection->average());
    }

    public function test_json_serialize(): void
    {
        $collection = new FloatTypedRecords;
        $collection->add(1.5)->add(2.7)->add(3.9);

        $json = json_encode($collection);

        $this->assertSame('[1.5,2.7,3.9]', $json);
    }
}
