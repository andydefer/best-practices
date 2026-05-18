<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

use AndyDefer\BestPractices\Collections\TypedRecords;

/**
 * Collection typée pour les booléens.
 * 
 * @extends TypedRecords<bool>
 */
final class BoolTypedRecords extends TypedRecords
{
    public function __construct()
    {
        parent::__construct('bool');
    }

    /**
     * Garde uniquement les valeurs true.
     */
    public function trueOnly(): self
    {
        return $this->filter(fn($item) => $item === true);
    }

    /**
     * Garde uniquement les valeurs false.
     */
    public function falseOnly(): self
    {
        return $this->filter(fn($item) => $item === false);
    }

    /**
     * Compte le nombre de true.
     */
    public function countTrue(): int
    {
        return $this->trueOnly()->count();
    }

    /**
     * Compte le nombre de false.
     */
    public function countFalse(): int
    {
        return $this->falseOnly()->count();
    }

    /**
     * Vérifie si toutes les valeurs sont true.
     */
    public function allTrue(): bool
    {
        return $this->countTrue() === $this->count();
    }

    /**
     * Vérifie si toutes les valeurs sont false.
     */
    public function allFalse(): bool
    {
        return $this->countFalse() === $this->count();
    }

    /**
     * Vérifie si au moins une valeur est true.
     */
    public function anyTrue(): bool
    {
        return $this->countTrue() > 0;
    }

    /**
     * Vérifie si au moins une valeur est false.
     */
    public function anyFalse(): bool
    {
        return $this->countFalse() > 0;
    }
}
