Voici le document complet **Principe d'usage des Tests (Version finale)** avec l'encarte ajoutée :

```markdown
# Principe d'usage des Tests (Version finale)

## 1. Définition

Un **Test** est un composant qui valide le comportement attendu d'une unité de code. Il garantit que l'application fonctionne correctement et que les modifications futures ne cassent pas les fonctionnalités existantes.

```
Test → Validation du comportement → Garantie de non-régression → Documentation vivante
```

```php
final class UserServiceTest extends TestCase
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
| **FormRequest** | ✅ Oui (règles validation) | ✅ **OBLIGATOIRE** | Règles de validation, autorisation | Unitaire (mocks) |
| **Enum** | ✅ Oui (méthodes métier) | ✅ **OBLIGATOIRE** | `isAdmin()`, `getLabel()`, `fromValue()` | Unitaire |
| **Cast** | ✅ Oui (transformation) | ✅ **OBLIGATOIRE** | `get()`, `set()` | Unitaire |
| **Trait** | ✅ Oui (logique réutilisable) | ✅ **OBLIGATOIRE** | Méthodes partagées entre classes | Unitaire (classe factice) |
| **TypedRecords** | ✅ Oui (collection typée) | ✅ **OBLIGATOIRE** | `add()`, `filter()`, `map()`, validation types | Unitaire |
| **Model** | ✅ Oui (accesseurs, mutateurs, scopes) | ✅ **OBLIGATOIRE** | `FullName()`, `scopeActive()`, relations | Intégration (base de données) |
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
    protected function fullName(): Attribute  // Nouvelle syntaxe Laravel 10+
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

## 4. Types de tests (⚠️ RÈGLE IMPORTANTE)

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
| **FormRequest** | ✅ Oui | ❌ Non | `tests/Unit/FormRequests/` |
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
| **Action (complète)** | ✅ Oui | ✅ Oui | `tests/Integration/Actions/` |
| **Worker (complet)** | ✅ Oui | ✅ Oui | `tests/Integration/Workers/` |
| **Workflow complet** | ✅ Oui | ✅ Oui | `tests/Integration/Workflows/` |

### 4.3 Règle de choix : Unit vs Integration

| Situation | Type de test | Base de données | Localisation |
|-----------|--------------|-----------------|--------------|
| **Logique métier pure (Service)** | Unit | ❌ Non | `tests/Unit/Services/` |
| **Orchestration (Worker)** | Unit | ❌ Non | `tests/Unit/Workers/` |
| **Calcul / transformation** | Unit | ❌ Non | `tests/Unit/Services/` |
| **Validation (FormRequest)** | Unit | ❌ Non | `tests/Unit/FormRequests/` |
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

---

## 5. Organisation des tests par module (Mini-Package)

> **Les tests d'un mini-package DOIVENT être organisés dans le répertoire `tests/{ModuleName}/` et non dans `tests/Unit/` ou `tests/Feature/` globaux.**

### 5.1 Structure des tests par module

```
tests/
├── Logger/                                    ← Module Logger (mini-package)
│   ├── Unit/
│   │   ├── Enums/
│   │   │   └── LogLevelTest.php
│   │   ├── Records/
│   │   │   ├── LogRecordTest.php
│   │   │   └── LogQueryRecordTest.php
│   │   ├── Config/
│   │   │   └── LoggerConfigTest.php
│   │   ├── Services/
│   │   │   ├── LogPathServiceTest.php
│   │   │   └── Tasks/
│   │   │       ├── WriteLogTaskTest.php
│   │   │       ├── QueryLogsTaskTest.php
│   │   │       └── StreamLogsTaskTest.php
│   │   └── LoggerTest.php
│   └── Feature/
│       └── LoggerIntegrationTest.php
├── Currency/                                 ← Module Currency (mini-package)
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
│   └── Feature/
│       └── CurrencyIntegrationTest.php
├── Notification/                             ← Module Notification (mini-package)
│   ├── Unit/
│   │   ├── Enums/
│   │   ├── Records/
│   │   ├── Services/
│   │   └── NotifierTest.php
│   └── Feature/
│       └── NotificationIntegrationTest.php
├── Domain/                                   ← Code métier spécifique
│   ├── Users/
│   │   ├── Unit/
│   │   │   ├── Services/
│   │   │   │   └── UserServiceTest.php
│   │   │   └── Tasks/
│   │   │       └── CreateUserTaskTest.php
│   │   └── Integration/
│   │       ├── Models/
│   │       │   └── UserTest.php
│   │       ├── Repositories/
│   │       │   └── UserRepositoryTest.php
│   │       └── Routes/
│   │           └── UserRoutesTest.php
│   └── Orders/
│       ├── Unit/
│       └── Integration/
├── Fixtures/
│   ├── ReplyerFixture.php
│   └── SimpleTestData.php
└── TestCase.php
```

