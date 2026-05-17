<?php

namespace AndyDefer\BestPractices\Records;

interface Recordable
{
    /**
     * Convert the record to array (for database insertion).
     *
     * La sérialisation est automatique à partir de toutes les propriétés publiques.
     * Les clés sont automatiquement converties en snake_case.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Convert the record to array for database updates.
     *
     * Only includes non-null values, making it ideal for update operations
     * where you only want to set provided fields.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array;

    /**
     * Convert the record to JSON string (for HTTP client).
     */
    public function toJson(): string;
}
