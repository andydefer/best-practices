<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Repositories;

use AndyDefer\BestPractices\Records\Recordable;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;
use AndyDefer\BestPractices\Records\Repositories\PaginateRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Abstract base class for all repositories.
 *
 * Provides common database operations (CRUD, pagination, bulk operations)
 * for Eloquent models. All concrete repositories must extend this class
 * and implement the getModelClass() method.
 *
 * @template TModel of Model
 *
 * @author Andy Defer
 */
abstract class AbstractRepository implements AbstractRepositoryInterface
{
    /**
     * Returns the fully qualified class name of the Eloquent model.
     *
     * @return class-string<TModel>
     */
    abstract protected function getModelClass(): string;

    /**
     * Creates a new instance of the model.
     *
     * @return TModel
     */
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();

        return new $class;
    }

    /**
     * {@inheritDoc}
     */
    final public function create(Recordable $record): Model
    {
        return DB::transaction(function () use ($record): Model {
            return $this->newModel()->create($record->toArray());
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
        return DB::transaction(function () use ($id, $record): Model {
            $model = $this->find($id);

            if (! $model) {
                throw new \RuntimeException(sprintf(
                    '%s with id %d not found',
                    $this->getModelClass(),
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

            if (! $model) {
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
    final public function createBulk(array $records): Collection
    {
        return DB::transaction(function () use ($records): Collection {
            $models = new Collection;

            foreach ($records as $record) {
                $models->push($this->newModel()->create($record->toArray()));
            }

            return $models;
        });
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
