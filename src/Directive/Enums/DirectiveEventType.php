<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum DirectiveEventType: string
{
    use Enumable;

    case STARTED = 'started';
    case FINISHED = 'finished';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::STARTED => 'Started',
            self::FINISHED => 'Finished',
            self::FAILED => 'Failed',
        };
    }

    public function isStarted(): bool
    {
        return $this === self::STARTED;
    }

    public function isFinished(): bool
    {
        return $this === self::FINISHED;
    }

    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }
}
