# Logger : Système de logs structurés

## 1. Définition

**Logger** est un système de logs structurés en JSONL (JSON Lines). Il organise les logs par date et par heure, avec un format strictement typé et sécurisé.

```php
use AndyDefer\BestPractices\Logger\Logger;
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Logger\Records\LogDataRecord;

// Log structuré avec payload typé
$payload = new MixedPayloadCollection();
$payload->add('user_login', 1, '127.0.0.1', true);

$logData = new LogDataRecord(type: 'user_login', payload: $payload);
$logger->info($logData);
```

---

## 2. Architecture

### 2.1 Structure des fichiers sur disque

```
LOGS/
└── 2026-04-05/
    ├── 00-01.jsonl    ← 00:00 - 01:00 UTC
    ├── 01-02.jsonl    ← 01:00 - 02:00 UTC
    ├── 02-03.jsonl    ← 02:00 - 03:00 UTC
    └── ...
        └── 23-00.jsonl    ← 23:00 - 00:00 UTC
```

**Pourquoi l'organisation par heure ?**

| Problème | Solution |
|----------|----------|
| Fichiers journaliers trop gros | Découpage horaire (max 1 heure par fichier) |
| Recherche lente | On ne parcourt que les heures pertinentes |
| Concurrence d'écriture | Plusieurs fichiers = moins de verrouillage |
| Archivage flexible | Nettoyage possible à granularité horaire |

### 2.2 Format du fichier JSONL

Chaque ligne = un événement JSON :

```json
{"time":"2026-04-05T10:26:00Z","level":"info","data":{"type":"user_login","payload":[1,"127.0.0.1","Mozilla/5.0"]}}
{"time":"2026-04-05T11:26:00Z","level":"error","data":{"type":"payment_failed","payload":[123,99.99,"insufficient_funds"]}}
```

**Structure obligatoire d'une ligne :**

```json
{
    "time": "2026-04-05T10:26:00Z",
    "level": "info",
    "data": {
        "type": "user_login",
        "payload": [1, "127.0.0.1", "Mozilla/5.0"]
    }
}
```

| Champ | Type | Description |
|-------|------|-------------|
| `time` | `string` | Timestamp ISO 8601 UTC (automatique) |
| `level` | `string` | debug, info, warning, error |
| `data.type` | `string` | Type d'événement métier |
| `data.payload` | `array` | Données du log |

---

## 3. Installation

```bash
composer require andydefer/best-practices
```

### 3.1 Publication de la configuration (optionnel)

```bash
php artisan vendor:publish --tag=logger-config
```

### 3.2 Variables d'environnement

```env
LOGGER_PATH=/custom/log/path
LOGGER_RETENTION_DAYS=60
```

---

## 4. Configuration

### 4.1 Value Object LoggerConfig

```php
use AndyDefer\BestPractices\Logger\Config\LoggerConfig;

// Configuration par défaut
$config = LoggerConfig::default();
// basePath = storage_path('logs/structured')
// retentionDays = 30

// Configuration personnalisée (chaînage)
$config = LoggerConfig::default()
    ->withBasePath('/custom/log/path')
    ->withRetentionDays(60);
```

### 4.2 Fichier de configuration (optionnel)

```php
// config/logger.php
return [
    'path' => env('LOGGER_PATH', storage_path('logs/structured')),
    'retention_days' => env('LOGGER_RETENTION_DAYS', 30),
];
```

### 4.3 Injection personnalisée

```php
final class CustomLogger extends Logger
{
    public function __construct()
    {
        $config = LoggerConfig::default()
            ->withBasePath(storage_path('custom/logs'))
            ->withRetentionDays(90);
        
        $pathService = new LogPathService($config);
        parent::__construct(
            new WriteLogTask($pathService),
            new QueryLogsTask($pathService),
            new StreamLogsTask($pathService),
        );
    }
}
```

---

## 5. Utilisation de base

### 5.1 Injection de dépendances

```php
use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;

final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}
```

### 5.2 Création d'un payload

```php
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;

$payload = new MixedPayloadCollection();

// Ajout simple
$payload->add('user_login');
$payload->add($userId);
$payload->add($ip);
$payload->add(true);

// Ajout multiple en une fois
$payload->add('user_login', $userId, $ip, true);

// Chaînage
$payload->add('user_login')->add($userId)->add($ip)->add(true);
```

### 5.3 Types acceptés dans le payload

