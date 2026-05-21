<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Records;

use AndyDefer\BestPractices\Records\AbstractRecord;

final class ConfirmQuestionRecord extends AbstractRecord
{
    public function __construct(public readonly string $question) {}
}
