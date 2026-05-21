<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveInterface;
use AndyDefer\BestPractices\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\BestPractices\Directive\Records\ParsedDirectiveRecord;

class DirectiveHydratorService
{
    public function __construct(
        private readonly DirectiveFactoryInterface $factory,
    ) {}

    public function hydrate(string $class, ParsedDirectiveRecord $parsed): DirectiveInterface
    {
        $directive = $this->factory->make($class);

        if (method_exists($directive, 'setArguments')) {
            $directive->setArguments($this->extractArguments($parsed->arguments));
        }

        if (method_exists($directive, 'setOptions')) {
            $directive->setOptions($this->extractOptions($parsed->options));
        }

        return $directive;
    }

    public function hydrateBlueprint(string $class): DirectiveBlueprintRecord
    {
        try {
            $directive = $this->factory->make($class);
            $blueprint = $directive->getBlueprint();

            return $blueprint;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function hydrateForAliases(string $class): DirectiveInterface
    {
        try {
            $directive = $this->factory->make($class);

            return $directive;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    private function extractArguments(TypedRecords $arguments): array
    {
        $result = [];
        $items = $arguments->toArray();

        for ($i = 0; $i < $arguments->count(); $i += 2) {
            $key = $items[$i + 1] ?? null;
            $value = $items[$i] ?? null;
            if ($key !== null) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function extractOptions(TypedRecords $options): array
    {
        $result = [];
        $items = $options->toArray();

        for ($i = 0; $i < $options->count(); $i += 2) {
            $key = $items[$i] ?? null;
            $value = $items[$i + 1] ?? null;
            if ($key !== null) {
                $result[$key] = $value === 'true' ? true : ($value === 'false' ? false : $value);
            }
        }

        return $result;
    }
}
