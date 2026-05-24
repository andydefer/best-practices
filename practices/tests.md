# Principe d'usage des Tests (Version finale)

## 1. Définition

Un **Test** est un composant qui valide le comportement attendu d'une unité de code. Il garantit que l'application fonctionne correctement et que les modifications futures ne cassent pas les fonctionnalités existantes.

```
Test → Validation du comportement → Garantie de non-régression → Documentation vivante
```

```php
final class UserServiceTest extends IntegrationTestCase
{
    public function test_getUser_returns_user_record_when_user_exists(): void
    {
        // Arrange
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        $repository = $this->createMock(UserRepository::class);
        $repository->method('find')->willReturn($user);
        $service = new UserService($repository);

        // Act
        $result = $service->getUser($user->id);

        // Assert
        $this->assertInstanceOf(UserRecord::class, $result);
        $this->assertSame($user->id, $result->id);
    }
}
```

---

## 2. Problématique à laquelle les Tests répondent

| Problème | Solution |
|----------|----------|
| **Code non fiable** | Les tests valident le comportement |
| **Régression non détectée** | Les tests préviennent les régressions |
| **Documentation inexistante** | Les tests documentent le comportement attendu |
| **Refactoring risqué** | Les tests permettent de refactoriser en confiance |

---

## 3. Principe fondamental : TOUT ce qui contient de la logique métier DOIT être testé (⚠️ RÈGLE ABSOLUE)

> **Règle d'or : Tout fichier qui contient une logique métier (condition, boucle, calcul, transformation, orchestration) DOIT avoir son test correspondant (unitaire ou d'intégration selon le composant).**

### 3.1 Ce qui DOIT être testé

| Composant | Contient de la logique ? | DOIT être testé ? | Pourquoi | Type de test |
|-----------|-------------------------|-------------------|----------|--------------|
| **Worker** | ✅ Oui (orchestration) | ✅ **OBLIGATOIRE** | Orchestration de plusieurs Tasks | Unitaire (mocks) |
| **Action** | ✅ Oui (orchestration HTTP) | ✅ **OBLIGATOIRE** | Transformation Request → Record → Data | Unitaire (mocks) |
| **Task** | ✅ Oui (traitement unique) | ✅ **OBLIGATOIRE** | Action unitaire avec logique | Unitaire (mocks) |
| **Service** | ✅ Oui (logique métier pure) | ✅ **OBLIGATOIRE** | Calculs, conditions, transformations | Unitaire (mocks) |
| **Repository** | ✅ Oui (accès données) | ✅ **OBLIGATOIRE** | Requêtes, filtres, pagination | Intégration (base de données) |
| **Command** | ✅ Oui (logique console) | ✅ **OBLIGATOIRE** | Logique d'export, import, nettoyage | Unitaire (mocks) |
| **Middleware** | ✅ Oui (logique transversale) | ✅ **OBLIGATOIRE** | Authentification, autorisation, logging | Intégration (contexte HTTP) |
| **FormRequest** | ✅ Oui (règles validation) | ✅ **OBLIGATOIRE** | Règles de validation, autorisation | Intégration (requêtes HTTP) |
| **Enum** | ✅ Oui (méthodes métier) | ✅ **OBLIGATOIRE** | `isAdmin()`, `getLabel()`, `fromValue()` | Unitaire |
| **Cast** | ✅ Oui (transformation) | ✅ **OBLIGATOIRE** | `get()`, `set()` | Unitaire |
| **Trait** | ✅ Oui (logique réutilisable) | ✅ **OBLIGATOIRE** | Méthodes partagées entre classes | Unitaire (classe factice) |
| **TypedRecords** | ✅ Oui (collection typée) | ✅ **OBLIGATOIRE** | `add()`, `filter()`, `map()`, validation types | Unitaire |
| **Model** | ✅ Oui (accesseurs, mutateurs, scopes) | ✅ **OBLIGATOIRE** | `fullName()`, `scopeActive()`, relations | Intégration (base de données) |
| **Route** | ✅ Oui (accès, middleware) | ✅ **OBLIGATOIRE** | Authentification, autorisation, méthodes HTTP | Intégration (requêtes HTTP) |

### 3.2 Ce qui ne DOIT PAS être testé

| Composant | Contient de la logique ? | DOIT être testé ? | Pourquoi |
|-----------|-------------------------|-------------------|----------|
| **Record** | ❌ Non | ❌ Non requis | Sac de données typé, pas de logique |
| **Data** | ❌ Non | ❌ Non requis | Réponse API, pas de logique |
| **Migration** | ❌ Non | ❌ Non requis | Structure de base de données uniquement |
| **Seeder** | ❌ Non | ❌ Non requis | Données de test statiques |
| **Config** | ❌ Non | ❌ Non requis | Configuration statique |
| **Provider** | ❌ Non | ❌ Non requis | Enregistrement de services |

### 3.3 Vérification rapide

```php
// ✅ Ce code contient de la logique → DOIT être testé
final class UserService
{
    public function isAdult(UserRecord $user): bool
    {
        return $user->age >= 18;  // Condition → tester
    }
    
    public function getActiveUsers(TypedRecords $users): TypedRecords
    {
        return $users->filter(fn($user) => $user->isActive);  // Logique → tester
    }
}

// ✅ Ce code contient de la logique (accesseur) → DOIT être testé
final class User extends Model
{
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['first_name'] . ' ' . $attributes['last_name'],
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);  // Logique → tester
    }
}

// ❌ Ce code ne contient PAS de logique → NE DOIT PAS être testé
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,   // Pas de logique
        public readonly int $age,       // Pas de logique
    ) {}
}

// ❌ Migration sans logique → NE DOIT PAS être testée
final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
```

---

## 4. Types de tests

### 4.1 Unit Tests (isolés, sans base de données)

