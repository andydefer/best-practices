<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Tasks;

use AndyDefer\BestPractices\Directive\Records\AskQuestionRecord;

class AskQuestionTask
{
    public function execute(AskQuestionRecord $record): string
    {
        echo $record->question.' ';

        return trim(fgets(STDIN));
    }
}
