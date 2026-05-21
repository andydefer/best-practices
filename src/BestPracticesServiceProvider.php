<?php

// src/BestPracticesServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\BestPractices;

use AndyDefer\BestPractices\Directive\Providers\DirectiveServiceProvider;
use AndyDefer\BestPractices\Logger\Providers\LoggerServiceProvider;
use Illuminate\Support\ServiceProvider;

final class BestPracticesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrer le service provider du module Logger
        $this->app->register(LoggerServiceProvider::class);

        // Enregistrer le service provider du module Directive
        $this->app->register(DirectiveServiceProvider::class);
    }

    public function boot(): void
    {
        // Rien ici - chaque module gère ses propres publications
    }
}