> **Les tests unitaires testent une unité de code isolément (une classe, une méthode). Toutes les dépendances sont mockées. La base de données n'est PAS utilisée sauf exception.**

**⚠️ Règle :** Les tests unitaires doivent éviter au maximum l'utilisation de la base de données. Notre architecture est pensée pour être mockable.

| Composant | Test unitaire ? | Base de données ? | Localisation |
|-----------|----------------|-------------------|--------------|
| **Service** | ✅ Oui | ❌ Non | `tests/Unit/Services/` |
| **Task** | ✅ Oui | ❌ Non | `tests/Unit/Tasks/` |
| **Worker** | ✅ Oui | ❌ Non | `tests/Unit/Workers/` |
| **Action** | ✅ Oui | ❌ Non | `tests/Unit/Actions/` |
| **Enum** | ✅ Oui | ❌ Non | `tests/Unit/Enums/` |
| **Cast** | ✅ Oui | ❌ Non | `tests/Unit/Casts/` |
| **Trait** | ✅ Oui | ❌ Non | `tests/Unit/Traits/` |
| **TypedRecords** | ✅ Oui | ❌ Non | `tests/Unit/TypedRecords/` |
| **Command** | ✅ Oui | ❌ Non | `tests/Unit/Commands/` |

### 4.2 Integration Tests (avec base de données ou contexte complet)

> **Les tests d'intégration testent une fonctionnalité complète avec une vraie base de données en mémoire ou un contexte HTTP réel.**

**⚠️ Règle :** Les tests de log, notifications, repositories, models, routes et middlewares doivent utiliser de vraies interactions en base de données.

| Composant | Test d'intégration ? | Base de données ? | Localisation |
|-----------|---------------------|-------------------|--------------|
| **Repository** | ✅ Oui | ✅ Oui | `tests/Integration/Repositories/` |
| **Model** | ✅ Oui | ✅ Oui | `tests/Integration/Models/` |
| **Route** | ✅ Oui | ✅ Oui | `tests/Integration/Routes/` |
| **Middleware** | ✅ Oui | ✅ Oui | `tests/Integration/Middlewares/` |
| **FormRequest** | ✅ Oui | ✅ Oui | `tests/Integration/FormRequests/` |
| **Action (complète)** | ✅ Oui | ✅ Oui | `tests/Integration/Actions/` |
| **Worker (complet)** | ✅ Oui | ✅ Oui | `tests/Integration/Workers/` |
| **Workflow complet** | ✅ Oui | ✅ Oui | `tests/Integration/Workflows/` |

### 4.3 Règle de choix : Unit vs Integration

| Situation | Type de test | Base de données | Localisation |
|-----------|--------------|-----------------|--------------|
| **Logique métier pure (Service)** | Unit | ❌ Non | `tests/Unit/Services/` |
| **Orchestration (Worker)** | Unit | ❌ Non | `tests/Unit/Workers/` |
| **Calcul / transformation** | Unit | ❌ Non | `tests/Unit/Services/` |
| **Validation (FormRequest)** | Integration | ✅ Oui | `tests/Integration/FormRequests/` |
| **Enum** | Unit | ❌ Non | `tests/Unit/Enums/` |
| **Cast** | Unit | ❌ Non | `tests/Unit/Casts/` |
| **Trait** | Unit | ❌ Non | `tests/Unit/Traits/` |
| **TypedRecords** | Unit | ❌ Non | `tests/Unit/TypedRecords/` |
| **Command** | Unit | ❌ Non | `tests/Unit/Commands/` |
| **Repository** | Integration | ✅ Oui | `tests/Integration/Repositories/` |
| **Model** | Integration | ✅ Oui | `tests/Integration/Models/` |
| **Route** | Integration | ✅ Oui | `tests/Integration/Routes/` |
| **Middleware** | Integration | ✅ Oui | `tests/Integration/Middlewares/` |
| **Action (route complète)** | Integration | ✅ Oui | `tests/Integration/Actions/` |
| **Worker (complet)** | Integration | ✅ Oui | `tests/Integration/Workers/` |

### 4.4 Règle de décision simplifiée pour choisir le type de test

| Le test utilise... | Type de test | Heritage |
|-------------------|--------------|----------|
| **Uniquement des mocks** (pas de DB, pas de FS, pas d'API, pas d'heure système) | Unitaire | `UnitTestCase` |
| **Une vraie base de données** | Intégration | `IntegrationTestCase` |
| **Le système de fichiers** (logs, uploads) | Intégration | `IntegrationTestCase` |
| **L'heure système** (Carbon, time()) | Intégration | `IntegrationTestCase` |
| **Une API externe** (même mockée via Http fake) | Intégration | `IntegrationTestCase` |
| **Le cache, session, queue** | Intégration | `IntegrationTestCase` |

**Règle pragmatique :** 
- Si tu utilises `$this->createMock()` → `UnitTestCase`
- Si tu utilises `User::create()`, `Storage::fake()`, `Http::fake()`, `Carbon::setTestNow()` → `IntegrationTestCase`

---

## 5. Hiérarchie des TestCases (⚠️ RÈGLE ABSOLUE)

> **La base de test est maintenant séparée en deux classes distinctes pour forcer les bonnes pratiques et empêcher les mauvais usages.**

### 5.1 UnitTestCase (tests sans Laravel)

```php
<?php
// tests/UnitTestCase.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for pure unit tests that don't need Laravel.
 * No Laravel bootstrap, no database, no migrations.
 * 
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - NE PEUVENT PAS utiliser la base de données
 * - NE PEUVENT PAS utiliser les facades Laravel
 * - DOIVENT mocker toutes leurs dépendances
 */
abstract class UnitTestCase extends BaseTestCase
{
    // Rien du tout - tests purement unitaires
    // Pas de setUp(), pas de tearDown(), pas de Laravel
}
```

### 5.2 IntegrationTestCase (tests avec Laravel)

