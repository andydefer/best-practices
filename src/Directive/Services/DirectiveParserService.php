<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Records\ParsedDirectiveRecord;

class DirectiveParserService
{
    /**
     * Parse une signature de directive avec ses arguments
     */
    public function parse(string $signature, TypedRecords $argv): ParsedDirectiveRecord
    {
        $arguments = new TypedRecords('string');
        $options = new TypedRecords('string');

        // Extraire les noms des paramètres de la signature
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $parameterNames = $this->cleanParameterNames($matches[1]);

        $argIndex = 0;
        foreach ($argv as $arg) {
            if ($this->isLongOption($arg)) {
                $this->parseLongOption($arg, $options);
            } elseif ($this->isShortOption($arg)) {
                $this->parseShortOption($arg, $options);
            } else {
                $this->parseArgument($arg, $arguments, $parameterNames, $argIndex);
            }
        }

        return new ParsedDirectiveRecord($arguments, $options);
    }

    /**
     * Extrait les informations d'aide d'une signature
     */
    public function extractHelp(string $signature): array
    {
        preg_match_all('/\{([^}]+)\}/', $signature, $matches);
        $params = [];

        foreach ($matches[1] as $param) {
            if ($this->isLongOption($param)) {
                $params[] = $this->extractOptionHelp($param);
            } else {
                $params[] = $this->extractArgumentHelp($param);
            }
        }

        return $params;
    }

    /**
     * Convertit un ParsedDirectiveRecord en tableau associatif
     */
    public function toArray(ParsedDirectiveRecord $parsed): array
    {
        return [
            'arguments' => $this->argumentsToArray($parsed->arguments),
            'options' => $this->optionsToArray($parsed->options),
        ];
    }

    // ==================== Méthodes privées ====================

    private function cleanParameterNames(array $params): array
    {
        return array_map(function ($param) {
            $param = ltrim($param, '-');
            $param = str_contains($param, '=') ? explode('=', $param)[0] : $param;

            return str_ends_with($param, '?') ? substr($param, 0, -1) : $param;
        }, $params);
    }

    private function isLongOption(string $arg): bool
    {
        return str_starts_with($arg, '--');
    }

    private function isShortOption(string $arg): bool
    {
        return str_starts_with($arg, '-') && ! str_starts_with($arg, '--');
    }

    private function parseLongOption(string $arg, TypedRecords $options): void
    {
        $parts = explode('=', substr($arg, 2), 2);
        $options->add($parts[0]);
        // ✅ CORRECTION 2: Si valeur vide, garder '' au lieu de 'true'
        $options->add($parts[1] ?? 'true');
    }

    private function parseShortOption(string $arg, TypedRecords $options): void
    {
        $options->add(substr($arg, 1));
        $options->add('true');
    }

    private function parseArgument(string $arg, TypedRecords $arguments, array $parameterNames, int &$argIndex): void
    {
        if (isset($parameterNames[$argIndex])) {
            $arguments->add($arg);
            $arguments->add($parameterNames[$argIndex]);
        } else {
            $arguments->add($arg);
        }
        $argIndex++;
    }

    private function extractOptionHelp(string $param): array
    {
        $cleanParam = substr($param, 2); // Enlever --

        if (str_contains($cleanParam, '=')) {
            $parts = explode('=', $cleanParam, 2);

            return [
                'name' => $parts[0],
                'type' => 'option',
                'required' => false,
                'default' => $parts[1] === '' ? null : $parts[1],
            ];
        }

        return [
            'name' => $cleanParam,
            'type' => 'option',
            'required' => false,
            'default' => null,
        ];
    }

    private function extractArgumentHelp(string $param): array
    {
        $isOptional = str_ends_with($param, '?');

        return [
            'name' => $isOptional ? substr($param, 0, -1) : $param,
            'type' => 'argument',
            'required' => ! $isOptional,
        ];
    }

    private function argumentsToArray(TypedRecords $arguments): array
    {
        $result = [];
        $items = $arguments->toArray();

        for ($i = 0; $i < $arguments->count(); $i += 2) {
            if (isset($items[$i + 1])) {
                $result[$items[$i + 1]] = $items[$i];
            }
        }

        return $result;
    }

    private function optionsToArray(TypedRecords $options): array
    {
        $result = [];
        $items = $options->toArray();

        for ($i = 0; $i < $options->count(); $i += 2) {
            if (isset($items[$i])) {
                $value = $items[$i + 1] ?? 'true';
                // ✅ Conversion correcte des valeurs
                if ($value === 'true') {
                    $result[$items[$i]] = true;
                } elseif ($value === 'false') {
                    $result[$items[$i]] = false;
                } elseif ($value === '') {
                    $result[$items[$i]] = true; // Option sans valeur = flag
                } else {
                    $result[$items[$i]] = $value;
                }
            }
        }

        return $result;
    }
}
