<?php

namespace AndyDefer\BestPractices\Tests\Fixtures\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum TestBackedStringEnum: string
{
    use Enumerable;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}