| Type | Exemple |
|------|---------|
| `int` | `$payload->add(123)` |
| `float` | `$payload->add(99.99)` |
| `string` | `$payload->add('hello')` |
| `bool` | `$payload->add(true)` |
| `null` | `$payload->add(null)` |
| `AbstractRecord` | `$payload->add($userRecord)` |
| `TypedRecords` | `$payload->add($tags)` |
| `stdClass` | `$payload->add($obj)` |

### 5.4 Création d'un LogDataRecord

```php
use AndyDefer\BestPractices\Logger\Records\LogDataRecord;

$logData = new LogDataRecord(
    type: 'user_login',
    payload: $payload,
);
```

### 5.5 Écriture des logs

```php
// Les 4 niveaux de log (timestamp automatique)
$logger->debug($logData);
$logger->info($logData);
$logger->warning($logData);
$logger->error($logData);
```

### 5.6 Exemple complet

```php
$payload = new MixedPayloadCollection();
$payload->add('user_login', $user->id, request()->ip(), true);

$logData = new LogDataRecord(type: 'user_login', payload: $payload);

$logger->info($logData);
```

---

## 6. Types acceptés et refusés

### 6.1 Types ACCEPTÉS dans le payload

| Type | Exemple | Utilisation |
|------|---------|-------------|
| `int` | `$payload->add(123)` | Identifiants, compteurs |
| `float` | `$payload->add(99.99)` | Montants, prix |
| `string` | `$payload->add('hello')` | Messages, types, emails |
| `bool` | `$payload->add(true)` | Succès/échec, flags |
| `null` | `$payload->add(null)` | Valeurs optionnelles |
| `AbstractRecord` | `$payload->add($userRecord)` | Objets métier typés |
| `TypedRecords` | `$payload->add($tags)` | Collections imbriquées |
| `stdClass` | `$payload->add($obj)` | Désérialisation JSON |

### 6.2 Types REFUSÉS dans le payload

| Type | Pourquoi |
|------|----------|
| `array` | Non sérialisable proprement, utiliser `TypedRecords` |
| `DateTime` | Objet arbitraire non autorisé |
| Toute autre classe | Seuls `AbstractRecord`, `TypedRecords` et `stdClass` sont autorisés |
| `Enum` | Utiliser la valeur scalaire (`$enum->value`) |

### 6.3 Message d'erreur explicite

```php
// Tentative d'ajout d'un objet non autorisé
$payload->add(new DateTime());

// Exception: Object of type "DateTime" is not allowed in TypedRecords. 
// Only stdClass, AbstractRecord, and TypedRecords are allowed.
```

---

## 7. MixedPayloadCollection - Méthodes détaillées

### 7.1 Méthodes de base

```php
// add() - Ajoute un ou plusieurs éléments (chaînable)
$collection->add('hello');
$collection->add('a', 'b', 'c');
$collection->add(1, 2, 3)->add('end');

// count() - Nombre d'éléments
$count = $collection->count();

// isEmpty() / isNotEmpty()
if ($collection->isEmpty()) { ... }
if ($collection->isNotEmpty()) { ... }

// getAllowedTypes() - Types autorisés
$types = $collection->getAllowedTypes(); // ['int', 'string', ...]
```

### 7.2 Lecture des éléments

```php
// toArray() - Convertit en tableau PHP
$array = $collection->toArray();

// all() - Retourne une nouvelle collection identique
$newCollection = $collection->all();

// firstItem() - Premier élément (ou null)
$first = $collection->firstItem();

// first() - Nouvelle collection avec les n premiers éléments
$firstTwo = $collection->first(2);

// lastItem() - Dernier élément (ou null)
$last = $collection->lastItem();

// last() - Nouvelle collection avec les n derniers éléments
$lastTwo = $collection->last(2);
```

### 7.3 Transformation

```php
// map() - Transforme chaque élément
$doubles = $collection->map(fn($item) => $item * 2);

// filter() - Filtre les éléments
$filtered = $collection->filter(fn($item) => $item > 3);

// reject() - Inverse du filter
$rejected = $collection->reject(fn($item) => $item > 3);

// each() - Exécute une action sur chaque élément
$collection->each(fn($item) => $sum += $item);
```

### 7.4 Ordre et tri

```php
// sort() - Trie les éléments
$sorted = $collection->sort();

// sortBy() - Trie par propriété ou callback
$sorted = $collection->sortBy('price');
$sorted = $collection->sortBy(fn($item) => $item->price, descending: true);

// reverse() - Inverse l'ordre
$reversed = $collection->reverse();

// shuffle() - Mélange aléatoirement
$shuffled = $collection->shuffle();
```

