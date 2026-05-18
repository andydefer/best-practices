# Principe d'usage des Services (Version finale)

## 1. Définition

Un **Service** est un composant qui encapsule une **logique métier**. Il peut avoir **plusieurs méthodes**, à condition qu'elles appartiennent toutes au **même domaine métier**.

| Type | Rôle | Exemple |
|------|------|---------|
| **Service pur** | Calcul, transformation, validation | `PriceCalculatorService` |
| **Service technique** | Logique métier + délégation des effets de bord | `CacheService`, `DoctorAvailabilityService` |

```
Service → Logique métier → Plusieurs méthodes (même domaine) → Délègue les effets de bord à des Tasks ou Workers
```

```php
// Service pur : plusieurs méthodes du même domaine (calculs de prix)
final class PriceCalculatorService
{
    public function calculateSubtotal(OrderRecord $record): float
    {
        $total = 0;
        foreach ($record->items as $item) {
            $total += $item->price * $item->quantity;
        }
        return $total;
    }
    
    public function calculateTax(float $subtotal, string $country): float
    {
        return $subtotal * $this->getTaxRate($country);
    }
    
    public function calculateTotal(OrderRecord $record): float
    {
        $subtotal = $this->calculateSubtotal($record);
        $tax = $this->calculateTax($subtotal, $record->country);
        return $subtotal + $tax;
    }
}

// Service technique : plusieurs méthodes du même domaine (cache)
final class CacheService
{
    public function get(string $key): mixed { ... }
    public function set(string $key, mixed $value): void { ... }
    public function delete(string $key): void { ... }
    public function has(string $key): bool { ... }
}

// Service technique : plusieurs méthodes du même domaine (disponibilités docteur)
final class DoctorAvailabilityService
{
    public function nextAvailableSlot(DoctorRecord $doctor): SlotRecord { ... }
    public function isAvailableAt(DoctorRecord $doctor, DateTimeInterface $time): bool { ... }
    public function getSlots(DoctorRecord $doctor, DateRangeRecord $range): array { ... }
}
```

---

## 2. Problématique à laquelle les Services répondent

| Problème | Sans Service | Avec Service |
|----------|-------------|--------------|
| **Logique métier dupliquée** | Le même calcul de prix est copié dans 3 Workers | Un seul `PriceCalculatorService` réutilisable |
| **Tests difficiles** | Un Service avec effet de bord intégré est complexe à mocker | Le Service mocke la Task/Worker |
| **Responsabilité floue** | La logique et l'effet de bord sont mélangés | Le Service fait la logique |

### 2.1 Pourquoi séparer logique et effets de bord ?

```php
// ❌ MAUVAIS - Service avec effet de bord intégré (difficile à tester)
final class NotificationService
{
    public function sendSystemNotification(NotificationRecord $record): void
    {
        if ($record->priority < 5) {
            return;
        }
        
        // ❌ Effet de bord direct - difficile à mocker
        Mail::to($record->recipient)->send(new SystemNotification($record->message));
    }
}

// ✅ BON - Service (logique) + Task (effet de bord)
final class NotificationService
{
    public function __construct(
        private readonly NotificationFilterService $filter,
        private readonly SendSystemNotificationTask $sendNotification,
        private readonly LogNotificationTask $logNotification,
    ) {}
    
    public function sendSystemNotification(NotificationRecord $record): void
    {
        // Logique métier pure (facile à tester)
        if (!$this->filter->shouldSend($record)) {
            return;
        }
        
        // Effets de bord délégués (mockables)
        $this->sendNotification->execute($record);
        $this->logNotification->execute($record->id, 'sent');
    }
}
```

---

## 3. Service vs Worker vs Task : comment choisir ?

| Critère | Service | Worker | Task |
|---------|---------|--------|------|
| **Rôle** | Logique métier | Orchestration | Effet de bord unique ou même nature |
| **Retour** | Valeur (scalaire, Record, array) | `void` | `mixed` ou `void` |
| **Transaction DB** | ✅ Oui (si nécessaire) | ❌ Non | ❌ Non |
| **Logique métier** | ✅ Oui | ❌ Non | ❌ Non |
| **Plusieurs méthodes** | ✅ Oui (même domaine) | ❌ Non (une seule) | ❌ Non (une seule) |
| **Peut utiliser** | Services, Repositories, Tasks, Workers | Tasks, Services, Repositories | Services, Repositories |

### 3.1 Règle des 3 Tasks (⚠️ LOI IMMUABLE)

