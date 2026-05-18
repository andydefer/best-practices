<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum TestUserStatus
{
    use Enumable;

    case ACTIVE;
    case INACTIVE;
    case SUSPENDED;
}