```php
<?php
// tests/IntegrationTestCase.php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for integration tests that need Laravel.
 * Full Laravel bootstrap, database support, HTTP client.
 * 
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - PEUVENT utiliser la base de données (SQLite memory)
 * - PEUVENT utiliser les facades Laravel
 * - PEUVENT faire des requêtes HTTP
 */
abstract class IntegrationTestCase extends Orchestra
{
    protected LaravelBootstrapper $laravelBootstrapper;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        // Créer le bootstrapper avec le chemin personnalisé pour les tests
        $this->laravelBootstrapper = new LaravelBootstrapper();
        $this->laravelBootstrapper->setCustomBootstrapPath(__DIR__ . '/bootstrap/app.php');

        // Enregistrer le bootstrapper dans le conteneur
        $this->app->instance(LaravelBootstrapper::class, $this->laravelBootstrapper);

        // Bootstrap Laravel for tests
        $this->laravelBootstrapper->bootstrap();

        // Run migrations and seed data
        $this->runDatabaseMigrations();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->laravelBootstrapper->reset();
        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure SQLite in-memory database
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('view.paths', [__DIR__ . '/Fixtures/views']);
    }

    protected function getPackageProviders($app)
    {
        return [
            DirectiveServiceProvider::class,
        ];
    }

    protected function runDatabaseMigrations(): void
    {
        $migrationPath = __DIR__ . '/database/migrations';

        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }

        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--force' => true,
        ])->run();
    }

    protected function seedTestData(): void
    {
        // Create test users
        $users = [];
        for ($i = 1; $i <= 5; $i++) {
            $users[] = TestUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'is_active' => $i % 2 === 0,
                'email_verified_at' => $i <= 3 ? now() : null,
            ]);
        }

        // Create test posts
        foreach ($users as $user) {
            for ($i = 1; $i <= 3; $i++) {
                TestPost::create([
                    'user_id' => $user->id,
                    'title' => "Post {$i} by {$user->name}",
                    'content' => "This is the content of post {$i}",
                    'is_published' => $i % 2 === 0,
                    'published_at' => $i % 2 === 0 ? now() : null,
                    'tags' => ['test', 'fixture'],
                ]);
            }
        }
    }
}
```

### 5.3 Ancien TestCase (déprécié)

```php
<?php
// tests/TestCase.php (alias pour compatibilité - optionnel)

declare(strict_types=1);

namespace AndyDefer\Directive\Tests;

/**
 * @deprecated Use UnitTestCase or IntegrationTestCase instead
 * 
 * Cette classe est maintenue uniquement pour la compatibilité ascendante.
 * Les nouveaux tests NE DOIVENT PAS l'utiliser.
 */
abstract class TestCase extends UnitTestCase
{
    // Pour compatibilité ascendante uniquement
}
```

### 5.4 Règle absolue : Choix du TestCase parent

| Type de test | DOIT hériter de | Localisation | Interdiction |
|--------------|-----------------|--------------|--------------|
| **Test unitaire** | `UnitTestCase` | `tests/Unit/` | ❌ Base de données |
| **Test d'intégration** | `IntegrationTestCase` | `tests/Integration/` | ❌ Code non testable |

**⚠️ Conséquences :**
- Un test unitaire qui hérite de `IntegrationTestCase` est **une erreur architecturale**
- Un test d'intégration qui hérite de `UnitTestCase` ne pourra **pas** accéder à la base de données
- L'ancien `TestCase` est **déprécié** et ne doit plus être utilisé

```php
// ✅ BON - Test unitaire pur
final class CalculatorServiceTest extends UnitTestCase
{
    public function test_add_returns_sum(): void
    {
        $service = new CalculatorService();
        $result = $service->add(2, 3);
        $this->assertSame(5, $result);
    }
}

// ❌ MAUVAIS - Test unitaire qui utilise IntegrationTestCase
final class CalculatorServiceTest extends IntegrationTestCase  // ← Erreur !
{
    // Cela bootstrap Laravel pour rien, rend le test lent
}

// ✅ BON - Test d'intégration
final class UserRepositoryTest extends IntegrationTestCase
{
    public function test_find_returns_user(): void
    {
        $user = User::create(['name' => 'John']);
        $repository = new UserRepository();
        
        $found = $repository->find($user->id);
        
        $this->assertNotNull($found);
    }
}

// ❌ MAUVAIS - Test d'intégration qui hérite de UnitTestCase
final class UserRepositoryTest extends UnitTestCase  // ← Erreur !
{
    public function test_find_returns_user(): void
    {
        // User::create() n'existe pas dans UnitTestCase !
        // Impossible de tester correctement
    }
}
```

---

## 6. Organisation des tests par module (Mini-Package)

> **Les tests d'un mini-package DOIVENT être organisés dans le répertoire `tests/{ModuleName}/`.**

### 6.1 Structure des tests par module

