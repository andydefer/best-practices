<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

/**
 * Collection typée pour les nombres décimaux.
 *
 * @extends AbstractNumberTypedRecords<float>
 */
final class FloatTypedRecords extends AbstractNumberTypedRecords
{
    public function __construct()
    {
        parent::__construct('float');
    }

    /**
     * Arrondit chaque nombre.
     */
    public function round(int $precision = 0): self
    {
        return $this->map(fn($item) => round($item, $precision));
    }

    /**
     * Arrondit chaque nombre à l'entier supérieur.
     */
    public function ceil(): self
    {
        return $this->map(fn($item) => ceil($item));
    }

    /**
     * Arrondit chaque nombre à l'entier inférieur.
     */
    public function floor(): self
    {
        return $this->map(fn($item) => floor($item));
    }

    /**
     * Arrondit chaque nombre avec un nombre spécifique de décimales.
     * Retourne des floats, pas des strings.
     */
    public function format(int $decimals = 2): self
    {
        return $this->map(fn($item) => round($item, $decimals));
    }
}