> **Dès qu'une méthode (Service, Controller, ou autre) appelle 3 Tasks ou plus, il est OBLIGATOIRE de créer un Worker pour orchestrer ces Tasks.**

```php
// ❌ MAUVAIS - Service avec 3 Tasks dans une méthode (violation)
final class UserService
{
    public function register(RegisterUserRecord $record): void
    {
        $this->sendEmailTask->execute($record);   // Task 1
        $this->logTask->execute($record);         // Task 2
        $this->clearCacheTask->execute($record);  // Task 3
    }
}

// ✅ BON - Worker créé pour orchestrer les 3 Tasks
final class RegisterUserWorker
{
    public function execute(RegisterUserRecord $record): void
    {
        $this->sendEmailTask->execute($record);
        $this->logTask->execute($record);
        $this->clearCacheTask->execute($record);
    }
}

// ✅ BON - Service avec logique métier + délégation au Worker
final class UserService
{
    public function __construct(
        private readonly UserValidatorService $validator,
        private readonly RegisterUserWorker $registerWorker,
    ) {}
    
    public function register(RegisterUserRecord $record): void
    {
        // Logique métier
        if (!$this->validator->isValid($record)) {
            throw new InvalidUserException();
        }
        
        // Orchestration déléguée au Worker
        $this->registerWorker->execute($record);
    }
}
```

### 3.2 Règle de transition Service → Worker

> **Dès qu'une méthode de Service a besoin de plusieurs effets de bord de natures différentes (email + log + cache + appel API), comprenez que cette méthode ne mérite pas d'être dans un Service. Créez un Worker.**

---

## 4. Règle : Un Service n'est JAMAIS un simple wrapper

> **⚠️ CRITIQUE : Un Service ne doit JAMAIS être un simple wrapper d'une Task ou d'un Worker. Un Service doit avoir sa propre logique métier.**

```php
// ❌ MAUVAIS - Service simple wrapper (inutile)
final class NotificationService
{
    public function __construct(
        private readonly SendNotificationTask $sendNotification,
    ) {}
    
    public function sendSystemNotification(NotificationRecord $record): void
    {
        // Aucune logique métier, juste un wrapper
        $this->sendNotification->execute($record);
    }
}

// ✅ BON - Service avec logique métier réelle (plusieurs méthodes du même domaine)
final class NotificationService
{
    public function __construct(
        private readonly NotificationFilterService $filter,
        private readonly SendNotificationTask $sendNotification,
        private readonly LogNotificationTask $logNotification,
    ) {}
    
    public function sendSystemNotification(NotificationRecord $record): void
    {
        // Logique métier : filtrage
        if (!$this->filter->shouldSend($record)) {
            return;
        }
        
        // Logique métier : enrichissement
        $enriched = $this->enrichWithPriority($record);
        
        // Effets de bord délégués
        $this->sendNotification->execute($enriched);
        $this->logNotification->execute($record->id, 'sent');
    }
    
    public function sendBulkNotifications(array $records): void
    {
        foreach ($records as $record) {
            $this->sendSystemNotification($record);
        }
    }
    
    private function enrichWithPriority(NotificationRecord $record): NotificationRecord
    {
        $priority = $record->urgent ? 10 : 1;
        return new NotificationRecord(...);
    }
}

// ✅ BON - Si pas de logique métier, supprimer le Service
final class NotificationController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $record = new NotificationRecord(...);
        
        // Appel direct au Worker (ou à la Task)
        $this->sendNotificationWorker->execute($record);
        
        return response()->json(['success' => true]);
    }
}
```

### 4.1 Règle : Si pas de logique métier, pas de Service

| Situation | Solution |
|-----------|----------|
| Service avec logique métier (filtrage, calcul, validation, transformation) | ✅ Garder le Service |
| Service sans logique métier (simple appel à une Task/Worker) | ❌ Supprimer le Service, appeler directement la Task/Worker |

---

## 5. Recommandations sur les types

### 5.1 Types autorisés en entrée

> **Nous recommandons de prendre en paramètre des `Record`, des `scalaires`, des `Enum` ou des `TypedRecords`.**

