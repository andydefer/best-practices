<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

/**
 * Collection typée pour les nombres (int ou float).
 *
 * @extends AbstractNumberTypedRecords<int|float>
 */
final class NumberTypedRecords extends AbstractNumberTypedRecords
{
    public function __construct()
    {
        parent::__construct('int', 'float');
    }

    /**
     * Filtre les nombres zéro (pour les entiers et flottants à 0.0).
     */
    public function zero(): self
    {
        return $this->filter(fn($item) => $item == 0);
    }

    /**
     * Filtre les nombres non négatifs (>= 0).
     */
    public function nonNegative(): self
    {
        return $this->filter(fn($item) => $item >= 0);
    }
}