### 7.5 Calculs

```php
// sum() - Somme des éléments
$total = $collection->sum();
$total = $collection->sum(fn($item) => $item->price);

// avg() - Moyenne
$average = $collection->avg();

// max() / min()
$max = $collection->max();
$min = $collection->min();
```

### 7.6 Filtrage par type

```php
// ofType() - Éléments d'un type spécifique
$strings = $collection->ofType('string');
$records = $collection->ofType(UserRecord::class);
$objects = $collection->ofType(stdClass::class);

// exceptType() - Exclut un type
$withoutInts = $collection->exceptType('int');

// records() - Uniquement les AbstractRecord
$onlyRecords = $collection->records();

// scalars() - Uniquement les scalaires (int, float, string, bool, null)
$onlyScalars = $collection->scalars();

// ofRecord() - Un type de Record spécifique
$users = $collection->ofRecord(UserRecord::class);

// anyRecord() - Tous les Records (alias)
$allRecords = $collection->anyRecord();

// getTypes() - Types distincts présents
$types = $collection->getTypes();
```

### 7.7 Recherche

```php
// where() - Filtrer par propriété (pour objets)
$active = $collection->where('status', 'active');

// whereNotNull() - Propriété non nulle
$withPrice = $collection->whereNotNull('price');

// whereNull() - Propriété nulle
$withoutPrice = $collection->whereNull('price');

// contains() - Vérifie l'existence
if ($collection->contains('banana')) { ... }

// containsType() - Vérifie la présence d'un type
if ($collection->containsType('int')) { ... }

// isOnlyType() - Vérifie que tous les éléments sont d'un type
if ($collection->isOnlyType('int')) { ... }
```

### 7.8 Slicing et pagination

```php
// take() - n premiers éléments
$firstThree = $collection->take(3);

// skip() - Ignore les n premiers
$afterFirstTwo = $collection->skip(2);

// slice() - Extrait une plage
$middle = $collection->slice(2, 3);

// nth() - Un élément sur n
$everyOther = $collection->nth(2);
$offsetOne = $collection->nth(2, 1);

// values() - Réindexe les clés
$reindexed = $collection->values();
```

### 7.9 Manipulation avancée

```php
// unique() - Supprime les doublons
$unique = $collection->unique();
$uniqueByPrice = $products->unique(fn($item) => $item->price);

// merge() - Fusionne deux collections
$merged = $collection1->merge($collection2);

// intersect() - Éléments communs
$common = $collection1->intersect($collection2);

// diff() - Éléments uniques
$unique = $collection1->diff($collection2);

// flatMap() - Aplatit les collections imbriquées
$flattened = $nested->flatMap(fn($item) => $item);

// filterNull() - Supprime les null
$withoutNull = $collection->filterNull();

// random() - Éléments aléatoires
$randomItems = $collection->random(3);
```

### 7.10 Validation et assertions

```php
// isHomogeneous() - Tous du même type ?
if ($collection->isHomogeneous()) { ... }

// isHeterogeneous() - Types différents ?
if ($collection->isHeterogeneous()) { ... }

// assertAllOfType() - Vérifie et retourne la collection ou exception
$collection->assertAllOfType('int');

// assertNotEmpty() - Vérifie non vide
$collection->assertNotEmpty();

// assertContainsType() - Vérifie présence d'un type
$collection->assertContainsType('int');

// assertAllImplement() - Vérifie l'implémentation d'interface
$collection->assertAllImplement(AbstractRecord::class);

// assertScalar() - Vérifie que tous sont scalaires
$collection->assertScalar();

// assertRecords() - Vérifie que tous sont des Records
$collection->assertRecords();

// validate() - Validation personnalisée
$collection->validate(fn($item, $index) => $item > 0);
```

---

## 8. LogDataRecord

```php
namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,
        public readonly MixedPayloadCollection $payload,
    ) {}
}
```

---

## 9. LogRecord

```php
namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Records\Recordable;

final class LogRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $time,
        public readonly LogLevel $level,
        public readonly Recordable $data,
    ) {}
}
```

---

## 10. LogQueryRecord

```php
namespace AndyDefer\BestPractices\Logger\Records;

use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class LogQueryRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?string $type = null,
        public readonly ?LogLevel $level = null,
    ) {}
}
```

