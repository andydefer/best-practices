<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections\Utility;

use AndyDefer\BestPractices\Collections\Utility\IntTypedRecords;
use PHPUnit\Framework\TestCase;

final class AbstractNumberTypedRecordsTest extends TestCase
{
    private function createCollection(): IntTypedRecords
    {
        return new IntTypedRecords;
    }

    public function test_positive_filters_positive_numbers(): void
    {
        $collection = $this->createCollection();
        $collection->add(-5)->add(0)->add(3)->add(8)->add(-2);

        $result = $collection->positive();

        $this->assertNotSame($collection, $result);
        $this->assertSame([3, 8], $result->toArray());
    }

    public function test_negative_filters_negative_numbers(): void
    {
        $collection = $this->createCollection();
        $collection->add(-5)->add(0)->add(3)->add(8)->add(-2);

        $result = $collection->negative();

        $this->assertSame([-5, -2], $result->toArray());
    }

    public function test_between_filters_numbers_in_range(): void
    {
        $collection = $this->createCollection();
        $collection->add(1)->add(5)->add(10)->add(15)->add(20);

        $result = $collection->between(5, 15);

        $this->assertSame([5, 10, 15], $result->toArray());
    }

    public function test_average_calculates_mean(): void
    {
        $collection = $this->createCollection();
        $collection->add(10)->add(20)->add(30);

        $this->assertEquals(20.0, $collection->average());
    }

    public function test_average_on_empty_collection_returns_zero(): void
    {
        $collection = $this->createCollection();

        $this->assertSame(0.0, $collection->average());
    }

    public function test_range_generates_sequence_ascending(): void
    {
        $collection = IntTypedRecords::range(1, 5, 1);

        $this->assertSame([1, 2, 3, 4, 5], $collection->toArray());
    }

    public function test_range_generates_sequence_descending(): void
    {
        $collection = IntTypedRecords::range(5, 1, -1);

        $this->assertSame([5, 4, 3, 2, 1], $collection->toArray());
    }

    public function test_range_with_step_zero_returns_empty(): void
    {
        $collection = IntTypedRecords::range(1, 5, 0);

        $this->assertTrue($collection->isEmpty());
    }

    public function test_range_with_single_value(): void
    {
        $collection = IntTypedRecords::range(5, 5, 1);

        $this->assertCount(1, $collection);
        $this->assertSame([5], $collection->toArray());
    }
}
