<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections\Utility;

use AndyDefer\BestPractices\Collections\Utility\NumberTypedRecords;
use PHPUnit\Framework\TestCase;

final class NumberTypedRecordsTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new NumberTypedRecords;

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(['int', 'float'], $collection->getAllowedTypes());
    }

    public function test_add_mixed_numbers(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(1)->add(2.5)->add(3)->add(4.7);

        $this->assertCount(4, $collection);
        $this->assertSame([1, 2.5, 3, 4.7], $collection->toArray());
    }

    public function test_add_throws_exception_for_non_number(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $collection = new NumberTypedRecords;
        $collection->add('hello');
    }

    public function test_zero_filters_zero_values(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(0)->add(2.5)->add(0.0)->add(3)->add(-5);

        $result = $collection->zero();

        $this->assertNotSame($collection, $result);
        $this->assertSame([0, 0.0], $result->toArray());
    }

    public function test_non_negative_filters_non_negative_values(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(-5)->add(0)->add(2.5)->add(3)->add(-2.5);

        $result = $collection->nonNegative();

        $this->assertSame([0, 2.5, 3], $result->toArray());
    }

    public function test_positive_filters_positive_numbers(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(-5)->add(0)->add(2.5)->add(3)->add(-2.5);

        $result = $collection->positive();

        $this->assertSame([2.5, 3], $result->toArray());
    }

    public function test_negative_filters_negative_numbers(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(-5)->add(0)->add(2.5)->add(3)->add(-2.5);

        $result = $collection->negative();

        $this->assertSame([-5, -2.5], $result->toArray());
    }

    public function test_between_filters_numbers_in_range(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(1)->add(2.5)->add(5)->add(7.5)->add(10);

        $result = $collection->between(2, 8);

        $this->assertSame([2.5, 5, 7.5], $result->toArray());
    }

    public function test_average_calculates_mean(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(10)->add(20.5)->add(30);

        $this->assertEquals(20.166666666666668, $collection->average());
    }

    public function test_range_generates_sequence(): void
    {
        $collection = NumberTypedRecords::range(1, 5, 1);

        $this->assertSame([1, 2, 3, 4, 5], $collection->toArray());
    }

    public function test_range_with_float_step(): void
    {
        $collection = NumberTypedRecords::range(1.0, 3.0, 0.5);

        $this->assertSame([1.0, 1.5, 2.0, 2.5, 3.0], $collection->toArray());
    }

    public function test_range_with_negative_step(): void
    {
        $collection = NumberTypedRecords::range(5, 1, -1);

        $this->assertSame([5, 4, 3, 2, 1], $collection->toArray());
    }

    public function test_chained_operations(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(-5)->add(0)->add(2.5)->add(3)->add(8.7)->add(10);

        $result = $collection
            ->nonNegative()
            ->between(2, 9)
            ->positive();

        $this->assertSame([2.5, 3, 8.7], $result->toArray());
    }

    public function test_empty_collection_operations_return_empty(): void
    {
        $collection = new NumberTypedRecords;

        $this->assertTrue($collection->positive()->isEmpty());
        $this->assertTrue($collection->negative()->isEmpty());
        $this->assertTrue($collection->zero()->isEmpty());
        $this->assertTrue($collection->nonNegative()->isEmpty());
        $this->assertTrue($collection->between(1, 5)->isEmpty());
        $this->assertSame(0.0, $collection->average());
    }

    public function test_json_serialize(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(1)->add(2.5)->add(3);

        $json = json_encode($collection);

        $this->assertSame('[1,2.5,3]', $json);
    }

    public function test_to_array_returns_items(): void
    {
        $collection = new NumberTypedRecords;
        $collection->add(1)->add(2.5)->add(3);

        $this->assertSame([1, 2.5, 3], $collection->toArray());
    }
}
