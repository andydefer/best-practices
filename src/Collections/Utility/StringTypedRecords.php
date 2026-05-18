<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Collections\Utility;

use AndyDefer\BestPractices\Collections\TypedRecords;

/**
 * Collection typée pour les chaînes de caractères.
 *
 * @extends TypedRecords<string>
 */
final class StringTypedRecords extends TypedRecords
{
    public function __construct()
    {
        parent::__construct('string');
    }

    /**
     * Convertit toutes les chaînes en minuscules.
     */
    public function toLowercase(): self
    {
        return $this->map(fn ($item) => strtolower($item));
    }

    /**
     * Convertit toutes les chaînes en majuscules.
     */
    public function toUppercase(): self
    {
        return $this->map(fn ($item) => strtoupper($item));
    }

    /**
     * Filtre les chaînes qui contiennent une sous-chaîne.
     */
    public function containsSubstring(string $search): self
    {
        return $this->filter(fn ($item) => str_contains($item, $search));
    }

    /**
     * Filtre les chaînes qui commencent par un préfixe.
     */
    public function startsWith(string $prefix): self
    {
        return $this->filter(fn ($item) => str_starts_with($item, $prefix));
    }

    /**
     * Filtre les chaînes qui se terminent par un suffixe.
     */
    public function endsWith(string $suffix): self
    {
        return $this->filter(fn ($item) => str_ends_with($item, $suffix));
    }

    /**
     * Supprime les chaînes vides.
     */
    public function filterEmpty(): self
    {
        return $this->filter(fn ($item) => $item !== '' && $item !== null);
    }

    /**
     * Supprime les espaces en début et fin de chaque chaîne.
     */
    public function trim(): self
    {
        return $this->map(fn ($item) => trim($item));
    }

    /**
     * Limite la longueur des chaînes.
     */
    public function truncate(int $length, string $suffix = '...'): self
    {
        return $this->map(fn ($item) => strlen($item) > $length
            ? substr($item, 0, $length).$suffix
            : $item);
    }
}
