<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Collections;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Records\AbstractRecord;
use stdClass;

/**
 * Collection typée pour les payloads de logs.
 *
 * Accepte les types suivants :
 * - Scalaires : int, float, string, bool, null
 * - Records : toutes les classes qui étendent AbstractRecord
 * - Collections : TypedRecords imbriquées
 * - stdClass : objets simples de la désérialisation
 *
 * @extends TypedRecords<int|float|string|bool|null|AbstractRecord|TypedRecords|stdClass>
 */
final class MixedPayloadCollection extends TypedRecords
{
    public function __construct()
    {
        parent::__construct(
            'int',
            'float',
            'string',
            'bool',
            'null',
            AbstractRecord::class,
            TypedRecords::class,
            stdClass::class
        );
    }

    /**
     * Vérifie si tous les éléments du payload sont des scalaires.
     */
    public function isAllScalars(): bool
    {
        return $this->scalars()->count() === $this->count();
    }

    /**
     * Vérifie si tous les éléments du payload sont des Records.
     */
    public function isAllRecords(): bool
    {
        return $this->records()->count() === $this->count();
    }

    /**
     * Vérifie si tous les éléments du payload sont des stdClass.
     */
    public function isAllStdClass(): bool
    {
        $stdClassCount = 0;
        foreach ($this->items as $item) {
            if ($item instanceof stdClass) {
                $stdClassCount++;
            }
        }

        return $stdClassCount === $this->count();
    }

    /**
     * Retourne tous les éléments sous forme de tableau sérialisable.
     */
    public function toSerializableArray(): array
    {
        $result = [];
        foreach ($this->items as $item) {
            if ($item instanceof AbstractRecord) {
                $result[] = $item->toArray();
            } elseif ($item instanceof TypedRecords) {
                $result[] = $item->toArray();
            } elseif ($item instanceof stdClass) {
                $result[] = (array) $item;
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }
}
