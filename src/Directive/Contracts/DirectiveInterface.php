<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Contracts;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Records\DirectiveBlueprintRecord;

interface DirectiveInterface
{
    public function execute(): ExitCode;

    public function getSignature(): string;

    public function getDescription(): string;

    public function getAliases(): TypedRecords;

    /**
     * Retourne le blueprint (métadonnées) de la directive
     * Utilisé pour la découverte sans exécuter la directive
     */
    public function getBlueprint(): DirectiveBlueprintRecord;

    /**
     * Définit les arguments de la directive.
     */
    public function setArguments(array $args): self;

    /**
     * Récupère un argument par sa clé.
     */
    public function argument(string $key): ?string;

    /**
     * Définit les options de la directive.
     */
    public function setOptions(array $opts): self;

    /**
     * Récupère une option par sa clé.
     */
    public function option(string $key): ?string;

    /**
     * Vérifie si une option existe.
     */
    public function hasOption(string $key): bool;
}
