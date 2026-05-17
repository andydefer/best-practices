# Principe d'usage des Tasks (Version finale)

## 1. Définition

Une **Task** est un composant qui encapsule une **action de même nature**. Elle peut faire plusieurs choses tant que ces choses partagent un point commun de nature (ex: plusieurs créations en DB, plusieurs logs, plusieurs appels HTTP).

**⚠️ Une Task ne doit JAMAIS accéder directement aux Models Eloquent. Elle DOIT utiliser des Repositories.**

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

// Task pour plusieurs logs (même nature : écriture logs)
final class LogOrderTask
{
    public function execute(OrderRecord $record): void
    {
        Log::info('Order created', ['order_id' => $record->id]);
        Log::info('Order items', ['items' => $record->items]);
        Log::info('Order total', ['total' => $record->total]);
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
| `LogOrderTask` | Écritures de logs (3 logs) |
| `SendEmailTask` | Envoi d'email (1 email) |
| `CreateStripeCustomerTask` | Appel API Stripe |
| `FetchExchangeRateTask` | Appel API externe |

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

| Nature | Exemples d'actions |
|--------|---------------------|
| **Créations DB** | `$doctorRepository->create()`, `$feeRepository->create()`, `$profileRepository->create()` |
| **Logs** | `Log::info()`, `Log::error()`, `Log::warning()` |
| **Emails** | `Mail::send()`, `Mail::queue()` |
| **Appels HTTP** | `Http::get()`, `Http::post()`, `Http::put()` |
| **Cache** | `Cache::put()`, `Cache::forget()`, `Cache::remember()` |

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
    public function execute(SendWelcomeEmailRecord $record): void
    {
        Mail::to($record->email)->send(new WelcomeEmail($record->name));
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

### 5.2 Task avec plusieurs logs (même nature)

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Log;

use App\Records\OrderRecord;
use Illuminate\Support\Facades\Log;

final class LogOrderTask
{
    public function execute(OrderRecord $record): void
    {
        Log::info('Order created', [
            'order_id' => $record->id,
            'user_id' => $record->userId,
            'total' => $record->total,
        ]);
        
        Log::info('Order items', [
            'order_id' => $record->id,
            'items' => $record->items,
        ]);
        
        Log::info('Order status changed', [
            'order_id' => $record->id,
            'status' => $record->status,
        ]);
    }
}
```

### 5.3 Task pour appel HTTP externe

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Http;

use App\Records\CurrencyPairRecord;
use Illuminate\Support\Facades\Http;

final class FetchExchangeRateTask
{
    public function execute(CurrencyPairRecord $record): float
    {
        $response = Http::get('https://api.exchangerate.com/v1/rate', [
            'from' => $record->from,
            'to' => $record->to,
            'apikey' => config('services.exchangerate.api_key'),
        ]);
        
        if ($response->failed()) {
            throw new ExchangeRateException('Failed to fetch exchange rate');
        }
        
        return (float) $response->json('rate');
    }
}
```

### 5.4 Task pour appel API Stripe

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Stripe;

use App\Records\CreateStripeCustomerRecord;
use Stripe\Customer;

final class CreateStripeCustomerTask
{
    public function execute(CreateStripeCustomerRecord $record): Customer
    {
        return Customer::create([
            'email' => $record->email,
            'name' => $record->name,
            'metadata' => $record->metadata,
        ]);
    }
}
```

### 5.5 Task pour email

```php
<?php

declare(strict_types=1);

namespace App\Tasks\Email;

use App\Records\SendWelcomeEmailRecord;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;

final class SendWelcomeEmailTask
{
    public function execute(SendWelcomeEmailRecord $record): void
    {
        Mail::to($record->email)->send(new WelcomeEmail($record->name));
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