### 5.2 Pourquoi cette organisation ?

| Problème de l'ancienne organisation | Solution de la nouvelle organisation |
|--------------------------------------|--------------------------------------|
| Tests éparpillés dans `Unit/` et `Feature/` | Tests regroupés par module |
| Difficulté à transporter un module | Les tests voyagent avec le module |
| Pas de visibilité sur ce qui est testé | La structure des tests reflète la structure du code |
| Maintenance difficile | Chaque module a ses propres tests |

### 5.3 Exemple de test dans un module

```php
<?php

declare(strict_types=1);

namespace Tests\Logger\Unit\Services\Tasks;

use AndyDefer\Logger\Services\Tasks\WriteLogTask;
use AndyDefer\Logger\Config\LoggerConfig;
use Tests\TestCase;

final class WriteLogTaskTest extends TestCase
{
    private string $tempLogFile;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempLogFile = sys_get_temp_dir() . '/test.log';
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

---

## 6. Convention de nommage (⚠️ STRICT)

### 6.1 Nom du fichier

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

### 6.2 Nom de la classe

> **La classe de test DOIT avoir le même nom que le fichier.**

```php
// ✅ BON
final class UserServiceTest extends TestCase { ... }

// ❌ MAUVAIS
final class TestUserService extends TestCase { ... }
```

### 6.3 Nom des méthodes (⚠️ STRICT)

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

### 6.4 Structure du nom

```
test_{methodName}_{expectedBehavior}_{condition}
```

| Partie | Exemple |
|--------|---------|
| `test_{methodName}` | `test_getUser` |
| `_{expectedBehavior}` | `_returns_user_record` |
| `_{condition}` | `_when_user_exists` |

---

## 7. Structure AAA (Arrange-Act-Assert)

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

### 7.1 Pourquoi AAA ?

| Étape | Rôle |
|-------|------|
| **Arrange** | Préparer l'environnement, les données, les mocks |
| **Act** | Exécuter la méthode testée |
| **Assert** | Vérifier le résultat |

---

## 8. Règle de couverture des tests par module

> **Chaque classe du module qui contient de la logique DOIT être testée.**

| Composant du module | DOIT être testé ? | Type de test | Localisation dans les tests |
|---------------------|-------------------|--------------|----------------------------|
| **Enums** (méthodes) | ✅ Oui | Unitaire | `tests/{Module}/Unit/Enums/` |
| **Records** (si logique) | ✅ Oui | Unitaire | `tests/{Module}/Unit/Records/` |
| **Config** (Value Object) | ✅ Oui | Unitaire | `tests/{Module}/Unit/Config/` |
| **Services** | ✅ Oui | Unitaire | `tests/{Module}/Unit/Services/` |
| **Tasks** | ✅ Oui | Unitaire | `tests/{Module}/Unit/Services/Tasks/` |
| **Module principal** | ✅ Oui | Unitaire | `tests/{Module}/Unit/{Module}Test.php` |
| **Integration** | ✅ Oui | Intégration | `tests/{Module}/Feature/` |

---

## 9. Tableau récapitulatif complet par composant

| Composant | Contient de la logique ? | DOIT être testé ? | Type de test | Base de données | Localisation |
|-----------|-------------------------|-------------------|--------------|-----------------|--------------|
| **Action** | ✅ Orchestration HTTP | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Actions/` |
| **Action (intégration)** | ✅ Route complète | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Feature/` |
| **Cast** | ✅ Transformation | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Casts/` |
| **Command** | ✅ Logique console | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Commands/` |
| **Data** | ❌ Aucune | ❌ NON | - | - | - |
| **Enum** | ✅ Méthodes métier | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Enums/` |
| **FormRequest** | ✅ Règles validation | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/FormRequests/` |
| **Middleware** | ✅ Logique transversale | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Feature/` |
| **Migration** | ❌ Structure DB | ❌ NON | - | - | - |
| **Model** | ✅ Accesseurs, mutateurs, scopes | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Integration/Models/` |
| **Record** | ❌ Aucune | ❌ NON | - | - | - |
| **Repository** | ✅ Accès données, requêtes | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Integration/Repositories/` |
| **Route** | ✅ Accès, middleware | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Integration/Routes/` |
| **Seeder** | ❌ Données statiques | ❌ NON | - | - | - |
| **Service** | ✅ Logique métier pure | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Services/` |
| **Task** | ✅ Traitement unique | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Services/Tasks/` |
| **Trait** | ✅ Logique réutilisable | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Traits/` |
| **TypedRecords** | ✅ Collection typée | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/TypedRecords/` |
| **Worker** | ✅ Orchestration de Tasks | ✅ OUI | Unitaire | ❌ Non | `tests/{Module}/Unit/Workers/` |
| **Worker (intégration)** | ✅ Workflow complet | ✅ OUI | Intégration | ✅ Oui | `tests/{Module}/Feature/` |

---

## 10. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom fichier** | `{Component}Test.php` |
| **Nom classe** | `{Component}Test` |
| **Nom méthode** | `test_{methodName}_{expectedBehavior}_{condition}` |
| **Structure** | AAA (Arrange, Act, Assert) |
| **Type test** | Unit = classe isolée (sans DB), Integration = avec DB ou contexte HTTP |
| **Organisation** | Tests regroupés par module dans `tests/{ModuleName}/` |
| **Factories Laravel** | ❌ Interdit (préférer `User::create()`) |
| **Mocking** | Utiliser les mocks pour les dépendances |
| **Base de données** | Unit = ❌ éviter, Integration = ✅ utiliser |

---

## 11. Règle d'or

> **Un test doit être explicite, isolé, rapide et lisible. Si un test est difficile à écrire, c'est que ton code est difficile à tester. Refactorise. Les données de test sont créées explicitement avec `User::create()`, pas avec des factories. Les tests sont organisés par module dans `tests/{ModuleName}/`.**

```php
// Le test parfait
<?php
/**
 * Test suite for PerfectService.
 *
 * Verifies the business logic of the execute method under various conditions,
 * ensuring proper dependency interaction and result calculations.
 */
