<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum ExitCode: int
{
    use Enumable;

    case SUCCESS = 0;
    case FAILURE = 1;
    case NOT_FOUND = 3;
    case INVALID_ARGUMENT = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAILURE => 'Failure',
            self::NOT_FOUND => 'Not Found',
            self::INVALID_ARGUMENT => 'Invalid Argument',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::SUCCESS;
    }

    public function isFailure(): bool
    {
        return $this !== self::SUCCESS;
    }

    public function isNotFound(): bool
    {
        return $this === self::NOT_FOUND;
    }

    public function isInvalidArgument(): bool
    {
        return $this === self::INVALID_ARGUMENT;
    }
}
