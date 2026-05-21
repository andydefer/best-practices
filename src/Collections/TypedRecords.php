<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Traits\Records\ArrayableCollectionTrait;
use Closure;
use InvalidArgumentException;
use stdClass;
use UnitEnum;

/**
 * Type-safe collection for records and scalar values.
 *
 * Supports multiple allowed types at construction.
 *
 * @template TValue of object|string|int|float|bool
 */
class TypedRecords implements TypedRecordsInterface
{
    use ArrayableCollectionTrait;

    /**
     * @var array<TValue>
     */
    protected array $items = [];

    /**
     * @var array<class-string<AbstractRecord>|string>
     */
    private array $allowedTypes = [];

    /**
     * Mapping from PHP gettype() return values to our type names.
     */
    private const TYPE_MAPPING = [
        'integer' => 'int',
        'double' => 'float',
        'string' => 'string',
        'boolean' => 'bool',
        'NULL' => 'null',
        'object' => 'object',
    ];

    /**
     * @var array<string>|null
     */
    private static ?array $scalarTypes = null;

    /**
     * Get allowed scalar types from TYPE_MAPPING.
     *
     * @return array<string>
     */
    private static function getScalarTypes(): array
    {
        if (self::$scalarTypes === null) {
            self::$scalarTypes = array_values(self::TYPE_MAPPING);
        }

        return self::$scalarTypes;
    }

    /**
     * @param  class-string<AbstractRecord>|string  ...$types
     *
     * @throws InvalidArgumentException
     */
    public function __construct(...$types)
    {
        $this->validateTypes($types);
        $this->allowedTypes = $types;
    }

    /**
     * Normalize a type name from PHP gettype() to our internal representation.
     */
    private static function normalizeType(string $type): string
    {
        return self::TYPE_MAPPING[$type] ?? $type;
    }

    /**
     * Validate that all types are valid.
     *
     * @param  array<int, class-string<AbstractRecord>|string>  $types
     *
     * @throws InvalidArgumentException
     */
    private function validateTypes(array $types): void
    {
        if (empty($types)) {
            throw new InvalidArgumentException('At least one type must be provided');
        }

        foreach ($types as $type) {
            $this->validateSingleType($type);
        }
    }

    /**
     * Validate a single type.
     *
     * @param  class-string<AbstractRecord>|string  $type
     *
     * @throws InvalidArgumentException
     */
    private function validateSingleType(string $type): void
    {
        // Types scalaires autorisés
        if (in_array($type, self::getScalarTypes(), true)) {
            return;
        }

        // TypedRecords imbriqué
        if ($type === self::class) {
            return;
        }

        // AbstractRecord et ses descendants
        if ($type === AbstractRecord::class) {
            return;
        }

        // stdClass uniquement - pas d'autres objets !
        if ($type === stdClass::class) {
            return;
        }

        // Vérification pour les classes Record
        if (! class_exists($type)) {
            throw new InvalidArgumentException(sprintf('Type "%s" is not a valid class', $type));
        }

        if (! is_subclass_of($type, AbstractRecord::class)) {
            throw new InvalidArgumentException(sprintf(
                'Type "%s" must extend %s, be %s, be %s, or be a scalar (int, float, string, bool, null)',
                $type,
                AbstractRecord::class,
                self::class,
                stdClass::class
            ));
        }
    }

