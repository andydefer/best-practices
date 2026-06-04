# Principe d'usage du Logger

## 1. Définition

**Logger** est un système de logs structurés en JSONL (JSON Lines). Il organise les logs par date et par heure, avec un format strictement typé et sécurisé.

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Contracts\LoggerInterface;

final class UserController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function login(): void
    {
        $payload = new StrictDataObject([
            'event' => 'user_login',
            'user_id' => 123,
            'ip_address' => '127.0.0.1',
            'is_success' => true,
        ]);

        $logData = new LogDataRecord(type: 'auth', payload: $payload);

        $this->logger->info($logData);
    }
}
```

**Résultat dans le fichier de log :**
```json
{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"auth","payload":{"event":"user_login","user_id":123,"ip_address":"127.0.0.1","is_success":true}}}
```

---

## 2. Pourquoi ne pas utiliser le système de logs natif de Laravel ?

### 2.1 Problèmes du système Laravel

| Problème | Explication | Conséquence |
|----------|-------------|-------------|
| **Format non structuré** | Les logs sont du texte libre | Impossible de parser ou filtrer efficacement |
| **Types non préservés** | `Log::info('message', ['user' => $user])` → `"Array"` | Perte d'information, données inexploitables |
| **Pas de requêtage** | On ne peut chercher que par texte | Impossible de filtrer par type d'événement ou par niveau |
| **Tests fragiles** | `assertStringContainsString('User 123', $log)` | Un simple changement de texte casse les tests |
| **Format non standard** | Format propriétaire Laravel | Difficile à intégrer avec des outils externes |

### 2.2 Comparaison concrète

```php
// ❌ Laravel natif - Perte d'information
Log::info("Utilisateur {$user->id} connecté", ['ip' => $ip]);
// Sortie: [2024-01-15 14:30:00] local.INFO: Utilisateur 123 connecté {"ip":"127.0.0.1"}

// ✅ Logger - Structure complète et typée
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $user->id,
    'ip_address' => $ip,
    'is_success' => true,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// Sortie: {"time":"2024-01-15T14:30:00Z","level":"info","data":{"type":"auth","payload":{"event":"user_login","user_id":123,"ip_address":"127.0.0.1","is_success":true}}}
```

---

## 3. Architecture

### 3.1 Structure des fichiers sur disque

```
LOGS/
└── 2026-04-05/
    ├── 00-01.jsonl    ← 00:00 - 01:00 UTC
    ├── 01-02.jsonl    ← 01:00 - 02:00 UTC
    ├── 02-03.jsonl    ← 02:00 - 03:00 UTC
    └── ...
        └── 23-00.jsonl    ← 23:00 - 00:00 UTC
```

### 3.2 Format du fichier JSONL

Chaque ligne = un événement JSON avec payload en `snake_case` :

```json
{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"user_login","payload":{"user_id":1,"ip_address":"127.0.0.1","user_agent":"Mozilla/5.0"}}}
{"time":"2026-04-05T11:26:00Z","level":"error","data":{"type":"payment_failed","payload":{"order_id":123,"amount":99.99,"reason":"insufficient_funds"}}}
```

**Structure obligatoire d'une ligne :**

| Champ | Type | Description |
|-------|------|-------------|
| `time` | `string` | Timestamp ISO 8601 UTC (automatique) |
| `level` | `string` | debug, info, warning, error |
| `data.type` | `string` | Type d'événement métier (snake_case) |
| `data.payload` | `object` | Données du log (snake_case) |

---

## 4. Installation

```bash
composer require andydefer/laravel-logger
```

Le package s'enregistre automatiquement via Laravel.

### Configuration (optionnel)

```env
LOGGER_PATH=/custom/log/path
LOGGER_RETENTION_DAYS=60
```

---

## 5. Types de payload

`StrictDataObject` préserve la casse des clés. Les clés doivent être en **snake_case**.

| Type | Exemple |
|------|---------|
| `int` | `'user_id' => 123` |
| `float` | `'amount' => 99.99` |
| `string` | `'ip_address' => '127.0.0.1'` |
| `bool` | `'is_success' => true` |
| `null` | `'optional' => null` |
| `array` | `'tags' => ['premium', 'vip']` |
| `AbstractRecord` | `'user' => $userRecord` (snake_case) |
| `TypedCollection` | `'items' => $collection` |

### Règle : Les clés du payload sont en `snake_case`

```php
// ✅ BON - snake_case
$payload = new StrictDataObject([
    'user_id' => 123,
    'user_name' => 'John Doe',
    'is_active' => true,
]);