final class PerfectServiceTest extends TestCase
{
    /**
     * Test that execute returns the sum of record value and dependency value.
     *
     * Verifies that when all conditions are satisfied, the service correctly
     * adds the record's value to the value returned by the dependency.
     */
    public function test_execute_returns_sum_of_record_and_dependency_values(): void
    {
        // Arrange: Create a test user with valid credentials
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        
        // Arrange: Configure dependency mock to return a fixed value (42)
        $dependency = $this->createMock(Dependency::class);
        $dependency->method('getValue')->willReturn(42);
        
        // Arrange: Instantiate service with mocked dependency and create test record
        $service = new PerfectService($dependency);
        $record = new PerfectRecord(userId: $user->id, value: 10);

        // Act: Execute the service with the prepared record
        $result = $service->execute($record);

        // Assert: Verify the result equals the sum (10 + 42 = 52)
        $this->assertSame(52, $result);
        
        // Assert: Confirm the dependency method was called exactly once
        $dependency->shouldHaveReceived('getValue')->once();
    }
}
```

> **Rappel final : Si tu as un doute sur la nécessité de tester un composant, pose-toi la question : "Ce composant contient-il une condition (`if`), une boucle (`foreach`), un calcul, une transformation de données, ou une orchestration d'appels ?" Si oui, TESTE-LE.**

---

## 12. Interdiction du mot-clé `final` sur les classes destinées aux tests unitaires (⚠️ RÈGLE ABSOLUE)

> **⚠️ CRITIQUE : Les classes qui sont destinées à être testées unitairement (Services, Tasks, Workers, Actions, etc.) NE DOIVENT PAS être déclarées `final`. Le mot-clé `final` empêche PHPUnit de créer des mocks, rendant les tests impossibles.**

### 12.1 Problème : Le mot-clé `final` bloque le mocking

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

### 12.2 Solution : NE PAS utiliser `final` sur les classes à tester

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

### 12.3 Quand utiliser `final` (cas autorisés)

| Cas | Autorisation | Exemple |
|-----|--------------|---------|
| **Classes sans logique métier** | ✅ Oui | `Record`, `Data`, `Config` (Value Objects) |
| **Classes sans dépendances** | ✅ Oui | `Enum`, `AbstractRecord`, `AbstractData` |
| **Classes de production uniquement** | ⚠️ À éviter | Préférer ne pas mettre `final` |
| **Classes avec dépendances** | ❌ **INTERDIT** | Services, Tasks, Workers, Actions |

### 12.4 Comparaison : final vs non-final

| Aspect | Avec `final` | Sans `final` |
|--------|--------------|--------------|
| **Mockabilité** | ❌ Impossible | ✅ Possible |
| **Testabilité unitaire** | ❌ Impossible | ✅ Possible |
| **Performance** | ⚠️ Théoriquement meilleure | ✅ Négligeable |
| **Héritage** | ❌ Interdit | ✅ Possible (mais rare) |
| **Sécurité** | ⚠️ Empêche l'héritage malveillant | ✅ Géré par d'autres moyens |

### 12.5 Règle de décision

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

### 12.6 Exemple d'erreur et correction

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

### 12.7 Récapitulatif des classes concernées

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

### 12.8 Règle d'or

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

## 13. Tableau récapitulatif final des interdictions pour la testabilité

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

---

## 14. Règle d'or finale

> **ZÉRO `final` sur les classes avec logique. ZÉRO appel statique. TOUTES les dépendances injectées. Si vous voyez `final class UserService` ou `Log::info()` dans un Service, c'est une erreur.**

```php
// ✅ Ce qui est testable
class TestableService  // Pas de "final"
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
    ) {}
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
```