Voici la dernière version de `tasks.md` que nous avons écrite ensemble :

---

# Principe d'usage des Tasks (Version finale)

## 1. Définition

Une **Task** est un composant qui encapsule une **action de même nature**. Elle peut faire plusieurs choses tant que ces choses partagent un point commun de nature (ex: plusieurs créations en DB, plusieurs logs, plusieurs appels HTTP).

**⚠️ Une Task ne doit JAMAIS accéder directement aux Models Eloquent. Elle DOIT utiliser des Repositories.**

**⚠️ Une Task ne doit JAMAIS utiliser d'appels statiques (Log, Mail, Http, Cache, etc.). Elle DOIT injecter ses dépendances.**

```
Task → Action(s) de même nature → Réutilisable → Retourne mixed (ou void)
```

```php
// ✅ BON - Task avec plusieurs créations en DB via Repositories (même nature)
final class CreateDoctorProfileTask
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly ConsultationFeeRepository $feeRepository,
        private readonly DoctorProfileRepository $profileRepository,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        $doctor = $this->doctorRepository->create($record);
        $this->feeRepository->create($doctor->id, $record);
        $this->profileRepository->create($doctor->id, $record);
        
        return $doctor;
    }
}
```

---

## 2. Problématique à laquelle les Tasks répondent

| Problème | Sans Task | Avec Task |
|----------|-----------|-----------|
| **Actions de même nature dupliquées** | Création de Doctor + ConsultationFee + Profile copiée partout | `CreateDoctorProfileTask` réutilisable |
| **Test difficile** | Un Service avec multiples actions est complexe | La Task se teste isolément |
| **Accès direct aux Models** | Le code est couplé à Eloquent | La Task utilise des Repositories |
| **Appels statiques non testables** | `Log::info()`, `Mail::send()` impossibles à mocker | Dépendances injectées, faciles à mocker |

### 2.1 Règle : Une Task peut faire plusieurs choses de même nature

```php
// ✅ BON - Task avec plusieurs créations via Repositories (même nature)
final class CreateDoctorProfileTask
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly ConsultationFeeRepository $feeRepository,
        private readonly DoctorProfileRepository $profileRepository,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        // 3 actions de MÊME NATURE : toutes sont des créations via Repositories
        $doctor = $this->doctorRepository->create(new DoctorCreateRecord(
            name: $record->name,
            specialty: $record->specialty,
            email: $record->email,
        ));
        
        $this->feeRepository->create(new ConsultationFeeCreateRecord(
            doctorId: $doctor->id,
            durationMinutes: $record->durationMinutes,
            fee: $record->fee,
        ));
        
        $this->profileRepository->create(new DoctorProfileCreateRecord(
            doctorId: $doctor->id,
            bio: $record->bio,
            address: $record->address,
            phone: $record->phone,
        ));
        
        return $doctor;
    }
}
```

### 2.2 Ce qu'une Task NE peut PAS faire (mélange de natures)

```php
// ❌ MAUVAIS - Mélange de natures différentes
final class CreateDoctorAndNotifyTask
{
    public function execute(CreateDoctorProfileRecord $record): void
    {
        // Nature 1 : création DB (via Repository)
        $doctor = $this->doctorRepository->create($record);
        
        // ❌ Nature différente : envoi d'email
        $this->sendEmailTask->execute($record);
        
        // ❌ Nature différente : appel API
        $this->callApiTask->execute($record);
    }
}

// ✅ BON - Séparer : Task (créations) + Worker (orchestration)
final class CreateDoctorProfileTask
{
    // Uniquement les créations DB via Repositories
}

final class CreateDoctorWorker
{
    public function execute(CreateDoctorProfileRecord $record): void
    {
        $this->createDoctorProfileTask->execute($record);
        $this->sendWelcomeEmailTask->execute($record);
        $this->logDoctorCreatedTask->execute($record);
    }
}
```

### 2.3 Ce qu'une Task NE peut PAS être

> **Une Task ne peut pas être un simple wrapper d'une action de Repository. Mais elle peut utiliser des Repositories pour des opérations de même nature.**

