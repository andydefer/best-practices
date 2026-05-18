<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum TestBackedIntEnum: int
{
    use Enumable;

    case ONE = 1;
    case TWO = 2;
    case THREE = 3;
}
