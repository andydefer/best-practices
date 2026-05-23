# Logger : Système de logs structurés

## 1. Définition

**Logger** est un système de logs structurés en JSONL (JSON Lines). Il organise les logs par date et par heure, avec un format strictement typé et sécurisé.

```php
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Records\LogDataRecord;

// Log structuré avec payload typé
$payload = new MixedPayloadCollection();
$payload->add('user_login', 1, '127.0.0.1', true);

$logData = new LogDataRecord(type: 'user_login', payload: $payload);
$logger->info($logData);
```

---

## 2. Pourquoi ne pas utiliser le système de logs natif de Laravel ?

### 2.1 Problèmes du système Laravel

| Problème | Explication | Conséquence |
|----------|-------------|-------------|
| **Héritage UNIX dépassé** | Le système de logs PHP/Laravel repose sur les formats UNIX des années 1980 (syslog) | Format non structuré, difficile à parser |
| **Absence de format standard** | Chaque entrée est une ligne de texte libre | Impossible de requêter ou filtrer efficacement |
| **Types non préservés** | Les tableaux, objets et types complexes sont perdus | `['user' => ['id' => 1]]` devient `"Array"` |
| **Context non typé** | `Log::info('message', ['user' => $user])` | Aucune validation, erreurs silencieuses |
| **Pas de séparation sémantique** | Message et contexte mélangés | `"User 123 logged in from 127.0.0.1"` = impossible à parser |

### 2.2 Comparaison concrète

```php
// Laravel native - Message libre, contexte non typé
Log::info("User {$userId} logged in from {$ip}");
// Sortie: [2024-01-15 14:30:00] local.INFO: User 123 logged in from 127.0.0.1

// Notre Logger - Structure typée et requêtable
$payload = new MixedPayloadCollection();
$payload->add('user_login', $userId, $ip, true);
$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
// Sortie: {"time":"2024-01-15T14:30:00Z","level":"info","data":{"type":"auth","payload":["user_login",123,"127.0.0.1",true]}}
```

### 2.3 Problème critique pour les tests

| Problème | Laravel native | Notre Logger |
|----------|----------------|--------------|
| **Assertion sur le message** | `$this->assertStringContainsString('User 123', $log)` | Fragile, dépend du texte exact |
| **Assertion sur le contexte** | Impossible de tester le contexte typé | `$this->assertTrue($log->data->payload->contains(123))` |
| **Mock du logger** | `Log::shouldReceive('info')->with('message')` | Mock du message exact, casse à la moindre modification |
| **Vérification des types** | Impossible (tout devient string) | `$this->assertInstanceOf(MixedPayloadCollection::class, $log->data->payload)` |
| **Fiabilité des tests** | Faible (refactoring casse les tests) | Élevée (structure stable et typée) |

**Exemple de test fragile avec Laravel native :**
```php
// ❌ Test qui casse si on change la formulation
Log::shouldReceive('info')->with('User 123 logged in from 127.0.0.1');

// ❌ Impossible de tester la structure des données
Log::shouldReceive('info')->with(Argument::that(function ($message) {
    return str_contains($message, '123') && str_contains($message, '127.0.0.1');
}));
```

**Exemple de test robuste avec notre Logger :**
```php
// ✅ Test qui ne casse pas - structure indépendante du texte
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(function ($logData) {
        return $logData->type === 'auth'
            && $logData->payload->contains(123)
            && $logData->payload->contains('127.0.0.1');
    }));
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

**Pourquoi l'organisation par heure ?**

| Problème | Solution |
|----------|----------|
| Fichiers journaliers trop gros | Découpage horaire (max 1 heure par fichier) |
| Recherche lente | On ne parcourt que les heures pertinentes |
| Concurrence d'écriture | Plusieurs fichiers = moins de verrouillage |
| Archivage flexible | Nettoyage possible à granularité horaire |

### 3.2 Format du fichier JSONL

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
| `data.payload` | `array` | Données du log (typées à la source) |

**Avantages du format JSONL :**
- Chaque ligne est indépendante (pas besoin de parser tout le fichier)
- Streaming possible (lecture ligne par ligne)
- Compatible avec tous les outils de traitement de logs (ELK, Loki, Datadog)
- Append-only (pas de réécriture, idéal pour les logs)

---

## 4. Installation

```bash
composer require andydefer/laravel-logger
```

### 4.1 Prérequis

Ce package dépend de `andydefer/php-records` pour les structures typées.

### 4.2 Variables d'environnement (optionnel)

```env
LOGGER_PATH=/custom/log/path
LOGGER_RETENTION_DAYS=60
```

---

## 5. Configuration

### 5.1 Value Object LoggerConfig

```php
use AndyDefer\Logger\Config\LoggerConfig;

