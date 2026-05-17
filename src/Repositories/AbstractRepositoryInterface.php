<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Repositories;

use AndyDefer\BestPractices\Records\Recordable;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;
use AndyDefer\BestPractices\Records\Repositories\PaginateRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Interface for abstract repository operations.
 *
 * Defines the contract for all repository classes, providing standard CRUD
 * operations, pagination, and bulk operations. All concrete repositories
 * must implement these methods.
 *
 * @template TModel of Model
 *
 * @author Andy Defer
 */
interface AbstractRepositoryInterface
{
    /**
     * Creates a new record in the database.
     *
     * @param  Recordable  $record  The record containing creation data
     * @return TModel The created model instance
     */
    public function create(Recordable $record): Model;

    /**
     * Finds a record by its primary key.
     *
     * @param  int  $id  The primary key value
     * @return TModel|null The model instance if found, null otherwise
     */
    public function find(int $id): ?Model;

    /**
     * Finds multiple records matching the given criteria.
     *
     * @param  FindByRecord  $record  The search criteria (filters, limit, sort)
     * @return Collection<int, TModel> Collection of matching models
     */
    public function findBy(FindByRecord $record): Collection;

    /**
     * Updates a record by its primary key.
     *
     * @param  int  $id  The primary key value
     * @param  Recordable  $record  The record containing update data (only non-null values are applied)
     * @return TModel The updated model instance
     *
     * @throws \RuntimeException When the record is not found
     */
    public function update(int $id, Recordable $record): Model;

    /**
     * Deletes a record by its primary key.
     *
     * @param  int  $id  The primary key value
     * @return bool True if the record was deleted, false if not found
     */
    public function delete(int $id): bool;

    /**
     * Counts records matching the given criteria.
     *
     * @param  Recordable|null  $criteria  Optional filter criteria (null = count all)
     * @return int The total number of matching records
     */
    public function count(?Recordable $criteria = null): int;

    /**
     * Checks if at least one record exists matching the given criteria.
     *
     * @param  Recordable  $criteria  The filter criteria
     * @return bool True if at least one record exists, false otherwise
     */
    public function exists(Recordable $criteria): bool;

    /**
     * Paginates results with the given parameters.
     *
     * @param  PaginateRecord  $record  Pagination parameters (page, perPage, sort, filters, columns)
     * @return LengthAwarePaginator<TModel> Paginated results with metadata
     */
    public function paginate(PaginateRecord $record): LengthAwarePaginator;

    /**
     * Creates multiple records in a single transaction.
     *
     * @param  array<int, Recordable>  $records  Array of records to create
     * @return Collection<int, TModel> Collection of created model instances
     */
    public function createBulk(array $records): Collection;

    /**
     * Deletes multiple records matching the given criteria in a single transaction.
     *
     * @param  Recordable  $criteria  The filter criteria to identify records to delete
     * @return int The number of deleted records
     */
    public function deleteBulk(Recordable $criteria): int;
}
