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

## 3. Types de tests (⚠️ RÈGLE IMPORTANTE)

### 3.1 Unit Tests (isolés, sans base de données)

> **Les tests unitaires testent une unité de code isolément (une classe, une méthode). Toutes les dépendances sont mockées. La base de données n'est PAS utilisée sauf exception.**

**⚠️ Règle :** Les tests unitaires doivent éviter au maximum l'utilisation de la base de données. Notre architecture est pensée pour être mockable.

| Composant | Test unitaire ? | Base de données ? | Pourquoi |
|-----------|----------------|-------------------|----------|
| **Service** | ✅ Oui | ❌ Non | Logique métier pure, dépendances mockées |
| **Task** | ✅ Oui | ❌ Non | Action unique, dépendances injectées |
| **Worker** | ✅ Oui | ❌ Non | Orchestration, on mocke les Tasks |
| **Action** | ✅ Oui | ❌ Non | Orchestration HTTP, on mocke les Workers/Services |
| **Model** | ✅ Oui | ❌ Non | Déclarations uniquement |
| **Enum** | ✅ Oui | ❌ Non | Pas de dépendances |
| **FormRequest** | ✅ Oui | ❌ Non | Règles de validation |
| **Middleware** | ✅ Oui | ❌ Non | Logique transversale |
| **Repository** | ⚠️ Partiel | ✅ Oui | Préférer une vraie base de données en mémoire |

**Localisation :** `tests/Unit/{Component}/{Component}Test.php`

### 3.2 Feature Tests (avec base de données)

> **Les tests fonctionnels testent une fonctionnalité complète de bout en bout (une route, un workflow). Ils utilisent une vraie base de données en mémoire.**

**⚠️ Règle :** Les tests de log, notifications, et tout ce qui persiste quelque part doivent utiliser de vraies interactions en base de données.

| Cas | Test fonctionnel ? | Base de données ? | Pourquoi |
|-----|--------------------|-------------------|----------|
| **Route API** | ✅ Oui | ✅ Oui | Tester la route complète avec requête HTTP |
| **Route web (Inertia)** | ✅ Oui | ✅ Oui | Tester la route complète |
| **Workflow complet** | ✅ Oui | ✅ Oui | Inscription → Connexion → Action |
| **Repository** | ✅ Oui | ✅ Oui | Vérifier les vraies interactions DB |
| **Log / Notification** | ✅ Oui | ✅ Oui | Vérifier la persistance |

**Localisation :** `tests/Feature/{Feature}Test.php`

### 3.3 Règle de choix : Unit vs Feature

| Situation | Type de test | Base de données |
|-----------|--------------|-----------------|
| **Logique métier pure (Service)** | Unit | ❌ Non |
| **Orchestration (Worker)** | Unit | ❌ Non |
| **Calcul / transformation** | Unit | ❌ Non |
| **Validation (FormRequest)** | Unit | ❌ Non |
| **Enum** | Unit | ❌ Non |
| **Model (déclarations)** | Unit | ❌ Non |
| **Middleware** | Unit | ❌ Non |
| **Repository** | Feature | ✅ Oui |
| **Action (route complète)** | Feature | ✅ Oui |
| **Route avec HTTP** | Feature | ✅ Oui |
| **Log / Notification / Mail** | Feature | ✅ Oui |

---

## 4. Convention de nommage (⚠️ STRICT)

### 4.1 Nom du fichier

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

### 4.2 Nom de la classe

> **La classe de test DOIT avoir le même nom que le fichier.**

```php
// ✅ BON
final class UserServiceTest extends TestCase { ... }

// ❌ MAUVAIS
final class TestUserService extends TestCase { ... }
```

### 4.3 Nom des méthodes (⚠️ STRICT)

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

### 4.4 Structure du nom

```
test_{methodName}_{expectedBehavior}_{condition}
```

| Partie | Exemple |
|--------|---------|
| `test_{methodName}` | `test_getUser` |
| `_{expectedBehavior}` | `_returns_user_record` |
| `_{condition}` | `_when_user_exists` |

---

## 5. Structure AAA (Arrange-Act-Assert)

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

### 5.1 Pourquoi AAA ?

| Étape | Rôle |
|-------|------|
| **Arrange** | Préparer l'environnement, les données, les mocks |
| **Act** | Exécuter la méthode testée |
| **Assert** | Vérifier le résultat |

---

## 6. Création des données dans les tests (⚠️ RÈGLE IMPORTANTE)

> **⚠️ Les tests doivent être explicites. Pas de `User::factory()->create()`. Utilisez `User::create()` directement ou créez des données réelles.**

### 6.1 Arrangement simple

