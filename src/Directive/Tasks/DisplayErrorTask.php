<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Tasks;

use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Directive\Records\DisplayMessageRecord;

class DisplayErrorTask
{
    public function execute(string $message): void
    {
        $record = new DisplayMessageRecord($message, MessageType::ERROR);
        echo MessageType::ERROR->getColorCode().$message.MessageType::ERROR->getResetCode()."\n";
    }
}
