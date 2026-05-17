
# workers.md

# Principe d'usage des Workers (Version finale)

## 1. Définition

Un **Worker** est un composant qui orchestre une **opération métier complète** en coordonnant des **Tasks**, des **Services** et des **Repositories**. Il gère la transaction, l'ordre d'exécution et peut orchestrer plusieurs effets de bord de natures différentes.

```
Worker → Orchestration → Transaction → Retourne void
```

```php
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $user = $this->userRepository->create($record);           // Repository
            $this->createStripeCustomerTask->execute($record);       // Task (API)
        });
        
        $this->sendWelcomeEmailTask->execute($record);               // Task (email)
        $this->logUserActionTask->execute($user->id, 'registered');  // Task (log)
    }
}
```

---

## 2. Problématique à laquelle les Workers répondent

| Problème | Sans Worker | Avec Worker |
|----------|-------------|-------------|
| **Orchestration complexe** | L'inscription utilisateur est éparpillée dans le Controller | Centralisée dans `RegisterUserWorker` |
| **Transaction non maîtrisée** | Les transactions sont incohérentes | Le Worker gère la transaction |
| **Tasks de natures différentes** | Email + log + API + DB mélangés dans un Service | Le Worker orchestre tout |

### 2.1 Worker vs Service vs Task

| Composant | Rôle | Transaction | Orchestration | Actions de nature différente | Logique métier |
|-----------|------|-------------|---------------|------------------------------|----------------|
| **Worker** | Orchestration complète | ✅ Oui | ✅ Oui | ✅ Oui (peut mélanger) | ❌ Non |
| **Service** | Logique métier | ❌ Non | ❌ Non | ❌ Non (délègue au Worker) | ✅ Oui |
| **Task** | Action(s) de même nature | ❌ Non | ❌ Non | ❌ Non (nature unique) | ❌ Non |

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
        DB::transaction(function () use ($record) {
            // Task : créations DB (nature : écriture DB via Repositories)
            $this->createDoctorProfileTask->execute($record);
        });
        
        // Task : email (nature : envoi email)
        $this->sendWelcomeEmailTask->execute($record);
        
        // Task : log (nature : écriture log)
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
| `CreateDoctorWorker` | Création complète d'un docteur (DB + email + log) |

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
| **Faire des transactions DB** | ✅ Oui | `DB::transaction(...)` |
| **Lancer des événements** | ✅ Oui | `event(new UserRegistered(...))` |

---

## 6. Ce qu'un Worker NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Contenir de la logique métier pure** | C'est le rôle des Services | Déplacer dans un Service |
| **Avoir des effets de bord directs** | C'est le rôle des Tasks | Déplacer dans une Task |
| **Retourner une valeur** | Violation du contrat `void` | Utiliser un Service |
| **Avoir plusieurs méthodes** | Violation SRP du Worker | Créer un autre Worker |

```php
// ❌ MAUVAIS - Worker avec logique métier
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // ❌ Logique métier (devrait être dans un Service)
        if (!filter_var($record->email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email');
        }
        
        // ✅ Orchestration
        $user = $this->userRepository->create($record);
    }
}

// ✅ BON - Worker pur
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        // Validation déléguée à un Service
        $this->userValidator->validate($record);
        
        DB::transaction(function () use ($record) {
            $user = $this->userRepository->create($record);
            $this->createStripeCustomer->execute($record);
        });
        
        $this->sendWelcomeEmail->execute($record);
        $this->logUserAction->execute($user->id, 'registered');
    }
}
```

---

## 7. Transaction et atomicité

```php
final class CreateOrderWorker
{
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $order = $this->orderRepository->create($record);
            $this->inventoryTask->execute($record->items);
            $this->paymentTask->execute($record->payment, $order->total);
        });
        
        // Email hors transaction
        $this->sendConfirmationEmailTask->execute($record->email, $order->id);
    }
}
```

---

## 8. Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Workers\Doctor;

use App\Records\CreateDoctorProfileRecord;
use App\Repositories\DoctorRepository;
use App\Services\DoctorValidatorService;
use App\Tasks\Doctor\CreateDoctorProfileTask;
use App\Tasks\Email\SendWelcomeEmailTask;
use App\Tasks\Log\LogDoctorCreatedTask;

final class CreateDoctorWorker
{
    public function __construct(
        private readonly DoctorValidatorService $doctorValidator,
        private readonly DoctorRepository $doctorRepository,
        private readonly CreateDoctorProfileTask $createDoctorProfileTask,
        private readonly SendWelcomeEmailTask $sendWelcomeEmailTask,
        private readonly LogDoctorCreatedTask $logDoctorCreatedTask,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): void
    {
        // 1. Validation (Service)
        $this->doctorValidator->validate($record);
        
        $doctor = null;
        
        // 2. Transaction avec Task (créations DB via Repositories)
        DB::transaction(function () use ($record, &$doctor) {
            $doctor = $this->createDoctorProfileTask->execute($record);
        });
        
        // 3. Email (Task)
        $this->sendWelcomeEmailTask->execute(new SendWelcomeEmailRecord(
            email: $record->email,
            name: $record->name,
        ));
        
        // 4. Log (Task)
        $this->logDoctorCreatedTask->execute($doctor->id, 'doctor_created');
    }
}
```

---

## 9. Tableau récapitulatif final

| Composant | Rôle | Transaction | Orchestration | Actions de nature différente | Logique métier | Accès Models | Peut utiliser |
|-----------|------|-------------|---------------|------------------------------|----------------|--------------|---------------|
| **Service** | Logique métier | ❌ Non | ❌ Non | ❌ Non (délègue) | ✅ Oui | ❌ Non (via Repositories) | Services, Repositories, Tasks, Workers |
| **Task** | Action(s) de même nature | ❌ Non | ❌ Non | ❌ Non (nature unique) | ❌ Non | ❌ Non (via Repositories) | Services, Repositories |
| **Worker** | Orchestration complète | ✅ Oui | ✅ Oui | ✅ Oui (peut mélanger) | ❌ Non | ❌ Non (via Repositories) | Tasks, Services, Repositories |
| **Repository** | Accès base de données | ✅ Oui (écriture) | ❌ Non | ❌ Non | ❌ Non | ✅ Oui | - |

---

## 10. Flux complet

```
┌─────────────────────────────────────────────────────────────────┐
│                         CONTROLLER                              │
│                                                                 │
│   Data → Record → Worker (ou Service)                           │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          WORKER                                 │
│                    (orchestration métier)                       │
│                                                                 │
│   Transaction DB                                                │
│   ├── Task (créations DB via Repositories)                      │
│   └── Task (appel API)                                          │
│                                                                 │
│   Hors transaction                                              │
│   ├── Task (email)                                              │
│   └── Task (log)                                                │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          SERVICE                                │
│                    (logique métier pure)                        │
│                                                                 │
│   Calcule, valide, transforme                                   │
│   Peut appeler : Services, Repositories, Tasks, Workers         │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                           TASK                                  │
│                    (action(s) de même nature)                   │
│                                                                 │
│   Utilise des Repositories (pas d'accès direct aux Models)      │
│   Créations DB / Logs / Emails / Appels HTTP / Cache            │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        REPOSITORY                               │
│                    (accès base de données)                      │
│                                                                 │
│   Seul composant autorisé à accéder directement aux Models      │
└─────────────────────────────────────────────────────────────────┘