| Type | Recommandation | Exemple |
|------|----------------|---------|
| `Record` | ✅ Recommandé | `function calculate(OrderRecord $record): float` |
| `scalaire` (int, float, string, bool) | ✅ Recommandé | `function calculateTax(float $subtotal, string $country): float` |
| `Enum` | ✅ Recommandé | `function filterByRole(UserRole $role): array` |
| `TypedRecords` | ✅ Recommandé | `function processBatch(TypedRecords $records): array` |
| `Model` | ❌ **STRICTEMENT INTERDIT** | Un Service ne doit jamais recevoir de Model |
| `Data` | ❌ **STRICTEMENT INTERDIT** | Un Service ne doit jamais recevoir de Data |

---

### 5.2 Types autorisés en sortie

> **Nous recommandons de retourner des `Record`, des `scalaires`, des `Enum` ou des `TypedRecords`.**

| Type | Recommandation | Exemple |
|------|----------------|---------|
| `Record` | ✅ Recommandé | `return new PriceRecord(...)` |
| `scalaire` (int, float, string, bool) | ✅ Recommandé | `return $total` |
| `Enum` | ✅ Recommandé | `return UserRole::ADMIN` |
| `TypedRecords` | ✅ Recommandé | `return $slots` |
| `Model` | ⚠️ Acceptable mais moins bon | Préférer un `Record` |
| `Data` | ❌ Interdit | Les Services ne doivent pas connaître la couche API |

### 5.3 Interdiction stricte : les Data

> **⚠️ Les Services ne sont PAS autorisés à travailler avec la couche API. Ils ne doivent NI prendre des `Data` en paramètre, NI retourner des `Data`.**

```php
// ❌ MAUVAIS - Service qui prend une Data en paramètre
final class PriceCalculatorService
{
    public function calculate(PriceCalculatorData $data): float  // ❌
    {
        // ...
    }
}

// ❌ MAUVAIS - Service qui retourne une Data
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): PriceData  // ❌
    {
        // ...
    }
}

// ✅ BON - Service prend et retourne des Records
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): PriceRecord  // ✅
    {
        // ...
    }
}
```

---

## 6. Règle : Écrire du code testable unitairement (⚠️ RÈGLE ABSOLUE)

> **Un Service DOIT être testable unitairement. Cela signifie que toutes ses dépendances doivent pouvoir être mockées et que sa logique métier doit pouvoir être isolée.**

### 6.1 Ce qui rend un Service testable

| Caractéristique | Pourquoi c'est testable |
|-----------------|------------------------|
| **Dépendances injectées via constructeur** | On peut les remplacer par des mocks |
| **Pas d'effets de bord directs** | On n'a pas à mocker des appels statiques |
| **Délégation aux Tasks/Workers** | Les effets de bord sont mockables |
| **Entrées/Sorties typées (Record, scalaire, Enum, TypedRecords)** | Données prévisibles et structurées |
| **Interdiction des Models et Data** | Pas de coupling avec Eloquent ou l'API |
| **Logique métier pure (calculs, transformations, validations)** | Pas de dépendances externes |

### 6.2 Ce qui rend un Service NON testable (À ÉVITER)

```php
// ❌ MAUVAIS - Service NON testable
final class BadService
{
    public function execute(int $id): void
    {
        // ❌ Appel statique (non mockable)
        Log::info('Processing user', ['id' => $id]);
        
        // ❌ Model direct (non mockable proprement)
        $user = User::find($id);
        
        // ❌ Facade Laravel (non mockable)
        Cache::put('user_' . $id, $user);
        
        // ❌ Effet de bord direct (non mockable)
        Mail::to($user->email)->send(new WelcomeEmail());
    }
}
```

**Pourquoi ce code n'est pas testable unitairement :**
- `Log::info()` est un appel statique → impossible à mocker
- `User::find()` est un appel statique → appelle VRAIMENT la base de données
- `Cache::put()` est une facade → appel statique caché
- `Mail::send()` est une facade → appel statique caché
- Aucune dépendance injectée → tout est caché et statique

### 6.3 Ce qui rend un Service testable (À FAIRE)

```php
// ✅ BON - Service TESTABLE
final class GoodService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
        private readonly MailerInterface $mailer,
        private readonly UserValidatorService $validator,
    ) {}
    
    public function execute(int $id): void
    {
        // Logique métier testable
        if (!$this->validator->isValidUserId($id)) {
            throw new InvalidUserIdException();
        }
        
        // Appel au Repository (mockable)
        $user = $this->userRepository->find($id);
        
        if ($user === null) {
            return;
        }
        
        // Appels aux interfaces (toutes mockables)
        $this->logger->info('Processing user', ['id' => $id]);
        $this->cache->set('user_' . $id, $user);
        $this->mailer->to($user->email)->send(new WelcomeEmail());
    }
}
```

