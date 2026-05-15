# Principe d'usage des Services (Version finale)

## 1. Définition

Un **Service** est un composant qui encapsule une **logique métier**. Il peut être de deux types :

| Type | Rôle | Exemple |
|------|------|---------|
| **Service pur** | Calcul, transformation, validation | `PriceCalculatorService` |
| **Service technique** | Accès à une infrastructure (cache, email, log) | `CacheService`, `EmailService` |

```
Service → Logique métier ou accès technique → Peut avoir des effets de bord explicites
```

```php
// Service pur : calcul, pas d'effet de bord caché
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = 0;
        foreach ($record->items as $item) {
            $total += $item->price * $item->quantity;
        }
        return $total;
    }
}

// Service technique : offre des méthodes avec effets de bord explicites
final class CacheService
{
    public function set(string $key, mixed $value): void { ... }
    public function get(string $key): mixed { ... }
}
```

---

## 2. Problématique à laquelle les Services répondent

| Problème | Sans Service | Avec Service |
|----------|-------------|--------------|
| **Logique métier dupliquée** | Le même calcul de prix est copié dans 3 Controllers | Un seul `PriceCalculatorService` réutilisable |
| **Code difficile à tester** | La logique est noyée dans le Controller | Le Service se teste unitairement facilement |
| **Accès technique dispersé** | Redis, Email, Log sont appelés partout | Des Services dédiés centralisent l'accès |
| **Difficulté à modifier** | Changer une règle métier = modifier plusieurs endroits | Un seul Service à modifier |

### 2.1 Service vs Action

| Critère | **Service** | **Action** |
|---------|-------------|------------|
| **Rôle** | Logique métier ou accès technique | Opération métier complète |
| **Effets de bord** | ✅ Explicites (si nom de méthode le suggère) | ✅ Création, modification, email, event |
| **Retour** | ✅ Valeur (scalaire, Record, array) | ❌ `void` |
| **Transaction DB** | ❌ Non (sauf service technique) | ✅ Oui |
| **Exemple** | `PriceCalculatorService::calculate()` | `CreateOrderAction::execute()` |

```php
// Service : calcule, retourne une valeur
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float { ... }
}

// Action : effets de bord, retourne void
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            Order::create([...]);
            event(new OrderCreated(...));
        });
    }
}
```

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Action}{Entity}Service
```

| Type de Service | Convention | Exemple |
|----------------|------------|---------|
| **Calcul** | `{What}CalculatorService` | `PriceCalculatorService` |
| **Validation** | `{What}ValidatorService` | `EmailValidatorService` |
| **Transformation** | `{From}To{To}TransformerService` | `UserToRecordTransformerService` |
| **Cache** | `{Technology}CacheService` | `RedisCacheService` |
| **Email** | `{Type}EmailService` | `NotificationEmailService` |
| **Log** | `{Level}LoggerService` | `AppLoggerService` |

### 3.2 Localisation

```
app/Services/{Domain}/{Action}{Entity}Service.php
```

```
app/Services/
├── Calculator/
│   ├── PriceCalculatorService.php
│   └── DistanceCalculatorService.php
├── Validator/
│   ├── EmailValidatorService.php
│   └── UserValidatorService.php
├── Cache/
│   └── RedisCacheService.php
├── Email/
│   └── NotificationEmailService.php
└── Transformer/
    └── UserToRecordTransformerService.php
```

---

## 4. Les deux types de Services

### 4.1 Service pur (calcul, transformation, validation)

> **Un Service pur n'a AUCUN effet de bord. Il reçoit des données, calcule, retourne une valeur.**

```php
// ✅ BON - Service pur
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = 0;
        foreach ($record->items as $item) {
            $total += $item->price * $item->quantity;
        }
        return $total;
    }
}

