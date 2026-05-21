<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Config\LoggerConfig;
use AndyDefer\BestPractices\Logger\Records\DateRangeRecord;
use AndyDefer\BestPractices\Logger\Records\LogFileInfoRecord;

class LogPathService
{
    private LoggerConfig $config;

    public function __construct(?LoggerConfig $config = null)
    {
        $this->config = $config ?? LoggerConfig::default();
    }

    public function getHourlyFilePath(string $timestamp): string
    {
        $date = substr($timestamp, 0, 10);
        $hour = (int) substr($timestamp, 11, 2);
        $hourRange = $this->getHourRange($hour);

        return $this->config->basePath.'/'.$date.'/'.$hourRange.'.jsonl';
    }

    public function getDayFiles(string $date): TypedRecords
    {
        $results = new TypedRecords(LogFileInfoRecord::class);
        $dayPath = $this->config->basePath.'/'.$date;

        if (! is_dir($dayPath)) {
            return $results;
        }

        $files = glob($dayPath.'/*.jsonl');
        sort($files);

        foreach ($files as $file) {
            $hour = basename($file, '.jsonl');
            $size = filesize($file);
            $lines = $this->countFileLines($file);

            $results->add(new LogFileInfoRecord(
                date: $date,
                hour: $hour,
                path: $file,
                size: $size,
                lines: $lines,
            ));
        }

        return $results;
    }

    public function getDateRange(?string $from, ?string $to): TypedRecords
    {
        $dates = new TypedRecords('string');

        // Si $from est null, utiliser la date d'aujourd'hui moins retentionDays
        if ($from === null) {
            $start = date('Y-m-d', strtotime('-'.$this->config->retentionDays.' days'));
        } else {
            $start = substr($from, 0, 10);
        }

        // Si $to est null, utiliser la date d'aujourd'hui
        if ($to === null) {
            $end = date('Y-m-d');
        } else {
            $end = substr($to, 0, 10);
        }

        $current = strtotime($start);
        $endTimestamp = strtotime($end);

        // S'assurer que la date de début n'est pas après la date de fin
        if ($current > $endTimestamp) {
            return $dates;
        }

        while ($current <= $endTimestamp) {
            $dates->add(date('Y-m-d', $current));
            $current = strtotime('+1 day', $current);
        }

        return $dates;
    }

    public function getDateRangeWithInfo(?string $from, ?string $to): DateRangeRecord
    {
        $dates = $this->getDateRange($from, $to);

        // La logique de validation est ici, dans le Service
        $dates->assertAllOfType('string');

        $start = $dates->firstItem() ?? '';
        $end = $dates->lastItem() ?? '';

        return new DateRangeRecord(
            start: $start,
            end: $end,
            dates: $dates,
        );
    }

    public function getConfig(): LoggerConfig
    {
        return $this->config;
    }

    public function listAllLogFiles(): TypedRecords
    {
        $results = new TypedRecords(LogFileInfoRecord::class);

        if (! is_dir($this->config->basePath)) {
            return $results;
        }

        $dateDirs = glob($this->config->basePath.'/*', GLOB_ONLYDIR);

        foreach ($dateDirs as $datePath) {
            $date = basename($datePath);
            $dayFiles = $this->getDayFiles($date);

            foreach ($dayFiles as $fileInfo) {
                $results->add($fileInfo);
            }
        }

        return $results;
    }

    public function cleanupOldLogs(): int
    {
        $deletedCount = 0;
        $cutoffDate = date('Y-m-d', strtotime('-'.$this->config->retentionDays.' days'));

        $allFiles = $this->listAllLogFiles();

        foreach ($allFiles as $fileInfo) {
            if ($fileInfo->date < $cutoffDate) {
                if (unlink($fileInfo->path)) {
                    $deletedCount++;
                }
            }
        }

        // Supprimer les dossiers vides
        $dateDirs = glob($this->config->basePath.'/*', GLOB_ONLYDIR);
        foreach ($dateDirs as $datePath) {
            if (count(glob($datePath.'/*.jsonl')) === 0) {
                rmdir($datePath);
            }
        }

        return $deletedCount;
    }

    private function countFileLines(string $filePath): int
    {
        $lineCount = 0;
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            return 0;
        }

        while (fgets($handle) !== false) {
            $lineCount++;
        }

        fclose($handle);

        return $lineCount;
    }

    private function getHourRange(int $hour): string
    {
        $nextHour = ($hour + 1) % 24;

        return sprintf('%02d-%02d', $hour, $nextHour);
    }
}
