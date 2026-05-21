<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives;

use AndyDefer\BestPractices\Directive\AbstractDirective;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;

final class TestConcreteDirective extends AbstractDirective
{
    public function __construct(
        DisplayMessageTask $displayMessage,
        AskQuestionTask $askQuestion,
        ConfirmQuestionTask $confirmQuestion,
        DisplayTableTask $displayTable,
    ) {
        parent::__construct($displayMessage, $askQuestion, $confirmQuestion, $displayTable);
    }

    public function getSignature(): string
    {
        return 'test:concrete';
    }

    public function getDescription(): string
    {
        return 'Test concrete directive for AbstractDirective tests';
    }

    public function execute(): ExitCode
    {
        return ExitCode::SUCCESS;
    }
}