// Configuration par défaut
$config = LoggerConfig::default();
// basePath = storage_path('logs/structured')
// retentionDays = 30

// Configuration personnalisée (chaînage immuable)
$config = LoggerConfig::default()
    ->withBasePath('/custom/log/path')
    ->withRetentionDays(60);
```

### 5.2 Configuration via Laravel (optionnel)

```php
// config/logger.php (à créer manuellement)
return [
    'path' => env('LOGGER_PATH', storage_path('logs/structured')),
    'retention_days' => env('LOGGER_RETENTION_DAYS', 30),
];
```

### 5.3 Injection personnalisée

```php
use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Tasks\WriteLogTask;
use AndyDefer\Logger\Tasks\QueryLogsTask;
use AndyDefer\Logger\Tasks\StreamLogsTask;

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

## 6. Utilisation de base

### 6.1 Injection de dépendances

```php
use AndyDefer\Logger\Contracts\LoggerInterface;

final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}
```

### 6.2 Création d'un payload

```php
use AndyDefer\Logger\Collections\MixedPayloadCollection;

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

### 6.3 Types acceptés dans le payload

| Type | Exemple |
|------|---------|
| `int` | `$payload->add(123)` |
| `float` | `$payload->add(99.99)` |
| `string` | `$payload->add('hello')` |
| `bool` | `$payload->add(true)` |
| `null` | `$payload->add(null)` |
| `AbstractRecord` | `$payload->add($userRecord)` |
| `TypedCollection` | `$payload->add($tags)` |
| `stdClass` | `$payload->add($obj)` |

### 6.4 Création d'un LogDataRecord

```php
use AndyDefer\Logger\Records\LogDataRecord;

$logData = new LogDataRecord(
    type: 'user_login',
    payload: $payload,
);
```

### 6.5 Écriture des logs

```php
// Les 4 niveaux de log (timestamp automatique)
$logger->debug($logData);
$logger->info($logData);
$logger->warning($logData);
$logger->error($logData);
```

### 6.6 Exemple complet

```php
$payload = new MixedPayloadCollection();
$payload->add('user_login', $user->id, request()->ip(), true);

$logData = new LogDataRecord(type: 'auth', payload: $payload);

$logger->info($logData);
```

---

## 7. Types acceptés et refusés

### 7.1 Types ACCEPTÉS dans le payload

| Type | Exemple | Utilisation |
|------|---------|-------------|
| `int` | `$payload->add(123)` | Identifiants, compteurs |
| `float` | `$payload->add(99.99)` | Montants, prix |
| `string` | `$payload->add('hello')` | Messages, types, emails |
| `bool` | `$payload->add(true)` | Succès/échec, flags |
| `null` | `$payload->add(null)` | Valeurs optionnelles |
| `AbstractRecord` | `$payload->add($userRecord)` | Objets métier typés |
| `TypedCollection` | `$payload->add($tags)` | Collections imbriquées |
| `stdClass` | `$payload->add($obj)` | Désérialisation JSON |

### 7.2 Types REFUSÉS dans le payload

| Type | Pourquoi |
|------|----------|
| `array` | Non sérialisable proprement, utiliser `TypedCollection` |
| `DateTime` | Objet arbitraire non autorisé |
| Toute autre classe | Seuls `AbstractRecord`, `TypedCollection` et `stdClass` sont autorisés |
| `Enum` | Utiliser la valeur scalaire (`$enum->value`) |

### 7.3 Message d'erreur explicite

```php
// Tentative d'ajout d'un objet non autorisé
$payload->add(new DateTime());

