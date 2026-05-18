# Principe d'usage des Workers (Version finale)

## 1. Définition

Un **Worker** est un composant qui orchestre une **opération métier complète** en coordonnant des **Tasks**, des **Services** et des **Repositories**. Il gère l'ordre d'exécution et peut orchestrer plusieurs effets de bord de natures différentes.

**⚠️ Un Worker ne doit JAMAIS contenir de transaction. Les transactions sont gérées par les Repositories ou les Services.**

```
Worker → Orchestration → Retourne void (pas de transaction)
```

```php
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // Pas de transaction ici
        $user = $this->userRepository->create($record);           // Repository (avec transaction si besoin)
        $this->createStripeCustomerTask->execute($record);       // Task (API)
        $this->sendWelcomeEmailTask->execute($record);           // Task (email)
        $this->logUserActionTask->execute($user->id, 'registered');  // Task (log)
    }
}
```

---

## 2. Problématique à laquelle les Workers répondent

| Problème | Sans Worker | Avec Worker |
|----------|-------------|-------------|
| **Orchestration complexe** | L'inscription utilisateur est éparpillée dans le Controller | Centralisée dans `RegisterUserWorker` |
| **Tasks de natures différentes** | Email + log + API + DB mélangés dans un Service | Le Worker orchestre tout |

### 2.1 Worker vs Service vs Task

| Composant | Rôle | Transaction | Orchestration | Actions de nature différente | Logique métier |
|-----------|------|-------------|---------------|------------------------------|----------------|
| **Worker** | Orchestration complète | ❌ Non | ✅ Oui | ✅ Oui (peut mélanger) | ❌ Non |
| **Service** | Logique métier | ✅ Oui (si nécessaire) | ❌ Non | ❌ Non (délègue au Worker) | ✅ Oui |
| **Task** | Action(s) de même nature | ❌ Non | ❌ Non | ❌ Non (nature unique) | ❌ Non |
| **Repository** | Accès base de données | ✅ Oui (atomicité) | ❌ Non | ❌ Non | ❌ Non |

### 2.2 Règle des 3 Tasks (⚠️ LOI IMMUABLE)

> **Dès qu'une méthode (Service, Controller, ou autre) appelle 3 Tasks ou plus, il est OBLIGATOIRE de créer un Worker pour orchestrer ces Tasks.**

```php
// ❌ MAUVAIS - Service avec 3 Tasks (violation)
final class UserService
{
    public function register(RegisterUserRecord $record): void
    {
        $this->sendEmailTask->execute($record);   // Task 1
        $this->logTask->execute($record);         // Task 2
        $this->clearCacheTask->execute($record);  // Task 3
    }
}

// ✅ BON - Worker créé
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        $this->sendEmailTask->execute($record);
        $this->logTask->execute($record);
        $this->clearCacheTask->execute($record);
    }
}
```

### 2.3 Règle : Un Worker orchestre des actions de natures différentes

```php
// ✅ BON - Worker orchestre des actions de natures différentes
final class CreateDoctorWorker
{
    public function execute(CreateDoctorProfileRecord $record): void
    {
        // Repository (création DB)
        $doctor = $this->doctorRepository->create($record);
        
        // Task (API externe)
        $this->createStripeCustomerTask->execute($doctor);
        
        // Task (email)
        $this->sendWelcomeEmailTask->execute($record);
        
        // Task (log)
        $this->logDoctorCreatedTask->execute($record);
    }
}
```

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Action}{Entity}Worker
```

| Worker | Opération |
|--------|-----------|
| `RegisterUserWorker` | Inscription complète d'un utilisateur |
| `CreateOrderWorker` | Création complète d'une commande |
| `CreateDoctorWorker` | Création complète d'un docteur |

### 3.2 Localisation

```
app/Workers/{Domain}/{Action}{Entity}Worker.php
```

```
app/Workers/
├── User/
│   ├── RegisterUserWorker.php
│   └── DeleteUserWorker.php
├── Doctor/
│   └── CreateDoctorWorker.php
└── Order/
    ├── CreateOrderWorker.php
    └── CancelOrderWorker.php
