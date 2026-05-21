<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Services\Tasks;

use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Logger\Services\LogPathService;
use AndyDefer\BestPractices\Logger\Services\LogSerializerService;
use RuntimeException;

class WriteLogTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    public function execute(LogRecord $record): void
    {
        $filePath = $this->pathService->getHourlyFilePath($record->time);

        $directory = dirname($filePath);
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true)) {
                throw new RuntimeException("Cannot create log directory: {$directory}");
            }
        }

        $jsonLine = $this->serializer->serialize($record);

        $result = file_put_contents($filePath, $jsonLine, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            throw new RuntimeException("Cannot write log to file: {$filePath}");
        }
    }
}