```php
// ❌ MAUVAIS - Task simple wrapper de Repository (inutile)
final class CreateUserTask
{
    public function execute(CreateUserRecord $record): User
    {
        return $this->userRepository->create($record);  // Simple wrapper
    }
}

// ✅ BON - Utiliser le Repository directement dans le Worker
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        $user = $this->userRepository->create($record);  // Direct
    }
}

// ✅ BON - Task utile car elle fait plusieurs créations de même nature
final class CreateDoctorProfileTask
{
    // 3 créations, pas un simple wrapper
}
```

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Action}{Entity}Task
```

| Task | Nature des actions |
|------|---------------------|
| `CreateDoctorProfileTask` | Créations en DB via Repositories (3 créations) |
| `LogOrderTask` | Écritures de logs via LoggerInterface injecté |
| `SendEmailTask` | Envoi d'email via MailerInterface injecté |
| `CreateStripeCustomerTask` | Appel API Stripe via HttpClient injecté |
| `FetchExchangeRateTask` | Appel API externe via HttpClient injecté |

### 3.2 Localisation

```
app/Tasks/{Domain}/{Action}{Entity}Task.php
```

```
app/Tasks/
├── Doctor/
│   └── CreateDoctorProfileTask.php
├── Log/
│   └── LogOrderTask.php
├── Email/
│   └── SendWelcomeEmailTask.php
├── Http/
│   ├── FetchExchangeRateTask.php
│   └── CreateStripeCustomerTask.php
└── Cache/
    └── ClearUserCacheTask.php
```

### 3.3 Une Task = actions de même nature

> **Une Task peut faire plusieurs choses, mais TOUTES doivent être de la même nature.**

| Nature | Exemples d'actions (avec interfaces injectées) |
|--------|---------------------|
| **Créations DB** | `$doctorRepository->create()`, `$feeRepository->create()`, `$profileRepository->create()` |
| **Logs** | `$logger->info()`, `$logger->error()`, `$logger->warning()` |
| **Emails** | `$mailer->send()`, `$mailer->queue()` |
| **Appels HTTP** | `$httpClient->get()`, `$httpClient->post()`, `$httpClient->put()` |
| **Cache** | `$cache->put()`, `$cache->forget()`, `$cache->remember()` |

### 3.4 Accès aux données

> **Une Task ne doit JAMAIS accéder directement aux Models Eloquent. Elle DOIT utiliser des Repositories.**

```php
// ❌ MAUVAIS - Accès direct au Model
final class CreateDoctorProfileTask
{
    public function execute(CreateDoctorProfileRecord $record): void
    {
        $doctor = Doctor::create([...]);  // ❌ Direct
        ConsultationFee::create([...]);   // ❌ Direct
    }
}

// ✅ BON - Utilisation des Repositories
final class CreateDoctorProfileTask
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly ConsultationFeeRepository $feeRepository,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): void
    {
        $doctor = $this->doctorRepository->create($record);  // ✅
        $this->feeRepository->create($doctor->id, $record);  // ✅
    }
}
```

### 3.5 Méthode unique : `execute()`

> **Une Task a UNE SEULE méthode publique : `execute()`.**

```php
// ✅ BON
final class SendWelcomeEmailTask
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}
    
    public function execute(SendWelcomeEmailRecord $record): void
    {
        $this->mailer
            ->to($record->email)
            ->send(new WelcomeEmail($record->name));
    }
}
```

### 3.6 Règle : Écrire du code testable unitairement (⚠️ RÈGLE ABSOLUE)

> **Une Task DOIT être testable unitairement. Cela signifie :**
> - **AUCUN appel statique** (Log, Mail, Http, Cache, Facades Laravel)
> - **TOUTES les dépendances doivent être injectées via le constructeur**
> - **TOUTES les dépendances doivent pouvoir être mockées**

#### 3.6.1 Problème : Appels statiques non testables

```php
// ❌ MAUVAIS - Appels statiques (NON TESTABLE)
final class LogOrderTask
{
    public function execute(OrderRecord $record): void
    {
        // ❌ Appel statique à Log - Impossible à mocker
        Log::info('Order created', ['order_id' => $record->id]);
        Log::info('Order items', ['items' => $record->items]);
    }
}

// ❌ MAUVAIS - Facade Laravel (NON TESTABLE)
final class SendEmailTask
{
    public function execute(SendEmailRecord $record): void
    {
        // ❌ Facade Mail - Impossible à mocker proprement
        Mail::to($record->email)->send(new WelcomeEmail($record->name));
    }
}
```

**Pourquoi les appels statiques sont un problème :**
- Les appels statiques sont des **singletons cachés**
- Impossible de les remplacer par des mocks
- Le test appelle VRAIMENT l'API externe
- Test lent, fragile, coûteux
- Dépendance cachée non visible dans le constructeur

#### 3.6.2 Solution : Interfaces + Injection de dépendances

```php
// ✅ BON - Interface pour le Logger
interface LoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
}

// ✅ BON - Implémentation réelle (wrapper de Log)
final class LaravelLogger implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }
}

