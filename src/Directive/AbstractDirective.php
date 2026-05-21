<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive;

use AndyDefer\BestPractices\Collections\Utility\StringTypedRecords;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveInterface;
use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Directive\Records\AskQuestionRecord;
use AndyDefer\BestPractices\Directive\Records\ConfirmQuestionRecord;
use AndyDefer\BestPractices\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\BestPractices\Directive\Records\DisplayMessageRecord;
use AndyDefer\BestPractices\Directive\Records\DisplayTableRecord;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;

abstract class AbstractDirective implements DirectiveInterface
{
    protected array $arguments = [];

    protected array $options = [];

    public function __construct(
        protected readonly DisplayMessageTask $displayMessage,
        protected readonly AskQuestionTask $askQuestion,
        protected readonly ConfirmQuestionTask $confirmQuestion,
        protected readonly DisplayTableTask $displayTable,
    ) {}

    /**
     * Retourne le blueprint de la directive (métadonnées sans exécution)
     */
    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords;
    }

    // ==================== Gestion des arguments ====================

    public function setArguments(array $args): self
    {
        $this->arguments = $args;

        return $this;
    }

    public function argument(string $key): ?string
    {
        return $this->arguments[$key] ?? null;
    }

    // ==================== Gestion des options ====================

    public function setOptions(array $opts): self
    {
        $this->options = $opts;

        return $this;
    }

    public function option(string $key): ?string
    {
        return $this->options[$key] ?? null;
    }

    public function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    // ==================== Affichage ====================

    public function line(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::LINE));
    }

    public function info(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::INFO));
    }

    public function error(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::ERROR));
    }

    public function warn(string $message): void
    {
        $this->displayMessage->execute(new DisplayMessageRecord($message, MessageType::WARNING));
    }

    // ==================== Interaction utilisateur ====================

    public function ask(string $question): string
    {
        return $this->askQuestion->execute(new AskQuestionRecord($question));
    }

    public function confirm(string $question): bool
    {
        return $this->confirmQuestion->execute(new ConfirmQuestionRecord($question));
    }

    // ==================== Tableaux ====================

    public function table(array $headers, array $rows): void
    {
        $this->displayTable->execute(new DisplayTableRecord($headers, $rows));
    }
}
