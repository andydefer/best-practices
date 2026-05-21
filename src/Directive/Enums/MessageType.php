<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumable;

enum MessageType: string
{
    use Enumable;

    case INFO = 'info';
    case ERROR = 'error';
    case WARNING = 'warning';
    case LINE = 'line';

    public function getColorCode(): string
    {
        return match ($this) {
            self::INFO => "\033[32m",     // Vert
            self::ERROR => "\033[31m",    // Rouge
            self::WARNING => "\033[33m",  // Jaune
            self::LINE => '',              // Pas de couleur
        };
    }

    public function getResetCode(): string
    {
        return $this === self::LINE ? '' : "\033[0m";
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::INFO => 'Information',
            self::ERROR => 'Error',
            self::WARNING => 'Warning',
            self::LINE => 'Line',
        };
    }

    public function isInfo(): bool
    {
        return $this === self::INFO;
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function isWarning(): bool
    {
        return $this === self::WARNING;
    }

    public function isLine(): bool
    {
        return $this === self::LINE;
    }
}
