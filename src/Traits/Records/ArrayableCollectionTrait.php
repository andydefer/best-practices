<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits\Records;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use Stringable;
use Traversable;

/**
 * Trait pour ajouter les capacités de tableau à TypedRecords.
 *
 * @template TValue of object|string|int|float|bool
 */
trait ArrayableCollectionTrait
{

    /**
     * Check if offset exists.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * Get item at offset.
     *
     * @param mixed $offset
     * @return TValue|null
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    /**
     * Set item at offset (with type validation).
     *
     * @param mixed $offset
     * @param TValue $value
     * @throws InvalidArgumentException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->validateItem($value);

        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * Unset item at offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    /**
     * Get iterator for foreach support.
     *
     * @return Traversable<int, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Count items.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Convert to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        $types = implode('|', $this->allowedTypes);
        return sprintf('TypedRecords(%s) with %d items', $types, count($this->items));
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->items;
    }
}