### 6.4 Test unitaire d'un Service

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\GoodService;
use App\Contracts\LoggerInterface;
use App\Repositories\UserRepository;
use App\Contracts\CacheInterface;
use App\Contracts\MailerInterface;
use App\Services\UserValidatorService;
use App\Records\UserRecord;
use Tests\TestCase;

final class GoodServiceTest extends TestCase
{
    private GoodService $service;
    private LoggerInterface $logger;
    private UserRepository $userRepository;
    private CacheInterface $cache;
    private MailerInterface $mailer;
    private UserValidatorService $validator;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->validator = $this->createMock(UserValidatorService::class);
        
        $this->service = new GoodService(
            $this->logger,
            $this->userRepository,
            $this->cache,
            $this->mailer,
            $this->validator,
        );
    }
    
    public function test_execute_logs_and_caches_and_mails_when_user_found(): void
    {
        $user = new UserRecord(id: 1, email: 'john@example.com');
        
        $this->validator
            ->expects($this->once())
            ->method('isValidUserId')
            ->with(1)
            ->willReturn(true);
        
        $this->userRepository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);
        
        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Processing user', ['id' => 1]);
        
        $this->cache
            ->expects($this->once())
            ->method('set')
            ->with('user_1', $user);
        
        $this->mailer
            ->expects($this->once())
            ->method('to')
            ->with('john@example.com')
            ->willReturn($this->mailer);
        
        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(WelcomeEmail::class));
        
        $this->service->execute(1);
    }
    
    public function test_execute_does_nothing_when_user_not_found(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('isValidUserId')
            ->with(999)
            ->willReturn(true);
        
        $this->userRepository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        
        $this->logger->expects($this->never())->method('info');
        $this->cache->expects($this->never())->method('set');
        $this->mailer->expects($this->never())->method('send');
        
        $this->service->execute(999);
    }
    
    public function test_execute_throws_exception_when_user_id_invalid(): void
    {
        $this->validator
            ->expects($this->once())
            ->method('isValidUserId')
            ->with(0)
            ->willReturn(false);
        
        $this->userRepository->expects($this->never())->method('find');
        
        $this->expectException(InvalidUserIdException::class);
        
        $this->service->execute(0);
    }
}
```

### 6.5 Règle d'or pour la testabilité

> **ZÉRO appel statique. TOUTES les dépendances injectées. Si vous voyez `Log::`, `Mail::`, `Http::`, `Cache::`, `DB::`, `User::find()`, `Model::create()` ou toute Facade Laravel dans un Service, c'est une erreur.**

```php
// ✅ Ce qui est testable
final class TestableService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
        private readonly CacheInterface $cache,
        private readonly MailerInterface $mailer,
    ) {}
}

// ❌ Ce qui ne l'est PAS
final class UntestableService
{
    public function execute(): void
    {
        Log::info('message');           // ❌ Appel statique
        User::find(1);                  // ❌ Appel statique (Model)
        Cache::put('key', 'value');     // ❌ Facade
        Mail::send($email);             // ❌ Facade
        DB::table('users')->get();      // ❌ Facade
        Http::get($url);                // ❌ Facade
    }
}
```

### 6.6 Récapitulatif des interdictions pour la testabilité

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| `Log::info()` direct | Appel statique non mockable | Interface `LoggerInterface` injectée |
| `User::find()` direct | Appel statique non mockable | Repository injecté |
| `Cache::put()` direct | Facade statique non mockable | Interface `CacheInterface` injectée |
| `Mail::send()` direct | Facade statique non mockable | Interface `MailerInterface` injectée |
| `DB::table()` direct | Facade statique non mockable | Repository avec interface |
| `Http::get()` direct | Facade statique non mockable | Interface `HttpClientInterface` injectée |
| `new` dans le constructeur | Coupling caché non mockable | Injection de dépendances |

---

## 7. Règles fondamentales

### 7.1 Nommage

```
{Action}{Entity}Service
```

| Type de Service | Convention | Exemple |
|----------------|------------|---------|
| **Service pur** | `{What}CalculatorService` | `PriceCalculatorService` |
| **Service technique** | `{What}Service` | `CacheService`, `DoctorAvailabilityService` |

### 7.2 Localisation

```
app/Services/{Domain}/{Action}{Entity}Service.php
```

```
app/Services/
├── Calculator/
│   └── PriceCalculatorService.php
├── Notification/
│   └── NotificationService.php
├── Cache/
│   └── CacheService.php
└── Doctor/
    └── DoctorAvailabilityService.php
