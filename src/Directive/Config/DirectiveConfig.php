<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Config;

final class DirectiveConfig
{
    private function __construct(
        public readonly string $directivesPath,
    ) {}

    public static function default(): self
    {
        return new self(
            directivesPath: __DIR__.'/../../../app/Directives',
        );
    }

    public function withDirectivesPath(string $path): self
    {
        return new self($path);
    }
}