```php
// ✅ BON - Création explicite
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
    'role' => UserRole::ADMIN,
]);

// ❌ MAUVAIS - Factory Laravel (trop abstrait)
$user = User::factory()->create();

// ❌ MAUVAIS - Tableau brut
$user = new User(['id' => 1, 'name' => 'John Doe']);  // Pas de vrai enregistrement
```

### 6.2 Arrangement complexe ou répétitif

> **Si l'arrangement est complexe ou répété dans plusieurs tests, créez une `Task` qui retourne un `Record`.**

```php
// App\Tasks\CreateTestUserTask.php
final class CreateTestUserTask extends AbstractTask
{
    public function execute(CreateTestUserRecord $record): UserRecord
    {
        $user = User::create([
            'name' => $record->name,
            'email' => $record->email,
            'password' => bcrypt($record->password),
            'role' => $record->role,
        ]);
        
        return new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role,
        );
    }
}

// Utilisation dans le test
public function test_something(): void
{
    // Arrange
    $userRecord = $this->createTestUserTask->execute(new CreateTestUserRecord(
        name: 'John Doe',
        email: 'john@example.com',
        role: UserRole::ADMIN,
    ));
    
    // Act & Assert...
}
```

### 6.3 Pour les tests Repository (base de données autorisée)

```php
// ✅ BON - Création explicite en base de données
public function test_find_returns_user_when_exists(): void
{
    // Arrange
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);
    $repository = new UserRepository();

    // Act
    $result = $repository->find($user->id);

    // Assert
    $this->assertNotNull($result);
    $this->assertSame($user->id, $result->id);
}
```

---

## 7. Tests par composant

### 7.1 Tester un Service (logique métier pure)

```php
final class PriceCalculatorServiceTest extends TestCase
{
    public function test_calculate_returns_total_when_items_exist(): void
    {
        // Arrange
        $item1 = new OrderItemRecord(price: 10.0, quantity: 2);
        $item2 = new OrderItemRecord(price: 5.0, quantity: 1);
        $order = new OrderRecord(items: [$item1, $item2]);
        $service = new PriceCalculatorService();

        // Act
        $total = $service->calculate($order);

        // Assert
        $this->assertSame(25.0, $total);
    }

    public function test_calculate_returns_zero_when_items_empty(): void
    {
        // Arrange
        $order = new OrderRecord(items: []);
        $service = new PriceCalculatorService();

        // Act
        $total = $service->calculate($order);

        // Assert
        $this->assertSame(0.0, $total);
    }
}
```

### 7.2 Tester un Service avec dépendances (Repository mocké)

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
        $repository->expects($this->once())
            ->method('find')
            ->with($user->id)
            ->willReturn($user);
        
        $service = new UserService($repository);

        // Act
        $result = $service->getUser($user->id);

        // Assert
        $this->assertInstanceOf(UserRecord::class, $result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('John Doe', $result->name);
    }

    public function test_getUser_returns_null_when_user_not_found(): void
    {
        // Arrange
        $repository = $this->createMock(UserRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);
        
        $service = new UserService($repository);

        // Act
        $result = $service->getUser(999);

        // Assert
        $this->assertNull($result);
    }
}
```

### 7.3 Tester un Worker (orchestration)

```php
final class RegisterUserWorkerTest extends TestCase
{
    public function test_execute_creates_user_and_sends_email_and_logs(): void
    {
        // Arrange
        $record = new RegisterUserRecord(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password',
        );
        
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('create')
            ->willReturn($user);
        
        $sendEmailTask = $this->createMock(SendWelcomeEmailTask::class);
        $sendEmailTask->expects($this->once())
            ->method('execute');
        
        $logTask = $this->createMock(LogUserActionTask::class);
        $logTask->expects($this->once())
            ->method('execute');
        
        $worker = new RegisterUserWorker($userRepository, $sendEmailTask, $logTask);

        // Act
        $worker->execute($record);

        // Assert (les expect() des mocks vérifient les appels)
        $this->assertTrue(true);
    }
}
```

### 7.4 Tester une Action API

```php
final class ListUsersActionTest extends TestCase
{
    public function test_run_returns_json_response_with_users(): void
    {
        // Arrange
        $request = $this->createMock(ListUsersRequest::class);
        $request->method('validated')->willReturn([]);
        
        $users = [new UserRecord(id: 1, name: 'John'), new UserRecord(id: 2, name: 'Jane')];
        
        $service = $this->createMock(UserService::class);
        $service->expects($this->once())
            ->method('getUsers')
            ->willReturn($users);
        
        $action = new ListUsersAction($service);

        // Act
        $response = $action->run($request);

        // Assert
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }
}
```

### 7.5 Tester une Action Web (Inertia)

```php
final class ShowDashboardActionTest extends TestCase
{
    public function test_run_returns_inertia_response_when_access_granted(): void
    {
        // Arrange
        $request = $this->createMock(ShowDashboardRequest::class);
        $request->method('user')->willReturn((object)['id' => 1]);
        $request->method('fullUrl')->willReturn('/dashboard');
        $request->method('ip')->willReturn('127.0.0.1');
        
        $worker = $this->createMock(HandleDashboardAccessWorker::class);
        $worker->expects($this->once())->method('execute');
        
        $action = new ShowDashboardAction($worker);

        // Act
        $response = $action->run($request);

        // Assert
        $this->assertInstanceOf(\Inertia\Response::class, $response);
    }
}
```

### 7.6 Tester un Repository (⚠️ avec vraie base de données)

```php
final class UserRepositoryTest extends TestCase
{
    use RefreshDatabase; // Pour les tests Repository
    
