<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Feature;

use AndyDefer\BestPractices\Directive\Config\DirectiveConfig;
use AndyDefer\BestPractices\Directive\DirectiveKernel;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Providers\DirectiveServiceProvider;
use AndyDefer\BestPractices\Tests\TestCase;

final class DirectiveIntegrationTest extends TestCase
{
    private DirectiveKernel $kernel;

    private string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturesPath = __DIR__.'/../Fixtures/Directives';

        // Utiliser default() et withDirectivesPath() au lieu de new
        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        $this->app->instance(DirectiveConfig::class, $config);
        $this->app->register(DirectiveServiceProvider::class);

        $this->kernel = $this->app->make(DirectiveKernel::class);
    }

    public function test_kernel_returns_success_for_list_command(): void
    {
        $result = $this->kernel->run(['directive', '--list']);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_kernel_returns_not_found_for_unknown_command(): void
    {
        ob_start();
        $result = $this->kernel->run(['directive', 'unknown:command']);
        ob_end_clean();

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }
}
