<?php

// src/helpers.php

use AndyDefer\BestPractices\Constants\BestPracticesConstants;

if (! function_exists('best_practices_limit')) {
    function best_practices_limit(): int
    {
        return BestPracticesConstants::BEST_PRACTICES_LIMIT;
    }
}
