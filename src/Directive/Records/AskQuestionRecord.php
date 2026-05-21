<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;

final class AskQuestionRecord extends AbstractRecord
{
    public function __construct(public readonly string $question) {}
}
