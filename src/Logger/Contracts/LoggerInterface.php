<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Contracts;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Records\LogQueryRecord;
use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Records\Recordable;

interface LoggerInterface
{
    public function log(LogRecord $record): void;

    public function info(Recordable $data): void;

    public function warning(Recordable $data): void;

    public function error(Recordable $data): void;

    public function debug(Recordable $data): void;

    public function query(LogQueryRecord $query): TypedRecords;

    public function stream(?string $date = null): TypedRecords;
}