```
tests/
├── UnitTestCase.php                         # Base pour tests unitaires
├── IntegrationTestCase.php                  # Base pour tests d'intégration
├── Unit/                                    # Tests unitaires globaux
│   ├── Services/
│   │   └── GlobalServiceTest.php
│   └── Helpers/
│       └── GlobalHelperTest.php
├── Integration/                             # Tests d'intégration globaux
│   ├── Routes/
│   │   └── ApiRoutesTest.php
│   └── Middlewares/
│       └── AuthMiddlewareTest.php
├── Logger/                                  # Module Logger (mini-package)
│   ├── Unit/                                # Tests unitaires du module
│   │   ├── Enums/
│   │   │   └── LogLevelTest.php            # extends UnitTestCase
│   │   ├── Records/
│   │   │   ├── LogRecordTest.php           # extends UnitTestCase
│   │   │   └── LogQueryRecordTest.php      # extends UnitTestCase
│   │   ├── Config/
│   │   │   └── LoggerConfigTest.php        # extends UnitTestCase
│   │   ├── Services/
│   │   │   ├── LogPathServiceTest.php      # extends UnitTestCase
│   │   │   └── Tasks/
│   │   │       ├── WriteLogTaskTest.php    # extends UnitTestCase
│   │   │       ├── QueryLogsTaskTest.php   # extends UnitTestCase
│   │   │       └── StreamLogsTaskTest.php  # extends UnitTestCase
│   │   └── LoggerTest.php                  # extends UnitTestCase
│   └── Integration/                         # Tests d'intégration du module
│       ├── LoggerIntegrationTest.php       # extends IntegrationTestCase
│       └── Routes/
│           └── LogRoutesTest.php           # extends IntegrationTestCase
├── Currency/                                # Module Currency (mini-package)
│   ├── Unit/
│   │   ├── Enums/
│   │   │   └── CurrencyCodeTest.php
│   │   ├── Records/
│   │   │   └── ConversionRecordTest.php
│   │   ├── Config/
│   │   │   └── CurrencyConfigTest.php
│   │   ├── Services/
│   │   │   ├── CurrencyConverterTest.php
│   │   │   └── Tasks/
│   │   │       ├── FetchRateTaskTest.php
│   │   │       └── CacheRateTaskTest.php
│   │   └── CurrencyConverterTest.php
│   └── Integration/
│       ├── CurrencyIntegrationTest.php
│       └── Repositories/
│           └── RateRepositoryTest.php
├── Notification/                            # Module Notification (mini-package)
│   ├── Unit/
│   │   ├── Enums/
│   │   ├── Records/
│   │   ├── Services/
│   │   └── NotifierTest.php
│   └── Integration/
│       ├── NotificationIntegrationTest.php
│       └── Channels/
│           └── MailChannelTest.php
├── Domain/                                  # Code métier spécifique
│   ├── Users/
│   │   ├── Unit/
│   │   │   ├── Services/
│   │   │   │   └── UserServiceTest.php    # extends UnitTestCase
│   │   │   └── Tasks/
│   │   │       └── CreateUserTaskTest.php # extends UnitTestCase
│   │   └── Integration/
│   │       ├── Models/
│   │       │   └── UserTest.php           # extends IntegrationTestCase
│   │       ├── Repositories/
│   │       │   └── UserRepositoryTest.php # extends IntegrationTestCase
│   │       └── Routes/
│   │           └── UserRoutesTest.php     # extends IntegrationTestCase
│   └── Orders/
│       ├── Unit/
│       │   ├── Services/
│       │   │   └── OrderCalculatorTest.php
│       │   └── Tasks/
│       │       └── ProcessOrderTaskTest.php
│       └── Integration/
│           ├── Models/
│           │   └── OrderTest.php
│           └── Repositories/
│               └── OrderRepositoryTest.php
├── Fixtures/
│   ├── ReplyerFixture.php
│   ├── SimpleTestData.php
│   └── Models/
│       ├── TestUser.php
│       └── TestPost.php
└── bootstrap/
    └── app.php                              # Bootstrap personnalisé pour les tests
```

### 6.2 Pourquoi cette organisation ?

| Problème de l'ancienne organisation | Solution de la nouvelle organisation |
|--------------------------------------|--------------------------------------|
| Tests éparpillés dans `Unit/` et `Feature/` | Tests regroupés par module |
| Difficulté à transporter un module | Les tests voyagent avec le module |
| Pas de visibilité sur ce qui est testé | La structure des tests reflète la structure du code |
| Maintenance difficile | Chaque module a ses propres tests |
| `Feature/` mal nommé | Renommé en `Integration/` plus approprié |

### 6.3 Exemple de test unitaire dans un module

```php
<?php

declare(strict_types=1);

namespace Tests\Logger\Unit\Services\Tasks;

use AndyDefer\Logger\Services\Tasks\WriteLogTask;
use AndyDefer\Logger\Config\LoggerConfig;
use Tests\UnitTestCase;

final class WriteLogTaskTest extends UnitTestCase
{
    private string $tempLogFile;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogFile = sys_get_temp_dir() . '/test.log';
    }
    
    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }
        parent::tearDown();
    }
    
    public function test_execute_writes_message_to_log_file(): void
    {
        // Arrange
        $config = LoggerConfig::defaults()->withLogPath($this->tempLogFile);
        $task = new WriteLogTask($config);
        
        // Act
        $task->execute('Test message', 'info');
        
        // Assert
        $content = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('Test message', $content);
        $this->assertStringContainsString('info', $content);
    }
}
```

### 6.4 Exemple de test d'intégration dans un module

```php
<?php

declare(strict_types=1);

namespace Tests\Logger\Integration\Routes;

use AndyDefer\Logger\Models\LogEntry;
use Tests\IntegrationTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class LogRoutesTest extends IntegrationTestCase
{
    use RefreshDatabase;
    
    public function test_get_logs_returns_paginated_results(): void
    {
        // Arrange
        LogEntry::create([
            'message' => 'Test log message',
            'level' => 'info',
            'context' => json_encode(['user_id' => 1]),
        ]);
        
        // Act
        $response = $this->getJson('/api/logs');
        
        // Assert
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'message', 'level', 'created_at']
            ],
            'meta' => ['current_page', 'total']
        ]);
    }
}
```

---

## 7. Convention de nommage (⚠️ STRICT)

### 7.1 Nom du fichier

> **Le fichier de test DOIT se terminer par `Test.php`.**

```php
// ✅ BON
UserServiceTest.php
CreateUserActionTest.php
SendWelcomeEmailTaskTest.php

// ❌ MAUVAIS
UserServiceSpec.php
UserServiceTestCase.php
```

### 7.2 Nom de la classe

> **La classe de test DOIT avoir le même nom que le fichier.**