    public function test_find_returns_user_when_exists(): void
    {
        // Arrange
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        $repository = new UserRepository();

        // Act
        $result = $repository->find($user->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
        $this->assertSame('John Doe', $result->name);
    }
    
    public function test_create_creates_user_with_transaction(): void
    {
        // Arrange
        $record = new UserCreateRecord(
            name: 'John Doe',
            email: 'john@example.com',
            password: 'password',
            role: UserRole::USER,
        );
        $repository = new UserRepository();

        // Act
        $user = $repository->create($record);

        // Assert
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }
}
```

### 7.7 Tester un Model

```php
final class UserTest extends TestCase
{
    public function test_fullName_attribute_concatenates_first_and_last_name(): void
    {
        // Arrange
        $user = new User([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Act
        $fullName = $user->full_name;

        // Assert
        $this->assertSame('John Doe', $fullName);
    }
    
    public function test_email_attribute_is_normalized_to_lowercase(): void
    {
        // Arrange
        $user = new User([
            'email' => 'JOHN@EXAMPLE.COM',
        ]);

        // Act & Assert
        $this->assertSame('john@example.com', $user->email);
    }
}
```

### 7.8 Tester un Enum

```php
final class UserRoleTest extends TestCase
{
    public function test_values_returns_all_role_values(): void
    {
        // Arrange & Act
        $values = UserRole::values();

        // Assert
        $this->assertSame(['admin', 'user', 'doctor'], $values);
    }
    
    public function test_isAdmin_returns_true_for_admin_role(): void
    {
        // Arrange
        $role = UserRole::ADMIN;

        // Act & Assert
        $this->assertTrue($role->isAdmin());
        $this->assertFalse($role->isDoctor());
    }
    
    public function test_getLabel_returns_french_label_by_default(): void
    {
        // Arrange
        $role = UserRole::ADMIN;

        // Act
        $label = $role->getLabel();

        // Assert
        $this->assertSame('Administrateur', $label);
    }
}
```

### 7.9 Tester une FormRequest

```php
final class CreateUserRequestTest extends TestCase
{
    public function test_authorize_returns_true_for_admin(): void
    {
        // Arrange
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::ADMIN,
        ]);
        $this->actingAs($user);
        $request = new CreateUserRequest();

        // Act
        $authorized = $request->authorize();

        // Assert
        $this->assertTrue($authorized);
    }
    
    public function test_rules_returns_validation_rules(): void
    {
        // Arrange
        $request = new CreateUserRequest();

        // Act
        $rules = $request->rules();

        // Assert
        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
    }
}
```

### 7.10 Tester un Middleware

```php
final class AuthenticateMiddlewareTest extends TestCase
{
    public function test_handle_redirects_to_login_when_not_authenticated(): void
    {
        // Arrange
        $request = Request::create('/dashboard');
        $middleware = new AuthenticateMiddleware();

        // Act
        $response = $middleware->handle($request, fn() => response('OK'));

        // Assert
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringEndsWith('/login', $response->getTargetUrl());
    }
    
    public function test_handle_passes_request_when_authenticated(): void
    {
        // Arrange
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        $request = Request::create('/dashboard');
        $request->setUserResolver(fn() => $user);
        $middleware = new AuthenticateMiddleware();

        // Act
        $response = $middleware->handle($request, fn() => response('OK'));

        // Assert
        $this->assertSame('OK', $response->getContent());
    }
}
```

---

## 8. Conseils pour un code testable

### 8.1 Injection de dépendances

```php
// ❌ MAUVAIS - Difficile à tester
final class UserService
{
    public function getUser(int $id): ?User
    {
        return User::find($id);  // Appel statique, difficile à mocker
    }
}

// ✅ BON - Facile à tester
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function getUser(int $id): ?User
    {
        return $this->userRepository->find($id);  // Injecté, facile à mocker
    }
}
```

### 8.2 Éviter les appels statiques

```php
// ❌ MAUVAIS - Difficile à tester
final class UserService
{
    public function getCurrentUser(): ?User
    {
        return auth()->user();  // Appel statique
    }
}

