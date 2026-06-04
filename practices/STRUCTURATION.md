Voici le document de principes d'usage de la Structure mis à jour pour intégrer les nouveaux concepts (Tasks asynchrones, StrictDataObject, etc.) :

# Principe d'usage de la Structure (Version finale)

## 1. Définition

La **Structure** est l'organisation physique du code source. Elle suit le principe des **mini-packages** : chaque fonctionnalité ou outil est organisé comme un package indépendant, potentiellement extractible dans un package PHP dédié.

```
Mini-package → Organisation modulaire → Transportable → Extractible
```

---

## 2. Problématique à laquelle la Structure répond

| Problème | Solution |
|----------|----------|
| **Code monolithique** | Organisation en mini-packages indépendants |
| **Difficulté à réutiliser** | Code transportable d'un projet à l'autre |
| **Tests éparpillés** | Tests organisés par module |
| **Configuration rigide** | Value Objects pour la configuration |
| **Couplage fort** | Granularité et modularité |

---

## 3. Principe fondamental : Le Mini-Package (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : Chaque fonctionnalité ou outil DOIT être organisé comme un mini-package indépendant, avec sa propre structure (Value Objects, Records, Enums, Contracts, Services, Tasks, Config, Tests).**

### 3.1 Qu'est-ce qu'un Mini-Package ?

Un **mini-package** est une organisation de code qui respecte les principes suivants :

| Principe | Explication |
|----------|-------------|
| **Autonomie** | Le mini-package contient tout ce dont il a besoin (Services, Tasks, Records, Enums, Value Objects) |
| **Transportable** | Le code peut être copié d'un projet à l'autre sans modification majeure |
| **Modularité** | Le mini-package est indépendant des autres modules |
| **Granularité** | Chaque classe a une responsabilité unique et bien définie |
| **Testable** | Les tests sont inclus dans la structure du module |

### 3.2 Quand créer un Mini-Package ?

| Situation | Créer un Mini-Package ? | Pourquoi |
|-----------|------------------------|----------|
| **Outil générique** (logging, task, cache) | ✅ Oui | Transportable d'un projet à l'autre |
| **Fonctionnalité métier spécifique** (gestion des rendez-vous) | ✅ Oui | Modulaire, facile à maintenir |
| **Code utilitaire** | ✅ Oui | Réutilisable dans tout le projet |
| **Code ponctuel** (une seule action spécifique) | ❌ Non | Trop spécifique, pas de valeur réutilisable |
| **Configuration globale** | ❌ Non | Fait partie de l'application principale |

---

## 4. Arborescence standard d'un Mini-Package

### 4.1 Structure complète

```
src/{ModuleName}/
├── Abstracts/                      ← Classes abstraites
│   └── AbstractTask.php
├── Enums/                          ← Énumérations du module
│   └── {Enum}.php
├── Records/                        ← Records du module (snake_case)
│   ├── {Record}.php
│   └── TaskPayloadRecord.php
├── Contracts/                      ← Interfaces du module
│   └── {Interface}.php
├── Config/                         ← Value Objects de configuration
│   └── {Config}.php
├── Providers/                      ← Service Providers
│   └── {Module}ServiceProvider.php
├── Services/                       ← Services et orchestration
│   ├── {Service}.php
│   └── Tasks/                      ← Tâches asynchrones (file-based)
│       ├── WriteLogTask.php
│       ├── QueryLogsTask.php
│       └── StreamLogsTask.php
├── Utils/                          ← Utilitaires
│   └── StrictDataObject.php
└── {Module}.php                    ← Point d'entrée principal
```

### 4.2 Structure des tests associée

```
tests/{ModuleName}/
├── Unit/
│   ├── Abstracts/
│   │   └── AbstractTaskTest.php
│   ├── Enums/
│   │   └── {Enum}Test.php
│   ├── Records/
│   │   └── {Record}Test.php
│   ├── Config/
│   │   └── {Config}Test.php
│   ├── Services/
│   │   ├── {Service}Test.php
│   │   └── Tasks/
│   │       ├── WriteLogTaskTest.php
│   │       ├── QueryLogsTaskTest.php
│   │       └── StreamLogsTaskTest.php
│   └── {Module}Test.php
└── Feature/
    └── {Module}IntegrationTest.php
```

### 4.3 Exemple concret : Mini-Package Task

