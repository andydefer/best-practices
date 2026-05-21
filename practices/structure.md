# Principe d'usage de la Structure (Version Finale)

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

> **Règle d'or : Chaque fonctionnalité ou outil DOIT être organisé comme un mini-package indépendant, avec sa propre structure (Records, Enums, Contracts, Services, Tasks, Tests).**

### 3.1 Qu'est-ce qu'un Mini-Package ?

Un **mini-package** est une organisation de code qui respecte les principes suivants :

| Principe | Explication |
|----------|-------------|
| **Autonomie** | Le mini-package contient tout ce dont il a besoin (Services, Tasks, Records, Enums) |
| **Transportable** | Le code peut être copié d'un projet à l'autre sans modification majeure |
| **Modularité** | Le mini-package est indépendant des autres modules |
| **Granularité** | Chaque classe a une responsabilité unique et bien définie |
| **Testable** | Les tests sont inclus dans la structure du module |

### 3.2 Quand créer un Mini-Package ?

| Situation | Créer un Mini-Package ? | Pourquoi |
|-----------|------------------------|----------|
| **Outil générique** (logging, cache, currency) | ✅ Oui | Transportable d'un projet à l'autre |
| **Fonctionnalité métier spécifique** (gestion des rendez-vous) | ✅ Oui | Modulaire, facile à maintenir |
| **Code utilitaire** | ✅ Oui | Réutilisable dans tout le projet |
| **Code ponctuel** (une seule action spécifique) | ❌ Non | Trop spécifique, pas de valeur réutilisable |
| **Configuration globale** | ❌ Non | Fait partie de l'application principale |

### 3.3 Exemple : Un Mini-Package Logger

```
src/Logger/                          ← Racine du mini-package
├── Enums/
│   └── LogLevel.php                 ← Énumération des niveaux de log
├── Records/
│   ├── LogRecord.php                ← Record pour les données de log
│   └── LogQueryRecord.php           ← Record pour les requêtes
├── Contracts/
│   └── LoggerInterface.php          ← Contrat pour le service
├── Config/
│   └── LoggerConfig.php             ← Value Object de configuration
├── Providers/
│   └── LoggerServiceProvider.php    ← Enregistrement dans Laravel
├── Services/
│   ├── LogPathService.php           ← Service utilitaire
│   └── Tasks/
│       ├── WriteLogTask.php         ← Tâche d'écriture
│       ├── QueryLogsTask.php        ← Tâche de recherche
│       └── StreamLogsTask.php       ← Tâche de streaming
└── Logger.php                       ← Service principal (à la racine)
```

### 3.4 Structure des tests associée

```
tests/Logger/
├── Unit/
│   ├── Enums/
│   │   └── LogLevelTest.php
│   ├── Records/
│   │   ├── LogRecordTest.php
│   │   └── LogQueryRecordTest.php
│   ├── Config/
│   │   └── LoggerConfigTest.php
│   ├── Services/
│   │   ├── LogPathServiceTest.php
│   │   └── Tasks/
│   │       ├── WriteLogTaskTest.php
│   │       ├── QueryLogsTaskTest.php
│   │       └── StreamLogsTaskTest.php
│   └── LoggerTest.php
└── Feature/
    └── LoggerIntegrationTest.php
```

---

## 4. Arborescence standard d'un Mini-Package

### 4.1 Structure complète

```
src/{ModuleName}/
├── Enums/                          ← Énumérations du module
│   └── {Enum}.php
├── Records/                        ← Records du module
│   └── {Record}.php
├── Contracts/                      ← Interfaces du module
│   └── {Interface}.php
├── Config/                         ← Value Objects de configuration
│   └── {Config}.php
├── Providers/                      ← Service Providers
│   └── {Module}ServiceProvider.php
├── Services/                       ← Services et orchestration
│   ├── {Service}.php
│   └── Tasks/                      ← Tâches unitaires
│       └── {Task}.php
└── {Module}.php                    ← Point d'entrée principal
```

### 4.2 Structure des tests associée

```
tests/{ModuleName}/
├── Unit/
│   ├── Enums/
│   │   └── {Enum}Test.php
│   ├── Records/
│   │   └── {Record}Test.php
│   ├── Config/
│   │   └── {Config}Test.php
│   ├── Services/
│   │   ├── {Service}Test.php
│   │   └── Tasks/
│   │       └── {Task}Test.php
│   └── {Module}Test.php
└── Feature/
    └── {Module}IntegrationTest.php
```

---

## 5. Modularité et Granularité (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : GRANULARITÉ et MODULARITÉ sont les deux piliers de l'architecture mini-package.**

### 5.1 Granularité

> **Chaque classe DOIT avoir une responsabilité unique et bien définie.**

