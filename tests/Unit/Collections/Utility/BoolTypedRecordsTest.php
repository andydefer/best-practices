<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections\Utility;

use AndyDefer\BestPractices\Collections\Utility\BoolTypedRecords;
use PHPUnit\Framework\TestCase;

final class BoolTypedRecordsTest extends TestCase
{
    public function test_constructor_creates_empty_collection(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertTrue($collection->isEmpty());
        $this->assertSame(['bool'], $collection->getAllowedTypes());
    }

    public function test_add_bools(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $this->assertCount(3, $collection);
        $this->assertSame([true, false, true], $collection->toArray());
    }

    public function test_add_throws_exception_for_non_bool(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $collection = new BoolTypedRecords;
        $collection->add('hello'); // string not allowed
    }

    public function test_true_only_returns_only_true_values(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true)->add(false)->add(true);

        $result = $collection->trueOnly();

        $this->assertNotSame($collection, $result);
        $this->assertCount(3, $result);
        $this->assertSame([true, true, true], $result->toArray());
    }

    public function test_false_only_returns_only_false_values(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true)->add(false)->add(true);

        $result = $collection->falseOnly();

        $this->assertNotSame($collection, $result);
        $this->assertCount(2, $result);
        $this->assertSame([false, false], $result->toArray());
    }

    public function test_count_true_returns_number_of_true_values(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true)->add(false)->add(true);

        $this->assertSame(3, $collection->countTrue());
    }

    public function test_count_true_on_empty_collection_returns_zero(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertSame(0, $collection->countTrue());
    }

    public function test_count_false_returns_number_of_false_values(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true)->add(false)->add(true);

        $this->assertSame(2, $collection->countFalse());
    }

    public function test_count_false_on_empty_collection_returns_zero(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertSame(0, $collection->countFalse());
    }

    public function test_all_true_returns_true_when_all_are_true(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(true)->add(true);

        $this->assertTrue($collection->allTrue());
    }

    public function test_all_true_returns_false_when_any_false_exists(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $this->assertFalse($collection->allTrue());
    }

    public function test_all_true_on_empty_collection_returns_true(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertTrue($collection->allTrue()); // vacuously true
    }

    public function test_all_false_returns_true_when_all_are_false(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(false)->add(false)->add(false);

        $this->assertTrue($collection->allFalse());
    }

    public function test_all_false_returns_false_when_any_true_exists(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(false)->add(true)->add(false);

        $this->assertFalse($collection->allFalse());
    }

    public function test_all_false_on_empty_collection_returns_true(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertTrue($collection->allFalse()); // vacuously true
    }

    public function test_any_true_returns_true_when_at_least_one_true(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(false)->add(true)->add(false);

        $this->assertTrue($collection->anyTrue());
    }

    public function test_any_true_returns_false_when_no_true(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(false)->add(false)->add(false);

        $this->assertFalse($collection->anyTrue());
    }

    public function test_any_true_on_empty_collection_returns_false(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertFalse($collection->anyTrue());
    }

    public function test_any_false_returns_true_when_at_least_one_false(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $this->assertTrue($collection->anyFalse());
    }

    public function test_any_false_returns_false_when_no_false(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(true)->add(true);

        $this->assertFalse($collection->anyFalse());
    }

    public function test_any_false_on_empty_collection_returns_false(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertFalse($collection->anyFalse());
    }

    public function test_chained_operations(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true)->add(false)->add(true);

        $trueCount = $collection
            ->trueOnly()
            ->count();

        $this->assertSame(3, $trueCount);
    }

    public function test_original_collection_is_not_modified(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $collection->trueOnly();

        $this->assertSame([true, false, true], $collection->toArray());
    }

    public function test_empty_collection_operations_return_empty(): void
    {
        $collection = new BoolTypedRecords;

        $this->assertTrue($collection->trueOnly()->isEmpty());
        $this->assertTrue($collection->falseOnly()->isEmpty());
        $this->assertSame(0, $collection->countTrue());
        $this->assertSame(0, $collection->countFalse());
        $this->assertTrue($collection->allTrue());
        $this->assertTrue($collection->allFalse());
        $this->assertFalse($collection->anyTrue());
        $this->assertFalse($collection->anyFalse());
    }

    public function test_json_serialize(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $json = json_encode($collection);

        $this->assertSame('[true,false,true]', $json);
    }

    public function test_to_array_returns_items(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $this->assertSame([true, false, true], $collection->toArray());
    }

    public function test_count_method_returns_number_of_items(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true)->add(false)->add(true);

        $this->assertSame(3, $collection->count());
    }

    public function test_is_empty_returns_false_for_non_empty_collection(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true);

        $this->assertFalse($collection->isEmpty());
    }

    public function test_is_not_empty_returns_true_for_non_empty_collection(): void
    {
        $collection = new BoolTypedRecords;
        $collection->add(true);

        $this->assertTrue($collection->isNotEmpty());
    }
}
