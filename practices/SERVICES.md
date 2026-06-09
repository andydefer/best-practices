# Principe d'usage des Services (Version Finale - Complète)

## Table des matières

1. [Définition](#1-définition)
2. [Pourquoi les Services remplacent les Traits](#2-pourquoi-les-services-remplacent-les-traits)
3. [Caractéristiques fondamentales](#3-caractéristiques-fondamentales)
4. [Configuration externe vs constantes internes](#4-configuration-externe-vs-constantes-internes)
5. [État : Contextes vs Configuration : Configs](#5-état--contextes-vs-configuration--configs)
6. [Principes philosophiques avec exemples](#6-principes-philosophiques-avec-exemples)
7. [Ce qu'un Service NE peut PAS faire](#7-ce-quun-service-ne-peut-pas-faire)
8. [Le Service parfait](#8-le-service-parfait)
9. [Récapitulatif des contraintes](#9-récapitulatif-des-contraintes)
10. [Règle d'or](#10-règle-dor)

---

## 1. Définition

Un **Service** est un conteneur de méthodes qui partagent un même domaine métier. Il ne maintient **AUCUN état interne** et ne possède **AUCUNE propriété privée** (sauf des dépendances injectées).

```
Service → Conteneur de méthodes → Même domaine métier → Aucun état interne
```

---

## 2. Pourquoi les Services remplacent les Traits (⚠️ RÈGLE MAJEURE)

> **Les traits sont une mauvaise pratique. Les Services sont l'alternative propre par composition.**

### 2.1. Comparaison : Trait vs Service

| Aspect | Trait | Service |
|--------|-------|---------|
| **Testabilité** | ❌ Impossible à mocker, nécessite des fichiers réels | ✅ Facile à mocker, test isolé |
| **Dépendances** | ❌ Pas de constructeur, instanciation interne | ✅ Injection explicite dans le constructeur |
| **État interne** | ❌ Peut avoir des propriétés privées | ✅ Pas d'état (sauf injections) |
| **Couplage** | ❌ Couplage implicite à la classe utilisatrice | ✅ Découplage explicite |
| **Réutilisabilité** | ✅ Réutilisable (mais dangereux) | ✅ Réutilisable (et sûr) |
| **Configuration** | ❌ Constantes internes figées | ✅ Config injectée, modifiable |

---

## 3. Caractéristiques fondamentales

| Caractéristique | Règle |
|-----------------|-------|
| **État interne** | ❌ AUCUNE propriété privée (sauf dépendances injectées) |
| **Constructeur** | ✅ Uniquement pour injecter des dépendances |
| **Paramètres des méthodes** | ✅ Tout ce dont la méthode a besoin est passé en paramètre |
| **Stockage de données** | ❌ Ne stocke rien entre les appels |
| **Classe finale** | ❌ **NE PEUT PAS** être déclarée `final` (doit être mockable) |
| **Instanciation interne** | ❌ INTERDIT (tout doit être injecté) |
| **Constantes privées** | ❌ INTERDITES (utiliser une Config) |

---

## 4. Configuration externe vs constantes internes (⚠️ RÈGLE MAJEURE)

> **⚠️ Un Service ne doit JAMAIS contenir de constantes privées ou publiques pour la configuration. Toute valeur configurable doit être injectée via une Config.**

```php
// ❌ MAUVAIS - Service avec constantes internes figées
final class BadFileCreatorService
{
    private const DEFAULT_DIRECTORY_PERMISSION = 0755;  // ❌ Figé dans le code
    private const MAX_RETRY_ATTEMPTS = 3;               // ❌ Impossible à modifier

    public function createFile(string $path): void
    {
        mkdir(dirname($path), self::DEFAULT_DIRECTORY_PERMISSION, true);
        
        for ($i = 0; $i < self::MAX_RETRY_ATTEMPTS; $i++) {
            // Tentative d'écriture
        }
    }
}

// ✅ BON - Service avec Config injectée
final class GoodFileCreatorService
{
    public function __construct(
        private readonly FileCreatorConfig $config,  // ✅ Config injectée
    ) {}

    public function createFile(string $path): void
    {
        mkdir(dirname($path), $this->config->directoryPermission(), true);
        
        for ($i = 0; $i < $this->config->maxRetryAttempts(); $i++) {
            // Tentative d'écriture
        }
    }
}
```

---

## 5. État : Contextes vs Configuration : Configs

> **⚠️ DISTINCTION FONDAMENTALE :**
> - **Contexte** : Gère l'**état volatile** qui change au fil du traitement
> - **Config** : Gère la **configuration figée** qui ne change pas

```php
// ✅ Contexte : état qui change pendant le traitement
final class ProcessingContext
{
    private string $currentStep = 'start';
    private int $processedItems = 0;
    private array $errors = [];
    
    public function getCurrentStep(): string { return $this->currentStep; }
    public function setCurrentStep(string $step): void { $this->currentStep = $step; }
    public function incrementProcessedItems(): void { $this->processedItems++; }
    public function addError(string $error): void { $this->errors[] = $error; }
}

// ✅ Config : valeurs figées qui ne changent pas
final class BatchProcessingConfig extends AbstractConfig
{
    public function batchSize(): int { return (int) (getenv('BATCH_SIZE') ?: 100); }
    public function maxRetries(): int { return (int) (getenv('MAX_RETRIES') ?: 3); }
}

// ✅ Service : utilise Contexte (état) et Config (configuration figée)
final class BatchProcessorService
{
    public function __construct(private readonly BatchProcessingConfig $config) {}
    
    public function process(ProcessingContext $context): void
    {
        $context->setCurrentStep('processing');
        
        while ($context->getProcessedItems() < 1000 && !$context->hasErrors()) {
            for ($i = 0; $i < $this->config->batchSize(); $i++) {
                $context->incrementProcessedItems();
            }
        }
        
        $context->setCurrentStep('completed');
    }
}
```

---

## 6. Principes philosophiques avec exemples

### 6.1. Composition Over Inheritance

> **Préférer l'injection de dépendances à l'héritage. L'héritage reste possible quand il est pertinent.**

```php
// ✅ Composition (recommandé)
class DatabaseService
{
    public function __construct(
        private readonly ConnectionInterface $connection,  // Injection
        private readonly LoggerInterface $logger,         // Injection
    ) {}
}

// ✅ Composition avec injection de plusieurs dépendances
class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly NotificationServiceInterface $notifier,
        private readonly OrderValidatorService $validator,
    ) {}
}

// ⚠️ Héritage (acceptable dans certains cas)
class SpecificDatabaseService extends DatabaseService
{
    // Uniquement si la relation "est-un" est claire et stable
    // Exemple: MySQLDatabaseService extends DatabaseService
}
```

### 6.2. Dependency Inversion Principle (DIP)

> **Dépendre des interfaces plutôt que des classes concrètes. Les DTOs et Value Objects peuvent être concrets.**

```php
// ✅ Dépendance vers une interface (recommandé)
interface PaymentGatewayInterface
{
    public function charge(float $amount, string $currency): TransactionResultRecord;
}

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,  // Interface
    ) {}
}

// ✅ Plusieurs implémentations possibles
class StripeGateway implements PaymentGatewayInterface { ... }
class PayPalGateway implements PaymentGatewayInterface { ... }
class MercadopagoGateway implements PaymentGatewayInterface { ... }

// ✅ Value Object concret (acceptable)
final class EmailAddress
{
    public function __construct(private readonly string $value) {}
    public function getValue(): string { return $this->value; }
}
```

### 6.3. Capability-Based Design

> **Exposer des capacités spécifiques plutôt que des services fourre-tout. Un service peut avoir plusieurs méthodes cohésives.**

```php
// ❌ Service fourre-tout (anti-pattern)
class UtilsService
{
    public function sendEmail() { ... }
    public function calculateTax() { ... }
    public function formatDate() { ... }
    public function queryDatabase() { ... }
    // 50 méthodes sans cohésion
}

// ✅ Capacités spécifiques (recommandé)
class EmailSenderService { ... }
class TaxCalculatorService { ... }
class DateFormatterService { ... }
class DatabaseQueryExecutorService { ... }

// ✅ Service avec plusieurs méthodes cohésives (acceptable)
class OrderCalculatorService
{
    // Toutes les méthodes sont liées au même domaine : calcul de commande
    public function calculateSubtotal(Order $order): float { ... }
    public function calculateTax(float $subtotal, string $country): float { ... }
    public function calculateDiscount(Order $order, ?string $promoCode): float { ... }
    public function calculateTotal(Order $order): float { ... }
    public function calculateShippingFee(Order $order): float { ... }
}

// ✅ Service avec méthodes cohésives pour la validation
class OrderValidatorService
{
    public function validateCustomer(Customer $customer): ValidationResultRecord { ... }
    public function validateItems(OrderItemCollection $items): ValidationResultRecord { ... }
    public function validatePayment(PaymentRecord $payment): ValidationResultRecord { ... }
    public function validateOrder(Order $order): ValidationResultRecord { ... }
}
```

### 6.4. Single Responsibility Principle (SRP)

> **Une classe a une seule raison de changer. La "responsabilité" peut être interprétée différemment selon le contexte.**

```php
// ✅ Une seule responsabilité (recommandé)
class EmailValidatorService
{
    public function validate(string $email): bool { ... }
}

class EmailSenderService
{
    public function send(Email $email): void { ... }
}

class EmailFormatterService
{
    public function format(Email $email): string { ... }
}

// ✅ Responsabilité plus large mais cohérente (acceptable)
class EmailService
{
    // Toutes les méthodes concernent le même concept : Email
    public function validate(Email $email): bool { ... }
    public function send(Email $email): void { ... }
    public function format(Email $email): string { ... }
    public function parse(string $raw): EmailVO { ... }
}

// ❌ Responsabilité trop large (violation du SRP)
class SuperService
{
    public function sendEmail() { ... }
    public function calculateTax() { ... }
    public function generatePdf() { ... }
    public function queryDatabase() { ... }
    // Plusieurs raisons de changer
}
```

### 6.5. Interface Segregation Principle (ISP)

> **Préférer plusieurs petites interfaces à une grosse. Les services internes peuvent avoir des interfaces plus larges.**

```php
// ✅ Interfaces ségréguées (recommandé pour les contrats publics)
interface PaymentProcessorInterface
{
    public function processPayment(PaymentData $payment): TransactionResultRecord;
}

interface RefundProcessorInterface
{
    public function processRefund(string $transactionId): RefundResultRecord;
}

interface FraudCheckerInterface
{
    public function check(PaymentRecord $payment): FraudResultRecord;
}

// ✅ Service qui implémente plusieurs interfaces
class StripeService implements PaymentProcessor, RefundProcessor, FraudChecker
{
    public function processPayment(PaymentRecord $payment): TransactionResultRecord { ... }
    public function processRefund(string $transactionId): RefundResultRecord { ... }
    public function check(PaymentRecord $payment): FraudResultRecord { ... }
}

// ✅ Interface plus large pour un service interne (acceptable)
interface InternalOrderRepositoryInterface
{
    // CRUD complet, mais cohérent pour un repository interne
    public function create(OrderData $data): Order;
    public function update(string $id, OrderData $data): Order;
    public function delete(string $id): void;
    public function find(string $id): ?Order;
    public function findAll(): OrderRecordCollection;
    public function findByCustomer(string $customerId): OrderRecordCollection;
}

// ❌ Interface fourre-tout (anti-pattern)
interface BigInterface
{
    public function doA(): void;
    public function doB(): void;
    public function doC(): void;
    public function doD(): void;
    public function doE(): void;
    // 50 méthodes sans cohésion
}
```

---

## 7. Ce qu'un Service NE peut PAS faire (⚠️ RÈGLES STRICTES)

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Avoir des propriétés privées** (sauf injections) | État interne interdit | Utiliser un Contexte passé en paramètre |
| **Stocker des données volatiles** | Violation du principe sans état | Utiliser un Contexte |
| **Stocker des paramètres de configuration** | Couplage à des valeurs figées | Injecter une Config |
| **Avoir des constantes privées/publiques** | Valeurs figées dans le code | Injecter une Config |
| **Être `final`** | Empêche le mocking et l'extension | Laisser la classe extensible |
| **Instancier ses dépendances en interne** | Crée un couplage fort | Injecter les dépendances |
| **Contenir des méthodes statiques** | Couplage fort, difficile à tester | Utiliser l'injection |
| **Appeler des singletons globaux** | Couplage caché, violation du DIP | Injecter les dépendances |

---

## 8. Le Service parfait

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Configs\OrderConfig;
use App\Contexts\ProcessingContext;
use App\Records\OrderRecord;
use App\Data\OrderTotalData;

/**
 * Service de calcul des commandes.
 * 
 * ✅ Pas d'état interne
 * ✅ Dépendances injectées dans le constructeur
 * ✅ Toutes les données arrivent en paramètres
 * ✅ Pas de `final`
 * ✅ Testable et mockable
 * ✅ Config injectée pour la configuration figée
 * ✅ Contexte passé en paramètre pour l'état mutable
 */
class OrderCalculatorService
{
    public function __construct(
        private readonly TaxService $taxService,
        private readonly DiscountService $discountService,
        private readonly OrderConfig $config,
    ) {}

    public function calculateTotal(OrderRecord $order, ProcessingContext $context): OrderTotalRecord
    {
        $context->setCurrentStep('calculating');
        
        $subtotal = $this->calculateSubtotal($order);
        $tax = $this->taxService->calculate($subtotal, $order->country);
        $discount = $this->discountService->apply($subtotal, $order->promoCode);
        
        $shippingFee = $order->total > $this->config->freeShippingThreshold() 
            ? 0.0 
            : $this->config->standardShippingFee();
        
        $total = $subtotal + $tax - $discount + $shippingFee;
        
        $context->addResult('subtotal', $subtotal);
        $context->addResult('tax', $tax);
        $context->incrementProcessedItems();
        $context->setCurrentStep('completed');
        
        return new OrderTotalRecord(
            subtotal: $subtotal,
            tax: $tax,
            discount: $discount,
            shippingFee: $shippingFee,
            total: round($total, 2)
        );
    }
    
    private function calculateSubtotal(OrderRecord $order): float
    {
        return array_reduce($order->items, fn($c, $i) => $c + ($i->price * $i->quantity), 0);
    }
}
```

---

## 9. Récapitulatif des contraintes

| Contrainte | Règle | Exception |
|------------|-------|-----------|
| **Propriétés privées** | ❌ AUCUNE (sauf injections) | Aucune |
| **État interne** | ❌ INTERDIT | Utiliser un Contexte |
| **Configuration figée** | ❌ INTERDITE | Utiliser une Config |
| **Stockage de Configs** | ✅ AUTORISÉ (immuables) | - |
| **Stockage de Services** | ✅ AUTORISÉ (dépendances) | - |
| **Stockage de données volatiles** | ❌ INTERDIT | Utiliser un Contexte |
| **Constructeur** | ✅ Uniquement pour l'injection | Aucune |
| **Classe `final`** | ❌ INTERDIT | Aucune |
| **Instanciation interne** | ❌ INTERDIT | Aucune |
| **Constantes privées** | ❌ INTERDITES | Utiliser une Config |
| **Méthodes statiques** | ❌ INTERDITES | Aucune |

---

## 10. Règle d'or

> **Un Service est un conteneur pur de méthodes. Il n'a pas de mémoire, pas d'état interne, pas de cache, pas de constantes internes.**
>
> **⚠️ DISTINCTION FONDAMENTALE :**
> - **L'état qui change** → **Contexte** (passé en paramètre)
> - **La configuration figée** → **Config** (injectée dans le constructeur)
>
> **⚠️ Les Services remplacent les traits : composition explicite, testabilité parfaite.**
>
> **La composition est la clé : un service peut utiliser d'autres services, tous injectés dans le constructeur. Jamais d'instanciation interne, jamais de stockage d'état, jamais de constantes figées.**

```php
// ✅ Le Service parfait
class PerfectService
{
    public function __construct(
        private readonly AnotherService $service,
        private readonly AppConfig $config,
        private readonly UserRepository $repo,
    ) {}

    public function execute(OrderRecord $order, ProcessingContext $context): ResultRecord
    {
        $context->setCurrentStep('processing');
        
        $subtotal = $this->calculateSubtotal($order);
        $tax = $this->service->calculateTax($subtotal, $user->country, $this->config->vatRate($user->country));
        
        $context->addResult('tax', $tax);
        $context->incrementProcessedItems();
        $context->setCurrentStep('completed');
        
        return new ResultRecord($subtotal + $tax);
    }
    
    private function calculateSubtotal(OrderRecord $order): float
    {
        return array_reduce($order->items, fn($c, $i) => $c + ($i->price * $i->quantity), 0);
    }
}

final class AppConfig extends AbstractConfig
{
    public function vatRate(string $country): float
    {
        return match($country) {
            'FR' => (float) (getenv('VAT_RATE_FR') ?: 0.20),
            'DE' => (float) (getenv('VAT_RATE_DE') ?: 0.19),
            default => (float) (getenv('VAT_RATE_DEFAULT') ?: 0.20),
        };
    }
}

final class ProcessingContext
{
    private string $currentStep = 'start';
    private int $processedItems = 0;
    private array $results = [];
    
    public function setCurrentStep(string $step): void { $this->currentStep = $step; }
    public function incrementProcessedItems(): void { $this->processedItems++; }
    public function addResult(string $key, mixed $value): void { $this->results[$key] = $value; }
    public function getCurrentStep(): string { return $this->currentStep; }
    public function getProcessedItems(): int { return $this->processedItems; }
    public function getResult(string $key): mixed { return $this->results[$key] ?? null; }
}
```
---