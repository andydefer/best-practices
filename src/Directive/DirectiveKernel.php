<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\BestPractices\Directive\Services\DirectiveExecutionService;

class DirectiveKernel
{
    public function __construct(
        private readonly DirectiveExecutionService $service,
    ) {}

    public function run(array $argv): ExitCode
    {
        $signature = $argv[1] ?? '';
        $args = array_slice($argv, 2);
        $arguments = new TypedRecords('string');

        foreach ($args as $arg) {
            $arguments->add($arg);
        }

        return $this->service->execute(new DirectiveExecutionRecord(
            signature: $signature,
            arguments: $arguments,
        ));
    }
}