```
src/Task/
├── Abstracts/
│   └── AbstractTask.php
├── Enums/
│   ├── TaskMode.php
│   └── TaskStatus.php
├── Records/
│   ├── TaskConfigRecord.php
│   ├── TaskPayloadRecord.php
│   ├── TaskRecord.php
│   └── BatchResultRecord.php
├── Contracts/
│   └── TaskInterface.php
├── Config/
│   └── TaskConfig.php
├── Providers/
│   └── TaskServiceProvider.php
├── Services/
│   ├── TaskStorageService.php
│   ├── TaskRunnerService.php
│   ├── TaskValidatorService.php
│   ├── TaskRegistryService.php
│   └── TaskBatchService.php
├── Directives/
│   └── ProcessTasksDirective.php
└── Utils/
    └── StrictDataObject.php
```

---

## 5. Modularité et Granularité (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : GRANULARITÉ et MODULARITÉ sont les deux piliers de l'architecture mini-package.**

### 5.1 Granularité

> **Chaque classe DOIT avoir une responsabilité unique et bien définie.**

| ❌ MAUVAIS - Une classe qui fait tout | ✅ BON - Plusieurs classes granulaires |
|---------------------------------------|----------------------------------------|
| `TaskService.php` (écrit, lit, exécute, archive) | `TaskStorageService.php` (stockage uniquement) |
| | `TaskRunnerService.php` (exécution uniquement) |
| | `TaskValidatorService.php` (validation uniquement) |
| | `TaskRegistryService.php` (enregistrement uniquement) |

### 5.2 Modularité

> **Le mini-package DOIT être indépendant des autres modules.**

| ❌ MAUVAIS - Dépendance forte | ✅ BON - Interface et injection |
|-------------------------------|--------------------------------|
| `$user = User::find(1);` | `$this->userRepository->find(1);` |
| `Log::info(...)` | `$this->logger->info(...)` |
| `Cache::remember(...)` | `$this->cache->remember(...)` |

---

## 6. Configuration : Value Objects (⚠️ RÈGLE IMPORTANTE)

> **Règle d'or : Utilisez des Value Objects pour la configuration, pas les fichiers `config/` de Laravel.**

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Task\Config;

final class TaskConfig
{
    private function __construct(
        public readonly string $storagePath,
        public readonly bool $gracePeriodEnabled,
        public readonly int $gracePeriodSeconds,
        public readonly int $batchLimit,
        public readonly string $batchOrder,
    ) {}

    public static function default(): self
    {
        return new self(
            storagePath: storage_path('tasks'),
            gracePeriodEnabled: true,
            gracePeriodSeconds: 86400,
            batchLimit: 1000,
            batchOrder: 'oldest',
        );
    }

    public function withStoragePath(string $path): self
    {
        return new self(
            storagePath: $path,
            gracePeriodEnabled: $this->gracePeriodEnabled,
            gracePeriodSeconds: $this->gracePeriodSeconds,
            batchLimit: $this->batchLimit,
            batchOrder: $this->batchOrder,
        );
    }
}
```

---

## 7. Types de classes dans un Mini-Package

### 7.1 Records (snake_case)

> **Les Records sont en `snake_case` et utilisent `StrictDataObject`.**

```php
final class TaskPayloadRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly string $type,          // snake_case
        public readonly StrictDataObjectCollection $payload,
    ) {}
}
```

### 7.2 Value Objects (camelCase)

> **Les Value Objects sont en `camelCase` et utilisent `AbstractValueObject`.**

```php
final class IsoZuluTime extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        // validation...
    }
}
```

### 7.3 Enums (SCREAMING_SNAKE_CASE)

> **Les Enums sont en `SCREAMING_SNAKE_CASE` pour les cas, `snake_case` pour les valeurs.**

```php
enum TaskStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
}
```

### 7.4 Tasks asynchrones

> **Les Tasks sont des classes qui étendent `AbstractTask` et sont stockées en JSON.**

```php
final class SendWelcomeEmailTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'send-welcome-email',
            description: 'Send welcome email to new user',
            maxAttempts: 3,
        );
    }
    
    protected function process(): void
    {
        $data = $this->payload->getPayload()->first();
        // Logique métier
    }
}
```

---

## 8. Interdiction formelle des helpers de classe (⚠️ RÈGLE ABSOLUE)

> **⚠️ CRITIQUE : Les helpers qui retournent des instances de classes sont FORMELLEMENT INTERDITS.**

### 8.1 Pourquoi les helpers sont interdits ?

| Problème | Explication |
|----------|-------------|
| **Appel statique déguisé** | `logger()->info()` semble dynamique mais c'est un appel statique |
| **Non testable** | Impossible de mocker un helper |
| **Dépendance cachée** | La dépendance n'est pas visible dans le constructeur |
| **Violation du DIP** | Violation du principe d'inversion de dépendance |

### 8.2 ❌ CE QUI EST INTERDIT

```php
// ❌ INTERDIT - Helper qui retourne une instance de classe
if (!function_exists('logger')) {
    function logger(): LoggerInterface
    {
        return app(LoggerInterface::class);  // Appel statique caché
    }
}

