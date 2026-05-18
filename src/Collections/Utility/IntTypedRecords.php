<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

/**
 * Collection typée pour les entiers.
 *
 * @extends AbstractNumberTypedRecords<int>
 */
final class IntTypedRecords extends AbstractNumberTypedRecords
{
    public function __construct()
    {
        parent::__construct('int');
    }

    /**
     * Filtre les nombres zéro.
     */
    public function zero(): self
    {
        return $this->filter(fn ($item) => $item === 0);
    }

    /**
     * Filtre les nombres non négatifs (>= 0).
     */
    public function nonNegative(): self
    {
        return $this->filter(fn ($item) => $item >= 0);
    }

    /**
     * Filtre les nombres pairs.
     */
    public function even(): self
    {
        return $this->filter(fn ($item) => $item % 2 === 0);
    }

    /**
     * Filtre les nombres impairs.
     */
    public function odd(): self
    {
        return $this->filter(fn ($item) => $item % 2 !== 0);
    }

    /**
     * Calcule la médiane.
     */
    public function median(): float
    {
        $count = $this->count();
        if ($count === 0) {
            return 0.0;
        }

        $sorted = $this->sort()->all();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return (float) $sorted[$middle];
    }
}
