<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections;

use AndyDefer\BestPractices\Records\AbstractRecord;
use ArrayAccess;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Stringable;

/**
 * Type-safe collection for records and scalar values.
 *
 * @template TValue of object|string|int|float|bool
 */
interface TypedRecordsInterface extends ArrayAccess, IteratorAggregate, Countable, Stringable, JsonSerializable
{
    /**
     * Add one or multiple items.
     *
     * @param TValue ...$items
     * @return TypedRecords<TValue>
     */
    public function add(int|string|float|bool|null|AbstractRecord|TypedRecords ...$items): TypedRecords;

    /**
     * Convert the collection to a plain array.
     *
     * Returns all items as a native PHP array.
     *
     * @return array<TValue>
     */
    public function toArray(): array;


    /**
     * Get all items as a new TypedRecords collection.
     *
     * @return TypedRecords<TValue>
     */
    public function all(): TypedRecords;

    /**
     * Get the allowed types as array.
     *
     * @return array<string>
     */
    public function getAllowedTypes(): array;

    /**
     * Check if empty.
     */
    public function isEmpty(): bool;

    /**
     * Check if not empty.
     */
    public function isNotEmpty(): bool;

    /**
     * Map items to a new collection.
     *
     * @template TReturn
     * @param Closure(TValue): TReturn $callback
     * @return TypedRecords<TReturn>
     */
    public function map(Closure $callback): TypedRecords;

    /**
     * Filter items.
     *
     * @param Closure(TValue): bool $callback
     * @return TypedRecords<TValue>
     */
    public function filter(Closure $callback): TypedRecords;

    /**
     * Reject items.
     *
     * @param Closure(TValue): bool $callback
     * @return TypedRecords<TValue>
     */
    public function reject(Closure $callback): TypedRecords;

    /**
     * Execute callback on each item.
     *
     * @param Closure(TValue): void $callback
     * @return TypedRecords<TValue>
     */
    public function each(Closure $callback): TypedRecords;

    /**
     * Get first item.
     *
     * @return TValue|null
     */
    public function firstItem(): null|int|string|float|bool|AbstractRecord|TypedRecords;

    /**
     * Get first n items as new collection.
     *
     * @return TypedRecords<TValue>
     */
    public function first(int $limit): TypedRecords;

    /**
     * Get last item.
     *
     * @return TValue|null
     */
    public function lastItem(): null|int|string|float|bool|AbstractRecord|TypedRecords;

    /**
     * Get last n items as new collection.
     *
     * @return TypedRecords<TValue>
     */
    public function last(int $limit): TypedRecords;
    /**
     * Sort the collection.
     *
     * @return TypedRecords<TValue>
     */
    public function sort(int $flags = SORT_REGULAR): TypedRecords;

    /**
     * Sort by a callback or key.
     *
     * @param Closure(TValue): mixed|string $callback
     * @return TypedRecords<TValue>
     */
    public function sortBy(Closure|string $callback, bool $descending = false): TypedRecords;

    /**
     * Reverse the order.
     *
     * @return TypedRecords<TValue>
     */
    public function reverse(): TypedRecords;

    /**
     * Shuffle items.
     *
     * @return TypedRecords<TValue>
     */
    public function shuffle(): TypedRecords;

    /**
     * Calculate sum.
     *
     * @param Closure(TValue): int|float|null $callback
     * @return int|float
     */
    public function sum(?Closure $callback = null): int|float;

    /**
     * Calculate average.
     *
     * @param Closure(TValue): int|float|null $callback
     * @return float|null
     */
    public function avg(?Closure $callback = null): ?float;

    /**
     * Get maximum value.
     *
     * @param Closure(TValue): int|float|string|null $callback
     * @return int|float|string|null
     */
    public function max(?Closure $callback = null): int|float|string|null;

    /**
     * Get minimum value.
     *
     * @param Closure(TValue): int|float|string|null $callback
     * @return int|float|string|null
     */
    public function min(?Closure $callback = null): int|float|string|null;

    /**
     * Check if contains a value.
     *
     * @return bool
     */
    public function contains(int|string|float|bool|null|AbstractRecord|TypedRecords $value): bool;

    /**
     * Get only items of a specific type.
     *
     * @template T of object|string|int|float|bool
     * @param class-string<AbstractRecord>|string $type
     * @return TypedRecords<T>
     */
    public function ofType(string $type): TypedRecords;

    /**
     * Get items except those of a specific type.
     *
     * @return TypedRecords<TValue>
     */
    public function exceptType(string $type): TypedRecords;

    /**
     * Get distinct types present in the collection.
     *
     * @return TypedRecords<string>
     */
    public function getTypes(): TypedRecords;