// ✅ BON - Task avec dépendance injectée (TESTABLE)
final class LogOrderTask
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(OrderRecord $record): void
    {
        $this->logger->info('Order created', [
            'order_id' => $record->id,
            'user_id' => $record->userId,
            'total' => $record->total,
        ]);
        
        $this->logger->info('Order items', [
            'order_id' => $record->id,
            'items' => $record->items,
        ]);
    }
}
```

#### 3.6.3 Test unitaire de la Task

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Tasks\Log;

use App\Records\OrderRecord;
use App\Tasks\Log\LogOrderTask;
use App\Contracts\LoggerInterface;
use Tests\TestCase;

final class LogOrderTaskTest extends TestCase
{
    private LogOrderTask $task;
    private LoggerInterface $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->task = new LogOrderTask($this->logger);
    }
    
    public function test_execute_logs_order_creation_with_all_details(): void
    {
        $record = new OrderRecord(
            id: 123,
            userId: 456,
            total: 299.99,
            items: [
                ['product' => 'Laptop', 'price' => 999.99],
                ['product' => 'Mouse', 'price' => 29.99],
            ],
            status: 'pending',
        );
        
        $this->logger
            ->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Order created', [
                    'order_id' => 123,
                    'user_id' => 456,
                    'total' => 299.99,
                ]],
                ['Order items', [
                    'order_id' => 123,
                    'items' => $record->items,
                ]]
            );
        
        $this->task->execute($record);
    }
}
```

#### 3.6.4 Enregistrement des bindings dans le ServiceProvider

```php
// AppServiceProvider.php
use App\Contracts\LoggerInterface;
use App\Contracts\MailerInterface;
use App\Contracts\HttpClientInterface;
use App\Services\LaravelLogger;
use App\Services\LaravelMailer;
use App\Services\LaravelHttpClient;

public function register(): void
{
    $this->app->bind(LoggerInterface::class, LaravelLogger::class);
    $this->app->bind(MailerInterface::class, LaravelMailer::class);
    $this->app->bind(HttpClientInterface::class, LaravelHttpClient::class);
}
```

#### 3.6.5 Récapitulatif des interdictions

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| `Log::info()` direct | Appel statique non mockable | Interface `LoggerInterface` injectée |
| `Mail::send()` direct | Facade statique non mockable | Interface `MailerInterface` injectée |
| `Http::get()` direct | Facade statique non mockable | Interface `HttpClientInterface` injectée |
| `Cache::put()` direct | Facade statique non mockable | Interface `CacheInterface` injectée |
| `DB::table()` direct | Facade statique non mockable | Repository avec interface |
| `Customer::create()` direct | Appel statique non mockable | Interface `StripeClientInterface` injectée |

#### 3.6.6 Règle d'or

> **ZÉRO appel statique dans une Task. TOUTES les dépendances doivent être injectées via le constructeur. Si vous voyez `Log::`, `Mail::`, `Http::`, `Cache::`, `DB::` ou toute Facade Laravel dans une Task, c'est une erreur.**

```php
// ✅ Ce qui est testable
final class GoodTask
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}
}

// ❌ Ce qui ne l'est PAS
final class BadTask
{
    public function execute(): void
    {
        Log::info('message');     // ❌
        Mail::send($email);       // ❌
        Http::get($url);          // ❌
        Cache::put($key, $value); // ❌
        DB::table('users')->get(); // ❌
    }
}
```

---

## 4. Types autorisés en entrée/sortie

| Type | Entrée | Sortie |
|------|--------|--------|
| `Record` | ✅ Oui | ✅ Oui |
| `scalaire` | ✅ Oui | ✅ Oui |
| `Enum` | ✅ Oui | ✅ Oui |
| `Model` | ❌ Non | ✅ Oui (si retour d'une création via Repository) |

---

## 5. Exemples complets

### 5.1 Task avec plusieurs créations via Repositories (même nature)

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Doctor;

use App\Records\CreateDoctorProfileRecord;
use App\Repositories\DoctorRepository;
use App\Repositories\ConsultationFeeRepository;
use App\Repositories\DoctorProfileRepository;
use App\Models\Doctor;

final class CreateDoctorProfileTask
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly ConsultationFeeRepository $feeRepository,
        private readonly DoctorProfileRepository $profileRepository,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        $doctor = $this->doctorRepository->create(new DoctorCreateRecord(
            name: $record->name,
            specialty: $record->specialty,
            email: $record->email,
        ));
        
        $this->feeRepository->create(new ConsultationFeeCreateRecord(
            doctorId: $doctor->id,
            durationMinutes: $record->durationMinutes,
            fee: $record->fee,
        ));
        
        $this->profileRepository->create(new DoctorProfileCreateRecord(
            doctorId: $doctor->id,
            bio: $record->bio,
            address: $record->address,
            phone: $record->phone,
        ));
        
        return $doctor;
    }
}
```

### 5.2 Task avec plusieurs logs (même nature) - Version corrigée

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Log;

use App\Records\OrderRecord;
use App\Contracts\LoggerInterface;

final class LogOrderTask
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(OrderRecord $record): void
    {
        $this->logger->info('Order created', [
            'order_id' => $record->id,
            'user_id' => $record->userId,
            'total' => $record->total,
        ]);
        
        $this->logger->info('Order items', [
            'order_id' => $record->id,
            'items' => $record->items,
        ]);
        
        $this->logger->info('Order status changed', [
            'order_id' => $record->id,
            'status' => $record->status,
        ]);
    }
}
```