// Exception: Object of type "DateTime" is not allowed in TypedCollection. 
// Only stdClass, AbstractRecord, and TypedCollection are allowed.
```

---

## 8. MixedPayloadCollection - Méthodes détaillées

### 8.1 Méthodes de base

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

### 8.2 Lecture des éléments

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

### 8.3 Transformation

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

### 8.4 Ordre et tri

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

### 8.5 Calculs

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

### 8.6 Filtrage par type

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

### 8.7 Recherche

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

### 8.8 Slicing et pagination

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

### 8.9 Manipulation avancée

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

### 8.10 Validation et assertions

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

## 9. LogDataRecord

```php
namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Records\AbstractRecord;

final class LogDataRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $type,
        public readonly MixedPayloadCollection $payload,
    ) {}
}
```

---

## 10. LogRecord

```php
namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Records\AbstractRecord;
use AndyDefer\Records\Recordable;

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

## 11. LogQueryRecord

```php
namespace AndyDefer\Logger\Records;

use AndyDefer\Logger\Enums\LogLevel;
use AndyDefer\Records\AbstractRecord;

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

## 12. LogLevel

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

---

## 13. Recherche et requêtage

### 13.1 Query par type d'événement

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

### 13.2 Query par niveau de log

```php
$query = new LogQueryRecord(
    from: now()->subDays(7)->toIso8601ZuluString(),
    level: LogLevel::ERROR,
);

$errors = $logger->query($query);
```

### 13.3 Query combinée

```php
$query = new LogQueryRecord(
    from: now()->subDay()->toIso8601ZuluString(),
    type: 'payment_failed',
    level: LogLevel::ERROR,
);

$failedPayments = $logger->query($query);
```

### 13.4 Streaming avec StreamLogsTask

```php
use AndyDefer\Logger\Services\LogPathService;
use AndyDefer\Logger\Tasks\StreamLogsTask;

$pathService = new LogPathService(LoggerConfig::default());
$streamTask = new StreamLogsTask($pathService);

// Tous les logs d'une date spécifique
$logs = $streamTask->execute('2026-04-05');

// Tous les logs du jour
$logs = $streamTask->execute();

foreach ($logs as $log) {
    echo $log->time . ' - ' . $log->level->value . ' - ' . $log->data->type . "\n";
    
    foreach ($log->data->payload as $item) {
        echo '  - ' . $item . "\n";
    }
}
```

---

## 14. Cas d'usage métier

### 14.1 Log d'authentification

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

### 14.2 Log de paiement

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

### 14.3 Log avec Record métier

```php
use AndyDefer\Records\AbstractRecord;

final class UserContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $role,
    ) {}
}

$userRecord = new UserContextRecord(
    id: $user->id,
    email: $user->email,
    role: $user->role,
);

$payload = new MixedPayloadCollection();
$payload->add('user_created', $userRecord, true);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

### 14.4 Log avec collection imbriquée

```php
use AndyDefer\Records\Collections\TypedCollection;

$tags = new TypedCollection('string');
$tags->add('premium', 'vip', 'active');

$payload = new MixedPayloadCollection();
$payload->add('user_tags', $tags, $userId);

$logger->info(new LogDataRecord(type: 'user', payload: $payload));
```

### 14.5 Log d'erreur système

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

### 14.6 Log d'API externe

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

## 15. Tests

### 15.1 Pourquoi les tests sont plus fiables avec notre Logger

| Aspect | Laravel native | Notre Logger |
|--------|----------------|--------------|
| **Assertion sur le contenu** | `assertStringContainsString('User 123', $log)` | `assertTrue($log->data->payload->contains(123))` |
| **Assertion sur les types** | Impossible | `assertInstanceOf(MixedPayloadCollection::class, $log->data->payload)` |
| **Mock du logger** | Mock du message textuel fragile | Mock de l'objet typé stable |
| **Refactoring** | Changer la formulation casse les tests | Changer la formulation n'affecte pas les tests |
| **Validation des données** | Impossible | `$log->data->payload->assertAllOfType('int')` |

