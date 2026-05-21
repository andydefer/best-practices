<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives;

use AndyDefer\BestPractices\Collections\Utility\StringTypedRecords;
use AndyDefer\BestPractices\Directive\AbstractDirective;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;

final class TestEchoDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'test:echo';
    }

    public function getDescription(): string
    {
        return 'Test echo directive';
    }

    public function getAliases(): StringTypedRecords
    {
        $aliases = new StringTypedRecords;
        $aliases->add('echo');

        return $aliases;
    }

    public function execute(): ExitCode
    {
        $message = $this->argument('message') ?? 'Hello World';
        $this->line($message);

        return ExitCode::SUCCESS;
    }
}
