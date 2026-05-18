<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

use AndyDefer\BestPractices\Collections\TypedRecords;

/**
 * Classe de base abstraite pour les collections de nombres (int ou float).
 *
 * @template TValue of int|float
 *
 * @extends TypedRecords<TValue>
 */
abstract class AbstractNumberTypedRecords extends TypedRecords
{
    /**
     * Filtre les nombres positifs (> 0).
     *
     * @return static<TValue>
     */
    public function positive(): static
    {
        return $this->filter(fn ($item) => $item > 0);
    }

    /**
     * Filtre les nombres négatifs (< 0).
     *
     * @return static<TValue>
     */
    public function negative(): static
    {
        return $this->filter(fn ($item) => $item < 0);
    }

    /**
     * Filtre les nombres dans un intervalle.
     *
     * @param  TValue  $min
     * @param  TValue  $max
     * @return static<TValue>
     */
    public function between(int|float $min, int|float $max): static
    {
        return $this->filter(fn ($item) => $item >= $min && $item <= $max);
    }

    /**
     * Calcule la moyenne.
     */
    public function average(): float
    {
        $count = $this->count();

        return $count > 0 ? $this->sum() / $count : 0.0;
    }

    /**
     * Génère une séquence de nombres.
     *
     * @param  TValue  $start
     * @param  TValue  $end
     * @param  TValue  $step
     * @return static<TValue>
     */
    public static function range(int|float $start, int|float $end, int|float $step = 1): static
    {
        $collection = new static;

        if ($step == 0) {
            return $collection;
        }

        if ($start <= $end && $step > 0) {
            for ($i = $start; $i <= $end; $i += $step) {
                $collection->add($i);
            }
        } elseif ($start >= $end && $step < 0) {
            for ($i = $start; $i >= $end; $i += $step) {
                $collection->add($i);
            }
        }

        return $collection;
    }
}