### 15.2 Mock du Logger pour les tests unitaires

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\User;

use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Records\LogDataRecord;
use App\Services\User\UserService;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
final class UserServiceTest extends TestCase
{
    public function test_login_logs_successful_authentication(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        
        // Assert & Expect
        $logger->expects($this->once())
            ->method('info')
            ->with($this->callback(function ($logData) {
                return $logData instanceof LogDataRecord
                    && $logData->type === 'auth'
                    && $logData->payload->contains('user_login')
                    && $logData->payload->contains(123)
                    && $logData->payload->contains('127.0.0.1');
            }));
        
        // Act
        $service = new UserService($logger);
        $service->login(123, '127.0.0.1');
    }
    
    public function test_login_logs_failed_authentication_with_warning_level(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        
        // Assert & Expect - Vérification du niveau de log
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->callback(function ($logData) {
                return $logData->type === 'auth'
                    && $logData->payload->contains('user_login_failed')
                    && $logData->payload->contains('wrong_password');
            }));
        
        // Act
        $service = new UserService($logger);
        $service->loginFailed('user@example.com', 'wrong_password');
    }
    
    public function test_log_payload_maintains_type_safety(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        
        // Assert & Expect - Vérification des types dans le payload
        $logger->expects($this->once())
            ->method('info')
            ->with($this->callback(function ($logData) {
                // Vérification que le payload a le bon type
                return $logData->payload instanceof MixedPayloadCollection
                    && $logData->payload->count() === 4
                    && $logData->payload->isAllScalars(); // Tous les éléments sont scalaires
            }));
        
        // Act
        $service = new UserService($logger);
        $service->logUserAction(123, 'login', '127.0.0.1', true);
    }
}
```

### 15.3 Test d'intégration avec fichier réel

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Logger;

use AndyDefer\Logger\Logger;
use AndyDefer\Logger\Config\LoggerConfig;
use AndyDefer\Logger\Collections\MixedPayloadCollection;
use AndyDefer\Logger\Records\LogDataRecord;
use AndyDefer\Logger\Records\LogQueryRecord;
use AndyDefer\Logger\Enums\LogLevel;
use Orchestra\Testbench\TestCase;

final class LoggerIntegrationTest extends TestCase
{
    private string $testLogPath;
    private Logger $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer un chemin de test temporaire
        $this->testLogPath = sys_get_temp_dir() . '/logger_test_' . uniqid();
        
        $config = LoggerConfig::default()
            ->withBasePath($this->testLogPath)
            ->withRetentionDays(1);
        
        $this->logger = new Logger($config);
    }
    
    protected function tearDown(): void
    {
        // Nettoyage: Supprimer les fichiers de test
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        
        parent::tearDown();
    }
    
    public function test_log_is_written_and_readable(): void
    {
        // Arrange: Créer un log
        $payload = new MixedPayloadCollection();
        $payload->add('test_event', 42, 'hello');
        $logData = new LogDataRecord(type: 'test', payload: $payload);
        
        // Act: Écrire le log
        $this->logger->info($logData);
        
        // Assert: Lire et vérifier le log
        $query = new LogQueryRecord(
            type: 'test',
            level: LogLevel::INFO,
        );
        
        $results = $this->logger->query($query);
        
        $this->assertCount(1, $results);
        $this->assertSame('test', $results[0]->data->type);
        $this->assertTrue($results[0]->data->payload->contains(42));
        $this->assertTrue($results[0]->data->payload->contains('hello'));
        $this->assertSame(LogLevel::INFO, $results[0]->level);
    }
    
    public function test_log_levels_are_respected(): void
    {
        // Arrange: Créer plusieurs logs avec différents niveaux
        $payload = new MixedPayloadCollection();
        $payload->add('event');
        
        $logData = new LogDataRecord(type: 'debug_event', payload: $payload);
        
        // Act: Écrire à différents niveaux
        $this->logger->debug($logData);
        $this->logger->info($logData);
        $this->logger->warning($logData);
        $this->logger->error($logData);
        
        // Assert: Vérifier les comptages par niveau
        $debugCount = $this->logger->query(new LogQueryRecord(level: LogLevel::DEBUG))->count();
        $infoCount = $this->logger->query(new LogQueryRecord(level: LogLevel::INFO))->count();
        $warningCount = $this->logger->query(new LogQueryRecord(level: LogLevel::WARNING))->count();
        $errorCount = $this->logger->query(new LogQueryRecord(level: LogLevel::ERROR))->count();
        
        $this->assertSame(1, $debugCount);
        $this->assertSame(1, $infoCount);
        $this->assertSame(1, $warningCount);
        $this->assertSame(1, $errorCount);
    }
    
    private function deleteDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
```

