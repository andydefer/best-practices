<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;
use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Logger\Records\LogQueryRecord;
use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Logger\Services\Tasks\QueryLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\StreamLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\WriteLogTask;
use AndyDefer\BestPractices\Records\Recordable;

final class Logger implements LoggerInterface
{
    public function __construct(
        private readonly WriteLogTask $writeLogTask,
        private readonly QueryLogsTask $queryLogsTask,
        private readonly StreamLogsTask $streamLogsTask,
    ) {}

    public function log(LogRecord $record): void
    {
        $this->writeLogTask->execute($record);
    }

    public function info(Recordable $data): void
    {
        $this->log(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::INFO,
            data: $data,
        ));
    }

    public function warning(Recordable $data): void
    {
        $this->log(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::WARNING,
            data: $data,
        ));
    }

    public function error(Recordable $data): void
    {
        $this->log(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::ERROR,
            data: $data,
        ));
    }

    public function debug(Recordable $data): void
    {
        $this->log(new LogRecord(
            time: now()->toIso8601ZuluString(),
            level: LogLevel::DEBUG,
            data: $data,
        ));
    }

    public function query(LogQueryRecord $query): TypedRecords
    {
        return $this->queryLogsTask->execute($query);
    }

    public function stream(?string $date = null): TypedRecords
    {
        return $this->streamLogsTask->execute($date);
    }
}