| Paramètre | Type | Description |
|-----------|------|-------------|
| `from` | `?string` | Date de début (ISO 8601) |
| `to` | `?string` | Date de fin (ISO 8601) |
| `type` | `?string` | Type d'événement (ex: 'user_login') |
| `level` | `?LogLevel` | Niveau de log (DEBUG, INFO, WARNING, ERROR) |

---

## 11. LogLevel

```php
namespace AndyDefer\BestPractices\Logger\Enums;

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

---

## 12. Recherche et requêtage

### 12.1 Query par type d'événement

```php
$query = new LogQueryRecord(
    from: '2026-04-05T00:00:00Z',
    to: '2026-04-05T23:59:59Z',
    type: 'user_login',
);

$results = $logger->query($query);

foreach ($results as $log) {
    echo $log->time . "\n";
    echo $log->level->value . "\n";
    echo $log->data->type . "\n";
    
    foreach ($log->data->payload as $item) {
        echo $item . "\n";
    }
}
```

### 12.2 Query par niveau de log

```php
$query = new LogQueryRecord(
    from: now()->subDays(7)->toIso8601ZuluString(),
    level: LogLevel::ERROR,
);

$errors = $logger->query($query);
```

### 12.3 Query combinée

```php
$query = new LogQueryRecord(
    from: now()->subDay()->toIso8601ZuluString(),
    type: 'payment_failed',
    level: LogLevel::ERROR,
);

$failedPayments = $logger->query($query);
```

### 12.4 Streaming

```php
// Tous les logs d'une date
$logs = $logger->stream('2026-04-05');

// Tous les logs du jour
$logs = $logger->stream();

foreach ($logs as $log) {
    echo $log->time . ' - ' . $log->level->value . ' - ' . $log->data->type . "\n";
    
    foreach ($log->data->payload as $item) {
        echo '  - ' . $item . "\n";
    }
}
```

---

## 13. Cas d'usage métier

### 13.1 Log d'authentification

```php
// Connexion réussie
$payload = new MixedPayloadCollection();
$payload->add('user_login', $user->id, $user->email, request()->ip(), request()->userAgent(), true);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));

// Échec de connexion
$payload = new MixedPayloadCollection();
$payload->add('user_login_failed', $credentials['email'], request()->ip(), false, 'invalid_password');

$logger->warning(new LogDataRecord(type: 'auth', payload: $payload));
```

### 13.2 Log de paiement

```php
// Paiement initié
$payload = new MixedPayloadCollection();
$payload->add('payment_initiated', $order->id, $order->total, 'EUR', 'stripe');

$logger->info(new LogDataRecord(type: 'payment', payload: $payload));

// Paiement réussi
$payload = new MixedPayloadCollection();
$payload->add('payment_success', $order->id, $stripeId, $order->total);

$logger->info(new LogDataRecord(type: 'payment', payload: $payload));

// Paiement échoué
$payload = new MixedPayloadCollection();
$payload->add('payment_failed', $order->id, $exception->getCode(), $exception->getMessage());

$logger->error(new LogDataRecord(type: 'payment', payload: $payload));
```

### 13.3 Log avec Record métier

```php
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\Fixtures\Records\TestUserRecord;

// Utilisation du Record de fixture existant
$userRecord = new TestUserRecord(
    name: 'John Doe',
    email: 'john@example.com',
);

$payload = new MixedPayloadCollection();
$payload->add('user_created', $userRecord, true);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

### 13.4 Log avec collection imbriquée

```php
$tags = new TypedRecords('string');
$tags->add('premium', 'vip', 'active');

$payload = new MixedPayloadCollection();
$payload->add('user_tags', $tags, $userId);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

### 13.5 Log d'erreur système

```php
try {
    // Opération risquée
} catch (DatabaseException $e) {
    $payload = new MixedPayloadCollection();
    $payload->add('database_error', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getQuery() ?? 'unknown');
    
    $logger->error(new LogDataRecord(type: 'system', payload: $payload));
    
    throw $e;
}
```

### 13.6 Log d'API externe

```php
// Appel API sortant
$payload = new MixedPayloadCollection();
$payload->add('api_call', 'stripe', '/v1/customers', 'POST', json_encode($requestData));

$logger->info(new LogDataRecord(type: 'api', payload: $payload));

// Réponse API
$payload = new MixedPayloadCollection();
$payload->add('api_response', 'stripe', 200, $duration, json_encode($responseData));