// ❌ MAUVAIS - camelCase
$payload = new StrictDataObject([
    'userId' => 123,
    'userName' => 'John Doe',
    'isActive' => true,
]);
```

---

## 6. Les 4 niveaux de log

```php
$logger->debug($logData);   // DEBUG
$logger->info($logData);    // INFO
$logger->warning($logData); // WARNING
$logger->error($logData);   // ERROR
```

---

## 7. Travailler avec le payload

### Lire des propriétés

```php
$userId = $log->data->payload->user_id;      // Accès direct (snake_case)
$ip = $log->data->payload->ip_address;
$value = $log->data->payload->get('key');    // avec valeur par défaut
$hasKey = $log->data->payload->has('key');   // Vérifier existence
```

### Convertir en tableau

```php
$array = $log->data->payload->toArray();
// ['user_id' => 123, 'user_name' => 'John Doe', ...]
```

### Immuabilité - Créer une nouvelle version

```php
$newPayload = $payload->with('status', 'completed');  // Ajoute/modifie
$merged = $payload->merge(['new_key' => 'value']);    // Fusionne
$reduced = $payload->without('temp_key');              // Supprime
```

---

## 8. LogDataRecord

```php
namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,           // snake_case (ex: 'user_login')
        public readonly StrictDataObject $payload,
    ) {}
}
```

**Règle :** `$type` doit être en `snake_case`.

```php
// ✅ BON
$logData = new LogDataRecord(type: 'user_login', payload: $payload);
$logData = new LogDataRecord(type: 'payment_failed', payload: $payload);

// ❌ MAUVAIS
$logData = new LogDataRecord(type: 'userLogin', payload: $payload);
$logData = new LogDataRecord(type: 'UserLogin', payload: $payload);
```

---

## 9. LogQueryRecord

```php
namespace AndyDefer\Logger\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

final class LogQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?IsoZuluTime $from = null,
        public readonly ?IsoZuluTime $to = null,
        public readonly ?string $type = null,      // snake_case
        public readonly ?LogLevel $level = null,
    ) {}
}
```

### Requêter les logs

```php
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\ValueObjects\IsoZuluTime;

// Query par type d'événement
$query = new LogQueryRecord(
    from: new IsoZuluTime('2026-04-05T00:00:00Z'),
    to: new IsoZuluTime('2026-04-05T23:59:59Z'),
    type: 'user_login',  // snake_case
);

$results = $logger->query($query);

foreach ($results as $log) {
    echo $log->time->getValue() . "\n";
    echo $log->level->value . "\n";
    echo $log->data->type . "\n";
    echo $log->data->payload->user_id . "\n";
}
```

### Query par niveau

```php
use AndyDefer\Logger\Enums\LogLevel;

$query = new LogQueryRecord(
    from: new IsoZuluTime('2026-04-01T00:00:00Z'),
    to: new IsoZuluTime('2026-04-30T23:59:59Z'),
    level: LogLevel::ERROR,
);

$errors = $logger->query($query);
```

### Streaming (tous les logs d'un jour)

```php
// Jour spécifique
$logs = $logger->stream('2026-04-05');

// Aujourd'hui
$logs = $logger->stream();

foreach ($logs as $log) {
    // Traitement...
}
```

---

## 10. Buffer d'écriture (performance)

Le buffer regroupe les logs en mémoire avant de les écrire sur le disque.

```php
// Activer le buffer (100 logs avant écriture automatique)
$logger->enableBuffer(100);

// Ces logs restent en mémoire
for ($i = 0; $i < 50; $i++) {
    $logger->info($logData);
}

// Déclenche l'écriture automatique
$logger->info($logData);

// Ou vider manuellement
$logger->flush();

// Désactiver (vide automatiquement le buffer)
$logger->disableBuffer();

// Callback à chaque flush
$logger->onFlush(function ($count) {
    \Log::info("{$count} logs écrits");
});
```

---

## 11. LogLevel

```php
namespace AndyDefer\Logger\Enums;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function getLabel(): string;
    public function isDebug(): bool;
    public function isInfo(): bool;
    public function isWarning(): bool;
    public function isError(): bool;
}
```

**Utilisation :**

```php
$level = LogLevel::INFO;

$level->getLabel();   // 'Info'
$level->isInfo();     // true

// Toutes les valeurs
LogLevel::values();   // ['debug', 'info', 'warning', 'error']

// Depuis une valeur
LogLevel::fromValue('info'); // LogLevel::INFO
```

---

## 12. Commandes avec la directive

Le package intègre une directive pour nettoyer les vieux logs.

```bash
# Nettoyer les logs de plus de 30 jours (valeur par défaut)
./vendor/bin/directive logger-clean

# Nettoyer les logs de plus de 60 jours
./vendor/bin/directive logger-clean --days=60

# Simulation (ne supprime rien)
./vendor/bin/directive logger-clean --dry-run

# Mode verbeux (affiche les fichiers à supprimer)
./vendor/bin/directive logger-clean --verbose
```

---

## 13. Exemples concrets

### 13.1 Authentification

```php
// Connexion réussie (snake_case)
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $user->id,
    'email' => $user->email,
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
    'is_success' => true,
]);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));

// Échec de connexion
$payload = new StrictDataObject([
    'event' => 'user_login_failed',
    'email' => request()->email,
    'ip_address' => request()->ip(),
    'reason' => 'invalid_password',
]);

$logger->warning(new LogDataRecord(type: 'auth', payload: $payload));
```

### 13.2 Paiement

```php
// Paiement réussi
$payload = new StrictDataObject([
    'event' => 'payment_success',
    'order_id' => $order->id,
    'stripe_id' => $stripeId,
    'amount' => $order->total,
    'currency' => 'EUR',
]);

