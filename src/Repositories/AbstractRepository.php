<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Repositories;

use AndyDefer\BestPractices\Records\Recordable;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;
use AndyDefer\BestPractices\Records\Repositories\PaginateRecord;
use AndyDefer\BestPractices\Records\Repositories\RepositoryInfoRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Abstract base class for all repositories.
 *
 * Provides common database operations (CRUD, pagination, bulk delete)
 * for Eloquent models. All concrete repositories must extend this class
 * and implement the info() method to declare their Model and Record.
 *
 * **Important rules:**
 * - Each Repository is responsible for ONE entity (Model)
 * - Each Repository has ONE associated Record class for create/update
 * - For bulk operations (multiple creates), use a Task and loop over create()
 * - deleteBulk() is allowed because it uses criteria (Recordable) not an array
 *
 * @template TModel of Model
 * @template TRecord of Recordable
 *
 * @author Andy Defer
 */
abstract class AbstractRepository implements AbstractRepositoryInterface
{
    /**
     * Cached instance of the model.
     *
     * @var TModel|null
     */
    private ?Model $modelInstance = null;

    /**
     * {@inheritDoc}
     */
    abstract public function info(): RepositoryInfoRecord;

    /**
     * Creates a new instance of the model.
     *
     * @return TModel
     */
    final protected function newModel(): Model
    {
        if ($this->modelInstance === null) {
            $class = $this->info()->modelClass;
            $this->modelInstance = new $class;
        }

        return $this->modelInstance;
    }

    /**
     * Verifies that the given record is of the correct type for this repository.
     *
     * @param  Recordable  $record  The record to validate
     * @throws \InvalidArgumentException When the record type is incorrect
     */
    final protected function validateRecordType(Recordable $record): void
    {
        $expectedClass = $this->info()->recordClass;

        if (!$record instanceof $expectedClass) {
            throw new \InvalidArgumentException(sprintf(
                'Expected record of type %s, got %s',
                $expectedClass,
                get_class($record)
            ));
        }
    }

    /**
     * {@inheritDoc}
     */
    final public function create(Recordable $record): Model
    {
        $this->validateRecordType($record);

        return DB::transaction(function () use ($record): Model {
            return $this->newModel()->create($record->toDatabase());
        });
    }

    /**
     * {@inheritDoc}
     */
    final public function find(int $id): ?Model
    {
        return $this->newModel()->find($id);
    }

    /**
     * {@inheritDoc}
     */
    final public function findBy(FindByRecord $record): Collection
    {
        $query = $this->newModel()->newQuery();

        foreach ($record->filters->toDatabase() as $key => $value) {
            $query->where($key, $value);
        }

        if ($record->limit !== null) {
            $query->limit($record->limit);
        }

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir);
        }

        return $query->get();
    }

    /**
     * {@inheritDoc}
     */
    final public function update(int $id, Recordable $record): Model
    {
        $this->validateRecordType($record);

        return DB::transaction(function () use ($id, $record): Model {
            $model = $this->find($id);

            if (!$model) {
                throw new \RuntimeException(sprintf(
                    '%s with id %d not found',
                    $this->info()->modelClass,
                    $id
                ));
            }

            $model->update($record->toDatabase());

            return $model->fresh();
        });
    }

    /**
     * {@inheritDoc}
     */
    final public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $model = $this->find($id);

            if (!$model) {
                return false;
            }

            return $model->delete();
        });
    }

    /**
     * {@inheritDoc}
     */
    final public function count(?Recordable $criteria = null): int
    {
        $query = $this->newModel()->newQuery();

        if ($criteria !== null) {
            foreach ($criteria->toDatabase() as $key => $value) {
                $query->where($key, $value);
            }
        }

        return $query->count();
    }

    /**
     * {@inheritDoc}
     */
    final public function exists(Recordable $criteria): bool
    {
        $query = $this->newModel()->newQuery();

        foreach ($criteria->toDatabase() as $key => $value) {
            $query->where($key, $value);
        }

        return $query->exists();
    }

    /**
     * {@inheritDoc}
     */
    final public function paginate(PaginateRecord $record): LengthAwarePaginator
    {
        $query = $this->newModel()->newQuery();

        foreach ($record->filters->toDatabase() as $key => $value) {
            $query->where($key, $value);
        }

        if ($record->sortBy !== null) {
            $query->orderBy($record->sortBy, $record->sortDir);
        }

        return $query->paginate(
            perPage: $record->perPage,
            columns: $record->columns,
            pageName: 'page',
            page: $record->page
        );
    }

    /**
     * {@inheritDoc}
     */
    final public function deleteBulk(Recordable $criteria): int
    {
        return DB::transaction(function () use ($criteria): int {
            $query = $this->newModel()->newQuery();

            foreach ($criteria->toDatabase() as $key => $value) {
                $query->where($key, $value);
            }

            return $query->delete();
        });
    }
}
