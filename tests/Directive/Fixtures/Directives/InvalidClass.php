<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives;

// Cette classe n'implémente pas DirectiveInterface
// Elle doit être ignorée par le discovery service
final class InvalidClass
{
    public function getSignature(): string
    {
        return 'invalid:class';
    }

    public function getDescription(): string
    {
        return 'This is not a valid directive';
    }

    public function execute(): void
    {
        // ...
    }
}
