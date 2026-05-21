<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Services\Tasks;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Records\LogQueryRecord;
use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Logger\Services\LogPathService;
use AndyDefer\BestPractices\Logger\Services\LogSerializerService;
use InvalidArgumentException;

class QueryLogsTask
{
    public function __construct(
        private readonly LogPathService $pathService,
        private readonly LogSerializerService $serializer,
    ) {}

    public function execute(LogQueryRecord $query): TypedRecords
    {
        $results = new TypedRecords(LogRecord::class);

        $dateRange = $this->pathService->getDateRange($query->from, $query->to);
        $dateRange->assertAllOfType('string');

        $files = $this->getFilesFromDateRange($dateRange);

        foreach ($files as $filePath) {
            $this->searchFile($filePath, $query, $results);
        }

        return $results;
    }

    private function getFilesFromDateRange(TypedRecords $dateRange): TypedRecords
    {
        $files = new TypedRecords('string');

        foreach ($dateRange as $date) {
            $dayFiles = $this->pathService->getDayFiles($date);

            foreach ($dayFiles as $fileInfo) {
                $files->add($fileInfo->path);
            }
        }

        return $files;
    }

    private function searchFile(string $filePath, LogQueryRecord $query, TypedRecords $results): void
    {
        if (! file_exists($filePath)) {
            return;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            try {
                $record = $this->serializer->deserialize($line);
                if ($record === null) {
                    continue;
                }

                if ($this->matchesQuery($record, $query)) {
                    $results->add($record);
                }
            } catch (InvalidArgumentException $e) {
                // Log invalide, on l'ignore silencieusement
                continue;
            }
        }

        fclose($handle);
    }

    private function matchesQuery(LogRecord $record, LogQueryRecord $query): bool
    {
        if ($query->type !== null && $record->data->type !== $query->type) {
            return false;
        }

        if ($query->level !== null && $record->level !== $query->level) {
            return false;
        }

        if ($query->from !== null && $record->time < $query->from) {
            return false;
        }

        if ($query->to !== null && $record->time > $query->to) {
            return false;
        }

        return true;
    }
}