$logger->info(new LogDataRecord(type: 'payment', payload: $payload));

// Paiement échoué
$payload = new StrictDataObject([
    'event' => 'payment_failed',
    'order_id' => $order->id,
    'amount' => $order->total,
    'error_code' => $exception->getCode(),
    'error_message' => $exception->getMessage(),
]);

$logger->error(new LogDataRecord(type: 'payment', payload: $payload));
```

### 13.3 Log avec un Record (snake_case)

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_email,
        public readonly string $user_role,
    ) {}
}

$userRecord = new UserRecord(
    user_id: 1,
    user_email: 'john@example.com',
    user_role: 'admin',
);

$payload = new StrictDataObject([
    'event' => 'user_created',
    'user' => $userRecord,
    'created_by' => auth()->id(),
]);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

---

## 14. Tests unitaires

### 14.1 Mock du Logger

```php
use AndyDefer\Logger\Contracts\LoggerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class UserServiceTest extends TestCase
{
    public function test_login_logs_successful_authentication(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('info')
            ->with($this->callback(function ($logData) {
                return $logData->type === 'auth'
                    && $logData->payload->event === 'user_login'
                    && $logData->payload->user_id === 123
                    && $logData->payload->ip_address === '127.0.0.1';
            }));
        
        $service = new UserService($logger);
        $service->login(123, '127.0.0.1');
    }
}
```

### 14.2 Tester la structure, pas le texte

```php
// ✅ BON - Test robuste (structure)
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => $log->payload->user_id === 123));

// ❌ MAUVAIS - Test fragile (texte)
$logger->expects($this->once())
    ->method('info')
    ->with('User 123 logged in');
```

---

## 15. Bonnes pratiques

### 15.1 Première propriété = type d'événement

```php
// ✅ BON
$payload = new StrictDataObject([
    'event' => 'user_login',
    'user_id' => $userId,
    'ip_address' => $ip,
]);

// ❌ MAUVAIS
$payload = new StrictDataObject([
    'user_id' => $userId,
    'event' => 'user_login',
]);
```

### 15.2 snake_case pour tous les identifiants

```php
// ✅ BON
'user_id' => 123
'ip_address' => '127.0.0.1'
'is_success' => true
'created_at' => '2024-01-01'

// ❌ MAUVAIS
'userId' => 123
'ipAddress' => '127.0.0.1'
'isSuccess' => true
'createdAt' => '2024-01-01'
```

### 15.3 snake_case pour les types d'événements

```php
// ✅ BON
'type' => 'user_login'
'type' => 'payment_failed'
'type' => 'api_call'

// ❌ MAUVAIS
'type' => 'userLogin'
'type' => 'UserLogin'
```

### 15.4 Injection uniquement, pas de facade

```php
// ✅ BON - Injection explicite
final class MyService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}

// ❌ Éviter les facades
\Log::info(...);
```

---

## 16. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Payload** | `StrictDataObject` (préserve la casse) |
| **Clés du payload** | `snake_case` |
| **Type d'événement** | `snake_case` |
| **Record associé** | `snake_case` |
| **Injection** | ✅ Injection du `LoggerInterface` |
| **Appels statiques** | ❌ INTERDITS |
| **Timestamp** | ✅ Automatique |
| **Tests** | ✅ Tester la structure, pas le texte |
| **Buffer** | ✅ Optionnel pour performance |

---

## 17. Règle d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. Le timestamp est automatique. Les tests vérifient la STRUCTURE, pas le TEXTE.**
>
> **⚠️ Toutes les clés du payload sont en `snake_case`.**
> **⚠️ Le type d'événement est en `snake_case`.**
> **⚠️ Les Records utilisent `snake_case` pour leurs propriétés.**

```php
// ✅ Le log parfait
final class PerfectLoggingService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function logUserAction(int $userId, string $action, string $ip): void
    {
        $payload = new StrictDataObject([
            'event' => $action,           // snake_case
            'user_id' => $userId,         // snake_case
            'ip_address' => $ip,          // snake_case
            'timestamp' => now()->toIso8601ZuluString(),
        ]);

        $logData = new LogDataRecord(
            type: 'user_action',          // snake_case
            payload: $payload,
        );

        $this->logger->info($logData);
    }
}

// ✅ Le test parfait
final class PerfectLoggingServiceTest extends TestCase
{
    public function test_log_user_action(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        
        $logger->expects($this->once())
            ->method('info')
            ->with($this->callback(function ($logData) {
                return $logData->type === 'user_action'
                    && $logData->payload->event === 'login'
                    && $logData->payload->user_id === 123
                    && $logData->payload->ip_address === '127.0.0.1';
            }));
        
        $service = new PerfectLoggingService($logger);
        $service->logUserAction(123, 'login', '127.0.0.1');
    }
}
```

> **Rappel final : STRUCTURÉ + TYPÉ + REQUÊTABLE + TESTABLE + INJECTION + snake_case = MAINTENABILITÉ**
---