// ❌ MAUVAIS - Service pur avec effet de bord caché
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = 0;
        foreach ($record->items as $item) {
            $total += $item->price * $item->quantity;
        }
        
        // ❌ Effet de bord caché : l'appelant ne s'y attend pas
        $this->cache->set('last_calculation', $total);
        
        return $total;
    }
}
```

### 4.2 Service technique (cache, email, log, file)

> **Un Service technique offre des méthodes qui ont des effets de bord EXPLICITES. Le nom de la méthode doit suggérer l'effet de bord.**

```php
// ✅ BON - Service technique avec effets de bord explicites
final class RedisCacheService
{
    // Le nom "set" suggère un effet de bord (écriture)
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        Redis::setex($key, $ttl, serialize($value));
    }
    
    // Le nom "get" suggère une lecture (pas d'effet de bord attendu)
    public function get(string $key): mixed
    {
        return unserialize(Redis::get($key));
    }
    
    // Le nom "delete" suggère un effet de bord
    public function delete(string $key): void
    {
        Redis::del($key);
    }
}

// ✅ BON - Service email avec effet de bord explicite
final class NotificationEmailService
{
    // Le nom "send" suggère un effet de bord
    public function send(string $to, string $subject, string $body): void
    {
        Mail::to($to)->send(new GenericEmail($subject, $body));
    }
}

// ✅ BON - Service log avec effet de bord explicite
final class AppLoggerService
{
    // Le nom "info" suggère une écriture dans les logs
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }
}
```

---

## 5. La règle d'or des effets de bord

> **Un Service peut avoir des effets de bord, mais UNIQUEMENT si le nom de la méthode les suggère clairement. Une méthode qui s'appelle `calculate`, `get`, `validate` ou `transform` ne doit PAS avoir d'effet de bord caché.**

| Nom de méthode | Effet de bord attendu | Ce qui est interdit |
|----------------|----------------------|---------------------|
| `set()`, `save()`, `store()` | ✅ Oui (écriture) | Cache, DB, file |
| `delete()`, `remove()` | ✅ Oui (suppression) | Cache, DB, file |
| `send()` | ✅ Oui (envoi) | Email, notification |
| `log()`, `info()`, `error()` | ✅ Oui (écriture) | Logs |
| `calculate()` | ❌ Non | Tout effet de bord |
| `get()` | ❌ Non | Écriture cachée |
| `validate()` | ❌ Non | Log, cache, email |
| `transform()` | ❌ Non | Tout effet de bord |

```php
// ✅ BON - Les noms suggèrent les effets de bord
final class CacheService
{
    public function set(string $key, mixed $value): void  // ← set = écriture
    {
        Redis::set($key, $value);
    }
    
    public function get(string $key): mixed  // ← get = lecture
    {
        return Redis::get($key);
    }
}

// ❌ MAUVAIS - Nom ne suggère pas l'effet de bord
final class UserService
{
    public function getUser(int $id): UserRecord
    {
        // ❌ "getUser" n'évoque pas un log
        Log::info("Getting user {$id}");
        
        return User::find($id);
    }
}

// ❌ MAUVAIS - Nom ne suggère pas l'effet de bord
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = // calcul...
        
        // ❌ "calculate" n'évoque pas un cache
        $this->cache->set('last_total', $total);
        
        return $total;
    }
}
```

---

## 6. Ce qu'un Service peut faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Calculer, transformer, valider** | ✅ Oui | `$a + $b`, `array_map` |
| **Offrir des méthodes avec effets de bord explicites** | ✅ Oui | `CacheService::set()`, `EmailService::send()` |
| **Lire la base de données** | ✅ Oui (si méthode le suggère) | `UserRepositoryService::find()` |
| **Appeler d'autres Services** | ✅ Oui | `$this->cache->get()` |
| **Lire des constantes** | ✅ Oui | `self::TAX_RATE` |

---

## 7. Ce qu'un Service NE peut PAS faire (⚠️ INTERDICTIONS)

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Avoir des effets de bord internes cachés** | L'appelant ne les contrôle pas | Rendre l'effet de bord explicite dans le nom de la méthode |
| **Décider tout seul d'écrire en DB/log/cache** | Effet de bord non explicite | L'appelant décide d'appeler une méthode dédiée |
| **Avoir des effets de bord dans une méthode `calculate`/`get`/`validate`** | Le nom ne suggère pas l'effet de bord | Renommer la méthode ou déplacer l'effet de bord |
| **Retourner `void` sans raison explicite** | `void` = effet de bord, doit être explicite | Une méthode `void` doit avoir un nom qui suggère l'effet de bord (`send`, `save`, `delete`) |

```php
// ❌ TOUT CECI EST INTERDIT

