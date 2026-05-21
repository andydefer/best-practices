<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Factories;

use AndyDefer\BestPractices\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveInterface;
use Illuminate\Contracts\Container\Container;

final class ContainerDirectiveFactory implements DirectiveFactoryInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function make(string $class): DirectiveInterface
    {
        return $this->container->make($class);
    }
}
