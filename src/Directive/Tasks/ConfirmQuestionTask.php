<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Tasks;

use AndyDefer\BestPractices\Directive\Records\ConfirmQuestionRecord;

class ConfirmQuestionTask
{
    public function execute(ConfirmQuestionRecord $record): bool
    {
        echo $record->question.' (y/n) ';
        $answer = strtolower(trim(fgets(STDIN)));

        return in_array($answer, ['y', 'yes'], true);
    }
}
