# Principe d'usage des Contextes (Version Finale)

## Table des matières

1. [Définition et concepts](#1-définition-et-concepts)
2. [Différence entre Record, Value Object et Contexte](#2-différence-entre-record-value-object-et-contexte)
3. [Structure d'un Contexte](#3-structure-dun-contexte)
4. [Règles fondamentales](#4-règles-fondamentales)
5. [Contexte injecté dans le constructeur](#5-contexte-injecté-dans-le-constructeur)
6. [Utilisation avec les Services](#6-utilisation-avec-les-services)
7. [Exemple concret : Chaîne de responsabilité](#7-exemple-concret--chaîne-de-responsabilité)
8. [Avantages architecturaux](#8-avantages-architecturaux)
9. [Récapitulatif des différences](#9-récapitulatif-des-différences)
10. [Règle d'or](#10-règle-dor)

---

## 1. Définition et concepts

Un **Contexte** est une classe qui maintient un **état** et permet le transport de cet état entre plusieurs Services. Contrairement aux Services qui sont sans état, les Contextes sont conçus pour **stocker de la donnée volatile** et la faire évoluer au fil d'un traitement.

```
Contexte → Classe avec SEULEMENT état → Transport d'UN ÉTAT → Passe entre Services
```

### 1.1. Pourquoi un Contexte ?

| Problème | Solution avec Contexte |
|----------|----------------------|
| Les Services n'ont pas d'état | Le Contexte porte l'état |
| Besoin de partager des données entre Services | Le Contexte est passé en paramètre |
| Traitement en plusieurs étapes (chaîne de responsabilité) | Chaque Service modifie le Contexte |
| Éviter les paramètres de méthode trop nombreux | Le Contexte regroupe les données |

---

## 2. Différence entre Record, Value Object et Contexte

| Type | Rôle | Mutabilité | Identité | Logique | Exemple |
|------|------|------------|----------|---------|---------|
| **Record** | Transport de données **immuables** | ❌ Immutable | Par ses valeurs | ❌ Aucune | `OrderRecord`, `UserData` |
| **Value Object** | Concept métier **immuable** | ❌ Immutable | Par ses valeurs | ✅ Logique métier | `EmailAddress`, `Money` |
| **Contexte** | Transport d'**état** mutable | ✅ Mutable | Identité propre | ❌ Aucune | `ProcessingContext`, `OrderContext` |

### 2.1. Record (transport de données immuables)

```php
// Record : données figées, pas de logique, pas de modification
final class OrderRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $id,
        public readonly float $amount,
        public readonly string $customerId,
    ) {}
}

// ❌ Impossible de modifier un Record
$record = new OrderRecord('123', 100.0, 'CUST-1');
// $record->amount = 200.0; // ERREUR : readonly
```

### 2.2. Value Object (concept métier immuable avec logique)

```php
// Value Object : concept métier, logique métier, immutable
final class Money extends AbstractValueObject
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
    ) {}
    
    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Different currencies');
        }
        
        return new Money($this->amount + $other->amount, $this->currency);
    }
    
    public function getAmount(): float { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
}
```

### 2.3. Contexte (état mutable, passe entre Services)

```php
// Contexte : état mutable, transport entre Services, pas de logique métier
final class ProcessingContext
{
    private string $currentStep = 'start';
    private StringTypedCollection $results;
    private ?string $error = null;
    private int $retryCount = 0;
    
    public function __construct()
    {
        $this->results = new StringTypedCollection();
    }
    
    public function getCurrentStep(): string { return $this->currentStep; }
    public function setCurrentStep(string $step): void { $this->currentStep = $step; }
    
    public function addResult(string $key, mixed $value): void { $this->results->add("{$key}:{$value}"); }
    public function getResults(): StringTypedCollection { return $this->results; }
    
    public function getError(): ?string { return $this->error; }
    public function setError(?string $error): void { $this->error = $error; }
    
    public function incrementRetryCount(): void { $this->retryCount++; }
    public function getRetryCount(): int { return $this->retryCount; }
}
```

---

## 3. Structure d'un Contexte

### 3.1. Structure de base (avec TypedCollection, pas de tableaux bruts)

```php
<?php

declare(strict_types=1);

namespace App\Contexts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Collections\Utility\IntTypedCollection;
use App\Records\ValidationErrorRecord;
use App\Records\StepResultRecord;
use App\Collections\ValidationErrorRecordCollection;
use App\Collections\StepResultRecordCollection;

final class OrderProcessingContext
{
    // ✅ Propriétés privées (l'état)
    private string $orderId;
    private float $amount;
    private string $status = 'pending';
    
    // ✅ Utilisation de TypedCollection au lieu de tableaux bruts
    private ValidationErrorRecordCollection $validationErrors;
    private StepResultRecordCollection $stepResults;
    
    private ?string $approvedBy = null;
    private \DateTimeImmutable $startedAt;
    private int $attempts = 0;
    
    // ✅ Constructeur avec paramètres obligatoires
    public function __construct(string $orderId, float $amount)
    {
        $this->orderId = $orderId;
        $this->amount = $amount;
        $this->startedAt = new \DateTimeImmutable();
        $this->validationErrors = new ValidationErrorRecordCollection();
        $this->stepResults = new StepResultRecordCollection();
    }
    
    // ✅ Getters (lecture de l'état)
    public function getOrderId(): string { return $this->orderId; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): string { return $this->status; }
    public function getValidationErrors(): ValidationErrorRecordCollection { return $this->validationErrors; }
    public function getStepResults(): StepResultRecordCollection { return $this->stepResults; }
    public function getApprovedBy(): ?string { return $this->approvedBy; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getAttempts(): int { return $this->attempts; }
    
    // ✅ Setters (modification de l'état)
    public function setStatus(string $status): void { $this->status = $status; }
    
    // ✅ Ajout via TypedCollection
    public function addValidationError(ValidationErrorRecord $error): void
    {
        $this->validationErrors->add($error);
    }
    
    public function addStepResult(StepResultRecord $result): void
    {
        $this->stepResults->add($result);
    }
    
    public function setApprovedBy(string $approvedBy): void { $this->approvedBy = $approvedBy; }
    public function incrementAttempts(): void { $this->attempts++; }
    
    // ✅ Méthodes de question (utilitaires)
    public function isValid(): bool { return $this->validationErrors->isEmpty(); }
    public function isApproved(): bool { return $this->approvedBy !== null; }
    public function hasError(): bool { return !$this->validationErrors->isEmpty(); }
    public function getLastStepResult(): ?StepResultRecord
    {
        return $this->stepResults->last();
    }
}
```

### 3.2. Collections spécialisées associées

```php
<?php

declare(strict_types=1);

namespace App\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use App\Records\ValidationErrorRecord;

final class ValidationErrorRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(ValidationErrorRecord::class);
    }
    
    public function getByField(string $field): self
    {
        return $this->filter(fn(ValidationErrorRecord $error) => $error->field === $field);
    }
    
    public function getMessages(): StringTypedCollection
    {
        return $this->mapToType(
            fn(ValidationErrorRecord $error) => $error->message,
            StringTypedCollection::class
        );
    }
}

final class StepResultRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(StepResultRecord::class);
    }
    
    public function getByStepName(string $stepName): self
    {
        return $this->filter(fn(StepResultRecord $result) => $result->stepName === $stepName);
    }
    
    public function getTotalDuration(): float
    {
        return $this->sum(fn(StepResultRecord $result) => $result->duration);
    }
}
```

### 3.3. Records associés

```php
<?php

declare(strict_types=1);

namespace App\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ValidationErrorRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
        public readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}

final class StepResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $stepName,
        public readonly mixed $result,
        public readonly float $duration,
        public readonly \DateTimeImmutable $executedAt = new \DateTimeImmutable(),
    ) {}
}

final class WorkflowLogRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $step,
        public readonly string $message,
        public readonly string $level,
        public readonly \DateTimeImmutable $timestamp = new \DateTimeImmutable(),
    ) {}
}
```

### 3.4. Contexte qui contient d'autres Contextes, Records, Collections

```php
<?php

declare(strict_types=1);

namespace App\Contexts;

use App\Entities\Order;
use App\Entities\User;
use App\Records\PaymentRecord;
use App\Records\TransactionRecord;
use App\Collections\PaymentEventRecordCollection;

final class PaymentContext
{
    // ✅ Peut contenir d'autres Contextes, Records, Models, Entities
    private Order $order;
    private User $user;
    private PaymentRecord $paymentData;
    private ProcessingContext $processingContext;
    
    // ✅ État spécifique au paiement (avec TypedCollection)
    private ?TransactionRecord $transaction = null;
    private string $paymentStatus = 'pending';
    private ?ValidationErrorRecord $failureReason = null;
    private int $retryCount = 0;
    
    // ✅ Historique des événements (TypedCollection spécialisée)
    private PaymentEventRecordCollection $events;
    
    public function __construct(Order $order, User $user, PaymentRecord $paymentData)
    {
        $this->order = $order;
        $this->user = $user;
        $this->paymentData = $paymentData;
        $this->processingContext = new ProcessingContext();
        $this->events = new PaymentEventRecordCollection();
        $this->addEvent(PaymentEventRecord::started($order->getId()));
    }
    
    // Getters
    public function getOrder(): Order { return $this->order; }
    public function getUser(): User { return $this->user; }
    public function getPaymentData(): PaymentRecord { return $this->paymentData; }
    public function getProcessingContext(): ProcessingContext { return $this->processingContext; }
    public function getTransaction(): ?TransactionRecord { return $this->transaction; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }
    public function getFailureReason(): ?ValidationErrorRecord { return $this->failureReason; }
    public function getRetryCount(): int { return $this->retryCount; }
    public function getEvents(): PaymentEventRecordCollection { return $this->events; }
    
    // Setters
    public function setTransaction(TransactionRecord $transaction): void 
    { 
        $this->transaction = $transaction;
        $this->addEvent(PaymentEventRecord::transactionCreated($transaction->getId()));
    }
    
    public function setPaymentStatus(string $status): void 
    { 
        $this->paymentStatus = $status;
        $this->addEvent(PaymentEventRecord::statusChanged($status));
    }
    
    public function setFailureReason(ValidationErrorRecord $reason): void 
    { 
        $this->failureReason = $reason;
        $this->addEvent(PaymentEventRecord::failed($reason->message));
    }
    
    public function incrementRetryCount(): void 
    { 
        $this->retryCount++;
        $this->addEvent(PaymentEventRecord::retryAttempt($this->retryCount));
    }
    
    private function addEvent(PaymentEventRecord $event): void
    {
        $this->events->add($event);
    }
    
    // Méthodes de question
    public function isSuccessful(): bool { return $this->paymentStatus === 'success'; }
    public function isFailed(): bool { return $this->paymentStatus === 'failed'; }
    public function shouldRetry(): bool { return $this->isFailed() && $this->retryCount < 3; }
    public function hasTransaction(): bool { return $this->transaction !== null; }
}
```

### 3.5. Contexte avec enum

```php
<?php

declare(strict_types=1);

namespace App\Contexts;

use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use App\Records\WorkflowLogRecord;
use App\Records\ExceptionRecord;
use App\Collections\WorkflowLogRecordCollection;
use App\Collections\StepResultRecordCollection;

enum ProcessingStep: string
{
    case START = 'start';
    case VALIDATE = 'validate';
    case PROCESS = 'process';
    case COMPLETE = 'complete';
    case FAILED = 'failed';
}

final class WorkflowContext
{
    private ProcessingStep $currentStep = ProcessingStep::START;
    
    // ✅ Utilisation de TypedCollection spécialisée
    private WorkflowLogRecordCollection $logs;
    private StepResultRecordCollection $stepResults;
    
    private ?ExceptionRecord $exception = null;
    
    public function __construct()
    {
        $this->logs = new WorkflowLogRecordCollection();
        $this->stepResults = new StepResultRecordCollection();
    }
    
    public function getCurrentStep(): ProcessingStep { return $this->currentStep; }
    public function setCurrentStep(ProcessingStep $step): void { $this->currentStep = $step; }
    
    public function addLog(WorkflowLogRecord $log): void
    {
        $this->logs->add($log);
    }
    
    public function log(string $message, string $level = 'info'): void
    {
        $this->logs->add(new WorkflowLogRecord($this->currentStep->value, $message, $level));
    }
    
    public function getLogs(): WorkflowLogRecordCollection { return $this->logs; }
    
    public function addStepResult(StepResultRecord $result): void
    {
        $this->stepResults->add($result);
    }
    
    public function getStepResults(): StepResultRecordCollection { return $this->stepResults; }
    
    public function setException(\Throwable $exception): void 
    { 
        $this->exception = new ExceptionRecord($exception);
        $this->setCurrentStep(ProcessingStep::FAILED);
        $this->log($exception->getMessage(), 'error');
    }
    
    public function getException(): ?ExceptionRecord { return $this->exception; }
    public function hasException(): bool { return $this->exception !== null; }
    public function isCompleted(): bool { return $this->currentStep === ProcessingStep::COMPLETE; }
    public function isFailed(): bool { return $this->currentStep === ProcessingStep::FAILED; }
}
```

---

## 4. Règles fondamentales

### 4.1. Ce qu'un Contexte PEUT faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Avoir des propriétés privées** | ✅ | `private string $orderId;` |
| **Avoir des getters** | ✅ | `public function getOrderId(): string` |
| **Avoir des setters** | ✅ | `public function setOrderId(string $id): void` |
| **Avoir des méthodes utilitaires de question** | ✅ | `public function isValid(): bool` |
| **Contenir d'autres Contextes** | ✅ | `private ProcessingContext $context;` |
| **Contenir des Records** | ✅ | `private OrderRecord $record;` |
| **Contenir des Entities/Models** | ✅ | `private Order $order;` |
| **Contenir des TypedCollection** | ✅ | `private ValidationErrorRecordCollection $errors;` |
| **Être passé entre Services** | ✅ | `$service->execute($context);` |
| **Être mutable** | ✅ | Les setters modifient l'état |
| **Être injecté dans le constructeur d'un Service** | ✅ | `new Service($context)` |

### 4.2. Ce qu'un Contexte NE PEUT PAS faire (⚠️ STRICT)

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Avoir de la logique métier complexe** | Violation du SRP, le Contexte n'est qu'un conteneur d'état | Utiliser un Service |
| **Avoir des méthodes de calcul** | Le Contexte ne fait pas de calcul | Utiliser un Service |
| **Avoir des effets de bord (API, DB, fichiers)** | Le Contexte ne doit qu transporter l'état | Utiliser un Service |
| **Être immuable** | Le Contexte est conçu pour être mutable | Utiliser un Record à la place |
| **Avoir des méthodes statiques** | Le Contexte est une instance avec état | Injecter le Contexte |
| **Retourner des tableaux bruts** | Violation des principes du package | Utiliser TypedCollection |
| **Contenir des constantes de configuration** | La config appartient aux Configs | Utiliser une Config injectée |

### 4.3. Récapitulatif des interdictions

```php
// ❌ MAUVAIS - Contexte avec tableau brut et logique métier
final class BadContext
{
    private array $errors = [];  // ❌ Tableau brut
    
    // ❌ Méthode de calcul (logique métier)
    public function calculateTax(): float
    {
        return $this->amount * 0.2;  // À faire dans un Service
    }
    
    // ❌ Retourne un tableau brut
    public function getErrors(): array  // ❌ INTERDIT
    {
        return $this->errors;
    }
    
    // ❌ Effet de bord
    public function saveToDatabase(): void
    {
        DB::table('orders')->insert([...]);  // À faire dans un Service
    }
}

// ✅ BON - Contexte avec TypedCollection et sans logique
final class GoodContext
{
    private ValidationErrorRecordCollection $errors;  // ✅ TypedCollection spécialisée
    
    public function __construct()
    {
        $this->errors = new ValidationErrorRecordCollection();
    }
    
    public function getErrors(): ValidationErrorRecordCollection { return $this->errors; }
    
    public function addError(ValidationErrorRecord $error): void { $this->errors->add($error); }
    
    // ✅ Méthode utilitaire de question (pas de logique métier)
    public function hasErrors(): bool { return !$this->errors->isEmpty(); }
}
```

---

## 5. Contexte injecté dans le constructeur

> **⚠️ Un Service peut recevoir un Contexte dans son constructeur pour un traitement long ou pour partager l'état entre plusieurs méthodes du même Service.**

### 5.1. Service avec Contexte dans le constructeur

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contexts\BatchProcessingContext;
use App\Records\BatchResultRecord;
use App\Collections\BatchResultRecordCollection;

final class BatchProcessorService
{
    private BatchProcessingContext $context;
    
    // ✅ Le Contexte est injecté dans le constructeur
    public function __construct(BatchProcessingContext $context)
    {
        $this->context = $context;
    }
    
    public function processNext(): void
    {
        if ($this->context->isCompleted()) {
            return;
        }
        
        $currentItem = $this->context->getCurrentItem();
        
        try {
            $result = $this->processItem($currentItem);
            $this->context->addSuccess($currentItem, $result);
        } catch (\Exception $e) {
            $this->context->addFailure($currentItem, $e->getMessage());
        }
        
        $this->context->moveToNext();
    }
    
    public function processAll(): BatchResultRecord
    {
        while (!$this->context->isCompleted()) {
            $this->processNext();
        }
        
        return $this->context->getFinalResult();
    }
    
    private function processItem(mixed $item): mixed
    {
        // Traitement métier
        return $item;
    }
}

// Utilisation
$context = new BatchProcessingContext($items);
$processor = new BatchProcessorService($context);
$result = $processor->processAll();
```

### 5.2. Service avec Contexte optionnel dans le constructeur

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contexts\ProcessingContext;

final class FlexibleService
{
    private ProcessingContext $context;
    
    // ✅ Contexte optionnel dans le constructeur
    public function __construct(?ProcessingContext $context = null)
    {
        $this->context = $context ?? new ProcessingContext();
    }
    
    public function execute(): void
    {
        // Utilise $this->context
    }
    
    // ✅ On peut aussi passer un autre Contexte via une méthode
    public function executeWithContext(ProcessingContext $context): void
    {
        $this->context = $context;
        $this->execute();
    }
    
    public function getContext(): ProcessingContext
    {
        return $this->context;
    }
}
```

### 5.3. Orchestrator avec Contexte injecté

```php
<?php

declare(strict_types=1);

namespace App\Orchestrators;

use App\Contexts\PaymentContext;
use App\Services\PaymentValidatorService;
use App\Services\PaymentProcessorService;
use App\Services\NotificationService;

final class PaymentOrchestrator
{
    private PaymentContext $context;
    
    // ✅ Le Contexte est injecté une fois pour tout le traitement
    public function __construct(PaymentContext $context)
    {
        $this->context = $context;
    }
    
    public function execute(): PaymentContext
    {
        // ✅ Le même Contexte passe à travers tous les Services
        $validator = new PaymentValidatorService();
        $validator->validate($this->context);
        
        if ($this->context->getPaymentStatus() === 'failed') {
            $notifier = new NotificationService();
            $notifier->notifyFailure($this->context);
            return $this->context;
        }
        
        $processor = new PaymentProcessorService();
        $processor->process($this->context);
        
        $notifier = new NotificationService();
        
        if ($this->context->isSuccessful()) {
            $notifier->notifySuccess($this->context);
        } else {
            $notifier->notifyFailure($this->context);
        }
        
        return $this->context;
    }
}

// Utilisation
$context = new PaymentContext($order, $user, $paymentData);
$orchestrator = new PaymentOrchestrator($context);
$result = $orchestrator->execute();
```

---

## 6. Utilisation avec les Services

### 6.1. Service qui modifie un Contexte (passé en paramètre)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contexts\PaymentContext;
use App\Records\ValidationErrorRecord;
use App\Collections\ValidationErrorRecordCollection;

final class PaymentValidatorService
{
    public function __construct(
        private readonly ValidationRulesService $rules,
    ) {}
    
    // ✅ Le Service reçoit le Contexte en paramètre et le modifie
    public function validate(PaymentContext $context): void
    {
        $paymentData = $context->getPaymentData();
        
        if ($paymentData->amount <= 0) {
            $context->addValidationError(new ValidationErrorRecord('amount', 'Amount must be positive'));
            $context->setPaymentStatus('failed');
            return;
        }
        
        if (!$this->rules->isValidCurrency($paymentData->currency)) {
            $context->addValidationError(new ValidationErrorRecord('currency', 'Invalid currency'));
            $context->setPaymentStatus('failed');
            return;
        }
        
        $context->setPaymentStatus('validated');
    }
}
```

### 6.2. Service qui lit un Contexte (passé en paramètre)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contexts\PaymentContext;
use App\Records\TransactionRecord;
use App\Records\ValidationErrorRecord;

final class PaymentProcessorService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}
    
    // ✅ Le Service lit le Contexte et le modifie
    public function process(PaymentContext $context): void
    {
        if ($context->getPaymentStatus() !== 'validated') {
            $context->setFailureReason(new ValidationErrorRecord(
                field: 'payment',
                message: 'Payment not validated'
            ));
            $context->setPaymentStatus('failed');
            return;
        }
        
        try {
            $result = $this->gateway->charge(
                $context->getPaymentData()->amount,
                $context->getPaymentData()->currency,
                $context->getUser()->getId(),
            );
            
            $transaction = new TransactionRecord(
                id: $result->getTransactionId(),
                amount: $context->getPaymentData()->amount,
                status: 'success',
            );
            
            $context->setTransaction($transaction);
            $context->setPaymentStatus('success');
            
            // ✅ Le Contexte peut stocker des résultats intermédiaires via TypedCollection
            $context->getProcessingContext()->addStepResult(
                new StepResultRecord('gateway', $result, $result->getDuration())
            );
            
        } catch (\Exception $e) {
            $context->setFailureReason(new ValidationErrorRecord(
                field: 'gateway',
                message: $e->getMessage()
            ));
            $context->setPaymentStatus('failed');
        }
    }
}
```

---

## 7. Exemple concret : Chaîne de responsabilité

### 7.1. Collections spécialisées

```php
<?php

declare(strict_types=1);

namespace App\Collections;

use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use App\Records\ApprovalStepRecord;

final class ApprovalStepRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(ApprovalStepRecord::class);
    }
    
    public function getApproverIds(): StringTypedCollection
    {
        return $this->mapToType(
            fn(ApprovalStepRecord $step) => $step->approverId,
            StringTypedCollection::class
        );
    }
    
    public function getTotalDuration(): float
    {
        return $this->sum(fn(ApprovalStepRecord $step) => $step->duration);
    }
}
```

### 7.2. Records associés

```php
<?php

declare(strict_types=1);

namespace App\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ApprovalRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $documentId,
        public readonly StringTypedCollection $approvers,
        public readonly \DateTimeImmutable $approvedAt,
        public readonly bool $isApproved,
        public readonly ?string $rejectionReason = null,
    ) {}
}

final class ApprovalStepRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $stepName,
        public readonly string $approverId,
        public readonly float $duration,
        public readonly \DateTimeImmutable $executedAt,
        public readonly bool $skipped = false,
    ) {}
}

final class DocumentRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $id,
        public readonly float $amount,
        public readonly string $department,
        public readonly bool $hasSupportingDocs,
    ) {}
}
```

### 7.3. Contexte de la chaîne

```php
<?php

declare(strict_types=1);

namespace App\Contexts;

use App\Records\ApprovalRecord;
use App\Records\ApprovalStepRecord;
use App\Records\DocumentRecord;
use App\Records\ValidationErrorRecord;
use App\Collections\ApprovalStepRecordCollection;
use App\Collections\ValidationErrorRecordCollection;

final class ApprovalContext
{
    // ✅ État du traitement avec TypedCollection
    private DocumentRecord $document;
    private string $currentStep = 'start';
    private ApprovalStepRecordCollection $steps;
    private ValidationErrorRecordCollection $rejections;
    private ?ApprovalRecord $finalApproval = null;
    private \DateTimeImmutable $startedAt;
    private int $stepsExecuted = 0;
    
    public function __construct(DocumentRecord $document)
    {
        $this->document = $document;
        $this->startedAt = new \DateTimeImmutable();
        $this->steps = new ApprovalStepRecordCollection();
        $this->rejections = new ValidationErrorRecordCollection();
    }
    
    // Getters
    public function getDocument(): DocumentRecord { return $this->document; }
    public function getCurrentStep(): string { return $this->currentStep; }
    public function getSteps(): ApprovalStepRecordCollection { return $this->steps; }
    public function getRejections(): ValidationErrorRecordCollection { return $this->rejections; }
    public function getFinalApproval(): ?ApprovalRecord { return $this->finalApproval; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getStepsExecuted(): int { return $this->stepsExecuted; }
    
    // Setters
    public function setCurrentStep(string $step): void { $this->currentStep = $step; }
    
    public function addStep(ApprovalStepRecord $step): void 
    { 
        $this->steps->add($step);
        $this->stepsExecuted++;
    }
    
    public function addRejection(ValidationErrorRecord $error): void 
    { 
        $this->rejections->add($error);
    }
    
    public function setFinalApproval(ApprovalRecord $approval): void 
    { 
        $this->finalApproval = $approval;
        $this->currentStep = $approval->isApproved ? 'completed' : 'rejected';
    }
    
    // Méthodes de question
    public function isApproved(): bool 
    { 
        return $this->finalApproval !== null && $this->finalApproval->isApproved; 
    }
    
    public function isRejected(): bool 
    { 
        return !$this->rejections->isEmpty() || 
               ($this->finalApproval !== null && !$this->finalApproval->isApproved);
    }
    
    public function getTotalDuration(): float 
    { 
        return $this->steps->getTotalDuration();
    }
}
```

### 7.4. Handlers (Services) qui modifient le Contexte

```php
<?php

declare(strict_types=1);

namespace App\ChainOfResponsibility\Handlers;

use App\Contexts\ApprovalContext;
use App\Records\ApprovalStepRecord;

abstract class ApprovalHandler
{
    private ?ApprovalHandler $next = null;
    
    public function setNext(ApprovalHandler $handler): ApprovalHandler
    {
        $this->next = $handler;
        return $handler;
    }
    
    public function handle(ApprovalContext $context): void
    {
        $startTime = microtime(true);
        
        // ✅ Le Service modifie le Contexte
        $this->process($context);
        
        $duration = microtime(true) - $startTime;
        
        // ✅ Ajout d'un Record (via TypedCollection)
        $context->addStep(new ApprovalStepRecord(
            stepName: static::class,
            approverId: $this->getApproverId(),
            duration: $duration,
            executedAt: new \DateTimeImmutable(),
            skipped: $this->wasSkipped($context),
        ));
        
        if ($this->next !== null && !$context->isRejected() && !$context->isApproved()) {
            $this->next->handle($context);
        }
    }
    
    protected abstract function process(ApprovalContext $context): void;
    protected abstract function getApproverId(): string;
    protected function wasSkipped(ApprovalContext $context): bool { return false; }
}

final class ValidationHandler extends ApprovalHandler
{
    protected function getApproverId(): string { return 'system'; }
    
    protected function process(ApprovalContext $context): void
    {
        $document = $context->getDocument();
        
        if ($document->amount <= 0) {
            $context->addRejection(new ValidationErrorRecord('amount', 'Amount must be positive'));
            $context->setCurrentStep('validation_failed');
            return;
        }
        
        if ($document->amount > 10000 && !$document->hasSupportingDocs) {
            $context->addRejection(new ValidationErrorRecord('supporting_docs', 'Supporting documents required for high amount'));
            $context->setCurrentStep('validation_failed');
            return;
        }
        
        $context->setCurrentStep('validation_passed');
    }
}

final class ManagerApprovalHandler extends ApprovalHandler
{
    private string $department;
    
    public function __construct(string $department)
    {
        $this->department = $department;
    }
    
    protected function getApproverId(): string { return 'manager_' . $this->department; }
    
    protected function process(ApprovalContext $context): void
    {
        $document = $context->getDocument();
        
        if ($document->amount <= 5000) {
            $context->setCurrentStep('manager_skipped');
            return;
        }
        
        $context->setCurrentStep('manager_approved');
    }
    
    protected function wasSkipped(ApprovalContext $context): bool
    {
        return $context->getCurrentStep() === 'manager_skipped';
    }
}

final class DirectorApprovalHandler extends ApprovalHandler
{
    protected function getApproverId(): string { return 'director'; }
    
    protected function process(ApprovalContext $context): void
    {
        $document = $context->getDocument();
        
        if ($document->amount <= 50000) {
            $context->setCurrentStep('director_skipped');
            return;
        }
        
        $context->setCurrentStep('director_approved');
    }
    
    protected function wasSkipped(ApprovalContext $context): bool
    {
        return $context->getCurrentStep() === 'director_skipped';
    }
}

final class FinalApprovalHandler extends ApprovalHandler
{
    protected function getApproverId(): string { return 'system'; }
    
    protected function process(ApprovalContext $context): void
    {
        $approvers = $context->getSteps()->getApproverIds();
        
        $approval = new ApprovalRecord(
            documentId: $context->getDocument()->id,
            approvers: $approvers,
            approvedAt: new \DateTimeImmutable(),
            isApproved: !$context->isRejected(),
            rejectionReason: $context->getRejections()->isNotEmpty() 
                ? $context->getRejections()->first()->message 
                : null,
        );
        
        $context->setFinalApproval($approval);
    }
}
```

### 7.5. Orchestration de la chaîne

```php
<?php

declare(strict_types=1);

namespace App\ChainOfResponsibility;

use App\Contexts\ApprovalContext;
use App\Records\DocumentRecord;
use App\ChainOfResponsibility\Handlers\ValidationHandler;
use App\ChainOfResponsibility\Handlers\ManagerApprovalHandler;
use App\ChainOfResponsibility\Handlers\DirectorApprovalHandler;
use App\ChainOfResponsibility\Handlers\FinalApprovalHandler;

final class ApprovalChain
{
    private ValidationHandler $validationHandler;
    private ManagerApprovalHandler $managerHandler;
    private DirectorApprovalHandler $directorHandler;
    private FinalApprovalHandler $finalHandler;
    
    public function __construct(string $department)
    {
        // ✅ Construction de la chaîne de responsabilité
        $this->validationHandler = new ValidationHandler();
        $this->managerHandler = new ManagerApprovalHandler($department);
        $this->directorHandler = new DirectorApprovalHandler();
        $this->finalHandler = new FinalApprovalHandler();
        
        $this->validationHandler
            ->setNext($this->managerHandler)
            ->setNext($this->directorHandler)
            ->setNext($this->finalHandler);
    }
    
    public function execute(ApprovalContext $context): ApprovalContext
    {
        // ✅ Le Contexte traverse toute la chaîne
        $this->validationHandler->handle($context);
        
        return $context;
    }
}

// Utilisation
$document = new DocumentRecord(
    id: 'DOC-001',
    amount: 75000.0,
    department: 'sales',
    hasSupportingDocs: true,
);

$context = new ApprovalContext($document);

$chain = new ApprovalChain('sales');
$result = $chain->execute($context);

if ($result->isApproved()) {
    $approvers = $result->getSteps()->getApproverIds();
    echo "Document approved by: " . implode(', ', $approvers->toArray());
    echo "Total duration: " . $result->getTotalDuration() . " seconds";
} else {
    $rejections = $result->getRejections()->getMessages();
    echo "Document rejected: " . implode(', ', $rejections->toArray());
}
```

---

## 8. Avantages architecturaux

### 8.1. Avantages du Contexte

| Avantage | Explication |
|----------|-------------|
| **Services sans état** | Les Services restent purs, l'état est dans le Contexte |
| **Traçabilité** | Le Contexte garde l'historique de tout le traitement (via TypedCollection) |
| **Débogage facile** | On peut inspecter le Contexte à chaque étape |
| **Testabilité** | On peut mocker le Contexte ou vérifier son état final |
| **Réutilisable** | Le même Contexte peut passer par différentes chaînes |
| **Flexibilité** | On peut ajouter des propriétés sans changer les signatures des méthodes |
| **Type-safe** | Toutes les collections sont typées (TypedCollection) |
| **Injection possible** | Un Service peut recevoir un Contexte dans son constructeur |

### 8.2. Comparaison avec les alternatives

| Approche | Sans Contexte | Avec Contexte |
|----------|---------------|---------------|
| **Paramètres** | `method($a, $b, $c, $d, $e)` | `method(Context $ctx)` |
| **Retour de données** | `return [$result, $state1, $state2]` | Le Contexte est modifié |
| **Partage entre Services** | Passer toutes les données à chaque appel | Passer le même Contexte |
| **Traçabilité** | Difficile, il faut collecter manuellement | Le Contexte accumule l'information (via TypedCollection) |
| **Modification** | Changer la signature de toutes les méthodes | Ajouter une propriété dans le Contexte |
| **Type des données** | Tableaux bruts non typés | TypedCollection typée |

---

## 9. Récapitulatif des différences

| Type | Rôle | Mutabilité | Logique | Identité | Usage | Collections |
|------|------|------------|---------|----------|-------|--------------|
| **Record** | Transport de données immuables | ❌ Immutable | ❌ Aucune | Par valeurs | API, DTO, transfert | TypedCollection |
| **Value Object** | Concept métier immuable | ❌ Immutable | ✅ Logique métier | Par valeurs | Money, Email, Phone | TypedCollection |
| **Contexte** | Transport d'état mutable | ✅ Mutable | ❌ Aucune (sauf question) | Identité propre | Chaîne de responsabilité, workflows | TypedCollection |
| **Entity/Model** | Objet métier avec identité | ✅ Mutable | ✅ Logique métier | Identité unique | Doctrine, Eloquent | TypedCollection |

---

## 10. Règle d'or

> **Un Contexte est une classe qui ne fait que transporter et maintenir un ÉTAT. Il n'a pas de logique métier (sauf des méthodes de question simples). Il est conçu pour être passé entre Services qui le lisent et le modifient.**
>
> **⚠️ Un Contexte n'est PAS :**
> - ❌ Un Record (pas immuable)
> - ❌ Un Value Object (pas de logique métier)
> - ❌ Un Service (a de l'état)
> - ❌ Un DTO (pas juste pour transporter des données figées)
> - ❌ Un conteneur de tableaux bruts
>
> **✅ Un Contexte est :**
> - ✅ Une classe avec état (propriétés privées + getters/setters)
> - ✅ Mutable (les Services le modifient)
> - ✅ Transportable (passe entre Services)
> - ✅ Sans logique métier complexe
> - ✅ Avec des méthodes de question simples (`isValid()`, `hasError()`, `isComplete()`)
> - ✅ Utilisant des TypedCollection pour les collections (pas de tableaux bruts)
> - ✅ Injectable dans le constructeur d'un Service

```php
// ✅ Le Contexte parfait
final class PerfectContext
{
    // ✅ État : propriétés privées
    private string $currentStep = 'start';
    private StepResultRecordCollection $results;
    private ?ValidationErrorRecord $error = null;
    private int $counter = 0;
    
    public function __construct(private readonly string $id)
    {
        $this->results = new StepResultRecordCollection();
    }
    
    // ✅ Getters
    public function getId(): string { return $this->id; }
    public function getCurrentStep(): string { return $this->currentStep; }
    public function getResults(): StepResultRecordCollection { return $this->results; }
    public function getError(): ?ValidationErrorRecord { return $this->error; }
    public function getCounter(): int { return $this->counter; }
    
    // ✅ Setters (avec TypedCollection)
    public function setCurrentStep(string $step): void { $this->currentStep = $step; }
    public function addResult(StepResultRecord $result): void { $this->results->add($result); }
    
    public function setError(ValidationErrorRecord $error): void 
    { 
        $this->error = $error; 
        $this->currentStep = 'failed';
    }
    
    public function incrementCounter(): void { $this->counter++; }
    
    // ✅ Méthodes de question simples (pas de logique métier)
    public function hasError(): bool { return $this->error !== null; }
    public function isCompleted(): bool { return $this->currentStep === 'completed'; }
}

// ✅ Service qui reçoit le Contexte en paramètre
final class PerfectService
{
    public function execute(PerfectContext $context): void
    {
        if ($context->hasError()) {
            return;
        }
        
        try {
            $result = $this->doWork($context->getId());
            $context->addResult(new StepResultRecord('work', $result, 0.0));
            $context->incrementCounter();
            $context->setCurrentStep('completed');
        } catch (\Exception $e) {
            $context->setError(new ValidationErrorRecord('work', $e->getMessage()));
        }
    }
    
    private function doWork(string $id): mixed { return ['id' => $id]; }
}

// ✅ Service qui reçoit le Contexte dans le constructeur
final class PerfectServiceWithContext
{
    public function __construct(private PerfectContext $context) {}
    
    public function execute(): void
    {
        $service = new PerfectService();
        $service->execute($this->context);
    }
    
    public function getContext(): PerfectContext { return $this->context; }
}

// ✅ Orchestration
$context = new PerfectContext('job-123');
$service = new PerfectService();
$service->execute($context);

if (!$context->hasError()) {
    $results = $context->getResults();
    echo "Success! " . $results->count() . " results";
} else {
    echo "Error: " . $context->getError()->message;
}
```