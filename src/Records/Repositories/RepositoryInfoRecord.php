<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records\Repositories;

use AndyDefer\BestPractices\Records\AbstractRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Record that defines the relationship between a Repository, its Model, and its Record.
 *
 * Each Repository must return an instance of this Record to declare:
 * - The Eloquent Model class it manages
 * - The Record class it accepts for create/update operations
 *
 * @template TModel of Model
 * @template TRecord of \AndyDefer\BestPractices\Records\Recordable
 *
 * @author Andy Defer
 */
final class RepositoryInfoRecord extends AbstractRecord
{
    /**
     * @param  class-string<TModel>  $modelClass  The Eloquent Model class
     * @param  class-string<TRecord>  $recordClass  The Record class for create/update
     */
    public function __construct(
        public readonly string $modelClass,
        public readonly string $recordClass,
    ) {}
}