| ❌ MAUVAIS - Une classe qui fait tout | ✅ BON - Plusieurs classes granulaires |
|---------------------------------------|----------------------------------------|
| `Logger.php` (écrit, lit, stream, parse) | `WriteLogTask.php` (écrit uniquement) |
| | `QueryLogsTask.php` (lit uniquement) |
| | `StreamLogsTask.php` (stream uniquement) |
| | `LogParserService.php` (parse uniquement) |

### 5.2 Modularité

> **Le mini-package DOIT être indépendant des autres modules.**

| ❌ MAUVAIS - Dépendance forte | ✅ BON - Interface et injection |
|-------------------------------|--------------------------------|
| `$user = User::find(1);` | `$this->userRepository->find(1);` |
| `config('app.timezone')` | `$this->config->timezone` |
| `Cache::remember(...)` | `$this->cache->remember(...)` |

---

## 6. Configuration : Value Objects (⚠️ RÈGLE IMPORTANTE)

> **Règle d'or : Utilisez des Value Objects pour la configuration, pas les fichiers `config/` de Laravel.**

### 6.1 Structure d'un Value Object de configuration

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Config;

final class LoggerConfig
{
    private function __construct(
        public readonly string $basePath,
        public readonly int $retentionDays,
    ) {}

    public static function default(): self
    {
        return new self(
            basePath: storage_path('logs/structured'),
            retentionDays: 30,
        );
    }

    public function withBasePath(string $basePath): self
    {
        return new self(
            basePath: $basePath,
            retentionDays: $this->retentionDays,
        );
    }

    public function withRetentionDays(int $days): self
    {
        return new self(
            basePath: $this->basePath,
            retentionDays: $days,
        );
    }
}
```

---

## 7. Interdiction formelle des helpers de classe (⚠️ RÈGLE ABSOLUE)

> **⚠️ CRITIQUE : Les helpers qui retournent des instances de classes sont FORMELLEMENT INTERDITS.**

### 7.1 Pourquoi les helpers sont interdits ?

| Problème | Explication |
|----------|-------------|
| **Appel statique déguisé** | `logger()->info()` semble dynamique mais c'est un appel statique |
| **Non testable** | Impossible de mocker un helper |
| **Dépendance cachée** | La dépendance n'est pas visible dans le constructeur |
| **Violation du DIP** | Violation du principe d'inversion de dépendance |

### 7.2 ❌ CE QUI EST INTERDIT

```php
// ❌ INTERDIT - Helper qui retourne une instance de classe
if (!function_exists('logger')) {
    function logger(): LoggerInterface
    {
        return app(LoggerInterface::class);  // Appel statique caché
    }
}
```

### 7.3 ✅ CE QUI EST AUTORISÉ

```php
<?php
// src/Constants/helpers.php

use AndyDefer\BestPractices\Constants\BestPracticesConstants;

if (!function_exists('best_practices_limit')) {
    /**
     * Retourne la valeur de la constante BEST_PRACTICES_LIMIT.
     * 
     * ⚠️ Ce helper est autorisé car il retourne une valeur scalaire immuable,
     * pas une instance de classe. Il ne crée pas de dépendance cachée.
     */
    function best_practices_limit(): int
    {
        return BestPracticesConstants::BEST_PRACTICES_LIMIT;
    }
}

