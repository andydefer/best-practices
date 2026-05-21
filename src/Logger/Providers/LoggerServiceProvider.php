<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Providers;

use AndyDefer\BestPractices\Logger\Config\LoggerConfig;
use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;
use AndyDefer\BestPractices\Logger\Logger;
use AndyDefer\BestPractices\Logger\Services\LogPathService;
use AndyDefer\BestPractices\Logger\Services\LogSerializerService;
use AndyDefer\BestPractices\Logger\Services\Tasks\QueryLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\StreamLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\WriteLogTask;
use Illuminate\Support\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Config
        $this->app->singleton(LoggerConfig::class, function ($app) {
            $config = LoggerConfig::default();

            if ($app->has('config') && $app->config->has('logger')) {
                $appConfig = $app->config->get('logger', []);

                if (isset($appConfig['path'])) {
                    $config = $config->withBasePath($appConfig['path']);
                }
                if (isset($appConfig['retention_days'])) {
                    $config = $config->withRetentionDays($appConfig['retention_days']);
                }
            }

            return $config;
        });

        // Services
        $this->app->singleton(LogPathService::class, function ($app) {
            return new LogPathService($app->make(LoggerConfig::class));
        });

        $this->app->singleton(LogSerializerService::class, function ($app) {
            return new LogSerializerService;
        });

        // Tasks
        $this->app->singleton(WriteLogTask::class, function ($app) {
            return new WriteLogTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });

        $this->app->singleton(QueryLogsTask::class, function ($app) {
            return new QueryLogsTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });

        $this->app->singleton(StreamLogsTask::class, function ($app) {
            return new StreamLogsTask(
                $app->make(LogPathService::class),
                $app->make(LogSerializerService::class),
            );
        });

        // Logger principal
        $this->app->singleton(LoggerInterface::class, function ($app) {
            return new Logger(
                $app->make(WriteLogTask::class),
                $app->make(QueryLogsTask::class),
                $app->make(StreamLogsTask::class),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/logger.php' => config_path('logger.php'),
        ], 'logger-config');
    }
}
