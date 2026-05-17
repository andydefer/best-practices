<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum TestPureEnum
{
    use Enumerable;

    case ADMIN;
    case USER;
    case GUEST;
}