// ❌ INTERDIT - Helper pour une tâche
if (!function_exists('task')) {
    function task(): TaskRegistryService
    {
        return app(TaskRegistryService::class);
    }
}
```
### 8.3 Règle de validation pour les helpers

| Type de helper | Autorisé ? | Raison |
|----------------|------------|--------|
| Retourne une constante (`int`, `string`) | ✅ Oui | Valeur immuable, pas de dépendance |
| Retourne une configuration scalaire | ✅ Oui | Pas d'appel statique caché |
| Retourne une instance de classe | ❌ **INTERDIT** | Crée une dépendance cachée |
| Appelle `app()` ou `resolve()` | ❌ **INTERDIT** | Appel statique déguisé |
| Utilise `new ClassName()` | ❌ **INTERDIT** | Couplage fort caché |

### 8.4 Bonne pratique : Injection de dépendances

```php
// ✅ BON - Injection de dépendances
final class UserService
{
    public function __construct(
        private readonly TaskRegistryService $taskRegistry,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function register(UserRecord $record): void
    {
        $payload = new TaskPayloadRecord(
            type: 'welcome_email',
            payload: StrictDataObjectCollection::from([
                StrictDataObject::from(['email' => $record->email]),
            ]),
        );
        
        $this->taskRegistry->register(
            taskClass: SendWelcomeEmailTask::class,
            payload: $payload,
        );
        
        $this->logger->info('User registered', ['id' => $record->id]);
    }
}
```

### 8.5 Enregistrement des helpers dans composer.json

> **⚠️ IMPORTANT : Si vous utilisez des helpers (uniquement pour les constantes), vous DEVEZ les enregistrer dans le fichier `composer.json`.**

```json
{
    "autoload": {
        "psr-4": {
            "AndyDefer\\Task\\": "src/"
        },
        "files": [
            "src/Constants/helpers.php"
        ]
    }
}
```

**Règles pour les helpers dans composer.json :**

| Règle | Explication |
|-------|-------------|
| **Un seul fichier** | Un seul `helpers.php` pour tout le package |
| **Chemin explicite** | `src/Constants/helpers.php` |
| **Préfixe des fonctions** | `task_*` ou `best_practices_*` pour éviter les conflits |
| **Documentation** | Chaque helper doit être documenté |

---

## 9. Transportabilité (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : Un mini-package DOIT être écrit de manière générique pour pouvoir être transporté d'un projet à l'autre.**

### 9.1 Principes de transportabilité

| Principe | Explication |
|----------|-------------|
| **Pas de dépendance directe au framework** | Utiliser des interfaces |
| **Value Objects pour la configuration** | Pas de `config()` direct |
| **Abstractions pour les services externes** | Interfaces pour Cache, DB, HTTP |
| **Pas de helpers de classes** | Injection de dépendances uniquement |
| **Records en `snake_case`** | Standardisation interne |
| **Tests inclus** | Les tests voyagent avec le module |

### 9.2 Quand créer un vrai package ?

| Signal | Action |
|--------|--------|
| Utilisé dans 2 projets différents | ✅ Extraire dans un package |
| Pourrait bénéficier à la communauté | ✅ Extraire dans un package |
| Évolue indépendamment de l'application | ✅ Extraire dans un package |
| N'est utilisé que dans un seul projet | ❌ Rester en mini-package |

---

## 10. Enregistrement des modules

### 10.1 Service Provider par module

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Task\Providers;

use Illuminate\Support\ServiceProvider;

final class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrement des services du module
        $this->app->singleton(TaskRegistryService::class);
        $this->app->singleton(TaskBatchService::class);
    }
}
```

### 10.2 Enregistrement dans un Package / Library

```php
<?php

namespace AndyDefer\Task;

use AndyDefer\Task\Providers\TaskServiceProvider;
use Illuminate\Support\ServiceProvider;

final class TaskPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(TaskServiceProvider::class);
    }
}
```

### 10.3 Enregistrement dans une Application Laravel

```php
<?php
// bootstrap/providers.php

return [
    // ...
    AndyDefer\Task\Providers\TaskServiceProvider::class,
];
```

---

## 11. Configuration de PHPUnit (⚠️ IMPORTANT)

> **Pour exécuter les tests d'un mini-package, configurez PHPUnit pour inclure le dossier de tests du module.**

### 11.1 Structure du `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <!-- Tests du module Task -->
        <testsuite name="Task Unit">
            <directory suffix="Test.php">./tests/Task/Unit</directory>
        </testsuite>
        <testsuite name="Task Feature">
            <directory suffix="Test.php">./tests/Task/Feature</directory>
        </testsuite>
        