// Effet de bord caché dans une méthode "get"
final class UserService
{
    public function getUser(int $id): UserRecord
    {
        Log::info("Getting user");  // ❌ Caché
        return User::find($id);
    }
}

// Effet de bord caché dans une méthode "calculate"
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = // calcul...
        $this->cache->set('total', $total);  // ❌ Caché
        return $total;
    }
}

// Méthode void sans nom explicite
final class CacheService
{
    public function process(string $key, mixed $value): void  // ❌ "process" trop vague
    {
        Redis::set($key, $value);
    }
}

// ✅ BON - Nom explicite
final class CacheService
{
    public function set(string $key, mixed $value): void  // ✅ "set" est explicite
    {
        Redis::set($key, $value);
    }
}
```

---

## 8. Le rôle du Controller

> **C'est le Controller qui orchestre et décide quels effets de bord déclencher.**

```php
// ✅ BON - Controller orchestre
final class OrderController extends Controller
{
    public function calculate(OrderRequest $request): JsonResponse
    {
        // 1. Lecture DB
        $order = Order::find($request->orderId);
        
        // 2. Transformation Model → Record
        $orderRecord = new OrderRecord(...);
        
        // 3. Appel du Service pur (calcul)
        $total = $this->priceCalculatorService->calculate($orderRecord);
        
        // 4. Effets de bord DÉCIDÉS par le Controller
        $this->cacheService->set("order_total_{$order->id}", $total);
        $this->loggerService->info("Total calculated", ['order_id' => $order->id, 'total' => $total]);
        
        // 5. Réponse
        return response()->json(['total' => $total]);
    }
}
```

---

## 9. SRP : Un Service a une seule responsabilité

```php
// ✅ BON - Un Service = une responsabilité
final class TaxCalculatorService
{
    public function calculate(float $subtotal, string $country): float { ... }
}

// ❌ MAUVAIS - Multiples responsabilités
final class UtilityService
{
    public function calculateDistance(...): float { ... }  // Géographie
    public function calculateTax(...): float { ... }       // Finance
    public function validateEmail(...): bool { ... }       // Validation
    public function setCache(...): void { ... }            // Cache
}
```

---

## 10. Gestion des erreurs

### 10.1 Lever des exceptions métier

```php
// ✅ BON - Exceptions métier explicites
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        if (empty($record->items)) {
            throw new EmptyOrderException('Cannot calculate price for empty order');
        }
        
        return $this->calculateTotal($record->items);
    }
}
```

### 10.2 Ne pas lever d'exceptions HTTP

```php
// ❌ À ÉVITER
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        throw new HttpException(400, 'Bad request');  // ❌
    }
}
```

---

## 11. Exemples complets

### 11.1 Service pur (calcul)

```php
<?php

declare(strict_types=1);

namespace App\Services\Calculator;

use App\Records\OrderRecord;

final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        $total = 0;
        foreach ($record->items as $item) {
            $total += $item->price * $item->quantity;
        }
        return $total;
    }
}
```

### 11.2 Service pur (validation)

```php
<?php

declare(strict_types=1);

namespace App\Services\Validator;

use App\Records\EmailValidationRecord;

final class EmailValidatorService
{
    public function validate(EmailValidationRecord $record): bool
    {
        return filter_var($record->email, FILTER_VALIDATE_EMAIL);
    }
}
```

### 11.3 Service technique (cache)

```php
<?php

declare(strict_types=1);

namespace App\Services\Cache;

final class RedisCacheService
{
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        Redis::setex($key, $ttl, serialize($value));
    }
    
    public function get(string $key): mixed
    {
        $value = Redis::get($key);
        return $value ? unserialize($value) : null;
    }
    
    public function delete(string $key): void
    {
        Redis::del($key);
    }
    
    public function has(string $key): bool
    {
        return Redis::exists($key);
    }
}
```

### 11.4 Service technique (email)

```php
<?php

declare(strict_types=1);

namespace App\Services\Email;

final class NotificationEmailService
{
    public function send(string $to, string $subject, string $body): void
    {
        Mail::to($to)->send(new GenericEmail($subject, $body));
    }
}
```

### 11.5 Service qui appelle d'autres Services

```php
<?php

