<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum TestUserRole: string
{
    use Enumable;

    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';
}
