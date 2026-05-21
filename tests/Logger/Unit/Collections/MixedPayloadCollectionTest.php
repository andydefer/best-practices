<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit\Collections;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\TestCase;

final class MixedPayloadCollectionTest extends TestCase
{
    public function test_construct_creates_collection_with_correct_types(): void
    {
        $collection = new MixedPayloadCollection;

        $allowedTypes = $collection->getAllowedTypes();

        $this->assertContains('int', $allowedTypes);
        $this->assertContains('float', $allowedTypes);
        $this->assertContains('string', $allowedTypes);
        $this->assertContains('bool', $allowedTypes);
        $this->assertContains('null', $allowedTypes);
        $this->assertContains(AbstractRecord::class, $allowedTypes);
        $this->assertContains(TypedRecords::class, $allowedTypes);
    }

    public function test_add_accepts_multiple_scalars_at_once(): void
    {
        $collection = new MixedPayloadCollection;
        $collection->add(1, 2, 3, 'hello', 1.5, true, null);

        $this->assertSame(7, $collection->count());
        $this->assertTrue($collection->isAllScalars());
    }

    public function test_add_accepts_record_instance(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add($testRecord);

        $this->assertSame(1, $collection->count());
        $this->assertTrue($collection->isAllRecords());
        $this->assertSame($testRecord, $collection->firstItem());
    }

    public function test_add_accepts_multiple_records(): void
    {
        $collection = new MixedPayloadCollection;

        $record1 = new class('record1') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $record2 = new class('record2') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add($record1, $record2);

        $this->assertSame(2, $collection->count());
        $this->assertTrue($collection->isAllRecords());
    }

    public function test_add_accepts_typed_records_collection(): void
    {
        $collection = new MixedPayloadCollection;

        $nestedCollection = new TypedRecords('int');
        $nestedCollection->add(1, 2, 3);

        $collection->add($nestedCollection);

        $this->assertSame(1, $collection->count());
        $this->assertInstanceOf(TypedRecords::class, $collection->firstItem());
    }

    public function test_add_accepts_mixed_scalars_and_records(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add(1, 'string', $testRecord, true);

        $this->assertSame(4, $collection->count());
        $this->assertFalse($collection->isAllScalars());
        $this->assertFalse($collection->isAllRecords());
    }

    public function test_to_serializable_array_converts_records_to_arrays(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}

            public function toArray(): array
            {
                return ['name' => $this->name];
            }
        };

        $collection->add(1, 'hello', $testRecord);

        $serialized = $collection->toSerializableArray();

        $this->assertSame(1, $serialized[0]);
        $this->assertSame('hello', $serialized[1]);
        $this->assertSame(['name' => 'test'], $serialized[2]);
    }

    public function test_to_serializable_array_handles_nested_typed_records(): void
    {
        $collection = new MixedPayloadCollection;

        $nestedCollection = new TypedRecords('int');
        $nestedCollection->add(1, 2, 3);

        $collection->add($nestedCollection);

        $serialized = $collection->toSerializableArray();

        $this->assertSame([1, 2, 3], $serialized[0]);
    }

    public function test_is_all_scalars_returns_true_when_only_scalars(): void
    {
        $collection = new MixedPayloadCollection;
        $collection->add(1, 2, 'hello', true, null);

        $this->assertTrue($collection->isAllScalars());
    }

    public function test_is_all_scalars_returns_false_when_contains_record(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add(1, $testRecord);

        $this->assertFalse($collection->isAllScalars());
    }

    public function test_is_all_records_returns_true_when_only_records(): void
    {
        $collection = new MixedPayloadCollection;

        $record1 = new class('record1') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $record2 = new class('record2') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add($record1, $record2);

        $this->assertTrue($collection->isAllRecords());
    }

    public function test_is_all_records_returns_false_when_contains_scalar(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add($testRecord, 1);

        $this->assertFalse($collection->isAllRecords());
    }

    public function test_chaining_add_returns_collection(): void
    {
        $collection = new MixedPayloadCollection;

        $result = $collection->add(1)->add(2)->add(3);

        $this->assertSame($collection, $result);
        $this->assertSame(3, $collection->count());
    }

    public function test_multiple_add_with_chaining(): void
    {
        $collection = new MixedPayloadCollection;

        $collection->add(1, 2)->add(3, 4)->add(5);

        $this->assertSame(5, $collection->count());
        $this->assertSame([1, 2, 3, 4, 5], $collection->toArray());
    }

    public function test_add_with_record_and_scalar_works(): void
    {
        $collection = new MixedPayloadCollection;

        $testRecord = new class('test') extends AbstractRecord
        {
            public function __construct(public readonly string $name) {}
        };

        $collection->add('type', $testRecord, 42, true);

        $this->assertSame(4, $collection->count());
    }

    public function test_add_with_nested_collection(): void
    {
        $collection = new MixedPayloadCollection;

        $nested = new TypedRecords('string');
        $nested->add('a', 'b', 'c');

        $collection->add('prefix', $nested, 'suffix');

        $this->assertSame(3, $collection->count());
        // La dernière valeur est 'suffix' (string), pas le nested
        $this->assertSame('suffix', $collection->lastItem());
        // La deuxième valeur est le nested
        $this->assertSame($nested, $collection->toArray()[1]);
    }

    public function test_add_with_multiple_nested_collections(): void
    {
        $collection = new MixedPayloadCollection;

        $nested1 = new TypedRecords('int');
        $nested1->add(1, 2, 3);

        $nested2 = new TypedRecords('string');
        $nested2->add('x', 'y', 'z');

        $collection->add($nested1, $nested2, 'end');

        $this->assertSame(3, $collection->count());
        $this->assertSame($nested1, $collection->toArray()[0]);
        $this->assertSame($nested2, $collection->toArray()[1]);
        $this->assertSame('end', $collection->toArray()[2]);
    }
}
