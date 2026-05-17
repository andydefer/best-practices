<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records\Repositories;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\Recordable;

/**
 * Record for find by criteria operations.
 *
 * Encapsulates search parameters including filters, limit, and sorting.
 * Used by repository findBy() methods to provide type-safe query parameters.
 *
 * @author Andy Defer
 */
final class FindByRecord extends AbstractRecord
{
    /**
     * @param  Recordable  $filters  Filter criteria (default: EmptyRecord = no filters)
     * @param  int|null  $limit  Maximum number of records to return (default: 100)
     * @param  string|null  $sortBy  Column name to sort by (optional)
     * @param  string  $sortDir  Sort direction: 'asc' or 'desc' (default: 'asc')
     */
    public function __construct(
        public readonly Recordable $filters = new EmptyRecord,
        public readonly ?int $limit = 100,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
    ) {}
}