### 5.3 Task pour appel HTTP externe - Version corrigée

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Http;

use App\Records\CurrencyPairRecord;
use App\Contracts\HttpClientInterface;

final class FetchExchangeRateTask
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}
    
    public function execute(CurrencyPairRecord $record): float
    {
        $response = $this->httpClient->get('https://api.exchangerate.com/v1/rate', [
            'from' => $record->from,
            'to' => $record->to,
        ]);
        
        if ($response->failed()) {
            throw new ExchangeRateException('Failed to fetch exchange rate');
        }
        
        return (float) $response->json('rate');
    }
}
```

### 5.4 Task pour email - Version corrigée

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Email;

use App\Records\SendWelcomeEmailRecord;
use App\Contracts\MailerInterface;
use App\Mail\WelcomeEmail;

final class SendWelcomeEmailTask
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}
    
    public function execute(SendWelcomeEmailRecord $record): void
    {
        $this->mailer
            ->to($record->email)
            ->send(new WelcomeEmail($record->name));
    }
}
```

> **⚠️ Une Task ne peut pas appeler une autre Task. Si une Task a besoin d'exécuter plusieurs actions de même nature, elle les fait elle-même. Si les actions sont de natures différentes, c'est le rôle d'un Worker qui se charge d'orchester les tasks.**

```php
// ❌ MAUVAIS - Task qui appelle une autre Task
final class CreateDoctorProfileTask extends AbstractTask
{
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        $doctor = $this->doctorRepository->create($record);
        // ❌ Une Task ne peut pas appeler une autre Task
        $this->consultationFeeTask->execute($doctor->id, $record);
        
        return $doctor;
    }
}

// ✅ BON - Task qui fait plusieurs actions de même nature elle-même
final class CreateDoctorProfileTask extends AbstractTask
{
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        $doctor = $this->doctorRepository->create($record);
        $this->feeRepository->create($doctor->id, $record);
        $this->profileRepository->create($doctor->id, $record);
        
        return $doctor;
    }
}

// ✅ BON - Worker pour orchestrer des Tasks de natures différentes
final class CreateDoctorWorker extends AbstractWorker
{
    public function __construct(
        private readonly CreateDoctorProfileTask $createDoctorProfile,
        private readonly SendWelcomeEmailTask $sendWelcomeEmail,
        private readonly LogDoctorCreatedTask $logDoctorCreated,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): void
    {
        $this->createDoctorProfile->execute($record);
        $this->sendWelcomeEmail->execute($record);
        $this->logDoctorCreated->execute($record);
    }
}
```

---

## 6. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Action}{Entity}Task` |
| **Méthode** | Une seule : `execute()` |
| **Nature** | Actions de même nature uniquement |
| **Accès Models** | ❌ Interdit (utiliser Repositories) |
| **Appel autre Task** | ❌ Interdit (utiliser Worker) |
| **Simple wrapper** | ❌ Interdit (Task = plusieurs actions ou action complexe) |
| **Appels statiques** | ❌ INTERDIT (Log, Mail, Http, Cache, Facades) |
| **Dépendances** | ✅ DOIVENT être injectées via constructeur |
| **Testabilité** | ✅ Doit être testable unitairement |

---

## 7. Règle d'or

> **Une Task = actions de MÊME NATURE. Si les actions sont de natures différentes, ce n'est pas une Task, c'est un Worker. Une Task ne peut pas être un simple wrapper de Repository.**
>
> **ZÉRO appel statique. TOUTES les dépendances injectées. Si vous voyez `Log::`, `Mail::`, `Http::`, `Cache::`, `DB::` ou toute Facade Laravel dans une Task, c'est une erreur.**

```php
// La Task parfaite
final class CreateDoctorProfileTask
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly ConsultationFeeRepository $feeRepository,
        private readonly DoctorProfileRepository $profileRepository,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function execute(CreateDoctorProfileRecord $record): Doctor
    {
        // 3 actions de MÊME NATURE : créations via Repositories
        $doctor = $this->doctorRepository->create($record);
        $this->feeRepository->create($doctor->id, $record);
        $this->profileRepository->create($doctor->id, $record);
        
        $this->logger->info('Doctor profile created', ['doctor_id' => $doctor->id]);
        
        return $doctor;
    }
}
```