```

### 3.3 Méthode unique : `execute()`

> **Un Worker a UNE SEULE méthode publique : `execute()`, qui retourne `void`.**

```php
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // Orchestration
    }
}
```

---

## 4. Signature de la méthode `execute()`

### 4.1 Paramètre : UN SEUL Record

```php
// ✅ BON
public function execute(RegisterUserRecord $record): void
{
    $email = $record->email;
}

// ❌ MAUVAIS
public function execute(string $email, string $name): void
{
    // ...
}
```

### 4.2 Retour : `void`

```php
// ✅ BON
public function execute(RegisterUserRecord $record): void
{
    // Orchestration
}

// ❌ MAUVAIS
public function execute(RegisterUserRecord $record): User
{
    // ...
}
```

---

## 5. Ce qu'un Worker peut faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Orchestrer des Tasks (natures différentes)** | ✅ Oui | `$this->sendEmailTask->execute()` + `$this->logTask->execute()` |
| **Orchestrer des Services** | ✅ Oui | `$this->priceCalculator->calculate(...)` |
| **Orchestrer des Repositories** | ✅ Oui | `$this->userRepository->create(...)` |
| **Lancer des événements** | ✅ Oui | `event(new UserRegistered(...))` |

---

## 6. Ce qu'un Worker NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Contenir de la logique métier pure** | C'est le rôle des Services | Déplacer dans un Service |
| **Avoir des effets de bord directs** | C'est le rôle des Tasks | Déplacer dans une Task |
| **Retourner une valeur** | Violation du contrat `void` | Utiliser un Service |
| **Avoir plusieurs méthodes** | Violation SRP du Worker | Créer un autre Worker |
| **Contenir une transaction DB** | C'est le rôle des Repositories ou Services | Déplacer la transaction dans le Repository ou Service |
| **Appeler `DB::transaction()` directement** | Appel statique non testable + violation de couche | Injecter un `TransactionManagerInterface` dans le Repository/Service |

```php
// ❌ MAUVAIS - Worker avec logique métier et transaction
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // ❌ Logique métier (devrait être dans un Service)
        if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email');
        }
        
        // ❌ Transaction (devrait être dans le Repository)
        DB::transaction(function () use ($record) {
            $user = $this->userRepository->create($record);
            $this->createStripeCustomer->execute($record);
        });
        
        $this->sendWelcomeEmail->execute($record);
    }
}

