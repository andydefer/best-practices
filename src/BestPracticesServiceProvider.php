<?php
// src/BestPracticesServiceProvider.php

declare(strict_types=1);

namespace AndyDefer\BestPractices;

use AndyDefer\BestPractices\Commands\ExportBestPracticesCommand;
use Illuminate\Support\ServiceProvider;

final class BestPracticesServiceProvider extends ServiceProvider
{
    public function boot(): void {}
}
