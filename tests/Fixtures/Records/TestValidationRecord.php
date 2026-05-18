<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;
use stdClass;

/**
 * Fixture record for testing validation rules.
 *
 * This record is specifically designed to test the validation rules of AbstractRecord:
 * - TypedRecords cannot be null
 * - TypedRecords cannot have union types
 * - Arrays are not allowed
 *
 * @author Andy Defer
 */
final class TestValidationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $validString = 'default',
        public readonly int $validInt = 0,
        public readonly TypedRecords $validCollection = new TypedRecords('string'),
        public readonly ?TypedRecords $invalidNullableCollection = new TypedRecords('string'),
        public readonly TypedRecords|stdClass $invalidUnionCollection = new TypedRecords('string'),
        public readonly array $invalidArray = [],
    ) {
        parent::__construct();
    }
}