declare(strict_types=1);

namespace App\Services\Calculator;

use App\Records\OrderRecord;

final class OrderTotalCalculatorService
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculator,
        private readonly TaxCalculatorService $taxCalculator,
        private readonly DiscountCalculatorService $discountCalculator,
    ) {}
    
    public function calculate(OrderRecord $record): float
    {
        $subtotal = $this->priceCalculator->calculate($record);
        $tax = $this->taxCalculator->calculate($subtotal, $record->country);
        $discount = $this->discountCalculator->calculate($subtotal, $record->promoCode);
        
        return $subtotal + $tax - $discount;
    }
}
```

---

## 12. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Action}{Entity}Service` |
| **Type** | Pur (calcul) ou technique (cache, email, log) |
| **Effets de bord** | ✅ Explicites (nom de méthode doit le suggérer) |
| **Effets de bord cachés** | ❌ Interdit (dans `calculate`, `get`, `validate`) |
| **Entrée** | N'importe quel type |
| **Sortie** | Valeur (scalaire, Record, array) ou `void` (si effet de bord explicite) |
| **Méthode `void`** | ✅ Autorisée uniquement si nom suggère l'effet de bord (`send`, `save`, `delete`) |
| **Transaction DB** | ❌ Non (c'est le rôle des Actions) |
| **SRP** | ✅ Une seule responsabilité |

---

## 13. Checklist d'acceptance

- [ ] Le nom se termine par `Service`
- [ ] Le nom de la méthode suggère clairement son comportement
- [ ] Si la méthode a un effet de bord, son nom le suggère (`set`, `send`, `save`, `delete`)
- [ ] Si la méthode s'appelle `calculate`, `get`, `validate`, `transform`, elle n'a PAS d'effet de bord
- [ ] Les méthodes `void` ont un nom qui suggère l'effet de bord
- [ ] Aucun effet de bord caché (log, cache, écriture DB) dans une méthode qui ne le suggère pas
- [ ] Le Service a une seule responsabilité
- [ ] Le Service ne fait pas de transactions DB (c'est le rôle des Actions)

---

## 14. Règle d'or

> **Une méthode qui s'appelle `calculate` doit uniquement calculer. Une méthode qui s'appelle `set` peut écrire. Le nom est le contrat.**

```php
// Le Service idéal
final class IdealService
{
    // Les noms disent ce qu'ils font
    public function calculate(InputRecord $input): float { ... }  // ← pur
    public function get(string $key): mixed { ... }               // ← lecture
    public function set(string $key, mixed $value): void { ... }  // ← écriture
    public function send(EmailRecord $email): void { ... }        // ← effet de bord
    public function delete(string $key): void { ... }             // ← effet de bord
}
```

---

## 15. Service vs Action : le tableau final

| Critère | **Service** | **Action** |
|---------|-------------|------------|
| **Rôle** | Logique métier ou accès technique | Opération métier complète |
| **Effets de bord** | ✅ Explicites (si nom le suggère) | ✅ Création, modification, email, event |
| **Transaction DB** | ❌ Non | ✅ Oui |
| **Retour** | Valeur ou `void` (si effet de bord explicite) | `void` |
| **Nom des méthodes** | `calculate`, `get`, `set`, `send`, `delete` | `execute` |
| **Paramètre** | Un ou plusieurs paramètres | Un seul Record |
| **Exemple** | `PriceCalculatorService::calculate()` | `CreateOrderAction::execute()` |

```
┌─────────────────────────────────────────────────────────────────┐
│                         CONTROLLER                              │
│                                                                 │
│   1. Va chercher les données (DB, API)                          │
│   2. Appelle les Services (purs ou techniques)                  │
│   3. Décide des effets de bord (cache, log, email)              │
│   4. Appelle une Action si besoin (transaction, event)          │
│   5. Réponse HTTP                                               │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          SERVICE                                │
│                                                                 │
│   ✅ Service pur : calcule, valide, transforme (pas d'effet)    │
│   ✅ Service technique : set(), get(), send(), delete()         │
│                                                                 │
│   ❌ Effet de bord caché dans calculate() / get()               │
└─────────────────────────────────────────────────────────────────┘