```

### 7.3 Règle d'or

> **Un Service ne doit jamais avoir d'effet de bord directement. Il délègue TOUJOURS les effets de bord à des Tasks ou des Workers.**

```php
// ✅ BON - Service délègue à une Task
final class NotificationService
{
    public function __construct(
        private readonly NotificationFilterService $filter,
        private readonly SendNotificationTask $sendNotification,
    ) {}
    
    public function sendSystemNotification(NotificationRecord $record): void
    {
        if (!$this->filter->shouldSend($record)) {
            return;
        }
        
        $this->sendNotification->execute($record);
    }
}

// ✅ BON - Service délègue à un Worker
final class UserService
{
    public function __construct(
        private readonly UserValidatorService $validator,
        private readonly RegisterUserWorker $registerWorker,
    ) {}
    
    public function register(RegisterUserRecord $record): void
    {
        if (!$this->validator->isValidEmail($record->email)) {
            throw new InvalidEmailException();
        }
        
        $this->registerWorker->execute($record);
    }
}
```

---

## 8. Ce qu'un Service peut faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Calculer, transformer, valider** | ✅ Oui | `$a + $b`, `array_map` |
| **Avoir plusieurs méthodes (même domaine)** | ✅ Oui | `get()`, `set()`, `delete()` |
| **Appeler des Services** | ✅ Oui | `$this->priceCalculator->calculate(...)` |
| **Appeler des Repositories** | ✅ Oui | `$this->userRepository->find($id)` |
| **Appeler des Tasks** | ✅ Oui | `$this->logTask->execute(...)` |
| **Appeler des Workers** | ✅ Oui | `$this->registerWorker->execute(...)` |
| **Retourner des Record** | ✅ Oui | `return new PriceRecord(...)` |

---
## 9. Ce qu'un Service NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Avoir un effet de bord direct** | Difficulté à tester | Déplacer dans une Task |
| **Être un simple wrapper d'une Task** | Aucune valeur ajoutée | Supprimer le Service, appeler directement la Task |
| **Orchestrer plusieurs effets de bord de natures différentes** | C'est le rôle des Workers | Créer un Worker |
| **Faire des transactions DB** | C'est le rôle des Workers | Créer un Worker |
| **Retourner des `Data`** | Violation de la couche | Retourner des `Record` |
| **Prendre des `Data`** | Violation de la couche | Prendre des `Record` |
| **Prendre des `Model`** | Violation de la couche, difficile à tester | Prendre des `Record` |
| **Mélanger des domaines différents** | Violation SRP | Créer plusieurs Services |
| **Appels statiques (Log, Mail, Http, Cache, DB)** | Non testable | Injecter des interfaces |

### 9.1 Mélange de domaines (interdit)

```php
// ❌ MAUVAIS - Mélange de domaines différents
final class UtilityService
{
    public function calculateDistance(...): float { ... }  // Géographie
    public function sendEmail(...): void { ... }            // Email
    public function logUser(...): void { ... }              // Log
}

// ✅ BON - Services séparés par domaine
final class DistanceCalculatorService { ... }
final class EmailService { ... }
final class UserLoggerService { ... }
```

---

## 10. Exemple complet

```php
final class NotificationService
{
    public function __construct(
        private readonly NotificationFilterService $filter,
        private readonly UserRepository $userRepository,
        private readonly SendNotificationTask $sendNotification,
        private readonly LogNotificationTask $logNotification,
        private readonly SendNotificationWorker $sendNotificationWorker, // Worker pour 3+ Tasks
        private readonly LoggerInterface $logger,
    ) {}
    
    // Méthode 1 : notification simple (2 Tasks → acceptable dans un Service)
    public function sendSystemNotification(NotificationRecord $record): void
    {
        // Logique métier : validation/filtrage
        if (!$this->filter->shouldSend($record)) {
            return;
        }
        
        // Logique métier : enrichissement
        $enriched = $this->enrichWithUserData($record);
        
        // Effets de bord délégués (2 Tasks)
        $this->sendNotification->execute($enriched);
        $this->logNotification->execute($record->id, 'sent');
        
        $this->logger->info('Notification sent', ['id' => $record->id]);
    }
    
