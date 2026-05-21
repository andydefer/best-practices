<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Contracts;

interface DirectiveFactoryInterface
{
    public function make(string $class): DirectiveInterface;
}