```php
// ✅ BON
final class UserServiceTest extends UnitTestCase { ... }

// ❌ MAUVAIS
final class TestUserService extends UnitTestCase { ... }
```

### 7.3 Nom des méthodes (⚠️ STRICT)

> **Les méthodes de test DOIVENT commencer par `test_` suivies d'une description en `snake_case` décrivant le comportement attendu.**

```php
// ✅ BON
public function test_getUser_returns_user_record_when_user_exists(): void
public function test_getUser_throws_exception_when_user_not_found(): void
public function test_calculateTotal_returns_sum_of_items(): void

// ❌ MAUVAIS
public function testGetUser(): void           // ❌ Pas de snake_case
public function testGetUserWhenExists(): void // ❌ Pas de snake_case
public function test_user_exists(): void      // ❌ Pas de préfixe test_
```

### 7.4 Structure du nom

```
test_{methodName}_{expectedBehavior}_{condition}
```

| Partie | Exemple |
|--------|---------|
| `test_{methodName}` | `test_getUser` |
| `_{expectedBehavior}` | `_returns_user_record` |
| `_{condition}` | `_when_user_exists` |

---

## 8. Structure AAA (Arrange-Act-Assert)

> **⚠️ TOUT test DOIT suivre la structure AAA (Arrange, Act, Assert).**

```php
public function test_calculateTotal_returns_sum_of_items(): void
{
    // Arrange (Préparer les données)
    $item1 = new OrderItemRecord(price: 10.0, quantity: 2);
    $item2 = new OrderItemRecord(price: 5.0, quantity: 1);
    $order = new OrderRecord(items: [$item1, $item2]);
    $service = new PriceCalculatorService();

    // Act (Exécuter)
    $total = $service->calculate($order);

    // Assert (Vérifier)
    $this->assertSame(25.0, $total);
}
```

### 8.1 Pourquoi AAA ?

| Étape | Rôle |
|-------|------|
| **Arrange** | Préparer l'environnement, les données, les mocks |
| **Act** | Exécuter la méthode testée |
| **Assert** | Vérifier le résultat |

---

## 9. Interdiction du mot-clé `final` sur les classes destinées aux tests unitaires (⚠️ RÈGLE ABSOLUE)

> **⚠️ CRITIQUE : Les classes qui sont destinées à être testées unitairement (Services, Tasks, Workers, Actions, etc.) NE DOIVENT PAS être déclarées `final`. Le mot-clé `final` empêche PHPUnit de créer des mocks, rendant les tests impossibles.**

### 9.1 Problème : Le mot-clé `final` bloque le mocking

```php
// ❌ MAUVAIS - Classe finale impossible à mocker
final class QueryLogsTask
{
    public function execute(LogQueryRecord $query): TypedRecords
    {
        // ...
    }
}

// Dans le test
$queryTask = $this->createMock(QueryLogsTask::class);
// ❌ Exception: Class "QueryLogsTask" is declared "final" and cannot be doubled
```

**Pourquoi cela pose problème ?**

| Problème | Conséquence |
|----------|-------------|
| **Impossible de mocker** | PHPUnit ne peut pas créer de mock d'une classe finale |
| **Tests impossibles** | On ne peut pas isoler la classe testée de ses dépendances |
| **Couplage forcé** | On est obligé d'utiliser la vraie implémentation |
| **Tests d'intégration seulement** | Impossible de faire des tests unitaires purs |

### 9.2 Solution : NE PAS utiliser `final` sur les classes à tester

```php
// ✅ BON - Classe sans final, mockable
class QueryLogsTask  // Pas de "final"
{
    public function execute(LogQueryRecord $query): TypedRecords
    {
        // ...
    }
}

// Dans le test - Ça fonctionne !
$queryTask = $this->createMock(QueryLogsTask::class);
$queryTask->expects($this->once())->method('execute')->willReturn($expectedResults);
```

### 9.3 Quand utiliser `final` (cas autorisés)

| Cas | Autorisation | Exemple |
|-----|--------------|---------|
| **Classes sans logique métier** | ✅ Oui | `Record`, `Data`, `Config` (Value Objects) |
| **Classes sans dépendances** | ✅ Oui | `Enum`, `AbstractRecord`, `AbstractData` |
| **Classes de production uniquement** | ⚠️ À éviter | Préférer ne pas mettre `final` |
| **Classes avec dépendances** | ❌ **INTERDIT** | Services, Tasks, Workers, Actions |

### 9.4 Comparaison : final vs non-final

| Aspect | Avec `final` | Sans `final` |
|--------|--------------|--------------|
| **Mockabilité** | ❌ Impossible | ✅ Possible |
| **Testabilité unitaire** | ❌ Impossible | ✅ Possible |
| **Performance** | ⚠️ Théoriquement meilleure | ✅ Négligeable |
| **Héritage** | ❌ Interdit | ✅ Possible (mais rare) |
| **Sécurité** | ⚠️ Empêche l'héritage malveillant | ✅ Géré par d'autres moyens |

### 9.5 Règle de décision

```php
// ❌ INTERDIT - Classe avec logique ET dépendances
final class UserService  // ← Supprimer "final"
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {}
}

// ✅ AUTORISÉ - Record sans logique
final class UserRecord extends AbstractRecord  // ← final autorisé
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {}
}

// ✅ AUTORISÉ - Enum sans dépendances
final class UserRole extends AbstractEnum  // ← final autorisé
{
    public const ADMIN = 'admin';
    public const USER = 'user';
}
```

### 9.6 Exemple d'erreur et correction

**Erreur PHPUnit :**
```
PHPUnit\Framework\MockObject\Generator\ClassIsFinalException: 
Class "AndyDefer\BestPractices\Logger\Services\Tasks\QueryLogsTask" 
is declared "final" and cannot be doubled
```

**Solution :**