    // Méthode 2 : notification avec plusieurs effets de bord (3+ Tasks → Worker obligatoire)
    public function sendComplexNotification(ComplexNotificationRecord $record): void
    {
        // Logique métier
        $enriched = $this->enrichWithUserData($record);
        
        // Délégation au Worker (qui orchestre les 3+ Tasks)
        $this->sendNotificationWorker->execute($enriched);
    }
    
    // Méthode 3 : notification groupée (même domaine)
    public function sendBulkNotifications(array $records): void
    {
        foreach ($records as $record) {
            $this->sendSystemNotification($record);
        }
    }
    
    // Méthode 4 : notification avec confirmation (même domaine)
    public function sendNotificationWithConfirmation(NotificationRecord $record): bool
    {
        $this->sendSystemNotification($record);
        
        // Logique métier : attente de confirmation
        return $this->waitForConfirmation($record->id);
    }
    
    // Méthode privée : logique métier pure
    private function enrichWithUserData(NotificationRecord $record): NotificationRecord
    {
        $user = $this->userRepository->find($record->userId);
        
        return new NotificationRecord(
            message: str_replace('{{name}}', $user->name, $record->message),
            priority: $record->urgent ? 10 : 1,
        );
    }
    
    private function waitForConfirmation(int $notificationId): bool
    {
        // Logique métier pure
        return $this->confirmationService->wait($notificationId, 30);
    }
}
```

---

## 11. Tableau récapitulatif

| Contrainte | Règle |
|------------|-------|
| **Rôle** | Logique métier (calcul, validation, transformation) |
| **Méthodes** | Plusieurs possibles, mais TOUTES du même domaine |
| **Entrées** | Recommandé : Record, scalaire, Enum, TypedRecords (interdit : Data, Model) |
| **Sorties** | Recommandé : Record, scalaire, Enum, TypedRecords (interdit : Data) |
| **Simple wrapper** | ❌ Interdit (supprimer le Service) |
| **3 Tasks dans une méthode** | ❌ Interdit (créer un Worker) |
| **Effet de bord direct** | ❌ Interdit (déléguer à une Task) |
| **Transaction DB** | ❌ Interdit (créer un Worker) |
| **Appels statiques (Log, Mail, Http, Cache, DB)** | ❌ Interdit (injecter des interfaces) |
| **Peut utiliser** | Services, Repositories, Tasks, Workers |
| **Nommage** | `{Action}{Entity}Service` |
| **Testabilité** | ✅ Doit être testable unitairement |

---

## 12. Règle d'or

> **Un Service a une logique métier. Il peut avoir plusieurs méthodes, mais toutes doivent appartenir au même domaine. Il ne prend ni ne retourne jamais de `Data`. ZÉRO appel statique. TOUTES les dépendances injectées. Si vous n'avez pas de logique métier, vous n'avez pas besoin d'un Service.**

```php
// Le Service parfait : plusieurs méthodes du même domaine, logique métier pure, délégation des effets de bord, testable
final class PerfectService
{
    public function __construct(
        private readonly SomeRepository $repository,
        private readonly SomeCalculatorService $calculator,
        private readonly SomeTask $task,
        private readonly SomeWorker $worker,
        private readonly LoggerInterface $logger,
    ) {}
    
    // Méthode 1 : logique simple (2 Tasks)
    public function execute(InputRecord $record): OutputRecord
    {
        $this->validate($record);
        $result = $this->calculator->calculate($record);
        $output = $this->transform($result);
        
        // 2 Tasks → acceptable dans un Service
        $this->task->execute($output);
        $this->logger->info('Executed', ['id' => $record->id]);
        
        return $output;
    }
    
    // Méthode 2 : logique complexe (3+ Tasks) → délégation au Worker
    public function executeComplex(ComplexInputRecord $record): OutputRecord
    {
        $result = $this->calculator->calculate($record);
        $output = $this->transform($result);
        
        // 3+ Tasks → Worker obligatoire
        $this->worker->execute($output);
        
        return $output;
    }
    
    // Méthode 3 (même domaine)
    public function executeBatch(array $records): array
    {
        $results = [];
        foreach ($records as $record) {
            $results[] = $this->execute($record);
        }
        return $results;
    }
    
    // Méthode 4 (même domaine)
    public function validate(InputRecord $record): void
    {
        if (empty($record->requiredField)) {
            throw new ValidationException('Required field missing');
        }
    }
    
    private function transform($result): OutputRecord
    {
        return new OutputRecord(...);
    }
}
```