---

## 16. Enregistrement dans Laravel

### 16.1 Service Provider

Le Service Provider est automatiquement enregistré par le package :

```php
// Le package enregistre automatiquement :
AndyDefer\Logger\LoggerServiceProvider::class
```

### 16.2 Injection automatique

```php
// Dans n'importe quelle classe Laravel
use AndyDefer\Logger\Contracts\LoggerInterface;

final class UserController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
    
    public function login(Request $request)
    {
        // ...
        $this->logger->info($logData);
        // ...
    }
}
```

---

## 17. Bonnes pratiques

### 17.1 Structure du payload

```php
// ✅ Premier élément = type d'événement (pour faciliter le filtrage)
$payload->add('user_login', $userId, $ip, $success);

// ❌ Ordre incohérent
$payload->add($userId, 'user_login', $ip);
```

### 17.2 Noms de type cohérents

```php
// ✅ snake_case pour les types d'événements
'type' => 'user_login'
'type' => 'payment_failed'
'type' => 'api_call'

// ❌ Formats inconsistants
'type' => 'userLogin'
'type' => 'UserLogin'
```

### 17.3 Versionnement par position

```php
// Version 1 : 3 éléments
$payload->add('user_login', $userId, $ip);

// Version 2 : 4 éléments (user_agent en position 4)
$payload->add('user_login', $userId, $ip, $userAgent);
```

### 17.4 Chaînage

```php
// ✅ Chaînage fluide
$payload->add('user_login')->add($userId)->add($ip)->add($success);

// ✅ Multiple en une fois
$payload->add('user_login', $userId, $ip, $success);
```

### 17.5 Injection de dépendance uniquement

```php
// ✅ Injection explicite
final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}

// ❌ Pas de helper global ou facade
logger()->info(...);
Log::info(...);
```

### 17.6 Timestamp automatique

```php
// ✅ Le timestamp est automatique
$logger->info($logData);

// ❌ Pas besoin de passer le timestamp manuellement
$logger->info(now()->toIso8601ZuluString(), $logData);
```

### 17.7 Tests robustes

```php
// ✅ Tester la structure, pas le texte
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => $log->payload->contains(123)));

// ❌ Tester du texte qui peut changer
$logger->expects($this->once())
    ->method('info')
    ->with('User 123 logged in');
```

---

## 18. Règle d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. Le payload ne contient que des scalaires, des Records, des TypedCollection ou des stdClass. Le timestamp est automatique. Les tests vérifient la STRUCTURE, pas le TEXTE.**

```php
// ✅ Le log parfait
$payload = new MixedPayloadCollection();
$payload->add('user_login', $userId, $ip, $success);

$logger->info(new LogDataRecord(type: 'auth', payload: $payload));
```

```php
// ✅ Le test parfait
$logger->expects($this->once())
    ->method('info')
    ->with($this->callback(fn($log) => 
        $log->type === 'auth' 
        && $log->payload->contains($userId)
        && $log->payload->contains($ip)
    ));
```
> **Rappel final : STRUCTURÉ + TYPÉ + REQUÊTABLE + TESTABLE + INJECTION = MAINTENABILITÉ**