    /**
     * Check if a value matches any of the allowed types.
     */
    private function matchesAllowedType(mixed $value): bool
    {
        $valueType = self::normalizeType(gettype($value));

        foreach ($this->allowedTypes as $allowedType) {
            // Correspondance par type scalaire
            if ($valueType === $allowedType) {
                return true;
            }

            // Correspondance pour TypedRecords imbriqué
            if ($allowedType === self::class && $value instanceof self) {
                return true;
            }

            // Correspondance pour stdClass UNIQUEMENT
            if ($allowedType === stdClass::class && $value instanceof stdClass) {
                return true;
            }

            // Correspondance pour les Records
            if ($value instanceof $allowedType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the type name of a value for error messages.
     */
    private function getValueTypeName(mixed $value): string
    {
        if ($value instanceof self) {
            return self::class;
        }

        if ($value instanceof AbstractRecord) {
            return $value::class;
        }

        if ($value instanceof stdClass) {
            return stdClass::class;
        }

        if (is_object($value)) {
            return 'object('.$value::class.')';
        }

        return self::normalizeType(gettype($value));
    }

    /**
     * Validate that an item matches at least one allowed type.
     *
     * @param  TValue  $item
     *
     * @throws InvalidArgumentException
     */
    private function validateItem(mixed $item): void
    {
        // Interdiction formelle des Enums
        if ($item instanceof UnitEnum) {
            throw new InvalidArgumentException(sprintf(
                'Enum %s is not allowed in TypedRecords. Use its scalar value instead.',
                $item::class
            ));
        }

        // Interdiction des objets qui ne sont pas stdClass
        if (is_object($item) && ! ($item instanceof stdClass) && ! ($item instanceof AbstractRecord) && ! ($item instanceof self)) {
            throw new InvalidArgumentException(sprintf(
                'Object of type "%s" is not allowed in TypedRecords. Only stdClass, AbstractRecord, and TypedRecords are allowed.',
                $item::class
            ));
        }

        if (! $this->matchesAllowedType($item)) {
            $allowedTypesStr = implode('|', $this->allowedTypes);
            throw new InvalidArgumentException(sprintf(
                'Expected type(s) %s, got %s',
                $allowedTypesStr,
                $this->getValueTypeName($item)
            ));
        }
    }

    /**
     * Check if a value is a scalar.
     */
    private function isScalarValue(mixed $value): bool
    {
        return is_int($value) || is_string($value) || is_float($value) || is_bool($value) || $value === null;
    }

    // ==================== INTERFACE METHODS ====================

    /**
     * Add one or multiple items.
     *
     * @return TypedRecords<TValue>
     */
    final public function add(int|string|float|bool|null|AbstractRecord|TypedRecords|stdClass ...$items): static
    {
        foreach ($items as $item) {
            $this->validateItem($item);
            $this->items[] = $item;
        }

        return $this;
    }

    /**
     * Convert the collection to a plain array.
     *
     * Returns all items as a native PHP array.
     *
     * @return array<TValue>
     */
    final public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Get all items as a new TypedRecords collection.
     *
     * @return TypedRecords<TValue>
     */
    final public function all(): static
    {
        $result = new static(...$this->allowedTypes);
        $result->add(...$this->items);

        return $result;
    }

    /**
     * Get the allowed types as a array.
     *
     * @return array<string>
     */
    final public function getAllowedTypes(): array
    {
        return $this->allowedTypes;
    }

    final public function count(): int
    {
        return count($this->items);
    }

    final public function isEmpty(): bool
    {
        return empty($this->items);
    }

    final public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    final public function map(Closure $callback): static
    {
        if (empty($this->items)) {
            return new static(...$this->allowedTypes);
        }

        $mappedItems = [];

        foreach ($this->items as $item) {
            $mappedItems[] = $callback($item);
        }

        if (empty($mappedItems)) {
            return new static(...$this->allowedTypes);
        }

        $firstResult = $mappedItems[0];
        $returnType = match (true) {
            $firstResult instanceof self => self::class,
            $firstResult instanceof AbstractRecord => $firstResult::class,
            $firstResult instanceof stdClass => stdClass::class,
            is_int($firstResult) => 'int',
            is_float($firstResult) => 'float',
            is_string($firstResult) => 'string',
            is_bool($firstResult) => 'bool',
            $firstResult === null => 'null',
            default => throw new InvalidArgumentException('Map callback must return a scalar, Record, TypedRecords, or stdClass'),
        };

        $result = new static($returnType);

        foreach ($mappedItems as $item) {
            /** @var int|string|float|bool|null|AbstractRecord|TypedRecords|stdClass $item */
            $result->add($item);
        }

        return $result;
    }

    final public function filter(Closure $callback): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_values(array_filter($this->items, $callback));

        return $result;
    }

    final public function reject(Closure $callback): static
    {
        return $this->filter(fn ($item) => ! $callback($item));
    }

    final public function each(Closure $callback): static
    {
        foreach ($this->items as $item) {
            $callback($item);
        }

        return $this;
    }

    final public function firstItem(): null|int|string|float|bool|AbstractRecord|TypedRecords|stdClass
    {
        return $this->items[0] ?? null;
    }

    final public function first(int $limit): static
    {
        if ($limit <= 0) {
            return new static(...$this->allowedTypes);
        }

        $result = new static(...$this->allowedTypes);
        $result->items = array_slice($this->items, 0, $limit);

        return $result;
    }

    final public function lastItem(): null|int|string|float|bool|AbstractRecord|TypedRecords|stdClass
    {
        return empty($this->items) ? null : $this->items[array_key_last($this->items)];
    }

    final public function last(int $limit): static
    {
        if ($limit <= 0) {
            return new static(...$this->allowedTypes);
        }

        $result = new static(...$this->allowedTypes);
        $result->items = array_slice($this->items, -$limit, $limit);

        return $result;
    }

    final public function sort(int $flags = SORT_REGULAR): static
    {
        $items = $this->items;
        sort($items, $flags);

        $result = new static(...$this->allowedTypes);
        $result->items = $items;

        return $result;
    }

    final public function sortBy(Closure|string $callback, bool $descending = false): static
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($callback) {
            $valueA = is_callable($callback) ? $callback($a) : $a->{$callback};
            $valueB = is_callable($callback) ? $callback($b) : $b->{$callback};

            return $valueA <=> $valueB;
        });

        if ($descending) {
            $items = array_reverse($items);
        }

        $result = new static(...$this->allowedTypes);
        $result->items = $items;

        return $result;
    }

