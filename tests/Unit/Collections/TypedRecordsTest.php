<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Unit\Collections;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserRole;
use AndyDefer\BestPractices\Tests\Fixtures\Enums\TestUserStatus;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestProductRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserRecord;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TypedRecordsTest extends TestCase
{
    // ========== TESTS DE CONSTRUCTEUR ET TYPES ==========

    public function test_construct_creates_collection_with_string_type(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $collection->add('hello', 'world');

        // Assert
        $this->assertCount(2, $collection->toArray());
        $this->assertSame(['hello', 'world'], $collection->toArray());
        $this->assertSame(['string'], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_int_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $collection->add(1, 2, 3);

        // Assert
        $this->assertCount(3, $collection->toArray());
        $this->assertSame([1, 2, 3], $collection->toArray());
        $this->assertSame(['int'], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_float_type(): void
    {
        // Arrange
        $collection = new TypedRecords('float');

        // Act
        $collection->add(1.5, 2.7, 3.9);

        // Assert
        $this->assertCount(3, $collection->toArray());
        $this->assertSame([1.5, 2.7, 3.9], $collection->toArray());
        $this->assertSame(['float'], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_bool_type(): void
    {
        // Arrange
        $collection = new TypedRecords('bool');

        // Act
        $collection->add(true, false, true);

        // Assert
        $this->assertCount(3, $collection->toArray());
        $this->assertSame([true, false, true], $collection->toArray());
        $this->assertSame(['bool'], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_record_type(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $product = new TestProductRecord(name: 'Product 1');

        // Act
        $collection->add($product);

        // Assert
        $this->assertCount(1, $collection->toArray());
        $this->assertSame([TestProductRecord::class], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_typed_records_type(): void
    {
        // Arrange
        $collection = new TypedRecords(TypedRecords::class);
        $inner = new TypedRecords('string');
        $inner->add('hello', 'world');

        // Act
        $collection->add($inner);

        // Assert
        $this->assertCount(1, $collection->toArray());
        $this->assertSame([TypedRecords::class], $collection->getAllowedTypes());
        $this->assertInstanceOf(TypedRecords::class, $collection->firstItem());
    }

    public function test_construct_creates_collection_with_multiple_scalar_types(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'float', 'string');

        // Act
        $collection->add(42, 3.14, 'text');

        // Assert
        $this->assertCount(3, $collection->toArray());
        $this->assertSame([42, 3.14, 'text'], $collection->toArray());
        $this->assertSame(['int', 'float', 'string'], $collection->getAllowedTypes());
    }

    public function test_construct_creates_collection_with_record_and_scalar_types(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class, 'string');
        $product = new TestProductRecord(name: 'Product');

        // Act
        $collection->add($product, 'Just a string');

        // Assert
        $this->assertCount(2, $collection->toArray());
        $this->assertInstanceOf(TestProductRecord::class, $collection->toArray()[0]);
        $this->assertSame('Just a string', $collection->toArray()[1]);
        $this->assertSame([TestProductRecord::class, 'string'], $collection->getAllowedTypes());
    }

    public function test_construct_throws_exception_when_no_types_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one type must be provided');

        // Act
        new TypedRecords();
    }

    public function test_construct_throws_exception_for_invalid_class_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Type "NonExistentClass" is not a valid class');

        // Act
        new TypedRecords('NonExistentClass');
    }

    public function test_construct_throws_exception_for_non_record_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must extend .*AbstractRecord/');

        // Act
        new TypedRecords(\stdClass::class);
    }

    // ========== TESTS DE VALIDATION DES ITEMS ==========

    public function test_add_accepts_value_matching_string_type(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $collection->add('hello');

        // Assert
        $this->assertSame(['hello'], $collection->toArray());
    }

    public function test_add_throws_exception_for_wrong_scalar_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected type(s) string, got int');

        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $collection->add(123);
    }

    public function test_add_throws_exception_for_wrong_record_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Expected type\(s\) .*TestProductRecord, got .*TestUserRecord/');

        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);

        // Act
        $collection->add(new TestUserRecord(name: 'User', email: 'user@example.com'));
    }

    public function test_add_accepts_value_matching_any_allowed_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'float', 'string');

        // Act
        $collection->add(42, 3.14, 'text');

        // Assert
        $this->assertCount(3, $collection->toArray());
        $this->assertSame([42, 3.14, 'text'], $collection->toArray());
    }

    public function test_add_accepts_null_when_null_is_allowed(): void
    {
        // Arrange
        $collection = new TypedRecords('string', 'null');

        // Act
        $collection->add(null, 'hello');

        // Assert
        $this->assertCount(2, $collection->toArray());
        $this->assertNull($collection->firstItem());
        $this->assertSame('hello', $collection->lastItem());
    }

    public function test_add_accepts_typed_records_collection(): void
    {
        // Arrange
        $collection = new TypedRecords(TypedRecords::class);
        $inner = new TypedRecords('string');
        $inner->add('hello');

        // Act
        $collection->add($inner);

        // Assert
        $this->assertCount(1, $collection->toArray());
        $this->assertInstanceOf(TypedRecords::class, $collection->firstItem());
    }

    public function test_add_throws_exception_for_disallowed_type_in_multi_type_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected type(s) int|float, got string');

        // Arrange
        $collection = new TypedRecords('int', 'float');

        // Act
        $collection->add('not allowed');
    }

    // ========== TESTS DE MAP ==========

    public function test_map_returns_new_collection_with_transformed_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $mapped = $collection->map(fn($item) => $item * 2);

        // Assert
        $this->assertCount(3, $mapped->toArray());
        $this->assertSame([2, 4, 6], $mapped->toArray());
        $this->assertSame(['int'], $mapped->getAllowedTypes());
        $this->assertNotSame($collection, $mapped);
    }

    public function test_map_transforms_int_to_string(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $mapped = $collection->map(fn($item) => "Number: {$item}");

        // Assert
        $this->assertSame(['string'], $mapped->getAllowedTypes());
        $this->assertSame(['Number: 1', 'Number: 2', 'Number: 3'], $mapped->toArray());
    }

    public function test_map_transforms_int_to_bool(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 0);

        // Act
        $mapped = $collection->map(fn($item) => $item > 0);

        // Assert
        $this->assertSame(['bool'], $mapped->getAllowedTypes());
        $this->assertSame([true, true, false], $mapped->toArray());
    }

    public function test_map_transforms_int_to_float(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $mapped = $collection->map(fn($item) => $item / 2.0);

        // Assert
        $this->assertSame(['float'], $mapped->getAllowedTypes());
        $this->assertSame([0.5, 1.0, 1.5], $mapped->toArray());
    }

    public function test_map_transforms_string_to_record(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('Product A', 'Product B');

        // Act
        $mapped = $collection->map(fn($item) => new TestProductRecord(name: $item));

        // Assert
        $this->assertSame([TestProductRecord::class], $mapped->getAllowedTypes());
        $this->assertCount(2, $mapped->toArray());
        $this->assertSame('Product A', $mapped->toArray()[0]->name);
        $this->assertSame('Product B', $mapped->toArray()[1]->name);
    }

    public function test_map_transforms_string_to_typed_records(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('hello', 'world');

        // Act
        $mapped = $collection->map(fn($item) => (new TypedRecords('string'))->add($item));

        // Assert
        $this->assertSame([TypedRecords::class], $mapped->getAllowedTypes());
        $this->assertCount(2, $mapped->toArray());
        $this->assertInstanceOf(TypedRecords::class, $mapped->firstItem());
    }

    public function test_map_on_empty_collection_returns_empty_collection_with_same_types(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $mapped = $collection->map(fn($item) => $item * 2);

        // Assert
        $this->assertSame(['int'], $mapped->getAllowedTypes());
        $this->assertEmpty($mapped->toArray());
    }

    // ========== TESTS DE FILTER ET REJECT ==========

    public function test_filter_returns_items_matching_callback(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $filtered = $collection->filter(fn($item) => $item > 3);

        // Assert
        $this->assertCount(2, $filtered->toArray());
        $this->assertSame([4, 5], $filtered->toArray());
        $this->assertSame(['int'], $filtered->getAllowedTypes());
    }

    public function test_filter_returns_empty_collection_when_no_match(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $filtered = $collection->filter(fn($item) => $item > 10);

        // Assert
        $this->assertEmpty($filtered->toArray());
        $this->assertSame(['int'], $filtered->getAllowedTypes());
    }

    public function test_reject_returns_items_not_matching_callback(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $rejected = $collection->reject(fn($item) => $item > 3);

        // Assert
        $this->assertCount(3, $rejected->toArray());
        $this->assertSame([1, 2, 3], $rejected->toArray());
    }

    // ========== TESTS DE EACH ==========

    public function test_each_executes_callback_on_each_item(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);
        $sum = 0;

        // Act
        $collection->each(function ($item) use (&$sum) {
            $sum += $item;
        });

        // Assert
        $this->assertSame(6, $sum);
    }

    public function test_each_returns_original_collection_for_chaining(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2);

        // Act
        $result = $collection->each(fn($item) => $item * 2);

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_each_on_empty_collection_does_nothing(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $called = false;

        // Act
        $collection->each(function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertFalse($called);
    }

    // ========== TESTS DE FIRST ET LAST ==========

    public function test_firstItem_returns_first_item(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('first', 'second', 'third');

        // Act
        $result = $collection->firstItem();

        // Assert
        $this->assertSame('first', $result);
    }

    public function test_firstItem_returns_null_when_collection_is_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $result = $collection->firstItem();

        // Assert
        $this->assertNull($result);
    }

    public function test_first_returns_new_collection_with_n_items(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('first', 'second', 'third');

        // Act
        $result = $collection->first(2);

        // Assert
        $this->assertInstanceOf(TypedRecords::class, $result);
        $this->assertCount(2, $result->toArray());
        $this->assertSame(['first', 'second'], $result->toArray());
    }

    public function test_lastItem_returns_last_item(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('first', 'second', 'third');

        // Act
        $result = $collection->lastItem();

        // Assert
        $this->assertSame('third', $result);
    }

    public function test_lastItem_returns_null_when_collection_is_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $result = $collection->lastItem();

        // Assert
        $this->assertNull($result);
    }

    public function test_last_returns_new_collection_with_n_items(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('first', 'second', 'third');

        // Act
        $result = $collection->last(2);

        // Assert
        $this->assertInstanceOf(TypedRecords::class, $result);
        $this->assertCount(2, $result->toArray());
        $this->assertSame(['second', 'third'], $result->toArray());
    }

    public function test_first_and_last_on_single_item_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('only');

        // Act & Assert
        $this->assertSame('only', $collection->firstItem());
        $this->assertSame('only', $collection->lastItem());
    }

    // ========== TESTS DE SORT ==========

    public function test_sort_orders_items_ascending(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(3, 1, 2);

        // Act
        $sorted = $collection->sort();

        // Assert
        $this->assertSame([1, 2, 3], $sorted->toArray());
        $this->assertNotSame($collection, $sorted);
    }

    public function test_sort_with_flags_respects_case(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('b', 'A', 'c');

        // Act
        $sorted = $collection->sort();

        // Assert
        $this->assertSame(['A', 'b', 'c'], $sorted->toArray());
    }

    // ========== TESTS DE SORT_BY ==========

    public function test_sortBy_orders_using_callback(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(name: 'Product C', price: 300),
            new TestProductRecord(name: 'Product A', price: 100),
            new TestProductRecord(name: 'Product B', price: 200)
        );

        // Act
        $sorted = $collection->sortBy(fn($item) => $item->name);

        // Assert
        $this->assertSame('Product A', $sorted->toArray()[0]->name);
        $this->assertSame('Product B', $sorted->toArray()[1]->name);
        $this->assertSame('Product C', $sorted->toArray()[2]->name);
    }

    public function test_sortBy_orders_using_string_key(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(name: 'Product C', price: 300),
            new TestProductRecord(name: 'Product A', price: 100),
            new TestProductRecord(name: 'Product B', price: 200)
        );

        // Act
        $sorted = $collection->sortBy('price');

        // Assert
        $this->assertSame(100, $sorted->toArray()[0]->price);
        $this->assertSame(200, $sorted->toArray()[1]->price);
        $this->assertSame(300, $sorted->toArray()[2]->price);
    }

    public function test_sortBy_descending_orders_descending(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $sorted = $collection->sortBy(fn($item) => $item, true);

        // Assert
        $this->assertSame([3, 2, 1], $sorted->toArray());
    }

    // ========== TESTS DE REVERSE ==========

    public function test_reverse_reverses_order_of_items(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('first', 'second', 'third');

        // Act
        $reversed = $collection->reverse();

        // Assert
        $this->assertSame(['third', 'second', 'first'], $reversed->toArray());
        $this->assertNotSame($collection, $reversed);
    }

    public function test_reverse_on_empty_collection_returns_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $reversed = $collection->reverse();

        // Assert
        $this->assertEmpty($reversed->toArray());
    }

    public function test_reverse_on_single_item_returns_same_item(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('only');

        // Act
        $reversed = $collection->reverse();

        // Assert
        $this->assertSame(['only'], $reversed->toArray());
    }

    // ========== TESTS DE SHUFFLE ==========

    public function test_shuffle_returns_collection_with_same_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $shuffled = $collection->shuffle();

        // Assert
        $this->assertCount(5, $shuffled->toArray());
        $this->assertEmpty(array_diff($collection->toArray(), $shuffled->toArray()));
        $this->assertNotSame($collection, $shuffled);
    }

    // ========== TESTS DE SUM ==========

    public function test_sum_returns_sum_of_int_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(10, 20, 30);

        // Act
        $result = $collection->sum();

        // Assert
        $this->assertSame(60, $result);
    }

    public function test_sum_uses_callback_when_provided(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(price: 100),
            new TestProductRecord(price: 200),
            new TestProductRecord(price: 300)
        );

        // Act
        $result = $collection->sum(fn($item) => $item->price);

        // Assert
        $this->assertSame(600, $result);
    }

    public function test_sum_on_empty_collection_returns_zero(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $result = $collection->sum();

        // Assert
        $this->assertSame(0, $result);
    }

    public function test_sum_on_float_collection_returns_float(): void
    {
        // Arrange
        $collection = new TypedRecords('float');
        $collection->add(1.5, 2.5, 3.0);

        // Act
        $result = $collection->sum();

        // Assert
        $this->assertSame(7.0, $result);
    }

    // ========== TESTS DE AVG ==========

    public function test_avg_returns_average_of_int_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(10, 20, 30);

        // Act
        $result = $collection->avg();

        // Assert
        $this->assertSame(20.0, $result);
    }

    public function test_avg_uses_callback_when_provided(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(price: 100),
            new TestProductRecord(price: 200),
            new TestProductRecord(price: 300)
        );

        // Act
        $result = $collection->avg(fn($item) => $item->price);

        // Assert
        $this->assertSame(200.0, $result);
    }

    public function test_avg_returns_null_when_collection_is_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $result = $collection->avg();

        // Assert
        $this->assertNull($result);
    }

    // ========== TESTS DE MAX ET MIN ==========

    public function test_max_returns_maximum_int_value(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(10, 30, 20);

        // Act
        $result = $collection->max();

        // Assert
        $this->assertSame(30, $result);
    }

    public function test_max_uses_callback_when_provided(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(price: 100),
            new TestProductRecord(price: 300),
            new TestProductRecord(price: 200)
        );

        // Act
        $result = $collection->max(fn($item) => $item->price);

        // Assert
        $this->assertSame(300, $result);
    }

    public function test_max_returns_null_when_collection_is_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $result = $collection->max();

        // Assert
        $this->assertNull($result);
    }

    public function test_min_returns_minimum_int_value(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(10, 30, 20);

        // Act
        $result = $collection->min();

        // Assert
        $this->assertSame(10, $result);
    }

    public function test_min_uses_callback_when_provided(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(price: 100),
            new TestProductRecord(price: 300),
            new TestProductRecord(price: 200)
        );

        // Act
        $result = $collection->min(fn($item) => $item->price);

        // Assert
        $this->assertSame(100, $result);
    }

    public function test_min_returns_null_when_collection_is_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $result = $collection->min();

        // Assert
        $this->assertNull($result);
    }

    // ========== TESTS DE CONTAINS ==========

    public function test_contains_returns_true_when_value_exists(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('apple', 'banana', 'cherry');

        // Act & Assert
        $this->assertTrue($collection->contains('banana'));
    }

    public function test_contains_returns_false_when_value_not_exists(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('apple', 'banana', 'cherry');

        // Act & Assert
        $this->assertFalse($collection->contains('grape'));
    }

    public function test_contains_works_with_record_type(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product A');
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product);

        // Act & Assert
        $this->assertTrue($collection->contains($product));
    }

    // ========== TESTS D'ETAT ==========

    public function test_isEmpty_returns_true_for_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act & Assert
        $this->assertTrue($collection->isEmpty());
    }

    public function test_isEmpty_returns_false_for_non_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('hello');

        // Act & Assert
        $this->assertFalse($collection->isEmpty());
    }

    public function test_isNotEmpty_returns_true_for_non_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('hello');

        // Act & Assert
        $this->assertTrue($collection->isNotEmpty());
    }

    public function test_isNotEmpty_returns_false_for_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act & Assert
        $this->assertFalse($collection->isNotEmpty());
    }

    // ========== TESTS DE COUNT ET ALL ==========

    public function test_count_returns_number_of_items(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('a', 'b', 'c');

        // Act & Assert
        $this->assertSame(3, $collection->count());
    }

    public function test_all_returns_all_items_as_array(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('a', 'b');

        // Act & Assert
        $this->assertSame(['a', 'b'], $collection->toArray());
    }

    public function test_all_returns_empty_array_for_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('string');

        // Act & Assert
        $this->assertSame([], $collection->toArray());
    }

    // ========== TESTS DE CHAÎNAGE ==========

    public function test_chained_operations_return_correct_result(): void
    {
        // Arrange, Act & Assert
        $result = (new TypedRecords('int'))
            ->add(5, 2, 8, 1, 3)
            ->filter(fn($item) => $item > 2)
            ->sort()
            ->map(fn($item) => $item * 2)
            ->toArray();

        $this->assertSame([6, 10, 16], $result);
    }

    public function test_complex_chain_with_multiple_transformations(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(
            new TestProductRecord(name: 'Product C', price: 300, isFeatured: false),
            new TestProductRecord(name: 'Product A', price: 100, isFeatured: true),
            new TestProductRecord(name: 'Product B', price: 200, isFeatured: true),
            new TestProductRecord(name: 'Product D', price: 400, isFeatured: false)
        );

        // Act
        $result = $collection
            ->filter(fn($item) => $item->isFeatured === true)
            ->sortBy('price')
            ->map(fn($item) => $item->name)
            ->toArray();

        // Assert
        $this->assertSame(['Product A', 'Product B'], $result);
    }

    // ========== TESTS D'IMMUTABILITÉ ==========

    public function test_operations_return_new_collections_and_preserve_original(): void
    {
        // Arrange
        $original = new TypedRecords('int');
        $original->add(1, 2, 3);

        // Act
        $filtered = $original->filter(fn($item) => $item > 1);
        $mapped = $original->map(fn($item) => $item * 2);
        $sorted = $original->sort();

        // Assert
        $this->assertNotSame($original, $filtered);
        $this->assertNotSame($original, $mapped);
        $this->assertNotSame($original, $sorted);
        $this->assertSame([1, 2, 3], $original->toArray());
    }

    // ========== TESTS DE TYPE SAFETY ==========

    public function test_filter_preserves_allowed_types(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class, 'string');
        $collection->add(new TestProductRecord(name: 'Product A'), 'just a string');

        // Act
        $filtered = $collection->filter(fn($item) => $item instanceof TestProductRecord);

        // Assert
        $this->assertSame([TestProductRecord::class, 'string'], $filtered->getAllowedTypes());
    }

    public function test_map_changes_allowed_types(): void
    {
        // Arrange
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add(new TestProductRecord(name: 'Product A'));

        // Act
        $mapped = $collection->map(fn($item) => $item->name);

        // Assert
        $this->assertSame(['string'], $mapped->getAllowedTypes());
    }

    // ========== TESTS DE OF TYPE ==========

    public function test_ofType_returns_only_items_of_specific_scalar_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string', 'float');
        $collection->add(42, 'hello', 3.14, 100, 'world');

        // Act
        $result = $collection->ofType('string');

        // Assert
        $this->assertSame(['string'], $result->getAllowedTypes());
        $this->assertSame(['hello', 'world'], $result->toArray());
    }

    public function test_ofType_returns_only_items_of_specific_record_type(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class);
        $collection->add($product, $user);

        // Act
        $result = $collection->ofType(TestProductRecord::class);

        // Assert
        $this->assertSame([TestProductRecord::class], $result->getAllowedTypes());
        $this->assertSame([$product], $result->toArray());
    }

    public function test_ofType_returns_empty_collection_when_no_match(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(42, 100);

        // Act
        $result = $collection->ofType('float');

        // Assert
        $this->assertSame(['float'], $result->getAllowedTypes());
        $this->assertEmpty($result->toArray());
    }

    // ========== TESTS DE EXCEPT TYPE ==========

    public function test_exceptType_removes_items_of_specific_scalar_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string', 'float');
        $collection->add(42, 100, 'hello', 3.14);

        // Act
        $result = $collection->exceptType('int');

        // Assert
        $this->assertSame(['string', 'float'], $result->getAllowedTypes());
        $this->assertSame(['hello', 3.14], $result->toArray());
    }

    public function test_exceptType_removes_items_of_specific_record_type(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class);
        $collection->add($product, $user);

        // Act
        $result = $collection->exceptType(TestUserRecord::class);

        // Assert
        $this->assertSame([TestProductRecord::class], $result->getAllowedTypes());
        $this->assertSame([$product], $result->toArray());
    }

    public function test_exceptType_throws_exception_when_excluding_all_allowed_types(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot exclude all allowed types');

        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(42);

        // Act
        $collection->exceptType('int');
    }

    // ========== TESTS DE GET TYPES ==========

    public function test_getTypes_returns_distinct_scalar_types_present(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string', 'float', 'bool');
        $collection->add(42, 'hello', 3.14, true);

        // Act
        $result = $collection->getTypes();

        // Assert
        $this->assertSame(['string'], $result->getAllowedTypes());
        $this->assertEqualsCanonicalizing(['int', 'string', 'float', 'bool'], $result->toArray());
    }

    public function test_getTypes_returns_distinct_record_types_present(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class);
        $collection->add($product, $user);

        // Act
        $result = $collection->getTypes();

        // Assert
        $this->assertSame(['string'], $result->getAllowedTypes());
        $this->assertEqualsCanonicalizing([TestProductRecord::class, TestUserRecord::class], $result->toArray());
    }

    public function test_getTypes_returns_empty_collection_when_no_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');

        // Act
        $result = $collection->getTypes();

        // Assert
        $this->assertEmpty($result->toArray());
    }

    // ========== TESTS DE RECORDS ET SCALARS ==========

    public function test_records_returns_only_abstract_record_items(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class, 'string');
        $collection->add($product, $user, 'hello');

        // Act
        $result = $collection->records();

        // Assert
        $this->assertSame([AbstractRecord::class], $result->getAllowedTypes());
        $this->assertSame([$product, $user], $result->toArray());
    }

    public function test_records_returns_empty_collection_when_no_records(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(42, 'hello');

        // Act
        $result = $collection->records();

        // Assert
        $this->assertSame([AbstractRecord::class], $result->getAllowedTypes());
        $this->assertEmpty($result->toArray());
    }

    public function test_scalars_returns_only_scalar_values(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $collection = new TypedRecords(TestProductRecord::class, 'int', 'string', 'float', 'bool');
        $collection->add($product, 42, 'hello', 3.14, true);

        // Act
        $result = $collection->scalars();

        // Assert
        $expected = ['int', 'string', 'float', 'bool', 'null'];
        $actual = $result->getAllowedTypes();

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertEqualsCanonicalizing([42, 'hello', 3.14, true], $result->toArray());
    }

    public function test_scalars_returns_empty_collection_when_no_scalars(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product);

        // Act
        $result = $collection->scalars();

        // Assert
        $this->assertEmpty($result->toArray());
    }

    // ========== TESTS DE OF RECORD ==========

    public function test_ofRecord_returns_only_items_of_specific_record_class(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class);
        $collection->add($product, $user);

        // Act
        $result = $collection->ofRecord(TestProductRecord::class);

        // Assert
        $this->assertSame([TestProductRecord::class], $result->getAllowedTypes());
        $this->assertSame([$product], $result->toArray());
    }

    public function test_ofRecord_throws_exception_for_non_record_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must extend/');

        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $collection->ofRecord(\stdClass::class);
    }

    // ========== TESTS DE ANY RECORD ==========

    public function test_anyRecord_returns_all_records_regardless_of_type(): void
    {
        // Arrange
        $product = new TestProductRecord(name: 'Product');
        $user = new TestUserRecord(name: 'User', email: 'user@example.com');
        $collection = new TypedRecords(TestProductRecord::class, TestUserRecord::class, 'string');
        $collection->add($product, $user, 'hello');

        // Act
        $result = $collection->anyRecord();

        // Assert
        $this->assertSame([AbstractRecord::class], $result->getAllowedTypes());
        $this->assertSame([$product, $user], $result->toArray());
    }

    // ========== TESTS DE WHERE ==========

    public function test_where_returns_items_with_matching_property_value(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A', price: 100);
        $product2 = new TestProductRecord(name: 'Product B', price: 200);
        $product3 = new TestProductRecord(name: 'Product C', price: 100);
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2, $product3);

        // Act
        $result = $collection->where('price', 100);

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertSame([$product1, $product3], $result->toArray());
    }

    public function test_where_returns_empty_collection_when_no_match(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A', price: 100);
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1);

        // Act
        $result = $collection->where('price', 999);

        // Assert
        $this->assertEmpty($result->toArray());
    }

    // ========== TESTS DE WHERE NOT NULL ==========

    public function test_whereNotNull_returns_items_with_non_null_property(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A', price: 100);
        $product2 = new TestProductRecord(name: 'Product B', price: null);
        $product3 = new TestProductRecord(name: 'Product C', price: 200);
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2, $product3);

        // Act
        $result = $collection->whereNotNull('price');

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertSame([$product1, $product3], $result->toArray());
    }

    // ========== TESTS DE WHERE NULL ==========

    public function test_whereNull_returns_items_with_null_property(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A', price: 100);
        $product2 = new TestProductRecord(name: 'Product B', price: null);
        $product3 = new TestProductRecord(name: 'Product C', price: null);
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2, $product3);

        // Act
        $result = $collection->whereNull('price');

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertSame([$product2, $product3], $result->toArray());
    }

    // ========== TESTS DE TAKE ==========

    public function test_take_returns_first_n_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $result = $collection->take(3);

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([1, 2, 3], $result->toArray());
    }

    public function test_take_returns_all_items_when_limit_exceeds_size(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $result = $collection->take(10);

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([1, 2, 3], $result->toArray());
    }

    // ========== TESTS DE SKIP ==========

    public function test_skip_returns_items_after_offset(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $result = $collection->skip(2);

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([3, 4, 5], $result->toArray());
    }

    public function test_skip_returns_empty_collection_when_offset_exceeds_size(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $result = $collection->skip(10);

        // Assert
        $this->assertEmpty($result->toArray());
    }

    // ========== TESTS DE SLICE ==========

    public function test_slice_returns_subset_of_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $result = $collection->slice(1, 3);

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([2, 3, 4], $result->toArray());
    }

    // ========== TESTS DE UNIQUE ==========

    public function test_unique_removes_duplicate_values(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 2, 3, 3, 3);

        // Act
        $result = $collection->unique();

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([1, 2, 3], $result->toArray());
    }

    public function test_unique_uses_callback_for_comparison(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A', price: 100);
        $product2 = new TestProductRecord(name: 'Product B', price: 100);
        $product3 = new TestProductRecord(name: 'Product C', price: 200);
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2, $product3);

        // Act
        $result = $collection->unique(fn($item) => $item->price);

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertSame([$product1, $product3], $result->toArray());
    }

    // ========== TESTS DE MERGE ==========

    public function test_merge_combines_two_collections(): void
    {
        // Arrange
        $collection1 = new TypedRecords('int');
        $collection1->add(1, 2);
        $collection2 = new TypedRecords('int');
        $collection2->add(3, 4);

        // Act
        $result = $collection1->merge($collection2);

        // Assert
        $this->assertCount(4, $result->toArray());
        $this->assertSame([1, 2, 3, 4], $result->toArray());
    }

    // ========== TESTS DE INTERSECT ==========

    public function test_intersect_returns_common_items(): void
    {
        // Arrange
        $collection1 = new TypedRecords('int');
        $collection1->add(1, 2, 3);
        $collection2 = new TypedRecords('int');
        $collection2->add(2, 3, 4);

        // Act
        $result = $collection1->intersect($collection2);

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertEqualsCanonicalizing([2, 3], $result->toArray());
    }

    // ========== TESTS DE DIFF ==========

    public function test_diff_returns_items_not_in_other_collection(): void
    {
        // Arrange
        $collection1 = new TypedRecords('int');
        $collection1->add(1, 2, 3, 4);
        $collection2 = new TypedRecords('int');
        $collection2->add(2, 4);

        // Act
        $result = $collection1->diff($collection2);

        // Assert
        $this->assertCount(2, $result->toArray());
        $this->assertEqualsCanonicalizing([1, 3], $result->toArray());
    }

    // ========== TESTS DE FLAT MAP ==========

    public function test_flatMap_flattens_nested_collections(): void
    {
        // Arrange
        $collection = new TypedRecords(TypedRecords::class);
        $inner1 = new TypedRecords('int');
        $inner1->add(1, 2);
        $inner2 = new TypedRecords('int');
        $inner2->add(3, 4);
        $collection->add($inner1, $inner2);

        // Act
        $result = $collection->flatMap(fn($item) => $item);

        // Assert
        $this->assertCount(4, $result->toArray());
        $this->assertSame([1, 2, 3, 4], $result->toArray());
    }

    // ========== TESTS DE VALUES ==========

    public function test_values_resets_keys_to_sequential_integers(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);
        $filtered = $collection->filter(fn($item) => $item > 1);

        // Act
        $result = $filtered->values();

        // Assert
        $this->assertSame([0, 1], array_keys($result->toArray()));
        $this->assertSame([2, 3], $result->toArray());
    }

    // ========== TESTS DE FILTER NULL ==========

    public function test_filterNull_removes_null_values(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'null');
        $collection->add(1, null, 2, null, 3);

        // Act
        $result = $collection->filterNull();

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertSame([1, 2, 3], $result->toArray());
    }

    // ========== TESTS DE NTH ==========

    public function test_nth_returns_every_nth_item(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5, 6);

        // Act
        $result = $collection->nth(2);

        // Assert
        $this->assertSame([1, 3, 5], $result->toArray());
    }

    public function test_nth_with_offset(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5, 6);

        // Act
        $result = $collection->nth(2, 1);

        // Assert
        $this->assertSame([2, 4, 6], $result->toArray());
    }

    public function test_nth_throws_exception_for_invalid_step(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Step must be greater than 0');

        // Arrange
        $collection = new TypedRecords('int');

        // Act
        $collection->nth(0);
    }

    // ========== TESTS DE RANDOM ==========

    public function test_random_returns_random_items(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3, 4, 5);

        // Act
        $result = $collection->random(3);

        // Assert
        $this->assertCount(3, $result->toArray());
        $this->assertEmpty(array_diff($result->toArray(), $collection->toArray()));
    }

    public function test_random_throws_exception_when_number_exceeds_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot get more random items than collection size');

        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2);

        // Act
        $collection->random(3);
    }

    // ========== TESTS DE IS ONLY TYPE ==========

    public function test_isOnlyType_returns_true_when_all_items_match_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act & Assert
        $this->assertTrue($collection->isOnlyType('int'));
    }

    public function test_isOnlyType_returns_false_when_any_item_differs(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 'hello', 2);

        // Act & Assert
        $this->assertFalse($collection->isOnlyType('int'));
    }

    public function test_isOnlyType_returns_true_for_empty_collection(): void
    {
        // Arrange
        $collection = new TypedRecords('int');

        // Act & Assert
        $this->assertTrue($collection->isOnlyType('int'));
    }

    // ========== TESTS DE CONTAINS TYPE ==========

    public function test_containsType_returns_true_when_type_exists(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act & Assert
        $this->assertTrue($collection->containsType('int'));
    }

    public function test_containsType_returns_false_when_type_not_exists(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act & Assert
        $this->assertFalse($collection->containsType('float'));
    }

    // ========== TESTS DE IS HOMOGENEOUS ==========

    public function test_isHomogeneous_returns_true_when_all_items_same_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act & Assert
        $this->assertTrue($collection->isHomogeneous());
    }

    public function test_isHomogeneous_returns_false_when_items_have_different_types(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 'hello', 2);

        // Act & Assert
        $this->assertFalse($collection->isHomogeneous());
    }

    // ========== TESTS DE IS HETEROGENEOUS ==========

    public function test_isHeterogeneous_returns_true_when_items_have_different_types(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 'hello', 2);

        // Act & Assert
        $this->assertTrue($collection->isHeterogeneous());
    }

    public function test_isHeterogeneous_returns_false_when_all_items_same_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act & Assert
        $this->assertFalse($collection->isHeterogeneous());
    }

    // ========== TESTS DE ASSERT ALL OF TYPE ==========

    public function test_assertAllOfType_returns_self_when_all_items_match_type(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $result = $collection->assertAllOfType('int');

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertAllOfType_throws_exception_when_any_item_differs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected all items to be of type "int"');

        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 'hello', 2);

        // Act
        $collection->assertAllOfType('int');
    }

    // ========== TESTS DE ASSERT NOT EMPTY ==========

    public function test_assertNotEmpty_returns_self_when_collection_not_empty(): void
    {
        // Arrange
        $collection = new TypedRecords('string');
        $collection->add('hello');

        // Act
        $result = $collection->assertNotEmpty();

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertNotEmpty_throws_exception_when_collection_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Collection is empty');

        // Arrange
        $collection = new TypedRecords('string');

        // Act
        $collection->assertNotEmpty();
    }

    // ========== TESTS DE ASSERT CONTAINS TYPE ==========

    public function test_assertContainsType_returns_self_when_type_exists(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act
        $result = $collection->assertContainsType('int');

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertContainsType_throws_exception_when_type_not_exists(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Collection does not contain type "float"');

        // Arrange
        $collection = new TypedRecords('int', 'string');
        $collection->add(1, 2, 3);

        // Act
        $collection->assertContainsType('float');
    }

    // ========== TESTS DE ASSERT ALL IMPLEMENT ==========

    public function test_assertAllImplement_returns_self_when_all_items_implement_interface(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A');
        $product2 = new TestProductRecord(name: 'Product B');
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2);

        // Act
        $result = $collection->assertAllImplement(AbstractRecord::class);

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertAllImplement_throws_exception_when_item_does_not_implement_interface(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Arrange
        $collection = new TypedRecords('int', TestProductRecord::class);
        $collection->add(42, new TestProductRecord(name: 'Product'));

        // Act
        $collection->assertAllImplement(AbstractRecord::class);
    }

    // ========== TESTS DE ASSERT SCALAR ==========

    public function test_assertScalar_returns_self_when_all_items_are_scalar(): void
    {
        // Arrange
        $collection = new TypedRecords('int', 'string', 'float', 'bool');
        $collection->add(1, 'hello', 3.14, true);

        // Act
        $result = $collection->assertScalar();

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertScalar_throws_exception_when_any_item_is_not_scalar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected scalar value');

        // Arrange
        $collection = new TypedRecords('int', TestProductRecord::class);
        $collection->add(1, new TestProductRecord(name: 'Product'));

        // Act
        $collection->assertScalar();
    }

    // ========== TESTS DE ASSERT RECORDS ==========

    public function test_assertRecords_returns_self_when_all_items_are_records(): void
    {
        // Arrange
        $product1 = new TestProductRecord(name: 'Product A');
        $product2 = new TestProductRecord(name: 'Product B');
        $collection = new TypedRecords(TestProductRecord::class);
        $collection->add($product1, $product2);

        // Act
        $result = $collection->assertRecords();

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_assertRecords_throws_exception_when_any_item_is_not_record(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected AbstractRecord');

        // Arrange
        $collection = new TypedRecords('int', TestProductRecord::class);
        $collection->add(1, new TestProductRecord(name: 'Product'));

        // Act
        $collection->assertRecords();
    }

    // ========== TESTS DE VALIDATE ==========

    public function test_validate_returns_self_when_all_items_pass_validation(): void
    {
        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, 3);

        // Act
        $result = $collection->validate(fn($item, $index) => $item > 0);

        // Assert
        $this->assertSame($collection, $result);
    }

    public function test_validate_throws_exception_when_any_item_fails_validation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed for item at index 2');

        // Arrange
        $collection = new TypedRecords('int');
        $collection->add(1, 2, -3, 4);

        // Act
        $collection->validate(fn($item, $index) => $item > 0);
    }
}
