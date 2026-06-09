# Principe d'usage des Services (Version Finale - Complète)

## Table des matières

1. [Définition](#1-définition)
2. [Pourquoi les Services remplacent les Traits](#2-pourquoi-les-services-remplacent-les-traits)
3. [Caractéristiques fondamentales](#3-caractéristiques-fondamentales)
4. [Configuration externe vs constantes internes](#4-configuration-externe-vs-constantes-internes)
5. [Principes philosophiques](#5-principes-philosophiques)
6. [Ce qu'un Service NE peut PAS faire](#6-ce-quun-service-ne-peut-pas-faire)
7. [Le Service parfait](#7-le-service-parfait)
8. [Récapitulatif des contraintes](#8-récapitulatif-des-contraintes)
9. [Règle d'or](#9-règle-dor)

---

## 1. Définition

Un **Service** est un conteneur de méthodes qui partagent un même domaine métier. Il ne maintient **AUCUN état interne** et ne possède **AUCUNE propriété privée** (sauf des dépendances injectées).

```
Service → Conteneur de méthodes → Même domaine métier → Aucun état interne
```

---

## 2. Pourquoi les Services remplacent les Traits (⚠️ RÈGLE MAJEURE)

> **Les traits sont une mauvaise pratique. Les Services sont l'alternative propre par composition.**

### 2.1. Problèmes des traits

| Problème | Explication |
|----------|-------------|
| **Couplage implicite** | Le trait dépend de l'état de la classe qui l'utilise (propriétés, méthodes) |
| **Difficulté de test** | Un trait ne peut pas être mocké isolément |
| **Conflits de noms** | Deux traits peuvent avoir des méthodes avec le même nom |
| **Violation du SRP** | Le trait ajoute des responsabilités de manière cachée |
| **Hard debugging** | La résolution des méthodes est complexe à tracer |
| **Pas de constructeur** | Le trait ne peut pas avoir de constructeur pour injecter des dépendances |

### 2.2. Exemple complet : Trait FileCreator (anti-pattern)

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Traits;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

trait FileCreator
{
    private Filesystem $files;

    // ❌ Configuration en constante interne
    private const DEFAULT_DIRECTORY_PERMISSION = 0755;
    private const MAX_RETRY_ATTEMPTS = 3;

    protected function initFileCreator(): void
    {
        $this->files = new Filesystem;
    }

    protected function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        if ($this->files->exists($destinationPath) && !$force) {
            $this->error("File already exists: {$destinationPath}");
            return false;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        try {
            $stub = $this->files->get($stubPath);
        } catch (FileNotFoundException $e) {
            $this->error("Stub template not found at: {$stubPath}");
            return false;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        if ($this->files->put($destinationPath, $content) === false) {
            $this->error("Cannot create file: {$destinationPath}");
            return false;
        }

        return true;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, self::DEFAULT_DIRECTORY_PERMISSION, true);
        }
    }

    protected function toPascalCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    protected function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    protected function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath.'/'.$className : $className,
        ];
    }

    protected function buildNamespace(string $baseNamespace, string $subPath): string
    {
        if (!$subPath) {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $subPath);
    }

    protected function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        $directory = getcwd().$baseDir;
        if ($subPath) {
            $directory .= $subPath.'/';
        }

        return $directory.$className.'.php';
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Traits\FileCreator;

final class CreateTaskDirective extends AbstractDirective
{
    use FileCreator;  // ❌ Couplage implicite, impossible à tester isolément

    public function execute(): ExitCode
    {
        $this->initFileCreator();  // Initialisation manuelle
        
        $stubPath = __DIR__ . '/../Stubs/task.stub';
        $taskName = $this->argument('name');
        $segments = $this->extractPathSegments($taskName);
        
        $namespace = $this->buildNamespace('App\\Tasks', $segments['subPath']);
        $className = $this->toPascalCase($segments['className']);
        
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ signature }}' => $this->toKebabCase($className),
        ];
        
        $destination = $this->getAppPath('/app/Tasks/', $className, $segments['subPath']);
        
        if ($this->createFile($stubPath, $destination, $replacements, $this->option('force'))) {
            $this->info("Task created: {$destination}");
            return ExitCode::SUCCESS;
        }
        
        return ExitCode::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Directives\CreateTaskDirective;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use PHPUnit\Framework\TestCase;

final class CreateTaskDirectiveTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        chdir($this->tempDir);
    }

    // ❌ Test impossible : on ne peut pas mocker le trait FileCreator
    // Le test doit créer de VRAIS fichiers sur le disque
    public function test_execute_creates_task_file(): void
    {
        $interaction = $this->createMock(DirectiveInteractionService::class);
        $directive = new CreateTaskDirective($interaction);
        
        // Le trait utilise directement le Filesystem réel
        // On ne peut pas le mocker, on crée donc de vrais fichiers
        file_put_contents('/tmp/task.stub', 'class {{ class }}');
        
        // Test qui écrit sur le disque (lent, fragile, non isolé)
        $result = $directive->execute();
        
        $this->assertFileExists('/app/Tasks/UserTask.php');  // Effet de bord réel
        
        // Nettoyage nécessaire
        unlink('/tmp/task.stub');
        unlink('/app/Tasks/UserTask.php');
    }
}
```

### 2.3. Exemple complet : Service FileCreator (bonne pratique)

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

// ✅ Configuration injectée, pas de constantes internes
final class FileCreatorService
{
    public function __construct(
        private readonly Filesystem $files,           // ✅ Injection explicite
        private readonly FileCreatorConfig $config,   // ✅ Config injectée (pas de private const)
    ) {}

    public function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        // ✅ Utilisation de la config injectée, pas de constante interne
        if ($this->files->exists($destinationPath) && !$force) {
            return false;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        try {
            $stub = $this->files->get($stubPath);
        } catch (FileNotFoundException $e) {
            return false;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        return $this->files->put($destinationPath, $content) !== false;
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            // ✅ Config injectée pour les permissions
            $this->files->makeDirectory($path, $this->config->directoryPermission(), true);
        }
    }

    public function toPascalCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    public function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    public function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath.'/'.$className : $className,
        ];
    }

    public function buildNamespace(string $baseNamespace, string $subPath): string
    {
        if (!$subPath) {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $subPath);
    }

    public function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        $directory = getcwd().$baseDir;
        if ($subPath) {
            $directory .= $subPath.'/';
        }

        return $directory.$className.'.php';
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Configs;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;

// ✅ Configuration externalisée
final class FileCreatorConfig extends AbstractConfig
{
    public function directoryPermission(): int
    {
        return (int) (getenv('FILE_CREATOR_DIR_PERMISSION') ?: 0755);
    }

    public function maxRetryAttempts(): int
    {
        return (int) (getenv('FILE_CREATOR_MAX_RETRY') ?: 3);
    }

    public function defaultFilePermission(): int
    {
        return (int) (getenv('FILE_CREATOR_FILE_PERMISSION') ?: 0644);
    }

    public function backupBeforeOverwrite(): bool
    {
        return getenv('FILE_CREATOR_BACKUP') === 'true';
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;

final class CreateTaskDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator,  // ✅ Injection du service
    ) {
        parent::__construct();
    }

    public function execute(): ExitCode
    {
        $stubPath = __DIR__ . '/../Stubs/task.stub';
        $taskName = $this->argument('name');
        $segments = $this->fileCreator->extractPathSegments($taskName);
        
        $namespace = $this->fileCreator->buildNamespace('App\\Tasks', $segments['subPath']);
        $className = $this->fileCreator->toPascalCase($segments['className']);
        
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ signature }}' => $this->fileCreator->toKebabCase($className),
        ];
        
        $destination = $this->fileCreator->getAppPath('/app/Tasks/', $className, $segments['subPath']);
        
        if ($this->fileCreator->createFile($stubPath, $destination, $replacements, $this->option('force'))) {
            $this->info("Task created: {$destination}");
            return ExitCode::SUCCESS;
        }
        
        $this->error("Failed to create task");
        return ExitCode::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Directives\CreateTaskDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use PHPUnit\Framework\TestCase;

final class CreateTaskDirectiveTest extends TestCase
{
    private FileCreatorService $fileCreator;
    private DirectiveInteractionService $interaction;
    private CreateTaskDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Mock du service (testable, isolé)
        $this->fileCreator = $this->createMock(FileCreatorService::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        
        $this->directive = new CreateTaskDirective($this->fileCreator);
        $this->directive->setInteraction($this->interaction);
    }

    public function test_execute_creates_task_successfully(): void
    {
        // Arrange
        $this->directive->setArguments(new ParameterCollection());
        $this->directive->setOptions(new ParameterCollection());
        
        $this->fileCreator->method('extractPathSegments')
            ->willReturn([
                'segments' => ['admin'],
                'className' => 'user',
                'subPath' => 'Admin',
                'fullPath' => 'Admin/user'
            ]);
        
        $this->fileCreator->method('toPascalCase')
            ->with('user')
            ->willReturn('User');
        
        $this->fileCreator->method('buildNamespace')
            ->with('App\\Tasks', 'Admin')
            ->willReturn('App\\Tasks\\Admin');
        
        $this->fileCreator->method('toKebabCase')
            ->with('User')
            ->willReturn('user');
        
        $this->fileCreator->method('createFile')
            ->willReturn(true);
        
        $this->interaction->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task created'));
        
        // Act
        $result = $this->directive->execute();
        
        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_file_creation_fails(): void
    {
        // Arrange
        $this->directive->setArguments(new ParameterCollection());
        $this->directive->setOptions(new ParameterCollection());
        
        $this->fileCreator->method('extractPathSegments')
            ->willReturn(['segments' => [], 'className' => 'user', 'subPath' => '', 'fullPath' => 'user']);
        
        $this->fileCreator->method('toPascalCase')->willReturn('User');
        $this->fileCreator->method('buildNamespace')->willReturn('App\\Tasks');
        $this->fileCreator->method('createFile')->willReturn(false);
        
        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Failed to create task');
        
        // Act
        $result = $this->directive->execute();
        
        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
```

### 2.4. Comparaison : Trait vs Service

| Aspect | Trait | Service |
|--------|-------|---------|
| **Testabilité** | ❌ Impossible à mocker, nécessite des fichiers réels | ✅ Facile à mocker, test isolé |
| **Dépendances** | ❌ Pas de constructeur, instanciation interne | ✅ Injection explicite dans le constructeur |
| **État interne** | ❌ Peut avoir des propriétés privées | ✅ Pas d'état (sauf injections) |
| **Couplage** | ❌ Couplage implicite à la classe utilisatrice | ✅ Découplage explicite |
| **Réutilisabilité** | ✅ Réutilisable (mais dangereux) | ✅ Réutilisable (et sûr) |
| **Debugging** | ❌ Difficile (résolution dynamique) | ✅ Facile (appel explicite) |
| **Effets de bord** | ❌ Modifie la classe qui l'utilise | ✅ Aucun effet de bord |
| **Configuration** | ❌ Constantes internes figées | ✅ Config injectée, modifiable |
| **Paramétrage** | ❌ Impossible de changer sans modifier le code | ✅ Possible via différentes Configs |

---

## 3. Caractéristiques fondamentales

| Caractéristique | Règle |
|-----------------|-------|
| **État interne** | ❌ AUCUNE propriété privée (sauf dépendances injectées) |
| **Constructeur** | ✅ Uniquement pour injecter des dépendances (Services, Repositories, Configs) |
| **Paramètres des méthodes** | ✅ Tout ce dont la méthode a besoin est passé en paramètre |
| **Stockage de données** | ❌ Ne stocke rien entre les appels |
| **Classe finale** | ❌ **NE PEUT PAS** être déclarée `final` (doit être mockable) |
| **Instanciation interne** | ❌ INTERDIT (tout doit être injecté) |
| **Constantes privées** | ❌ INTERDITES (utiliser une Config injectée à la place) |
| **Valeurs par défaut figées** | ❌ INTERDITES (injecter via Config) |

---

## 4. Configuration externe vs constantes internes (⚠️ RÈGLE MAJEURE)

> **⚠️ Un Service ne doit JAMAIS contenir de constantes privées ou publiques pour la configuration. Toute valeur configurable doit être injectée via une Config.**

### 4.1. Pourquoi interdire les constantes ?

| Problème avec `private const` | Solution avec Config injectée |
|-------------------------------|------------------------------|
| **Valeur figée dans le code** | Valeur modifiable sans changer le Service |
| **Impossible à modifier pour les tests** | On peut mocker la Config pour les tests |
| **Couplage à des valeurs spécifiques** | Découplage total |
| **Difficulté à adapter à différents environnements** | Une Config différente par environnement |
| **Violation de l'Open/Closed Principle** | Le Service est fermé aux modifications |

### 4.2. Exemple : Service avec constantes internes (anti-pattern)

```php
// ❌ MAUVAIS - Service avec constantes internes figées
final class BadFileCreatorService
{
    private const DEFAULT_DIRECTORY_PERMISSION = 0755;  // ❌ Figé dans le code
    private const MAX_RETRY_ATTEMPTS = 3;               // ❌ Impossible à modifier
    private const DEFAULT_FILE_PERMISSION = 0644;       // ❌ Couplage fort

    public function createFile(string $path): void
    {
        // Utilisation des constantes figées
        mkdir(dirname($path), self::DEFAULT_DIRECTORY_PERMISSION, true);
        
        for ($i = 0; $i < self::MAX_RETRY_ATTEMPTS; $i++) {
            // Tentative d'écriture
        }
    }
}

// ❌ Impossible de tester avec des valeurs différentes
// ❌ Impossible d'adapter à différents environnements
```

### 4.3. Exemple : Service avec Config injectée (bonne pratique)

```php
// ✅ BON - Service avec Config injectée
final class GoodFileCreatorService
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly FileCreatorConfig $config,  // ✅ Config injectée
    ) {}

    public function createFile(string $path): void
    {
        // ✅ Utilisation des valeurs de la Config (modifiables)
        mkdir(dirname($path), $this->config->directoryPermission(), true);
        
        for ($i = 0; $i < $this->config->maxRetryAttempts(); $i++) {
            // Tentative d'écriture
        }
    }
}

// ✅ La Config peut être modifiée sans toucher au Service
final class FileCreatorConfig extends AbstractConfig
{
    public function directoryPermission(): int
    {
        return getenv('FILE_CREATOR_DIR_PERMISSION') ?: 0755;
    }
    
    public function maxRetryAttempts(): int
    {
        return getenv('FILE_CREATOR_MAX_RETRY') ?: 3;
    }
}

// ✅ Test avec des valeurs différentes
public function test_retry_attempts(): void
{
    $config = $this->createMock(FileCreatorConfig::class);
    $config->method('maxRetryAttempts')->willReturn(5);  // ✅ Modifiable pour le test
    
    $service = new GoodFileCreatorService($files, $config);
}
```

### 4.4. Ce qu'une Config peut contenir

| Type de valeur | Exemple | Autorisation |
|----------------|---------|--------------|
| Permissions fichiers | `directoryPermission(): int` | ✅ |
| Seuils et limites | `maxRetryAttempts(): int` | ✅ |
| Timeouts | `timeout(): int` | ✅ |
| Chemins par défaut | `defaultPath(): string` | ✅ |
| URLs de services | `apiBaseUrl(): string` | ✅ |
| Clés API | `apiKey(): string` | ✅ |
| Flags de comportement | `backupBeforeOverwrite(): bool` | ✅ |

---

## 5. Principes philosophiques

### 5.1. Composition Over Inheritance

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Composition Over Inheritance** | Préférer l'injection de dépendances à l'héritage | L'héritage reste possible quand il est pertinent |

```php
// ✅ Composition (recommandé)
class DatabaseService
{
    public function __construct(
        private readonly ConnectionInterface $connection,  // Injection
        private readonly LoggerInterface $logger,         // Injection
    ) {}
}

// ⚠️ Héritage (acceptable dans certains cas)
class SpecificDatabaseService extends DatabaseService
{
    // Uniquement si la relation "est-un" est claire et stable
}
```

### 5.2. Dependency Inversion Principle (DIP)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Dependency Inversion** | Dépendre des interfaces plutôt que des classes concrètes | Les DTOs et Value Objects peuvent être concrets |

```php
// ✅ Dépendance vers une interface (recommandé)
interface PaymentGatewayInterface { ... }

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,  // Interface
    ) {}
}

// ✅ DTO concret (acceptable)
final class OrderData  // Pas besoin d'interface pour un DTO
{
    public function __construct(
        public readonly string $id,
        public readonly float $amount,
    ) {}
}
```

### 5.3. Capability-Based Design

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Capability-Based Design** | Exposer des capacités spécifiques plutôt que des services fourre-tout | Un service peut avoir plusieurs méthodes cohésives |

```php
// ❌ Service fourre-tout (anti-pattern)
class UtilsService
{
    public function sendEmail() { ... }
    public function calculateTax() { ... }
    public function formatDate() { ... }
    public function queryDatabase() { ... }
}

// ✅ Capacités spécifiques (recommandé)
class EmailSender { ... }
class TaxCalculator { ... }
class DateFormatter { ... }
class DatabaseQueryExecutor { ... }

// ✅ Service avec plusieurs méthodes cohésives (acceptable)
class OrderCalculatorService
{
    public function calculateSubtotal(Order $order): float { ... }
    public function calculateTax(float $subtotal): float { ... }
    public function calculateTotal(Order $order): float { ... }
    // Toutes les méthodes sont liées au même domaine : calcul de commande
}
```

### 5.4. Domain-Driven Design (DDD)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Domain-Driven Design** | Organiser le code par domaine fonctionnel | La structure peut évoluer librement |

```php
// ✅ Organisation par domaine (recommandé)
namespace App\Domain\Order\Services;
namespace App\Domain\Order\Entities;
namespace App\Domain\Order\ValueObjects;

// ⚠️ Organisation par couche (acceptable)
namespace App\Services;
namespace App\Models;
namespace App\DTOs;
```

### 5.5. Single Responsibility Principle (SRP)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Single Responsibility** | Une classe a une seule raison de changer | La "responsabilité" peut être interprétée différemment selon le contexte |

```php
// ✅ Une seule responsabilité (recommandé)
class EmailValidator { ... }      // Ne fait que valider
class EmailSender { ... }          // Ne fait qu'envoyer
class EmailFormatter { ... }       // Ne fait que formater

// ✅ Responsabilité plus large mais cohérente (acceptable)
class EmailService
{
    public function validate(Email $email): bool { ... }
    public function send(Email $email): void { ... }
    public function format(Email $email): string { ... }
    // Toutes les méthodes concernent le même concept : Email
}
```

### 5.6. Open/Closed Principle (OCP)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Open/Closed** | Ouvert à l'extension, fermé à la modification | L'extension peut se faire par héritage ou composition |

```php
// ✅ Extension par composition (recommandé)
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,  // On peut injecter différents gateways
    ) {}
}

// ⚠️ Extension par héritage (acceptable)
class VatCalculator
{
    protected function getRate(): float { return 0.2; }
}

class ReducedVatCalculator extends VatCalculator
{
    protected function getRate(): float { return 0.1; }  // Extension par redéfinition
}
```

### 5.7. Liskov Substitution Principle (LSP)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Liskov Substitution** | Les sous-types doivent être substituables à leurs types de base | Les DTOs et Value Objects n'ont pas besoin d'être substituables |

```php
// ✅ Respect de LSP
interface PaymentGateway
{
    public function pay(float $amount): void;  // Contrat clair
}

class StripeGateway implements PaymentGateway
{
    public function pay(float $amount): void { /* Implémentation */ }
}

class PayPalGateway implements PaymentGateway
{
    public function pay(float $amount): void { /* Implémentation */ }
}

// ✅ Pas de LSP pour DTO (acceptable)
final class OrderData  // final, pas d'héritage, pas besoin de LSP
{
    public function __construct(public readonly float $amount) {}
}
```

### 5.8. Interface Segregation Principle (ISP)

| Principe | Ce qu'il encourage | Ce qu'il n'impose pas |
|----------|-------------------|----------------------|
| **Interface Segregation** | Préférer plusieurs petites interfaces à une grosse | Les services internes peuvent avoir des interfaces plus larges |

```php
// ✅ Interfaces ségréguées (recommandé pour les contrats publics)
interface PaymentProcessor { ... }
interface RefundProcessor { ... }
interface FraudChecker { ... }

// ✅ Interface plus large pour un service interne (acceptable)
interface InternalOrderServiceInterface
{
    public function create(OrderData $data): Order;
    public function update(string $id, OrderData $data): Order;
    public function delete(string $id): void;
    public function find(string $id): ?Order;
    // CRUD complet, mais cohérent pour un repository interne
}
```

---

## 6. Ce qu'un Service NE peut PAS faire (⚠️ RÈGLES STRICTES)

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Avoir des propriétés privées** (sauf injections) | État interne interdit | Utiliser des paramètres de méthode |
| **Stocker des données volatiles** (compteur, dernier ID, cache) | Violation du principe sans état | Utiliser un cache externe ou un Value Object |
| **Stocker des paramètres de configuration** (chemin, clé API) | Couplage à des valeurs figées | Injecter une Config |
| **Avoir des constantes privées/publiques** | Valeurs figées dans le code | Injecter une Config |
| **Être `final`** | Empêche le mocking et l'extension | Laisser la classe extensible |
| **Instancier ses dépendances en interne** | Crée un couplage fort, impossible à tester | Injecter les dépendances |
| **Contenir des méthodes statiques** | Couplage fort, difficile à tester | Utiliser l'injection de dépendances |
| **Appeler des singletons globaux** (app(), resolve()) | Couplage caché, violation du DIP | Injecter les dépendances |
| **Utiliser `new` pour créer des objets métier** | Couplage fort | Injecter des factories |
| **Avoir des effets de bord cachés** | Comportement imprévisible | Rendre les effets de bord explicites |

---

## 7. Le Service parfait

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Configs\OrderConfig;
use App\Records\OrderRecord;
use App\Data\OrderTotalData;

/**
 * Service de calcul des commandes.
 * 
 * ✅ Pas d'état interne
 * ✅ Dépendances injectées dans le constructeur
 * ✅ Toutes les données arrivent en paramètres
 * ✅ Pas de `final`
 * ✅ Testable et mockable
 * ✅ Config injectée (pas de constantes internes)
 * ✅ Respecte les principes SOLID
 */
class OrderCalculatorService
{
    public function __construct(
        private readonly TaxService $taxService,           // ✅ Service injecté
        private readonly DiscountService $discountService, // ✅ Service injecté
        private readonly OrderConfig $config,              // ✅ Config injectée (pas de private const)
    ) {}

    /**
     * Calcule le sous-total d'une commande.
     * 
     * @param OrderRecord $order La commande (passée en paramètre)
     * @return float Le sous-total
     */
    public function calculateSubtotal(OrderRecord $order): float
    {
        $total = 0.0;
        foreach ($order->items as $item) {
            $total += $item->price * $item->quantity;
        }
        
        // ✅ Utilisation de la config injectée
        if ($this->config->applyRounding()) {
            $total = round($total, $this->config->roundingPrecision());
        }
        
        return $total;
    }

    /**
     * Calcule le total d'une commande (sous-total + taxes - remises).
     * 
     * @param OrderRecord $order La commande
     * @param string $country Le pays pour la TVA
     * @param string|null $promoCode Code promo optionnel
     * @return OrderTotalData Le total détaillé
     */
    public function calculateTotal(OrderRecord $order, string $country, ?string $promoCode = null): OrderTotalData
    {
        $subtotal = $this->calculateSubtotal($order);
        
        $tax = $this->taxService->calculate($subtotal, $country);
        $discount = $this->discountService->apply($subtotal, $promoCode);
        
        // ✅ Application des frais de livraison (depuis la Config)
        $shippingFee = $order->total > $this->config->freeShippingThreshold() 
            ? 0.0 
            : $this->config->standardShippingFee();
        
        $total = $subtotal + $tax - $discount + $shippingFee;
        
        return new OrderTotalData(
            subtotal: $subtotal,
            tax: $tax,
            discount: $discount,
            shippingFee: $shippingFee,
            total: round($total, 2)
        );
    }

    /**
     * Vérifie si une commande est éligible à la livraison gratuite.
     * 
     * @param OrderRecord $order La commande
     * @return bool
     */
    public function isEligibleForFreeShipping(OrderRecord $order): bool
    {
        return $order->total >= $this->config->freeShippingThreshold();
    }

    /**
     * Calcule le nombre total d'articles dans une commande.
     * 
     * @param OrderRecord $order La commande
     * @return int
     */
    public function getTotalItemsCount(OrderRecord $order): int
    {
        $count = 0;
        foreach ($order->items as $item) {
            $count += $item->quantity;
        }
        return $count;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Configs;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;

// ✅ Configuration externalisée, pas de constantes dans le Service
final class OrderConfig extends AbstractConfig
{
    public function freeShippingThreshold(): float
    {
        return (float) (getenv('FREE_SHIPPING_THRESHOLD') ?: 50.0);
    }

    public function standardShippingFee(): float
    {
        return (float) (getenv('STANDARD_SHIPPING_FEE') ?: 5.99);
    }

    public function applyRounding(): bool
    {
        return getenv('APPLY_ROUNDING') === 'true';
    }

    public function roundingPrecision(): int
    {
        return (int) (getenv('ROUNDING_PRECISION') ?: 2);
    }

    public function maxItemsPerOrder(): int
    {
        return (int) (getenv('MAX_ITEMS_PER_ORDER') ?: 100);
    }

    public function vatRate(string $country): float
    {
        $rates = [
            'FR' => (float) (getenv('VAT_RATE_FR') ?: 0.2),
            'DE' => (float) (getenv('VAT_RATE_DE') ?: 0.19),
            'BE' => (float) (getenv('VAT_RATE_BE') ?: 0.21),
        ];
        
        return $rates[$country] ?? (float) (getenv('VAT_RATE_DEFAULT') ?: 0.2);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\OrderCalculatorService;
use App\Services\TaxService;
use App\Services\DiscountService;
use App\Configs\OrderConfig;
use App\Records\OrderRecord;
use PHPUnit\Framework\TestCase;

final class OrderCalculatorServiceTest extends TestCase
{
    private OrderCalculatorService $service;
    private TaxService $taxService;
    private DiscountService $discountService;
    private OrderConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Tous les services peuvent être mockés
        $this->taxService = $this->createMock(TaxService::class);
        $this->discountService = $this->createMock(DiscountService::class);
        $this->config = $this->createMock(OrderConfig::class);
        
        $this->service = new OrderCalculatorService(
            $this->taxService,
            $this->discountService,
            $this->config
        );
    }

    public function test_calculate_subtotal_with_multiple_items(): void
    {
        // Arrange
        $this->config->method('applyRounding')->willReturn(true);
        $this->config->method('roundingPrecision')->willReturn(2);
        
        $order = new OrderRecord(
            items: [
                new OrderItemRecord(price: 10.0, quantity: 2),
                new OrderItemRecord(price: 15.5, quantity: 1),
            ]
        );
        
        // Act
        $subtotal = $this->service->calculateSubtotal($order);
        
        // Assert
        $this->assertEquals(35.5, $subtotal);
    }

    public function test_calculate_total_applies_tax_and_discount(): void
    {
        // Arrange
        $this->config->method('freeShippingThreshold')->willReturn(50.0);
        $this->config->method('standardShippingFee')->willReturn(5.99);
        $this->config->method('vatRate')->with('FR')->willReturn(0.2);
        
        $this->taxService->method('calculate')->willReturn(7.0);
        $this->discountService->method('apply')->willReturn(10.0);
        
        $order = new OrderRecord(
            items: [new OrderItemRecord(price: 100.0, quantity: 1)],
            total: 100.0
        );
        
        // Act
        $result = $this->service->calculateTotal($order, 'FR', 'PROMO10');
        
        // Assert
        $this->assertEquals(100.0, $result->subtotal);
        $this->assertEquals(7.0, $result->tax);
        $this->assertEquals(10.0, $result->discount);
        $this->assertEquals(0.0, $result->shippingFee); // Gratuit car > 50
        $this->assertEquals(97.0, $result->total);
    }

    public function test_config_can_be_changed_for_tests(): void
    {
        // ✅ La config peut être modifiée sans changer le Service
        $this->config->method('freeShippingThreshold')->willReturn(100.0);  // Seuil différent
        
        $order = new OrderRecord(items: [], total: 75.0);
        
        $result = $this->service->isEligibleForFreeShipping($order);
        
        // Le seuil étant à 100, la commande à 75 n'est pas éligible
        $this->assertFalse($result);
    }
}
```

---

## 8. Récapitulatif des contraintes

| Contrainte | Règle | Exception |
|------------|-------|-----------|
| **Propriétés privées** | ❌ AUCUNE (sauf injections) | Aucune |
| **État interne** | ❌ INTERDIT | Aucune |
| **Stockage de Configs** | ✅ AUTORISÉ (immuables) | - |
| **Stockage de Services** | ✅ AUTORISÉ (dépendances) | - |
| **Stockage de données volatiles** | ❌ INTERDIT | Aucune |
| **Constructeur** | ✅ Uniquement pour l'injection | Aucune |
| **Classe `final`** | ❌ INTERDIT | Aucune |
| **Instanciation interne** | ❌ INTERDIT | Aucune |
| **Constantes privées** | ❌ INTERDITES | Aucune (utiliser Config) |
| **Méthodes statiques** | ❌ INTERDITES | Aucune |
| **Singletons globaux** | ❌ INTERDITS | Aucune |
| **`new` sur objets métier** | ❌ INTERDIT | Injecter des factories |

---

## 9. Règle d'or

> **Un Service est un conteneur pur de méthodes. Il n'a pas de mémoire, pas d'état interne, pas de cache, pas de constantes internes. Toute donnée dont il a besoin lui est fournie au moment de l'appel ou injectée via le constructeur.**
>
> **⚠️ Les Services remplacent les traits : là où on aurait utilisé un trait (couplage implicite, testabilité impossible), on utilise un service injecté (composition explicite, testabilité parfaite).**
>
> **⚠️ Les constantes privées sont interdites : toute valeur configurable doit être injectée via une Config.**
>
> **La composition est la clé : un service peut utiliser d'autres services, tous injectés dans le constructeur. Jamais d'instanciation interne, jamais de stockage d'état, jamais de constantes figées.**

```php
// ✅ Le Service parfait
class PerfectService
{
    // ✅ Uniquement des injections dans le constructeur
    public function __construct(
        private readonly AnotherService $service,   // Service injecté
        private readonly AppConfig $config,         // Config injectée (pas de constantes internes)
        private readonly UserRepository $repo,      // Repository injecté
    ) {}

    // ✅ Toutes les données arrivent en paramètres
    public function execute(OrderRecord $order, User $user): Result
    {
        // ✅ Pas d'état interne
        // ✅ Pas de cache
        // ✅ Pas de compteur
        // ✅ Pas de constantes
        // ✅ Utilisation des dépendances injectées
        
        $subtotal = $this->calculateSubtotal($order);
        
        // ✅ Les valeurs config (seuils, taux, etc.) viennent de la Config injectée
        $tax = $this->service->calculateTax($subtotal, $user->country, $this->config->vatRate($user->country));
        
        return new Result($subtotal + $tax);
    }
    
    private function calculateSubtotal(OrderRecord $order): float
    {
        return array_reduce($order->items, fn($c, $i) => $c + ($i->price * $i->quantity), 0);
    }
}

// ✅ La Config externalisée (pas de constantes dans le Service)
final class AppConfig extends AbstractConfig
{
    public function vatRate(string $country): float
    {
        // ✅ Valeurs modifiables via environnement
        return match($country) {
            'FR' => (float) (getenv('VAT_RATE_FR') ?: 0.20),
            'DE' => (float) (getenv('VAT_RATE_DE') ?: 0.19),
            'BE' => (float) (getenv('VAT_RATE_BE') ?: 0.21),
            default => (float) (getenv('VAT_RATE_DEFAULT') ?: 0.20),
        };
    }
}

// ✅ Test parfait
final class PerfectServiceTest extends TestCase
{
    public function test_execute(): void
    {
        // Tous les services peuvent être mockés
        $service = $this->createMock(AnotherService::class);
        
        // ✅ La Config peut être mockée pour simuler différents environnements
        $config = $this->createMock(AppConfig::class);
        $config->method('vatRate')->with('FR')->willReturn(0.10);  // TVA à 10% pour le test
        
        $repo = $this->createMock(UserRepository::class);
        
        $perfectService = new PerfectService($service, $config, $repo);
        
        // Test isolé, sans effets de bord, sans fichiers réels, sans base de données
        // ✅ Les valeurs de configuration sont mockées, pas figées dans le code
        $result = $perfectService->execute($order, $user);
        
        $this->assertInstanceOf(Result::class, $result);
    }
}
```
---