// ✅ BON - Passer le contexte en paramètre
final class UserService
{
    public function getUser(UserContextRecord $context): ?UserRecord
    {
        return $this->userRepository->find($context->userId);
    }
}
```

### 8.3 Éviter les `new` dans les Services

```php
// ❌ MAUVAIS - Difficile à tester
final class UserService
{
    public function register(RegisterUserRecord $record): void
    {
        $user = new User();  // Impossible à mocker
        $user->name = $record->name;
        $user->save();
    }
}

// ✅ BON - Utiliser le Repository
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function register(RegisterUserRecord $record): void
    {
        $this->userRepository->create($record);  // Mockable
    }
}
```

### 8.4 Éviter les méthodes `private` non testables

```php
// ❌ MAUVAIS - Logique dans une méthode privée (non testable isolément)
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        return $this->calculateTax($record);
    }
    
    private function calculateTax(OrderRecord $record): float  // Non testable seule
    {
        return $record->subtotal * 0.20;
    }
}

// ✅ BON - Méthode publique ou extraite dans un Service
final class PriceCalculatorService
{
    public function calculate(OrderRecord $record): float
    {
        return $this->calculateTax($record->subtotal);
    }
    
    public function calculateTax(float $subtotal): float  // Testable
    {
        return $subtotal * 0.20;
    }
}

// ✅ BON - Extraite dans un Service dédié
final class TaxCalculatorService
{
    public function calculate(float $subtotal): float
    {
        return $subtotal * 0.20;
    }
}
```

### 8.5 Utiliser des interfaces pour les dépendances

```php
// ✅ BON - Interface pour faciliter le mocking
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
}

final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,  // Interface = facile à mocker
    ) {}
}
```

### 8.6 Éviter les "magic strings" et "magic numbers"

```php
// ❌ MAUVAIS - Magic number
if ($user->role === 'admin') { ... }

// ✅ BON - Utiliser un Enum
if ($user->role->isAdmin()) { ... }

// ❌ MAUVAIS - Magic number dans un test
$this->assertSame(30, $result);  // Pourquoi 30 ?

// ✅ BON - Constante ou variable explicite
$expectedDays = 30;
$this->assertSame($expectedDays, $result);
```

---

## 9. Organisation des dossiers

```
tests/
├── Unit/
│   ├── Services/
│   │   └── UserServiceTest.php
│   ├── Tasks/
│   │   └── SendWelcomeEmailTaskTest.php
│   ├── Workers/
│   │   └── RegisterUserWorkerTest.php
│   ├── Actions/
│   │   ├── Api/
│   │   │   └── Users/
│   │   │       ├── ListUsersActionTest.php
│   │   │       └── CreateUserActionTest.php
│   │   └── Web/
│   │       └── Dashboard/
│   │           └── ShowDashboardActionTest.php
│   ├── Models/
│   │   └── UserTest.php
│   ├── Enums/
│   │   └── UserRoleTest.php
│   ├── Factories/
│   │   └── UserDataFactoryTest.php
│   ├── FormRequests/
│   │   └── CreateUserRequestTest.php
│   └── Middlewares/
│       └── AuthenticateMiddlewareTest.php
├── Feature/
│   ├── Repositories/
│   │   └── UserRepositoryTest.php
│   ├── Controllers/
│   │   └── Api/
│   │       └── Users/
│   │           ├── ListUsersControllerTest.php
│   │           └── CreateUserControllerTest.php
│   └── Auth/
│       └── AuthenticationTest.php
├── Fixtures/
│   ├── ReplyerFixture.php
│   └── SimpleTestData.php
└── TestCase.php
```

---

## 10. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom fichier** | `{Component}Test.php` |
| **Nom classe** | `{Component}Test` |
| **Nom méthode** | `test_{methodName}_{expectedBehavior}_{condition}` |
| **Structure** | AAA (Arrange, Act, Assert) |
| **Type test** | Unit = classe isolée (sans DB), Feature = avec DB |
| **Factories Laravel** | ❌ Interdit (préférer `User::create()`) |
| **Mocking** | Utiliser les mocks pour les dépendances |
| **Base de données** | Unit = ❌ éviter, Feature = ✅ utiliser |
| **Repository** | Feature avec `RefreshDatabase` |

---

## 11. Règle d'or

> **Un test doit être explicite, isolé, rapide et lisible. Si un test est difficile à écrire, c'est que ton code est difficile à tester. Refactorise. Les données de test sont créées explicitement avec `User::create()`, pas avec des factories.**

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