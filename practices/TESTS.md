# Principe d'usage des Tests (Version finale)

## 1. Définition

Un **Test** est un composant qui valide le comportement attendu d'une unité de code. Il garantit que l'application fonctionne correctement et que les modifications futures ne cassent pas les fonctionnalités existantes.

```
Test → Validation du comportement → Garantie de non-régression → Documentation vivante
```

```php
final class UserServiceTest extends UnitTestCase
{
    public function test_getUser_returns_user_record_when_user_exists(): void
    {
        // Arrange
        $user = new UserRecord(user_id: 1, user_name: 'John Doe', user_email: 'john@example.com');
        $repository = $this->createMock(UserRepository::class);
        $repository->method('find')->willReturn($user);
        $service = new UserService($repository);

        // Act
        $result = $service->getUser(1);

        // Assert
        $this->assertInstanceOf(UserRecord::class, $result);
        $this->assertSame(1, $result->user_id);
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
| **Action** | ✅ Oui (orchestration HTTP) | ✅ **OBLIGATOIRE** | Transformation Request → Record → Data | Intégration (requêtes HTTP) |
| **Task** | ✅ Oui (traitement unique) | ✅ **OBLIGATOIRE** | Action unitaire avec logique | Unitaire (mocks) |
| **Service** | ✅ Oui (logique métier pure) | ✅ **OBLIGATOIRE** | Calculs, conditions, transformations | Unitaire (mocks) |
| **Repository** | ✅ Oui (accès données) | ✅ **OBLIGATOIRE** | Requêtes, filtres, pagination | Intégration (base de données) |
| **Enum** | ✅ Oui (méthodes métier) | ✅ **OBLIGATOIRE** | `isAdmin()`, `getLabel()`, `fromValue()` | Unitaire |
| **Data** | ❌ Non (DTO pur) | ❌ Non requis | Pas de logique métier | - |
| **Record** | ❌ Non (DTO pur) | ❌ Non requis | Pas de logique métier | - |

### 3.2 Vérification rapide

```php
// ✅ Ce code contient de la logique → DOIT être testé
final class UserService
{
    public function isAdult(UserRecord $user): bool
    {
        return $user->user_age >= 18;  // Condition → tester
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
}

// ❌ Ce code ne contient PAS de logique → NE DOIT PAS être testé
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $user_name,   // Pas de logique
        public readonly int $user_age,       // Pas de logique
    ) {}
}
```

---

## 4. Types de tests

### 4.1 Unit Tests (isolés, sans base de données)

> **Les tests unitaires testent une unité de code isolément (une classe, une méthode). Toutes les dépendances sont mockées. La base de données n'est PAS utilisée.**

| Composant | Test unitaire ? | Base de données ? | Localisation |
|-----------|----------------|-------------------|--------------|
| **Service** | ✅ Oui | ❌ Non | `tests/Unit/Services/` |
| **Task** | ✅ Oui | ❌ Non | `tests/Unit/Tasks/` |
| **Enum** | ✅ Oui | ❌ Non | `tests/Unit/Enums/` |
| **Data** | ❌ Non | ❌ Non | - |

### 4.2 Integration Tests (avec base de données ou contexte complet)

> **Les tests d'intégration testent une fonctionnalité complète avec une vraie base de données en mémoire ou un contexte HTTP réel.**

| Composant | Test d'intégration ? | Base de données ? | Localisation |
|-----------|---------------------|-------------------|--------------|
| **Action** | ✅ Oui | ✅ Oui | `tests/Integration/Actions/` |
| **Repository** | ✅ Oui | ✅ Oui | `tests/Integration/Repositories/` |
| **Model** | ✅ Oui | ✅ Oui | `tests/Integration/Models/` |
| **Route** | ✅ Oui | ✅ Oui | `tests/Integration/Routes/` |

### 4.3 Règle de décision simplifiée

| Le test utilise... | Type de test | Héritage |
|-------------------|--------------|----------|
| **Uniquement des mocks** (pas de DB, pas de FS, pas d'API) | Unitaire | `UnitTestCase` |
| **Une vraie base de données** | Intégration | `IntegrationTestCase` |
| **Le système de fichiers** (logs, uploads) | Intégration | `IntegrationTestCase` |
| **L'heure système** (Carbon, time()) | Intégration | `IntegrationTestCase` |

---

## 5. Hiérarchie des TestCases (⚠️ RÈGLE ABSOLUE)

### 5.1 UnitTestCase (tests sans Laravel)

```php
<?php
// tests/UnitTestCase.php

