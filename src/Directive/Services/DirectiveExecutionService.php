<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Enums\DirectiveEventType;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\BestPractices\Directive\Records\DirectiveLogRecord;
use AndyDefer\BestPractices\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\BestPractices\Directive\Records\DisplayMessageRecord;
use AndyDefer\BestPractices\Directive\Tasks\DisplayErrorTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;

/**
 * Service de gestion des commandes de directives
 *
 * ✅ Plusieurs méthodes du même domaine (exécution, liste, aide)
 * ✅ Retourne des valeurs (ExitCode)
 * ✅ Logique métier (routage, recherche)
 * ✅ Dépendances injectées
 */
class DirectiveExecutionService
{
    private TypedRecords $directives;

    public function __construct(
        private readonly DirectiveDiscoveryService $discovery,
        private readonly DirectiveParserService $parser,
        private readonly DirectiveHydratorService $hydrator,
        private readonly DirectiveRendererService $renderer,
        private readonly DisplayMessageTask $displayMessage,
        private readonly DisplayErrorTask $displayError,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->directives = $this->discovery->discover();
    }

    /**
     * Exécute une directive (méthode principale)
     * Retourne un ExitCode comme tout bon Service
     */
    public function execute(DirectiveExecutionRecord $record): ExitCode
    {
        $signature = $record->signature;

        // Routage des commandes spéciales
        if ($this->isListCommand($signature)) {
            return $this->handleListCommand();
        }

        if ($this->isHelpCommand($signature)) {
            return $this->handleHelpCommand();
        }

        // Exécution d'une directive normale
        return $this->executeDirective($record);
    }

    /**
     * Vérifie si une directive existe (logique métier)
     */
    public function exists(string $signature): bool
    {
        return $this->findDirective($signature) !== null;
    }

    /**
     * Liste toutes les directives disponibles (logique métier)
     */
    public function listDirectives(): TypedRecords
    {
        return $this->directives;
    }

    /**
     * Trouve une directive par sa signature (logique métier)
     */
    public function findDirectiveBySignature(string $signature): ?DirectiveMetadataRecord
    {
        return $this->findDirective($signature);
    }

    /**
     * Exécute une directive et retourne le code de sortie
     */
    private function executeDirective(DirectiveExecutionRecord $record): ExitCode
    {
        $directive = $this->findDirective($record->signature);

        if ($directive === null) {
            $this->displayError->execute(
                $this->renderer->renderNotFound($record->signature)
            );

            return ExitCode::NOT_FOUND;
        }

        $parsed = $this->parser->parse($directive->signature, $record->arguments);
        $command = $this->hydrator->hydrate($directive->class, $parsed);

        $this->logStart($directive);

        $exitCode = $command->execute();

        $this->logFinish($directive, $exitCode);

        return $exitCode;
    }

    /**
     * Gère la commande --list
     */
    private function handleListCommand(): ExitCode
    {
        $this->displayMessage->execute(
            new DisplayMessageRecord(
                $this->renderer->renderList($this->directives),
                MessageType::LINE
            )
        );

        return ExitCode::SUCCESS;
    }

    /**
     * Gère la commande --help
     */
    private function handleHelpCommand(): ExitCode
    {
        $this->displayMessage->execute(
            new DisplayMessageRecord(
                $this->renderer->renderHelp(),
                MessageType::LINE
            )
        );

        return ExitCode::SUCCESS;
    }

    /**
     * Logique métier : recherche d'une directive
     */
    private function findDirective(string $signature): ?DirectiveMetadataRecord
    {
        foreach ($this->directives as $directive) {
            if ($directive->signature === $signature) {
                return $directive;
            }
            if ($directive->aliases->contains($signature)) {
                return $directive;
            }
        }

        return null;
    }

    /**
     * Logique métier : détection commande liste
     */
    private function isListCommand(string $signature): bool
    {
        return $signature === '--list' || $signature === '-l';
    }

    /**
     * Logique métier : détection commande aide
     */
    private function isHelpCommand(string $signature): bool
    {
        return $signature === '--help' || $signature === '-h';
    }

    private function logStart(DirectiveMetadataRecord $directive): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info(new DirectiveLogRecord(
            type: DirectiveEventType::STARTED,
            signature: $directive->signature,
            class: $directive->class,
        ));
    }

    private function logFinish(DirectiveMetadataRecord $directive, ExitCode $exitCode): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info(new DirectiveLogRecord(
            type: DirectiveEventType::FINISHED,
            signature: $directive->signature,
            exitCode: $exitCode,
        ));
    }
}