```php
// ❌ Avant (cause l'erreur)
final class QueryLogsTask
{
    // ...
}

// ✅ Après (correction)
class QueryLogsTask  // Suppression du mot-clé "final"
{
    // ...
}
```

### 9.7 Récapitulatif des classes concernées

| Type de classe | `final` autorisé ? | Raison |
|----------------|-------------------|--------|
| **Service** | ❌ Non | Contient de la logique, doit être mockable |
| **Task** | ❌ Non | Contient de la logique, doit être mockable |
| **Worker** | ❌ Non | Contient de l'orchestration, doit être mockable |
| **Action** | ❌ Non | Contient de l'orchestration HTTP, doit être mockable |
| **Repository** | ❌ Non | Accès base de données, doit être mockable |
| **Command** | ❌ Non | Logique console, doit être mockable |
| **Middleware** | ❌ Non | Logique transversale, doit être mockable |
| **Enum** | ✅ Oui | Pas de dépendances, pas de logique complexe |
| **Record** | ✅ Oui | Sac de données immutable |
| **Data** | ✅ Oui | Réponse API immutable |
| **Config** (Value Object) | ✅ Oui | Configuration immutable |
| **Abstract Class** | ✅ Oui | Par définition abstraite |

### 9.8 Règle d'or

> **Pour les classes destinées aux tests unitaires (Services, Tasks, Workers, Actions, Repositories, Commands, Middlewares), le mot-clé `final` est STRICTEMENT INTERDIT. Ces classes DOIVENT pouvoir être mockées par PHPUnit.**
>
> **Le mot-clé `final` est autorisé UNIQUEMENT pour les classes sans logique métier (Records, Data, Config, Enums).**

```php
// ✅ BON - Service mockable
class UserService  // Pas de "final"
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {}
}

// ✅ BON - Record avec final autorisé
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}

// ❌ MAUVAIS - Service avec final (non testable)
final class UserService  // ← Supprimer "final" immédiatement
{
    // ...
}
```

---

## 10. Création des données de test (⚠️ RÈGLE FERME)

> **Les données de test DOIVENT être créées explicitement avec `Model::create()`. L'utilisation des factories Laravel est INTERDITE.**

### 10.1 Pourquoi ?

| Problème des factories | Solution avec `Model::create()` |
|------------------------|--------------------------------|
| Masquent les données réelles | Les données sont explicites |
| Créent des scénarios magiques | Le scénario est écrit en code |
| Difficile à maintenir (plusieurs layers) | Un seul endroit : le test |
| Fausse la compréhension du métier | On voit vraiment ce qui est testé |
| Nécessite de connaître l'API factory | Lisible par n'importe qui |
| Peut créer des données invalides | Tu contrôles chaque champ |
| Difficile à déboguer | Évident à comprendre |

### 10.2 Exemple concret

```php
// ✅ BON - Scénario clair et explicite
final class ClientAnalyticsTest extends IntegrationTestCase
{
    public function test_getClientAnalytics_returns_correct_totals(): void
    {
        // Arrange - Client avec commandes variées
        $client = Client::create([
            'name' => 'Acme Corp',
            'email' => 'contact@acme.com',
        ]);
        
        // 2 commandes annulées
        Order::create([
            'client_id' => $client->id,
            'status' => 'cancelled',
            'amount' => 100,
            'cancelled_at' => now(),
        ]);
        
        Order::create([
            'client_id' => $client->id,
            'status' => 'cancelled',
            'amount' => 200,
            'cancelled_at' => now()->subDay(),
        ]);
        
        // 1 commande en attente
        Order::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'amount' => 300,
        ]);
        
        // 2 commandes payées
        Order::create([
            'client_id' => $client->id,
            'status' => 'paid',
            'amount' => 400,
            'paid_at' => now(),
        ]);
        
        Order::create([
            'client_id' => $client->id,
            'status' => 'paid',
            'amount' => 500,
            'paid_at' => now()->subDay(),
        ]);
        
        // Act
        $analytics = (new ClientAnalyticsService())->getStats($client);
        
        // Assert
        $this->assertSame(2, $analytics->cancelledOrders);
        $this->assertSame(1, $analytics->pendingOrders);
        $this->assertSame(2, $analytics->paidOrders);
        $this->assertSame(900, $analytics->totalPaidAmount); // 400 + 500
    }
}
```

### 10.3 Cas où factoriser est accepté

Si tu as BESOIN de factoriser (vraiment), crée **ta propre méthode utilitaire dans le test** :

```php
private function createOrderWithStatus(int $clientId, string $status, ?float $amount = null): Order
{
    $orderData = [
        'client_id' => $clientId,
        'status' => $status,
        'amount' => $amount ?? fake()->numberBetween(50, 1000),
    ];
    
    if ($status === 'cancelled') {
        $orderData['cancelled_at'] = now();
    }
    
    if ($status === 'paid') {
        $orderData['paid_at'] = now();
    }
    
    return Order::create($orderData);
}
```

**⚠️ Cette factorisation est LOCALE au test, pas globale via factory.**

### 10.4 Règle d'or

> **Si tu ne peux pas relire et comprendre un scénario de test en 30 secondes, c'est que tu as trop caché la création des données.**

---

