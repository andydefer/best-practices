<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;

/**
 * Fixture record for testing collections of collections.
 *
 * Used to test that AbstractRecord can handle properties of type
 * TypedRecords where the collection itself contains collections.
 */
final class TestCollectionsRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedRecords $stringCollections = new TypedRecords(TypedRecords::class),
        public readonly TypedRecords $intCollections = new TypedRecords(TypedRecords::class),
    ) {}
}