    final public function reverse(): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_reverse($this->items);

        return $result;
    }

    final public function shuffle(): static
    {
        $items = $this->items;
        shuffle($items);

        $result = new static(...$this->allowedTypes);
        $result->items = $items;

        return $result;
    }

    final public function sum(?Closure $callback = null): int|float
    {
        $values = $callback === null
            ? $this->items
            : array_map($callback, $this->items);

        return array_sum($values);
    }

    final public function avg(?Closure $callback = null): ?float
    {
        $count = count($this->items);

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    final public function max(?Closure $callback = null): int|float|string|null
    {
        $values = $callback === null
            ? $this->items
            : array_map($callback, $this->items);

        return empty($values) ? null : max($values);
    }

    final public function min(?Closure $callback = null): int|float|string|null
    {
        $values = $callback === null
            ? $this->items
            : array_map($callback, $this->items);

        return empty($values) ? null : min($values);
    }

    final public function contains(int|string|float|bool|null|AbstractRecord|TypedRecords|stdClass $value): bool
    {
        return in_array($value, $this->items, true);
    }

    final public function ofType(string $type): static
    {
        $result = new static($type);

        foreach ($this->items as $item) {
            $itemType = $this->getValueTypeName($item);
            if ($itemType === $type || ($item instanceof $type)) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function exceptType(string $type): static
    {
        $allowedTypes = array_values(array_filter(
            $this->allowedTypes,
            fn ($t) => $t !== $type
        ));

        if (empty($allowedTypes)) {
            throw new InvalidArgumentException('Cannot exclude all allowed types');
        }

        $result = new static(...$allowedTypes);

        foreach ($this->items as $item) {
            $itemType = $this->getValueTypeName($item);
            if ($itemType !== $type && ! ($item instanceof $type)) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function getTypes(): static
    {
        $types = [];

        foreach ($this->items as $item) {
            $type = $this->getValueTypeName($item);
            if (! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        $result = new static('string');
        foreach ($types as $type) {
            $result->add($type);
        }

        return $result;
    }

    final public function records(): static
    {
        $result = new static(AbstractRecord::class);

        foreach ($this->items as $item) {
            if ($item instanceof AbstractRecord) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function scalars(): static
    {
        $scalarTypes = self::getScalarTypes();

        $allowedScalarTypes = array_intersect($this->allowedTypes, $scalarTypes);

        if (empty($allowedScalarTypes)) {
            $result = new static(...$scalarTypes);
        } else {
            $result = new static(...$allowedScalarTypes);
        }

        foreach ($this->items as $item) {
            if ($this->isScalarValue($item)) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function ofRecord(string $recordClass): static
    {
        if (! is_subclass_of($recordClass, AbstractRecord::class) && $recordClass !== AbstractRecord::class) {
            throw new InvalidArgumentException(sprintf('%s must extend %s', $recordClass, AbstractRecord::class));
        }

        $result = new static($recordClass);

        foreach ($this->items as $item) {
            if ($item instanceof $recordClass) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function anyRecord(): static
    {
        return $this->records();
    }

    final public function where(string $property, int|string|float|bool|null|AbstractRecord|TypedRecords|stdClass $value): static
    {
        $result = new static(...$this->allowedTypes);

        foreach ($this->items as $item) {
            if (is_object($item) && property_exists($item, $property) && $item->{$property} === $value) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function whereNotNull(string $property): static
    {
        $result = new static(...$this->allowedTypes);

        foreach ($this->items as $item) {
            if (is_object($item) && property_exists($item, $property) && $item->{$property} !== null) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function whereNull(string $property): static
    {
        $result = new static(...$this->allowedTypes);

        foreach ($this->items as $item) {
            if (is_object($item) && property_exists($item, $property) && $item->{$property} === null) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function take(int $limit): static
    {
        return $this->first($limit);
    }

    final public function skip(int $offset): static
    {
        if ($offset <= 0) {
            return clone $this;
        }

        $result = new static(...$this->allowedTypes);
        $result->items = array_slice($this->items, $offset);

        return $result;
    }

    final public function slice(int $offset, ?int $length = null): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_slice($this->items, $offset, $length);

        return $result;
    }

    final public function unique(?Closure $callback = null): static
    {
        if ($callback !== null) {
            $mapped = array_map($callback, $this->items);
            $uniqueKeys = array_unique($mapped, SORT_REGULAR);
            $uniqueItems = array_intersect_key($this->items, $uniqueKeys);
        } else {
            $uniqueItems = array_unique($this->items, SORT_REGULAR);
        }

        $result = new static(...$this->allowedTypes);
        $result->items = array_values($uniqueItems);

        return $result;
    }

    final public function merge(TypedRecords $collection): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_merge($this->items, $collection->toArray());

        return $result;
    }

    final public function intersect(TypedRecords $collection): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_values(array_intersect($this->items, $collection->toArray()));

        return $result;
    }

    final public function diff(TypedRecords $collection): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_values(array_diff($this->items, $collection->toArray()));

        return $result;
    }

    final public function flatMap(Closure $callback): static
    {
        $result = null;

        foreach ($this->items as $item) {
            $mapped = $callback($item);

            if (! $mapped instanceof self) {
                throw new InvalidArgumentException('flatMap callback must return a TypedRecords instance');
            }

            if ($result === null) {
                $result = new static(...$mapped->getAllowedTypes());
            }

            foreach ($mapped->all() as $subItem) {
                $result->add($subItem);
            }
        }

        return $result ?? new static('null');
    }

    final public function values(): static
    {
        $result = new static(...$this->allowedTypes);
        $result->items = array_values($this->items);

        return $result;
    }

    final public function filterNull(): static
    {
        $result = new static(...$this->allowedTypes);

        foreach ($this->items as $item) {
            if ($item !== null) {
                $result->add($item);
            }
        }

        return $result;
    }

    final public function nth(int $step, int $offset = 0): static
    {
        if ($step <= 0) {
            throw new InvalidArgumentException('Step must be greater than 0');
        }

        $result = new static(...$this->allowedTypes);
        $items = array_slice($this->items, $offset);
        $nthItems = [];

        for ($i = 0; $i < count($items); $i += $step) {
            $nthItems[] = $items[$i];
        }

        $result->items = $nthItems;

        return $result;
    }

    final public function random(int $number = 1): static
    {
        if ($number <= 0) {
            throw new InvalidArgumentException('Number must be greater than 0');
        }

        if ($number > count($this->items)) {
            throw new InvalidArgumentException('Cannot get more random items than collection size');
        }

        $keys = array_rand($this->items, $number);
        $keys = is_array($keys) ? $keys : [$keys];

        $result = new static(...$this->allowedTypes);
        foreach ($keys as $key) {
            $result->add($this->items[$key]);
        }

        return $result;
    }

    final public function isOnlyType(string $type): bool
    {
        if (empty($this->items)) {
            return true;
        }

        foreach ($this->items as $item) {
            $itemType = $this->getValueTypeName($item);
            if ($itemType !== $type && ! ($item instanceof $type)) {
                return false;
            }
        }

        return true;
    }

    final public function containsType(string $type): bool
    {
        foreach ($this->items as $item) {
            $itemType = $this->getValueTypeName($item);
            if ($itemType === $type || ($item instanceof $type)) {
                return true;
            }
        }

        return false;
    }

    final public function isHomogeneous(): bool
    {
        $types = [];

        foreach ($this->items as $item) {
            $type = $this->getValueTypeName($item);
            if (! in_array($type, $types, true)) {
                $types[] = $type;
            }
            if (count($types) > 1) {
                return false;
            }
        }

        return true;
    }

    final public function isHeterogeneous(): bool
    {
        return ! $this->isHomogeneous();
    }

    final public function assertAllOfType(string $type): static
    {
        if (! $this->isOnlyType($type)) {
            $actualTypes = implode(', ', array_map(fn ($item) => $this->getValueTypeName($item), $this->items));
            throw new InvalidArgumentException(
                sprintf('Expected all items to be of type "%s", found: %s', $type, $actualTypes)
            );
        }

        return $this;
    }

    final public function assertNotEmpty(): static
    {
        if ($this->isEmpty()) {
            throw new InvalidArgumentException('Collection is empty');
        }

        return $this;
    }

    final public function assertContainsType(string $type): static
    {
        if (! $this->containsType($type)) {
            $availableTypes = implode(', ', array_map(fn ($item) => $this->getValueTypeName($item), $this->items));
            throw new InvalidArgumentException(
                sprintf('Collection does not contain type "%s". Available types: %s', $type, $availableTypes)
            );
        }

        return $this;
    }

    final public function assertAllImplement(string $interface): static
    {
        foreach ($this->items as $item) {
            if (! is_object($item)) {
                throw new InvalidArgumentException(
                    sprintf('Item of type "%s" is not an object', gettype($item))
                );
            }

            if (! $item instanceof $interface) {
                throw new InvalidArgumentException(
                    sprintf('Item of class "%s" does not implement "%s"', $item::class, $interface)
                );
            }
        }

        return $this;
    }

    final public function assertScalar(): static
    {
        foreach ($this->items as $item) {
            if (! $this->isScalarValue($item)) {
                throw new InvalidArgumentException(
                    sprintf('Expected scalar value, got "%s"', $this->getValueTypeName($item))
                );
            }
        }

        return $this;
    }

    final public function assertRecords(): static
    {
        foreach ($this->items as $item) {
            if (! $item instanceof AbstractRecord) {
                throw new InvalidArgumentException(
                    sprintf('Expected AbstractRecord, got "%s"', $this->getValueTypeName($item))
                );
            }
        }

        return $this;
    }

    final public function validate(Closure $validator): static
    {
        foreach ($this->items as $index => $item) {
            if (! $validator($item, $index)) {
                throw new InvalidArgumentException(
                    sprintf('Validation failed for item at index %d', $index)
                );
            }
        }

        return $this;
    }

    final public function __clone()
    {
        $newItems = [];
        foreach ($this->items as $item) {
            $newItems[] = $item instanceof self ? clone $item : $item;
        }
        $this->items = $newItems;
    }
}