## 11. Configuration PHPUnit (phpunit.xml)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="random"
         resolveDependencies="true">
    
    <testsuites>
        <!-- Tests unitaires (rapides, sans Laravel) -->
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
            <directory>tests/*/Unit</directory>
        </testsuite>
        
        <!-- Tests d'intégration (avec Laravel et base de données) -->
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
            <directory>tests/*/Integration</directory>
        </testsuite>
    </testsuites>
    
    <source>
        <include>
            <directory>app</directory>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>app/Console/Commands/stubs</directory>
            <directory>app/Providers</directory>
            <directory>database</directory>
        </exclude>
    </source>
    
    <php>
        <!-- Environnement de test -->
        <env name="APP_ENV" value="testing"/>
        <env name="APP_DEBUG" value="true"/>
        <env name="APP_KEY" value="base64:testkey1234567890testkey1234567890="/>
        
        <!-- Base de données de test (SQLite mémoire) -->
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        
        <!-- Cache désactivé pour les tests -->
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        
        <!-- Mail désactivé pour les tests -->
        <env name="MAIL_MAILER" value="array"/>
        
        <!-- Broadcast désactivé -->
        <env name="BROADCAST_DRIVER" value="log"/>
    </php>
    
    <coverage>
        <include>
            <directory>app</directory>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>app/Console/Commands/stubs</directory>
            <directory>app/Providers</directory>
            <directory>database</directory>
        </exclude>
        <report>
            <html outputDirectory="tests/coverage"/>
            <clover outputFile="tests/coverage/clover.xml"/>
            <text outputFile="php://stdout" showUncoveredFiles="true"/>
        </report>
    </coverage>
    
    <logging>
        <junit outputFile="tests/reports/junit.xml"/>
    </logging>
</phpunit>
```

---

## 12. Tableau récapitulatif complet par composant

| Composant | Contient de la logique ? | DOIT être testé ? | Type de test | Base de données | Localisation |
|-----------|-------------------------|-------------------|--------------|-----------------|--------------|
| **Action** | ✅ Orchestration HTTP | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Actions/` ou `tests/{Module}/Unit/Actions/` |
| **Action (intégration)** | ✅ Route complète | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Actions/` ou `tests/{Module}/Integration/Actions/` |
| **Cast** | ✅ Transformation | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Casts/` ou `tests/{Module}/Unit/Casts/` |
| **Command** | ✅ Logique console | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Commands/` ou `tests/{Module}/Unit/Commands/` |
| **Data** | ❌ Aucune | ❌ NON | - | - | - |
| **Enum** | ✅ Méthodes métier | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Enums/` ou `tests/{Module}/Unit/Enums/` |
| **FormRequest** | ✅ Règles validation | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/FormRequests/` ou `tests/{Module}/Integration/FormRequests/` |
| **Middleware** | ✅ Logique transversale | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Middlewares/` ou `tests/{Module}/Integration/Middlewares/` |
| **Migration** | ❌ Structure DB | ❌ NON | - | - | - |
| **Model** | ✅ Accesseurs, mutateurs, scopes | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Models/` ou `tests/{Module}/Integration/Models/` |
| **Record** | ❌ Aucune | ❌ NON | - | - | - |
| **Repository** | ✅ Accès données, requêtes | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Repositories/` ou `tests/{Module}/Integration/Repositories/` |
| **Route** | ✅ Accès, middleware | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Routes/` ou `tests/{Module}/Integration/Routes/` |
| **Seeder** | ❌ Données statiques | ❌ NON | - | - | - |
| **Service** | ✅ Logique métier pure | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Services/` ou `tests/{Module}/Unit/Services/` |
| **Task** | ✅ Traitement unique | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Tasks/` ou `tests/{Module}/Unit/Tasks/` |
| **Trait** | ✅ Logique réutilisable | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Traits/` ou `tests/{Module}/Unit/Traits/` |
| **TypedRecords** | ✅ Collection typée | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/TypedRecords/` ou `tests/{Module}/Unit/TypedRecords/` |
| **Worker** | ✅ Orchestration de Tasks | ✅ OUI | Unitaire | ❌ Non | `tests/Unit/Workers/` ou `tests/{Module}/Unit/Workers/` |
| **Worker (intégration)** | ✅ Workflow complet | ✅ OUI | Intégration | ✅ Oui | `tests/Integration/Workers/` ou `tests/{Module}/Integration/Workers/` |

---

## 13. Tableau récapitulatif des interdictions pour la testabilité

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| `final` sur les Services/Tasks/Workers | Empêche le mocking | Supprimer `final` |
| `Log::info()` direct | Appel statique non mockable | Interface `LoggerInterface` injectée |
| `User::find()` direct | Appel statique non mockable | Repository injecté |
| `Cache::put()` direct | Facade statique non mockable | Interface `CacheInterface` injectée |
| `Mail::send()` direct | Facade statique non mockable | Interface `MailerInterface` injectée |
| `DB::table()` direct | Facade statique non mockable | Repository avec interface |
| `new` dans le constructeur | Coupling caché non mockable | Injection de dépendances |
| Helper retournant une instance | Appel statique déguisé | Injection de dépendances |
| Hériter de `TestCase` | Ancienne classe dépréciée | Hériter de `UnitTestCase` ou `IntegrationTestCase` |
| Mettre des tests unitaires dans `tests/Feature/` | Dossier mal nommé | Utiliser `tests/Integration/` |
| Factories Laravel | Masquent les données réelles | Création explicite avec `Model::create()` |

---

## 14. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom fichier** | `{Component}Test.php` |
| **Nom classe** | `{Component}Test` |
| **Nom méthode** | `test_{methodName}_{expectedBehavior}_{condition}` |
| **Structure** | AAA (Arrange, Act, Assert) |
| **Type test** | Unit = classe isolée (sans DB), Integration = avec DB ou contexte HTTP |
| **Héritage** | Unit = `UnitTestCase`, Integration = `IntegrationTestCase` |
| **Organisation** | Tests regroupés par module dans `tests/{ModuleName}/` |
| **Dossier Feature** | ❌ Renommé en `Integration/` |
| **Création données** | ❌ Interdiction des factories, ✅ création explicite |
| **Mocking** | Utiliser les mocks pour les dépendances |
| **Base de données** | Unit = ❌ éviter, Integration = ✅ utiliser |
| **final** | ❌ Interdit sur les classes mockables |

---

## 15. Règle d'or finale

> **ZÉRO `final` sur les classes avec logique. ZÉRO appel statique. ZÉRO factory. TOUTES les dépendances injectées. TOUTES les données explicites.**
>
> **Si vous voyez `final class UserService` ou `Log::info()` dans un Service, ou `User::factory()->create()` dans un test, c'est une erreur.**
>
> **Les tests unitaires héritent de `UnitTestCase` (pas de Laravel). Les tests d'intégration héritent de `IntegrationTestCase` (Laravel complet). Le dossier `Feature/` n'existe plus, il est remplacé par `Integration/`.**
>
> **Un test doit être explicite, isolé, rapide et lisible. Si un test est difficile à écrire, c'est que ton code est difficile à tester. Refactorise. Les données de test sont créées explicitement avec `Model::create()`, pas avec des factories.**

```php
// ✅ Ce qui est testable
class TestableService  // Pas de "final"
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
    ) {}
}

