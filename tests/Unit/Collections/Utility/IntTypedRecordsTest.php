<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections\Utility;

use AndyDefer\BestPractices\Collections\Utility\IntTypedRecords;
use PHPUnit\Framework\TestCase;

final class IntTypedRecordsTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new IntTypedRecords();

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(['int'], $collection->getAllowedTypes());
    }

    public function test_add_ints(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(1)->add(2)->add(3);

        $this->assertCount(3, $collection);
        $this->assertSame([1, 2, 3], $collection->toArray());
    }

    public function test_add_throws_exception_for_non_int(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $collection = new IntTypedRecords();
        $collection->add('hello');
    }

    public function test_zero_filters_only_zero_values(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(-5)->add(0)->add(3)->add(0)->add(8);

        $result = $collection->zero();

        $this->assertNotSame($collection, $result);
        $this->assertSame([0, 0], $result->toArray());
    }

    public function test_nonNegative_filters_non_negative_values(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(-5)->add(0)->add(3)->add(8)->add(-2);

        $result = $collection->nonNegative();

        $this->assertSame([0, 3, 8], $result->toArray());
    }

    public function test_even_filters_even_numbers(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(1)->add(2)->add(3)->add(4)->add(5);

        $result = $collection->even();

        $this->assertSame([2, 4], $result->toArray());
    }

    public function test_odd_filters_odd_numbers(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(1)->add(2)->add(3)->add(4)->add(5);

        $result = $collection->odd();

        $this->assertSame([1, 3, 5], $result->toArray());
    }

    public function test_median_with_odd_count(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(10)->add(40)->add(20)->add(30)->add(50);

        $this->assertSame(30.0, $collection->median());
    }

    public function test_median_with_even_count(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(10)->add(40)->add(20)->add(30);

        $this->assertSame(25.0, $collection->median());
    }

    public function test_median_on_empty_collection_returns_zero(): void
    {
        $collection = new IntTypedRecords();

        $this->assertSame(0.0, $collection->median());
    }

    public function test_median_on_single_item_returns_that_item(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(42);

        $this->assertSame(42.0, $collection->median());
    }

    public function test_chained_operations(): void
    {
        $collection = new IntTypedRecords();
        $collection->add(-5)->add(0)->add(3)->add(8)->add(-2)->add(6);

        $result = $collection
            ->positive()
            ->even()
            ->between(2, 10);

        $this->assertSame([8, 6], $result->toArray());
    }
}