// ✅ BON - Worker pur (sans logique métier, sans transaction)
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // Validation déléguée à un Service
        $this->userValidator->validate($record);
        
        // Transaction gérée par le Repository
        $user = $this->userRepository->createWithStripe($record);
        
        // Effets de bord
        $this->sendWelcomeEmail->execute($record);
        $this->logUserAction->execute($user->id, 'registered');
    }
}
```

---

## 7. Règle : Écrire du code testable unitairement (⚠️ RÈGLE ABSOLUE)

> **Un Worker DOIT être testable unitairement. Cela signifie que toutes ses dépendances doivent pouvoir être mockées et qu'il ne doit contenir aucun appel statique.**

### 7.1 Problème : Appels statiques et transactions non testables

```php
// ❌ MAUVAIS - Worker NON testable
final class BadWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // ❌ Appel statique (non mockable)
        Log::info('Registering user', ['email' => $record->email]);
        
        // ❌ Transaction (appel statique non mockable)
        DB::transaction(function () use ($record) {
            $user = User::create([...]);  // ❌ Model direct
        });
        
        // ❌ Facade (non mockable)
        Mail::to($record->email)->send(new WelcomeEmail());
    }
}
```

**Pourquoi ce code n'est pas testable unitairement :**
- `Log::info()` est un appel statique → impossible à mocker
- `DB::transaction()` est un appel statique → impossible à mocker
- `User::create()` est un appel statique → appelle VRAIMENT la base de données
- `Mail::to()` est une facade → appel statique caché

### 7.2 Solution : Worker testable

```php
// ✅ BON - Worker TESTABLE
final class GoodWorker
{
    public function __construct(
        private readonly UserValidatorService $validator,
        private readonly UserRepository $userRepository,
        private readonly SendWelcomeEmailTask $sendWelcomeEmail,
        private readonly LogUserActionTask $logUserAction,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(RegisterUserRecord $record): void
    {
        // Validation (Service mockable)
        $this->validator->validate($record);
        
        // Création (Repository mockable - transaction à l'intérieur)
        $user = $this->userRepository->createWithStripe($record);
        
        // Effets de bord (Tasks mockables)
        $this->sendWelcomeEmail->execute($record);
        $this->logUserAction->execute($user->id, 'registered');
        
        // Log (interface mockable)
        $this->logger->info('User registered', ['user_id' => $user->id]);
    }
}
```

### 7.3 Test unitaire d'un Worker

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Workers\User;

use App\Records\RegisterUserRecord;
use App\Workers\User\RegisterUserWorker;
use App\Services\UserValidatorService;
use App\Repositories\UserRepository;
use App\Tasks\Email\SendWelcomeEmailTask;
use App\Tasks\Log\LogUserActionTask;
use App\Contracts\LoggerInterface;
use Tests\TestCase;

final class RegisterUserWorkerTest extends TestCase
{
    private RegisterUserWorker $worker;
    private UserValidatorService $validator;
    private UserRepository $userRepository;
    private SendWelcomeEmailTask $sendWelcomeEmail;
    private LogUserActionTask $logUserAction;
    private LoggerInterface $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->validator = $this->createMock(UserValidatorService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->sendWelcomeEmail = $this->createMock(SendWelcomeEmailTask::class);
        $this->logUserAction = $this->createMock(LogUserActionTask::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->worker = new RegisterUserWorker(
            $this->validator,
            $this->userRepository,
            $this->sendWelcomeEmail,
            $this->logUserAction,
            $this->logger,
        );
    }
    
    public function test_execute_validates_and_orchestrates_all_tasks(): void
    {
        $record = new RegisterUserRecord(
            email: 'john@example.com',
            name: 'John Doe',
            password: 'SecurePass123!',
        );
        
        $createdUser = new UserRecord(id: 1, email: 'john@example.com', name: 'John Doe');
        
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->with($record);
        
        $this->userRepository
            ->expects($this->once())
            ->method('createWithStripe')
            ->with($record)
            ->willReturn($createdUser);
        
        $this->sendWelcomeEmail
            ->expects($this->once())
            ->method('execute')
            ->with($record);
        
        $this->logUserAction
            ->expects($this->once())
            ->method('execute')
            ->with(1, 'registered');
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('User registered', ['user_id' => 1]);
        
        $this->worker->execute($record);
    }
    
    public function test_execute_throws_exception_when_validation_fails(): void
    {
        $record = new RegisterUserRecord(
            email: 'invalid',
            name: 'John Doe',
            password: 'weak',
        );
        
        $this->validator
            ->expects($this->once())
            ->method('validate')
            ->willThrowException(new ValidationException('Invalid email'));
        
        $this->userRepository
            ->expects($this->never())
            ->method('createWithStripe');
        
        $this->expectException(ValidationException::class);
        
        $this->worker->execute($record);
    }
}
```

### 7.4 Récapitulatif des interdictions pour la testabilité

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| `Log::info()` direct | Appel statique non mockable | Interface `LoggerInterface` injectée |
| `DB::transaction()` direct | Appel statique non mockable + violation couche | Transaction dans Repository ou Service |
| `User::create()` direct | Appel statique non mockable | Repository injecté |
| `Mail::send()` direct | Facade statique non mockable | Interface `MailerInterface` injectée |
| `Cache::put()` direct | Facade statique non mockable | Interface `CacheInterface` injectée |
| `Http::get()` direct | Facade statique non mockable | Interface `HttpClientInterface` injectée |

### 7.5 Règle d'or pour la testabilité

> **ZÉRO appel statique dans un Worker. ZÉRO transaction. TOUTES les dépendances injectées. TOUS les effets de bord délégués à des Tasks ou Services.**

```php
// Le Worker parfaitement testable
final class PerfectWorker
{
    public function __construct(
        private readonly SomeValidatorService $validator,
        private readonly SomeRepository $repository,
        private readonly SomeTask $task1,
        private readonly SomeOtherTask $task2,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(SomeRecord $record): void
    {
        // Validation déléguée
        $this->validator->validate($record);
        
        // Création (transaction gérée par le Repository)
        $result = $this->repository->create($record);
        
        // Effets de bord
        $this->task1->execute($result);
        $this->task2->execute($record);
        
        // Log
        $this->logger->info('Done', ['id' => $result->id]);
    }
}
```

---

## 8. Règle : Les transactions sont dans les Repositories

> **⚠️ CRITIQUE : Un Worker ne doit JAMAIS contenir de transaction. La transaction doit être gérée par la couche qui accède à la base de données : le Repository.**

### 8.1 Problème : Transaction dans le Worker

```php
// ❌ MAUVAIS - Transaction dans le Worker
final class CreateOrderWorker
{
    public function execute(CreateOrderRecord $record): void
    {
        // ❌ Transaction ici - mauvaise responsabilité
        DB::transaction(function () use ($record) {
            $order = $this->orderRepository->create($record);
            $this->inventoryRepository->updateStock($record->items);
            $this->paymentRepository->create($order->id, $record->payment);
        });
        
        $this->sendConfirmationEmailTask->execute($record);
    }
}
```

**Pourquoi c'est un problème :**
- Violation de la séparation des responsabilités
- Le Worker connaît les détails de la transaction
- Non testable unitairement (`DB::transaction()` est statique)
- Le code est couplé à la couche DB

### 8.2 Solution : Transaction dans le Repository

```php
// ✅ BON - Transaction dans le Repository
final class OrderRepository
{
    public function __construct(
        private readonly TransactionManagerInterface $transactionManager,
        private readonly InventoryRepository $inventoryRepository,
        private readonly PaymentRepository $paymentRepository,
    ) {}
    
    public function createWithInventoryAndPayment(CreateOrderRecord $record): Order
    {
        return $this->transactionManager->transaction(function () use ($record) {
            $order = $this->create($record);
            $this->inventoryRepository->updateStock($record->items);
            $this->paymentRepository->create($order->id, $record->payment);
            return $order;
        });
    }
}

// ✅ BON - Worker sans transaction
final class CreateOrderWorker
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly SendConfirmationEmailTask $sendConfirmationEmailTask,
    ) {}
    
    public function execute(CreateOrderRecord $record): void
    {
        // Transaction gérée par le Repository
        $order = $this->orderRepository->createWithInventoryAndPayment($record);
        
        // Effets de bord hors transaction
        $this->sendConfirmationEmailTask->execute($order);
    }
}
```

### 8.3 Règle de placement des transactions

| Composant | Transaction autorisée ? | Exemple |
|-----------|------------------------|---------|
| **Repository** | ✅ Oui (atomicité des opérations DB) | `createWithInventory()` |
| **Service** | ✅ Oui (atomicité d'une logique métier complexe) | `createOrderWithPayment()` |
| **Worker** | ❌ Non (le Worker orchestre uniquement) | - |
| **Task** | ❌ Non (une Task = action de même nature) | - |

### 8.4 Récapitulatif des règles de transaction

| Règle | Explication |
|-------|-------------|
| **Worker** | ❌ ZÉRO transaction |
| **Service** | ✅ Transaction autorisée (atomicité métier) |
| **Repository** | ✅ Transaction autorisée (atomicité DB) |
| **Task** | ❌ Pas de transaction (une Task = une action de même nature) |
| **TransactionManager** | ✅ TOUJOURS injecté (jamais `DB::transaction()` direct) |

---

## 9. Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Workers\Doctor;

use App\Records\CreateDoctorProfileRecord;
use App\Repositories\DoctorRepository;
use App\Services\DoctorValidatorService;
use App\Tasks\Email\SendWelcomeEmailTask;
use App\Tasks\Log\LogDoctorCreatedTask;
use App\Contracts\LoggerInterface;

final class CreateDoctorWorker
{
    public function __construct(
        private readonly DoctorValidatorService $doctorValidator,
        private readonly DoctorRepository $doctorRepository,
        private readonly SendWelcomeEmailTask $sendWelcomeEmailTask,
        private readonly LogDoctorCreatedTask $logDoctorCreatedTask,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): void
    {
        // 1. Validation (Service)
        $this->doctorValidator->validate($record);
        
        // 2. Création (Repository - transaction à l'intérieur si nécessaire)
        $doctor = $this->doctorRepository->createWithProfile($record);
        
        // 3. Email (Task)
        $this->sendWelcomeEmailTask->execute(new SendWelcomeEmailRecord(
            email: $record->email,
            name: $record->name,
        ));
        
        // 4. Log (Task)
        $this->logDoctorCreatedTask->execute($doctor->id, 'doctor_created');
        
        // 5. Log (Logger injecté)
        $this->logger->info('Doctor created', ['doctor_id' => $doctor->id]);
    }
}
```

---

## 10. Tableau récapitulatif final

| Composant | Rôle | Transaction | Orchestration | Actions de nature différente | Logique métier | Accès Models | Testable unitairement |
|-----------|------|-------------|---------------|------------------------------|----------------|--------------|----------------------|
| **Service** | Logique métier | ✅ Oui (si nécessaire) | ❌ Non | ❌ Non (délègue) | ✅ Oui | ❌ Non (via Repositories) | ✅ Oui (dépendances injectées) |
| **Task** | Action(s) de même nature | ❌ Non | ❌ Non | ❌ Non (nature unique) | ❌ Non | ❌ Non (via Repositories) | ✅ Oui (dépendances injectées) |
| **Worker** | Orchestration complète | ❌ Non (interdit) | ✅ Oui | ✅ Oui (peut mélanger) | ❌ Non | ❌ Non (via Repositories) | ✅ Oui (dépendances injectées) |
| **Repository** | Accès base de données | ✅ Oui (atomicité) | ❌ Non | ❌ Non | ❌ Non | ✅ Oui | ✅ Oui (TransactionManager injecté) |

---

## 11. Flux complet

```
┌─────────────────────────────────────────────────────────────────┐
│                         CONTROLLER                              │
│                                                                 │
│   Data → Record → Worker                                        │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          WORKER                                 │
│              (orchestration - SANS TRANSACTION)                 │
│                                                                 │
│   Validation (Service)                                          │
│   Création (Repository) ← Transaction gérée à l'intérieur       │
│   Email (Task)                                                  │
│   Log (Task)                                                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          SERVICE                                │
│                    (logique métier pure)                        │
│                                                                 │
│   Calcule, valide, transforme                                   │
│   Transaction autorisée pour atomicité métier                   │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                           TASK                                  │
│                    (action(s) de même nature)                   │
│                                                                 │
│   Utilise des Repositories (pas d'accès direct aux Models)      │
│   Pas de transaction (une seule nature d'action)                │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        REPOSITORY                               │
│                    (accès base de données)                      │
│                                                                 │
│   Seul composant autorisé à accéder directement aux Models      │
│   Transaction autorisée pour atomicité des opérations DB        │
│   TransactionManager injecté (pas de DB::transaction() direct)  │
└─────────────────────────────────────────────────────────────────┘
```

---

## 12. Règle d'or

> **Un Worker orchestre, il ne fait rien d'autre. ZÉRO transaction. ZÉRO appel statique. ZÉRO logique métier. ZÉRO retour de valeur. TOUTES les dépendances injectées. Les transactions sont dans les Repositories ou les Services.**

```php
// Le Worker parfait
final class PerfectWorker
{
    public function __construct(
        private readonly SomeValidatorService $validator,
        private readonly SomeRepository $repository,
        private readonly SomeTask $task1,
        private readonly SomeOtherTask $task2,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(SomeRecord $record): void
    {
        // 1. Validation (Service)
        $this->validator->validate($record);
        
        // 2. Opération principale (Repository - transaction à l'intérieur)
        $result = $this->repository->createWithAtomicity($record);
        
        // 3. Effets de bord (Tasks)
        $this->task1->execute($result);
        $this->task2->execute($record);
        
        // 4. Log (Logger injecté)
        $this->logger->info('Worker executed', ['result_id' => $result->id]);
    }
}
```