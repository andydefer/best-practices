<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Commands;

use AndyDefer\BestPractices\Logger\Services\LogCleanerService;
use Illuminate\Console\Command;

final class LoggerCleanCommand extends Command
{
    protected $signature = 'logger:clean 
                            {--days=30 : Days to keep} 
                            {--dry-run : Simulate without deleting}
                            {--verbose : Show detailed output}';

    protected $description = 'Clean old log files';

    public function handle(LogCleanerService $cleaner): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $verbose = (bool) $this->option('verbose');
        $cutoffDate = date('Y-m-d', strtotime("-$days days"));

        $stats = $cleaner->getStats();
        $this->info('Current statistics:');
        $this->line("  Files: {$stats['total_files']}");
        $this->line("  Size: {$stats['total_size_mb']} MB");
        $this->line("  Lines: {$stats['total_lines']}");
        $this->line("  Range: {$stats['oldest_date']} to {$stats['newest_date']}");

        if ($verbose) {
            $this->newLine();
            $this->line('Files to delete:');
            $files = $cleaner->getFilesByDate($cutoffDate);
            foreach ($files as $file) {
                $this->line("  - {$file->date}/{$file->hour} ({$file->size} bytes)");
            }
        }

        if ($dryRun) {
            $this->warn('Dry run mode - no files will be deleted');
            $this->info("Would delete files older than {$cutoffDate}");

            return self::SUCCESS;
        }

        if ($this->confirm("Delete logs older than {$cutoffDate}?")) {
            $deleted = $cleaner->cleanWithCutoff($cutoffDate);
            $this->info("Deleted {$deleted} files");

            $newStats = $cleaner->getStats();
            $this->newLine();
            $this->info('New statistics:');
            $this->line("  Files: {$newStats['total_files']}");
            $this->line("  Size: {$newStats['total_size_mb']} MB");
        }

        return self::SUCCESS;
    }
}
