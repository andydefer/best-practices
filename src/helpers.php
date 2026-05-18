<?php

// src/helpers.php

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Constants\BestPracticesConstants;

if (! function_exists('best_practices_limit')) {
    function best_practices_limit(): int
    {
        return BestPracticesConstants::BEST_PRACTICES_LIMIT;
    }
}

if (!function_exists('typed_records')) {
    /**
     * Create a new typed record collection.
     *
     * @template T
     * @param  class-string<T>|string  ...$types
     * @return TypedRecords<T>
     */
    function typed_records(...$types): TypedRecords
    {
        return new TypedRecords(...$types);
    }
}
