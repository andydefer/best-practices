<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum TestPureEnum
{
    use Enumable;

    case ADMIN;
    case USER;
    case GUEST;
}