        <!-- Tests généraux -->
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="TASK_STORAGE_PATH" value="/tmp/task_tests"/>
    </php>
</phpunit>
```

### 11.2 Exécution des tests

```bash
# Tests d'un module spécifique
./vendor/bin/phpunit --testsuite "Task Unit"

# Tous les tests
./vendor/bin/phpunit
```

---

## 12. Arborescence complète

```
project-root/
├── src/
│   ├── TaskPackageServiceProvider.php
│   ├── Constants/
│   │   ├── TaskConstants.php
│   │   └── helpers.php
│   ├── Task/                             ← Mini-package
│   │   ├── Abstracts/
│   │   │   └── AbstractTask.php
│   │   ├── Enums/
│   │   ├── Records/
│   │   ├── Contracts/
│   │   ├── Config/
│   │   ├── Providers/
│   │   ├── Services/
│   │   ├── Directives/
│   │   └── Utils/
│   └── Domain/                           ← Code métier
├── tests/
│   ├── Task/                             ← Tests du module
│   │   ├── Unit/
│   │   └── Feature/
│   └── TestCase.php
├── bootstrap/
│   └── providers.php
├── composer.json
└── phpunit.xml
```

---

## 13. Récapitulatif des règles

| Règle | Explication |
|-------|-------------|
| **Mini-package** | Chaque outil transportable est organisé comme un mini-package |
| **Granularité** | Une classe = une responsabilité unique |
| **Modularité** | Les modules sont indépendants |
| **Value Object config** | Pas de fichiers `config/`, utiliser des Value Objects |
| **Transportabilité** | Code générique, pas de dépendance directe |
| **Extraction** | Si utilisé dans 2+ projets → créer un vrai package |
| **Tests par module** | Tests dans `tests/{ModuleName}/` |
| **Helpers interdits** | Pas de helpers retournant des instances |
| **Constants helpers** | Uniquement pour exporter des constantes scalaires |
| **composer.json** | Enregistrer `helpers.php` dans `autoload.files` |
| **phpunit.xml** | Configurer les testsuites par module |
| **Records** | `snake_case` avec `StrictDataObject` |
| **Value Objects** | `camelCase` avec `AbstractValueObject` |
| **Enums** | `SCREAMING_SNAKE_CASE` / `snake_case` |
| **Tasks** | Asynchrones, stockage JSON, exécution via poller |

---

## 14. Règle d'Or

> **Pensez votre code comme un ensemble de Lego : chaque brique (mini-package) est indépendante, réutilisable, transportable.**
>
> **⚠️ ZÉRO helper de classe. L'injection de dépendances est la SEULE façon acceptable.**
>
> **Les helpers sont autorisés UNIQUEMENT pour exporter des constantes scalaires.**
>
> **Les Records sont en `snake_case` avec `StrictDataObject`. Les Value Objects sont en `camelCase`.**

```php
// ✅ AUTORISÉ - Helper pour constante
function task_storage_path(): string
{
    return TaskConfig::default()->storagePath;
}

// ❌ INTERDIT - Helper pour instance
function task(): TaskRegistryService
{
    return app(TaskRegistryService::class);
}

// ✅ BON - Injection explicite
final class UserService
{
    public function __construct(
        private readonly TaskRegistryService $taskRegistry,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function register(UserRecord $record): void
    {
        $payload = new TaskPayloadRecord(
            type: 'welcome_email',
            payload: StrictDataObjectCollection::from([
                StrictDataObject::from(['email' => $record->email]),
            ]),
        );
        
        $this->taskRegistry->register(
            taskClass: SendWelcomeEmailTask::class,
            payload: $payload,
        );
        
        $this->logger->info('User registered', ['id' => $record->id]);
    }
}
```

> **Rappel final : GRANULARITÉ + MODULARITÉ + TRANSPORTABILITÉ + INJECTION + CONVENTIONS = MAINTENABILITÉ**
---