<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives;

use AndyDefer\BestPractices\Directive\AbstractDirective;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;

final class TestDirective extends AbstractDirective
{
    private string $signature;

    private string $description;

    private ?ExitCode $exitCode;

    public function __construct(
        DisplayMessageTask $displayMessage,
        AskQuestionTask $askQuestion,
        ConfirmQuestionTask $confirmQuestion,
        DisplayTableTask $displayTable,
        string $signature = 'test:directive',
        string $description = 'Test directive',
        ?ExitCode $exitCode = ExitCode::SUCCESS,
    ) {
        parent::__construct($displayMessage, $askQuestion, $confirmQuestion, $displayTable);
        $this->signature = $signature;
        $this->description = $description;
        $this->exitCode = $exitCode;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function execute(): ExitCode
    {
        return $this->exitCode ?? ExitCode::SUCCESS;
    }
}
