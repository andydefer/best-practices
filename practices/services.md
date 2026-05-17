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
| **Transaction DB** | ❌ Non | ✅ Oui | ❌ Non |
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

> **Nous recommandons de prendre en paramètre des `Record`, des `scalaires`, des `Enum` ou des `array` de ces types.**

| Type | Recommandation | Exemple |
|------|----------------|---------|
| `Record` | ✅ Recommandé | `function calculate(OrderRecord $record): float` |
| `scalaire` (int, float, string, bool) | ✅ Recommandé | `function calculateTax(float $subtotal, string $country): float` |
| `Enum` | ✅ Recommandé | `function filterByRole(UserRole $role): array` |
| `array<Record>` | ✅ Recommandé | `function processBatch(array $records): array` |
| `array<scalaire>` | ✅ Recommandé | `function findByIds(array $ids): array` |
| `Model` | ❌ **STRICTEMENT INTERDIT** | Un Service ne doit jamais recevoir de Model |
| `Data` | ❌ **STRICTEMENT INTERDIT** | Un Service ne doit jamais recevoir de Data |

---

### 5.2 Types autorisés en sortie

> **Nous recommandons de retourner des `Record`, des `scalaires`, des `Enum` ou des `array` de ces types.**

| Type | Recommandation | Exemple |
|------|----------------|---------|
| `Record` | ✅ Recommandé | `return new PriceRecord(...)` |
| `scalaire` (int, float, string, bool) | ✅ Recommandé | `return $total` |
| `Enum` | ✅ Recommandé | `return UserRole::ADMIN` |
| `array<Record>` | ✅ Recommandé | `return $slots` |
| `array<scalaire>` | ✅ Recommandé | `return $ids` |
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

## 6. Règles fondamentales

### 6.1 Nommage

```
{Action}{Entity}Service
```

| Type de Service | Convention | Exemple |
|----------------|------------|---------|
| **Service pur** | `{What}CalculatorService` | `PriceCalculatorService` |
| **Service technique** | `{What}Service` | `CacheService`, `DoctorAvailabilityService` |

### 6.2 Localisation

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

### 6.3 Règle d'or

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

## 7. Ce qu'un Service peut faire

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
## 8. Ce qu'un Service NE peut PAS faire

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

### 8.1 Mélange de domaines (interdit)

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

## 9. Exemple complet

```php
final class NotificationService
{
    public function __construct(
        private readonly NotificationFilterService $filter,
        private readonly UserRepository $userRepository,
        private readonly SendNotificationTask $sendNotification,
        private readonly LogNotificationTask $logNotification,
        private readonly SendNotificationWorker $sendNotificationWorker, // Worker pour 3+ Tasks
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

## 10. Tableau récapitulatif

| Contrainte | Règle |
|------------|-------|
| **Rôle** | Logique métier (calcul, validation, transformation) |
| **Méthodes** | Plusieurs possibles, mais TOUTES du même domaine |
| **Entrées** | Recommandé : Record, scalaire, Enum, array (interdit : Data, Model) |
| **Sorties** | Recommandé : Record, scalaire, Enum, array (interdit : Data) |
| **Simple wrapper** | ❌ Interdit (supprimer le Service) |
| **3 Tasks dans une méthode** | ❌ Interdit (créer un Worker) |
| **Effet de bord direct** | ❌ Interdit (déléguer à une Task) |
| **Transaction DB** | ❌ Interdit (créer un Worker) |
| **Peut utiliser** | Services, Repositories, Tasks, Workers |
| **Nommage** | `{Action}{Entity}Service` |

---

## 11. Règle d'or

> **Un Service a une logique métier. Il peut avoir plusieurs méthodes, mais toutes doivent appartenir au même domaine. Il ne prend ni ne retourne jamais de `Data`. Si vous n'avez pas de logique métier, vous n'avez pas besoin d'un Service.**

```php
// Le Service parfait : plusieurs méthodes du même domaine, logique métier pure, délégation des effets de bord
final class PerfectService
{
    public function __construct(
        private readonly SomeRepository $repository,
        private readonly SomeCalculatorService $calculator,
        private readonly SomeTask $task,
        private readonly SomeWorker $worker,
    ) {}
    
    // Méthode 1 : logique simple (2 Tasks)
    public function execute(InputRecord $record): OutputRecord
    {
        $this->validate($record);
        $result = $this->calculator->calculate($record);
        $output = $this->transform($result);
        
        // 2 Tasks → acceptable dans un Service
        $this->task->execute($output);
        $this->logTask->execute($record->id, 'executed');
        
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