    /**
     * Get items that are records.
     *
     * @return TypedRecords<AbstractRecord>
     */
    public function records(): TypedRecords;

    /**
     * Get items that are scalar values.
     *
     * @return TypedRecords<int|string|float|bool|null>
     */
    public function scalars(): TypedRecords;

    /**
     * Get items of a specific record class.
     *
     * @template TRecord of AbstractRecord
     * @param class-string<TRecord> $recordClass
     * @return TypedRecords<TRecord>
     */
    public function ofRecord(string $recordClass): TypedRecords;

    /**
     * Get items that are instances of any record type.
     *
     * @return TypedRecords<AbstractRecord>
     */
    public function anyRecord(): TypedRecords;

    /**
     * Filter items where property equals value (for records only).
     *
     * @return TypedRecords<TValue>
     */
    public function where(string $property, int|string|float|bool|null|AbstractRecord|TypedRecords $value): TypedRecords;

    /**
     * Filter items where property is not null (for records only).
     *
     * @return TypedRecords<TValue>
     */
    public function whereNotNull(string $property): TypedRecords;

    /**
     * Filter items where property is null (for records only).
     *
     * @return TypedRecords<TValue>
     */
    public function whereNull(string $property): TypedRecords;

    /**
     * Take first n items.
     *
     * @return TypedRecords<TValue>
     */
    public function take(int $limit): TypedRecords;

    /**
     * Skip first n items.
     *
     * @return TypedRecords<TValue>
     */
    public function skip(int $offset): TypedRecords;

    /**
     * Slice the collection.
     *
     * @return TypedRecords<TValue>
     */
    public function slice(int $offset, ?int $length = null): TypedRecords;

    /**
     * Get unique items.
     *
     * @return TypedRecords<TValue>
     */
    public function unique(?Closure $callback = null): TypedRecords;

    /**
     * Merge with another TypedRecords.
     *
     * @return TypedRecords<TValue>
     */
    public function merge(TypedRecords $collection): TypedRecords;

    /**
     * Intersect with another TypedRecords.
     *
     * @return TypedRecords<TValue>
     */
    public function intersect(TypedRecords $collection): TypedRecords;

    /**
     * Diff with another TypedRecords.
     *
     * @return TypedRecords<TValue>
     */
    public function diff(TypedRecords $collection): TypedRecords;

    /**
     * Flat map items.
     *
     * @template TReturn
     * @param Closure(TValue): TypedRecords<TReturn> $callback
     * @return TypedRecords<TReturn>
     */
    public function flatMap(Closure $callback): TypedRecords;

    /**
     * Reset keys to sequential integers.
     *
     * @return TypedRecords<TValue>
     */
    public function values(): TypedRecords;

    /**
     * Get items that are not null.
     *
     * @return TypedRecords<TValue>
     */
    public function filterNull(): TypedRecords;

    /**
     * Get every nth item.
     *
     * @return TypedRecords<TValue>
     */
    public function nth(int $step, int $offset = 0): TypedRecords;

    /**
     * Get random items.
     *
     * @return TypedRecords<TValue>
     */
    public function random(int $number = 1): TypedRecords;

    /**
     * Check if collection contains only items of a specific type.
     *
     * @return bool
     */
    public function isOnlyType(string $type): bool;

    /**
     * Check if collection contains any item of a specific type.
     *
     * @return bool
     */
    public function containsType(string $type): bool;

    /**
     * Check if all items are of the same type.
     *
     * @return bool
     */
    public function isHomogeneous(): bool;

    /**
     * Check if collection contains mixed types.
     *
     * @return bool
     */
    public function isHeterogeneous(): bool;

    /**
     * Assert that all items are of a specific type.
     *
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function assertAllOfType(string $type): TypedRecords;

    /**
     * Assert that collection is not empty.
     *
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function assertNotEmpty(): TypedRecords;

    /**
     * Assert that collection contains at least one item of given type.
     *
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function assertContainsType(string $type): TypedRecords;

    /**
     * Assert that all items implement an interface.
     *
     * @param class-string $interface
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function assertAllImplement(string $interface): TypedRecords;

    /**
     * Ensure all items are scalar values.
     *
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function assertScalar(): TypedRecords;

    /**
     * Ensure all items are records.
     *
     * @throws InvalidArgumentException
     * @return TypedRecords<AbstractRecord>
     */
    public function assertRecords(): TypedRecords;

    /**
     * Validate each item with a custom callback.
     *
     * @param Closure(TValue, int): bool $validator
     * @throws InvalidArgumentException
     * @return TypedRecords<TValue>
     */
    public function validate(Closure $validator): TypedRecords;
}
