<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum TestBackedIntEnum: int
{
    use Enumerable;

    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
}