$logger->info(new LogDataRecord(type: 'api', payload: $payload));
```

---

## 14. Tests

### 14.1 Mock du Logger pour les tests unitaires

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\User;

use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Logger\Records\LogDataRecord;
use App\Services\User\UserService;
use Tests\TestCase;
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
                return $logData instanceof LogDataRecord
                    && $logData->type === 'user_login'
                    && $logData->payload->contains(1);
            }));
        
        $service = new UserService($logger);
        $service->login(new LoginUserRecord(userId: 1, ip: '127.0.0.1'));
    }
}
```
---

## 15. Helpers autorisés (constantes uniquement)

⚠️ **Les helpers sont autorisés UNIQUEMENT pour retourner des valeurs scalaires immuables**

```php
// src/Logger/helpers.php
use AndyDefer\BestPractices\Logger\Config\LoggerConfig;

if (!function_exists('logger_default_path')) {
    function logger_default_path(): string
    {
        return LoggerConfig::default()->basePath;
    }
}

if (!function_exists('logger_retention_days')) {
    function logger_retention_days(): int
    {
        return LoggerConfig::default()->retentionDays;
    }
}
```

**Enregistrement dans `composer.json` :**

```json
{
    "autoload": {
        "psr-4": {
            "AndyDefer\\BestPractices\\": "src/"
        },
        "files": [
            "src/Logger/helpers.php"
        ]
    }
}
```

---

## 16. Gestion des erreurs

### 16.1 Exceptions

| Exception | Condition |
|-----------|-----------|
| `RuntimeException` | Impossible de créer le dossier ou d'écrire dans le fichier |
| `InvalidArgumentException` | Type non autorisé dans le payload |

### 16.2 Exemple de gestion

```php
use RuntimeException;
use InvalidArgumentException;

try {
    $payload = new MixedPayloadCollection();
    $payload->add('user_login', $userId, $ip);
    
    $logData = new LogDataRecord(type: 'user_login', payload: $payload);
    $logger->info($logData);
    
} catch (InvalidArgumentException $e) {
    // Le payload contient un type non autorisé
    error_log('Invalid log payload: ' . $e->getMessage());
    
} catch (RuntimeException $e) {
    // Le logger a échoué (disque plein, permissions)
    error_log('Logger failed: ' . $e->getMessage());
}
```

---

## 17. Enregistrement dans Laravel

### 17.1 Via le package principal

Le Service Provider du Logger est automatiquement enregistré par `BestPracticesServiceProvider`.

### 17.2 Enregistrement manuel (optionnel)

```php
// config/app.php
'providers' => [
    // ...
    AndyDefer\BestPractices\Logger\Providers\LoggerServiceProvider::class,
],
```

---

## 18. Bonnes pratiques

### 18.1 Structure du payload

```php
// ✅ Premier élément = type d'événement
$payload->add('user_login', $userId, $ip, $success);

// ❌ Ordre incohérent
$payload->add($userId, 'user_login', $ip);
```

### 18.2 Noms de type cohérents

```php
// ✅ snake_case
'type' => 'user_login'
'type' => 'payment_failed'

// ❌ Formats inconsistants
'type' => 'userLogin'
'type' => 'UserLogin'
```

### 18.3 Versionnement par position

```php
// Version 1 : 3 éléments
$payload->add('user_login', $userId, $ip);

// Version 2 : 4 éléments (user_agent en position 4)
$payload->add('user_login', $userId, $ip, $userAgent);
```

### 18.4 Chaînage

```php
// ✅ Chaînage fluide
$payload->add('user_login')->add($userId)->add($ip)->add($success);

// ✅ Multiple en une fois
$payload->add('user_login', $userId, $ip, $success);
```

### 18.5 Injection de dépendance uniquement

```php
// ✅ Injection explicite
final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}

// ❌ Pas de helper
logger()->info(...);
```

### 18.6 Timestamp automatique

```php
// ✅ Le timestamp est automatique
$logger->info($logData);

// ❌ Pas besoin de passer le timestamp manuellement
$logger->info(now()->toIso8601ZuluString(), $logData);
```

---

## 19. Règle d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. Le payload ne contient que des scalaires, des Records, des TypedRecords ou des stdClass. Le timestamp est automatique.**

```php
// ✅ Le log parfait
$payload = new MixedPayloadCollection();
$payload->add('user_login', $userId, $ip, $success);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
```

> **Rappel final : STRUCTURÉ + SÉCURISÉ + REQUÊTABLE + TESTABLE + INJECTION = MAINTENABILITÉ**