// ✅ Le test unitaire associé
final class TestableServiceTest extends UnitTestCase  // ← UnitTestCase
{
    public function test_execute_returns_success_when_user_exists(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        $repository = $this->createMock(UserRepository::class);
        $repository->method('find')->willReturn(new UserRecord(id: 1, name: 'John'));
        $service = new TestableService($logger, $repository);
        
        // Act
        $result = $service->execute(1);
        
        // Assert
        $this->assertTrue($result);
    }
}

// ✅ Le test d'intégration avec données explicites
final class UserRepositoryTest extends IntegrationTestCase
{
    public function test_find_returns_user_with_complete_data(): void
    {
        // Arrange - Données explicites, pas de factory
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
        
        $repository = new UserRepository();
        
        // Act
        $result = $repository->find($user->id);
        
        // Assert
        $this->assertSame('John Doe', $result->name);
        $this->assertSame('john@example.com', $result->email);
        $this->assertTrue($result->isActive);
    }
}

// ❌ Ce qui ne l'est PAS
final class UntestableService  // ❌ "final" interdit
{
    public function execute(): void
    {
        Log::info('message');  // ❌ Appel statique
        User::find(1);         // ❌ Appel statique
    }
}

// ❌ Le test avec factory (interdit)
final class BadTest extends IntegrationTestCase
{
    public function test_something(): void
    {
        // ❌ INTERDIT - Factory masque les données
        $user = User::factory()->create();
        
        // On ne sait pas quel utilisateur a été créé
        // On ne contrôle pas les valeurs exactes
    }
}
```

> **Rappel final : Si tu as un doute sur la nécessité de tester un composant, pose-toi la question : "Ce composant contient-il une condition (`if`), une boucle (`foreach`), un calcul, une transformation de données, ou une orchestration d'appels ?" Si oui, TESTE-LE.**
>
> **Si ton test hérite de `IntegrationTestCase` mais n'utilise pas la base de données, c'est que tu devrais utiliser `UnitTestCase` à la place.**
>
> **Si ton test hérite de `UnitTestCase` mais essaie d'accéder à la base de données, le test échouera car Laravel n'est pas bootstrappé.**
>
> **Si tu écris `User::factory()` dans un test, arrête-toi et réécris-le avec `User::create()` et des données explicites. La lisibilité et la maintenabilité en valent la peine.**

---

## 16. Migration depuis l'ancienne structure

### 16.1 Étapes de migration

```bash
# 1. Renommer le dossier Feature en Integration
mv tests/Feature tests/Integration

# 2. Créer les nouvelles classes de base
# Créer tests/UnitTestCase.php
# Créer tests/IntegrationTestCase.php

# 3. Mettre à jour tous les tests unitaires
find tests/Unit -name "*Test.php" -exec sed -i 's/extends TestCase/extends UnitTestCase/g' {} \;
find tests/Unit -name "*Test.php" -exec sed -i 's/extends Orchestra\\Testbench\\TestCase/extends UnitTestCase/g' {} \;

# 4. Mettre à jour tous les tests d'intégration
find tests/Integration -name "*Test.php" -exec sed -i 's/extends TestCase/extends IntegrationTestCase/g' {} \;
find tests/Integration -name "*Test.php" -exec sed -i 's/extends Orchestra\\Testbench\\TestCase/extends IntegrationTestCase/g' {} \;

# 5. Mettre à jour les tests par module
find tests -type f -name "*Test.php" -exec sed -i 's/extends TestCase/extends UnitTestCase/g' {} \;
# Puis manuellement, identifier ceux qui ont besoin de IntegrationTestCase

# 6. Supprimer ou déprécier l'ancien TestCase
# Ajouter @deprecated dans tests/TestCase.php

# 7. Mettre à jour phpunit.xml
# Remplacer Feature par Integration dans les testsuites

# 8. Remplacer toutes les factories par des créations explicites
# Rechercher "::factory()" dans les tests et remplacer manuellement

# 9. Exécuter les tests pour valider
./vendor/bin/phpunit
```

### 16.2 Vérification post-migration

```bash
# Vérifier qu'aucun test n'utilise encore l'ancien TestCase
grep -r "extends TestCase" tests/ --include="*.php" | grep -v "@deprecated"

# Vérifier qu'aucun test n'est dans Feature/
ls tests/Feature/ 2>/dev/null && echo "ERREUR: Le dossier Feature existe encore"

# Vérifier qu'aucune factory n'est utilisée
grep -r "::factory()" tests/ --include="*.php" && echo "ERREUR: Des factories sont encore utilisées"

# Vérifier que les tests unitaires n'utilisent pas IntegrationTestCase
grep -r "extends IntegrationTestCase" tests/Unit/ --include="*.php" && echo "ERREUR: Des tests unitaires utilisent IntegrationTestCase"

# Vérifier que les tests d'intégration utilisent IntegrationTestCase
grep -r "extends UnitTestCase" tests/Integration/ --include="*.php" && echo "ERREUR: Des tests d'intégration utilisent UnitTestCase"
```

---

**Fin de la documentation.** 🚀