declare(strict_types=1);

namespace Tests;

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
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }
}
```

### 5.2 IntegrationTestCase (tests avec Laravel)

```php
<?php
// tests/IntegrationTestCase.php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for integration tests that need Laravel.
 * Full Laravel bootstrap, database support, HTTP client.
 * 
 * ⚠️ RÈGLE : Les tests qui héritent de cette classe :
 * - PEUVENT utiliser la base de données
 * - PEUVENT utiliser les facades Laravel
 * - PEUVENT faire des requêtes HTTP
 */
abstract class IntegrationTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }
}
```

### 5.3 Règle absolue : Choix du TestCase parent

| Type de test | DOIT hériter de | Localisation | Interdiction |
|--------------|-----------------|--------------|--------------|
| **Test unitaire** | `UnitTestCase` | `tests/Unit/` | ❌ Base de données |
| **Test d'intégration** | `IntegrationTestCase` | `tests/Integration/` | ❌ Code non testable |

---

## 6. Organisation des tests

```
tests/
├── UnitTestCase.php
├── IntegrationTestCase.php
├── Unit/                                    # Tests unitaires
│   ├── Services/
│   │   └── UserServiceTest.php
│   ├── Tasks/
│   │   └── SendWelcomeEmailTaskTest.php
│   ├── Enums/
│   │   └── UserRoleTest.php
│   └── ...
├── Integration/                             # Tests d'intégration
│   ├── Actions/
│   │   └── ShowUserActionTest.php
│   ├── Repositories/
│   │   └── UserRepositoryTest.php
│   └── ...
└── Fixtures/                                # Données de test
    ├── Records/
    │   └── UserRecord.php
    └── ...
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
    $item1 = new OrderItemRecord(product_id: 1, quantity: 2, price: 10.0);
    $item2 = new OrderItemRecord(product_id: 2, quantity: 1, price: 5.0);
    $order = new OrderRecord(items: [$item1, $item2]);
    $service = new PriceCalculatorService();

    // Act (Exécuter)
    $total = $service->calculate($order);

    // Assert (Vérifier)
    $this->assertSame(25.0, $total);
}
```

---

## 9. Interdiction du mot-clé `final` sur les classes destinées aux tests unitaires (⚠️ RÈGLE ABSOLUE)

> **⚠️ CRITIQUE : Les classes qui sont destinées à être testées unitairement (Services, Tasks, Actions, Repositories) NE DOIVENT PAS être déclarées `final`. Le mot-clé `final` empêche PHPUnit de créer des mocks, rendant les tests impossibles.**

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
```

### 9.3 Récapitulatif des classes concernées

| Type de classe | `final` autorisé ? | Raison |
|----------------|-------------------|--------|
| **Service** | ❌ Non | Contient de la logique, doit être mockable |
| **Task** | ❌ Non | Contient de la logique, doit être mockable |
| **Action** | ❌ Non | Contient de l'orchestration HTTP, doit être mockable |
| **Repository** | ❌ Non | Accès base de données, doit être mockable |
| **Enum** | ✅ Oui | Pas de dépendances, pas de logique complexe |
| **Record** | ✅ Oui | Sac de données immutable (snake_case) |
| **Data** | ✅ Oui | Réponse API immutable (camelCase) |

---

## 10. Création des données de test (⚠️ RÈGLE FERME)

> **Les données de test DOIVENT être créées explicitement avec `Model::create()`. L'utilisation des factories Laravel est INTERDITE.**

### 10.1 Pourquoi ?

| Problème des factories | Solution avec `Model::create()` |
|------------------------|--------------------------------|
| Masquent les données réelles | Les données sont explicites |
| Créent des scénarios magiques | Le scénario est écrit en code |
| Difficile à maintenir | Un seul endroit : le test |

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
        
        // Act
        $analytics = (new ClientAnalyticsService())->getStats($client);
        
        // Assert
        $this->assertSame(2, $analytics->cancelledOrders);
    }
}

