<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Tasks;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Logger\Services\LogPathService;
use AndyDefer\BestPractices\Logger\Services\LogSerializerService;

class StreamLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    public function execute(?string $date = null): TypedRecords
    {
        $results = new TypedRecords(LogRecord::class);

        $targetDate = $date ?? date('Y-m-d');
        $files = $this->pathService->getDayFiles($targetDate);

        foreach ($files as $fileInfo) {
            $this->streamFile($fileInfo->path, $results);
        }

        return $results;
    }

    private function streamFile(string $filePath, TypedRecords $results): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            $record = $this->serializer->deserialize($line);
            if ($record !== null) {
                $results->add($record);
            }
        }

        fclose($handle);
    }
}