if (!function_exists('best_practices_date_format')) {
    function best_practices_date_format(): string
    {
        return BestPracticesConstants::DATE_FORMAT;
    }
}
```

### 7.4 Règle de validation pour les helpers

| Type de helper | Autorisé ? | Raison |
|----------------|------------|--------|
| Retourne une constante (`int`, `string`) | ✅ Oui | Valeur immuable, pas de dépendance |
| Retourne une configuration scalaire | ✅ Oui | Pas d'appel statique caché |
| Retourne une instance de classe | ❌ **INTERDIT** | Crée une dépendance cachée |
| Appelle `app()` ou `resolve()` | ❌ **INTERDIT** | Appel statique déguisé |
| Utilise `new ClassName()` | ❌ **INTERDIT** | Couplage fort caché |

### 7.5 Bonne pratique : Injection de dépendances

```php
// ✅ BON - Injection de dépendances
final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,  // Dépendance explicite
    ) {}
    
    public function register(UserRecord $record): void
    {
        $this->logger->info('User registered', ['id' => $record->id]);
    }
}
```

### 7.6 Enregistrement des helpers dans composer.json

> **⚠️ IMPORTANT : Si vous utilisez des helpers (uniquement pour les constantes), vous DEVEZ les enregistrer dans le fichier `composer.json`.**

```json
{
    "autoload": {
        "psr-4": {
            "AndyDefer\\BestPractices\\": "src/"
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
| **Préfixe des fonctions** | `best_practices_*` pour éviter les conflits |
| **Documentation** | Chaque helper doit être documenté |

---

## 8. Transportabilité (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : Un mini-package DOIT être écrit de manière générique pour pouvoir être transporté d'un projet à l'autre.**

### 8.1 Principes de transportabilité

| Principe | Explication |
|----------|-------------|
| **Pas de dépendance directe au framework** | Utiliser des interfaces |
| **Value Objects pour la configuration** | Pas de `config()` direct |
| **Abstractions pour les services externes** | Interfaces pour Cache, DB, HTTP |
| **Pas de helpers de classes** | Injection de dépendances uniquement |
| **Tests inclus** | Les tests voyagent avec le module |

### 8.2 Quand créer un vrai package ?

| Signal | Action |
|--------|--------|
| Utilisé dans 2 projets différents | ✅ Extraire dans un package |
| Pourrait bénéficier à la communauté | ✅ Extraire dans un package |
| Évolue indépendamment de l'application | ✅ Extraire dans un package |
| N'est utilisé que dans un seul projet | ❌ Rester en mini-package |

---

## 9. Enregistrement des modules

### 9.1 Service Provider par module

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Logger\Providers;

use Illuminate\Support\ServiceProvider;

final class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Enregistrement des services du module
    }
}
```

### 9.2 Enregistrement dans un Package / Library

```php
<?php
// src/BestPracticesServiceProvider.php

namespace AndyDefer\BestPractices;

use AndyDefer\BestPractices\Logger\Providers\LoggerServiceProvider;
use Illuminate\Support\ServiceProvider;

final class BestPracticesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(LoggerServiceProvider::class);
    }
}
```

### 9.3 Enregistrement dans une Application Laravel

```php
<?php
// bootstrap/providers.php

return [
    // ...
    AndyDefer\BestPractices\Logger\Providers\LoggerServiceProvider::class,
];
```

### 9.4 Récapitulatif

| Contexte | Où enregistrer ? |
|----------|------------------|
| **Package / Library** | Dans le Service Provider principal |
| **Application Laravel** | Dans `bootstrap/providers.php` |

---

## 10. Configuration de PHPUnit (⚠️ IMPORTANT)

> **Pour exécuter les tests d'un mini-package, configurez PHPUnit pour inclure le dossier de tests du module.**

### 10.1 Structure du `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <!-- Tests du module Logger -->
        <testsuite name="Logger Unit">
            <directory suffix="Test.php">./tests/Logger/Unit</directory>
        </testsuite>
        <testsuite name="Logger Feature">
            <directory suffix="Test.php">./tests/Logger/Feature</directory>
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
        <env name="LOGGER_PATH" value="/tmp/logger_tests"/>
    </php>
</phpunit>
```

### 10.2 Exécution des tests

```bash
# Tests d'un module spécifique
./vendor/bin/phpunit --testsuite "Logger Unit"

# Tous les tests
./vendor/bin/phpunit
```

---

## 11. Arborescence complète

```
project-root/
├── src/
│   ├── BestPracticesServiceProvider.php
│   ├── Constants/
│   │   ├── BestPracticesConstants.php
│   │   └── helpers.php
│   ├── Logger/                             ← Mini-package
│   │   ├── Enums/
│   │   ├── Records/
│   │   ├── Contracts/
│   │   ├── Config/
│   │   ├── Providers/
│   │   ├── Services/
│   │   │   └── Tasks/
│   │   └── Logger.php
│   └── Domain/                             ← Code métier
├── tests/
│   ├── Logger/                             ← Tests du module
│   │   ├── Unit/
│   │   └── Feature/
│   └── TestCase.php
├── bootstrap/
│   └── providers.php
├── composer.json
└── phpunit.xml
```

---

## 12. Récapitulatif des règles

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

---

## 13. Règle d'Or

> **Pensez votre code comme un ensemble de Lego : chaque brique (mini-package) est indépendante, réutilisable, transportable.**
>
> **⚠️ ZÉRO helper de classe. L'injection de dépendances est la SEULE façon acceptable.**
>
> **Les helpers sont autorisés UNIQUEMENT pour exporter des constantes scalaires.**

```php
// ✅ AUTORISÉ - Helper pour constante
function best_practices_limit(): int
{
    return BestPracticesConstants::LIMIT;
}

// ❌ INTERDIT - Helper pour instance
function logger(): LoggerInterface
{
    return app(LoggerInterface::class);
}

// ✅ BON - Injection explicite
final class UserService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}
}
```

> **Rappel final : GRANULARITÉ + MODULARITÉ + TRANSPORTABILITÉ + INJECTION = MAINTENABILITÉ**
```