// ❌ MAUVAIS - Factory (interdit)
final class BadTest extends IntegrationTestCase
{
    public function test_something(): void
    {
        // ❌ INTERDIT - Factory masque les données
        $user = User::factory()->create();
    }
}
```

---

## 11. Exemples de tests par composant

### 11.1 Test unitaire d'un Service (Records en snake_case)

```php
final class UserServiceTest extends UnitTestCase
{
    public function test_getUser_returns_user_record_when_user_exists(): void
    {
        // Arrange
        $userRecord = new UserRecord(
            user_id: 1,
            user_name: 'John Doe',
            user_email: 'john@example.com',
        );
        
        $repository = $this->createMock(UserRepository::class);
        $repository->method('find')->willReturn($userRecord);
        
        $service = new UserService($repository);

        // Act
        $result = $service->getUser(1);

        // Assert
        $this->assertInstanceOf(UserRecord::class, $result);
        $this->assertSame(1, $result->user_id);
        $this->assertSame('John Doe', $result->user_name);
    }
}
```

### 11.2 Test unitaire d'un Enum

```php
final class UserRoleTest extends UnitTestCase
{
    public function test_isAdmin_returns_true_for_admin_role(): void
    {
        $role = UserRole::ADMIN;
        
        $this->assertTrue($role->isAdmin());
    }
    
    public function test_isAdmin_returns_false_for_user_role(): void
    {
        $role = UserRole::USER;
        
        $this->assertFalse($role->isAdmin());
    }
    
    public function test_getLabel_returns_french_label(): void
    {
        $role = UserRole::ADMIN;
        
        $this->assertSame('Administrateur', $role->getLabel(Language::FR));
    }
}
```

### 11.3 Test d'intégration d'un Repository

```php
final class UserRepositoryTest extends IntegrationTestCase
{
    use RefreshDatabase;
    
    public function test_find_returns_user_record_when_user_exists(): void
    {
        // Arrange
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
        ]);
        
        $repository = new UserRepository();
        
        // Act
        $result = $repository->find($user->id);
        
        // Assert
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->user_id);
        $this->assertSame('John Doe', $result->user_name);
    }
}
```

---

## 12. Récapitulatif des interdictions pour la testabilité

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| `final` sur les Services/Tasks/Actions | Empêche le mocking | Supprimer `final` |
| `Log::info()` direct | Appel statique non mockable | Interface `LoggerInterface` injectée |
| `User::find()` direct | Appel statique non mockable | Repository injecté |
| `Cache::put()` direct | Facade statique non mockable | Interface `CacheInterface` injectée |
| `new` dans le constructeur | Coupling caché non mockable | Injection de dépendances |
| Factories Laravel | Masquent les données réelles | Création explicite avec `Model::create()` |

---

## 13. Règle d'or

> **ZÉRO `final` sur les classes avec logique. ZÉRO appel statique. ZÉRO factory. TOUTES les dépendances injectées. TOUTES les données explicites.**
>
> **Les tests unitaires héritent de `UnitTestCase` (pas de Laravel). Les tests d'intégration héritent de `IntegrationTestCase` (Laravel complet).**
>
> **Les Records sont en `snake_case`. Les méthodes de test sont en `snake_case` avec préfixe `test_`.**

```php
// ✅ Ce qui est testable
class UserService  // Pas de "final"
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly UserRepository $userRepository,
    ) {}
}

// ✅ Le test unitaire associé
final class UserServiceTest extends UnitTestCase
{
    public function test_getUser_returns_user_record_when_user_exists(): void
    {
        // Arrange
        $userRecord = new UserRecord(user_id: 1, user_name: 'John');
        
        $repository = $this->createMock(UserRepository::class);
        $repository->method('find')->willReturn($userRecord);
        
        $service = new UserService($this->createMock(LoggerInterface::class), $repository);
        
        // Act
        $result = $service->getUser(1);
        
        // Assert
        $this->assertSame(1, $result->user_id);
    }
}

// ❌ Ce qui ne l'est PAS
final class UserService  // ❌ "final" interdit
{
    public function execute(): void
    {
        Log::info('message');  // ❌ Appel statique
        User::find(1);         // ❌ Appel statique
    }
}
```
---