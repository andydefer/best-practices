<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records\Repositories;

use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\Recordable;

/**
 * Standardized pagination record.
 *
 * Encapsulates all pagination parameters including page number, items per page,
 * sorting, filtering, and selectable columns.
 *
 * @author Andy Defer
 */
final class PaginateRecord extends AbstractRecord
{
    /**
     * @param  int  $perPage  Number of items per page (default: 15)
     * @param  int  $page  Current page number (default: 1)
     * @param  string|null  $sortBy  Column name to sort by (optional)
     * @param  string  $sortDir  Sort direction: 'asc' or 'desc' (default: 'asc')
     * @param  Recordable  $filters  Filter criteria (default: EmptyRecord)
     * @param  array<int, string>  $columns  Columns to select (default: ['*'])
     */
    public function __construct(
        public readonly int $perPage = 15,
        public readonly int $page = 1,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
        public readonly Recordable $filters = new EmptyRecord,
        /** @var array<int, string> */
        public readonly array $columns = ['*'],
    ) {}
}
