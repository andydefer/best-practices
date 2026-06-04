# Principe d'usage des Actions (Version finale - 2RAD Pattern)

## 1. Définition

Une **Action** est un composant qui encapsule la logique d'une **route unique**. Elle reçoit **un seul Record** (créé par la Request), orchestre les Services/Repositories, et retourne une **ResponseFactory**.

**⚠️ Une Action a un type de retour unique : `ResponseFactory`.**

**⚠️ Une Action ne reçoit JAMAIS une Request. Elle reçoit TOUJOURS un Record.**

```
Route → ActionRoute → Request → getRecord() → Record → Action → ResponseFactory → Response
```

```php
// ✅ BON - Action API (utilisation du Repository pour la BD)
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowUserRecord $request */
        $user = $this->userRepository->find($request->user_id);
        
        if ($user === null) {
            abort(404, 'User not found');
        }
        
        return ResponseFactory::json(UserData::from($user));
    }
}

// ✅ BON - Action Web (Inertia)
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowDashboardRecord $request */
        $stats = $this->dashboardService->getStats($request);
        
        return ResponseFactory::inertia('Dashboard/Index', [
            'stats' => $stats->toArray(),
        ]);
    }
}
```

---

## 2. Les classes fondamentales

### 2.1. AbstractAction (Template Method Pattern)

La classe abstraite que **toute Action doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Actions\Actions;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\EmptyRecord;
use Exception;

abstract class AbstractAction
{
    private AbstractRecord $recordRequest;

    /**
     * Template method that defines the execution flow.
     * This method is final and cannot be overridden.
     */
    final public function run(AbstractRecord $request = new EmptyRecord()): ResponseFactory
    {
        $this->recordRequest = $request;

        try {
            $this->before($request);
            $response = $this->handle($request);
            $this->after(true, null, $request);
            return $response;
        } catch (Exception $e) {
            $this->after(false, $e, $request);
            throw $e;
        }
    }

    /**
     * Hook called before the main handle() method.
     * Use for authentication, authorization, pre-processing, logging.
     */
    protected function before(AbstractRecord $request): void
    {
        // Override in concrete actions
    }

    /**
     * Core business logic of the action.
     * Must return a ResponseFactory.
     */
    abstract protected function handle(AbstractRecord $request): ResponseFactory;

    /**
     * Hook called after the main handle() method.
     * Use for cleanup, post-processing, notifications, metrics.
     */
    protected function after(bool $success, ?Exception $error = null, AbstractRecord $request = new EmptyRecord()): void
    {
        // Override in concrete actions
    }

    /**
     * Get the request Record.
     */
    public function getRecordRequest(): AbstractRecord
    {
        return $this->recordRequest;
    }
}
```

### 2.2. Template Method Pattern - Cycle de vie

```
run(AbstractRecord $request)
    │
    ├── $this->recordRequest = $request
    │
    ├── before($request)     ← Hook optionnel
    │
    ├── handle($request)     ← Logique métier (obligatoire)
    │
    ├── after(true, null, $request) ← Hook optionnel
    │
    └── return $response
```

### 2.3. ResponseFactory

Factory qui construit des réponses HTTP de manière déclarative et testable.

| Méthode | Description | Retour |
|---------|-------------|--------|
| `json(AbstractData $data, int $code = 200)` | Réponse JSON pour API | `ResponseFactory` |
| `jsonRaw(array $data, int $code = 200)` | Réponse JSON brute | `ResponseFactory` |
| `redirect(string $url, int $code = 302)` | Redirection HTTP | `ResponseFactory` |
| `redirectRoute(string $route, array $params = [], int $code = 302)` | Redirection vers route nommée | `ResponseFactory` |
| `redirectBack(int $code = 302)` | Redirection vers page précédente | `ResponseFactory` |
| `inertia(string $component, array $props = [])` | Réponse Inertia.js | `ResponseFactory` |
| `view(string $view, array $data = [], int $code = 200)` | Vue Blade | `ResponseFactory` |
| `noContent()` | Réponse vide 204 | `ResponseFactory` |
| `stream(callable $callback, string $contentType, int $code = 200)` | Streaming de données | `ResponseFactory` |
| `sse(callable $callback)` | Server-Sent Events | `ResponseFactory` |
| `fileInline(string $filePath, ?string $fileName = null)` | Affichage de fichier | `ResponseFactory` |
| `fileDownload(string $filePath, ?string $fileName = null)` | Téléchargement forcé | `ResponseFactory` |
| `text(string $content, int $code = 200)` | Texte brut | `ResponseFactory` |
| `html(string $html, int $code = 200)` | HTML brut | `ResponseFactory` |

### 2.4. ActionRoute

Enregistrement simplifié des routes.

```php
// routes/api.php
use AndyDefer\Actions\Support\ActionRoute;
use App\Http\Requests\Api\Users\ShowUserRequest;
use App\Actions\Api\Users\ShowUserAction;

ActionRoute::get('/api/users/{id}', ShowUserRequest::class, ShowUserAction::class);
ActionRoute::post('/api/users', CreateUserRequest::class, CreateUserAction::class);
ActionRoute::put('/api/users/{id}', UpdateUserRequest::class, UpdateUserAction::class);
ActionRoute::delete('/api/users/{id}', DeleteUserRequest::class, DeleteUserAction::class);
```

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **Une Action est dédiée à UNE SEULE route. On ne peut pas réutiliser la même Action pour deux routes différentes.**

```php
// ✅ BON - Action dédiée à une route
final class ShowUserAction extends AbstractAction
{
    // Utilisée uniquement pour GET /api/users/{id}
}

// ❌ MAUVAIS - Action réutilisée pour plusieurs routes
final class UserAction extends AbstractAction
{
    public function list(): ResponseFactory { ... }   // GET /api/users
    public function show(): ResponseFactory { ... }   // GET /api/users/{id}
}
```

| Raison | Explication |
|--------|-------------|
| **SRP** | Chaque route a sa propre logique |
| **Évolution** | Modification d'une route sans impacter les autres |
| **Visibilité** | `ShowUserAction` dit clairement ce qu'il fait |

---

## 4. Règle : Une Action ne reçoit JAMAIS une Request (⚠️ RÈGLE ABSOLUE)

> **⚠️ Une Action ne peut jamais recevoir une Form Request en paramètre. Elle reçoit TOUJOURS un Record créé par la méthode `getRecord()` de la Request.**

```php
// ✅ BON - L'Action reçoit un Record
final class ShowUserAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowUserRecord $request */
        // $request contient TOUT ce dont l'Action a besoin
    }
}

// ❌ MAUVAIS - L'Action reçoit une Request (INTERDIT)
final class ShowUserAction extends AbstractAction
{
    protected function handle(ShowUserRequest $request): ResponseFactory  // ❌
    {
        // ...
    }
}
```

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Réutilisabilité** | Le Record peut être créé par d'autres moyens (console, job, test) |

---

## 5. Conventions de casse (⚠️ STRICTES)

> **Les conventions de casse sont OBLIGATOIRES et dépendent du type de classe.**

| Type de classe | Convention | Exemple |
|----------------|------------|---------|
| **Record** | `snake_case` pour les propriétés | `$user_id`, `$user_name` |
| **Data** | `camelCase` pour les propriétés | `$userId`, `$userName` |
| **Value Object** | `camelCase` pour les propriétés | `$emailAddress`, `$domain` |
| **Enum** | `SCREAMING_SNAKE_CASE` pour les cas | `UserRole::SUPER_USER` |

### 5.1. Exemple avec Record (snake_case)

```php
// ✅ BON - Record avec propriétés en snake_case
final class ShowUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly bool $include_posts = false,
    ) {}
}

// Hydratation (les clés correspondent exactement)
$record = ShowUserRecord::from([
    'user_id' => 123,
    'include_posts' => true,
]);
```

### 5.2. Exemple avec Value Object (camelCase)

```php
// ✅ BON - Value Object avec propriétés en camelCase
final class EmailAddress extends AbstractValueObject
{
    public function __construct(
        public readonly string $value,
    ) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }
    }
    
    public function getDomain(): string
    {
        return substr(strrchr($this->value, "@"), 1);
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}

// Hydratation via from() (camelCase)
$email = EmailAddress::from(['value' => 'john@example.com']);
```

### 5.3. Exemple avec Data (camelCase)

```php
// ✅ BON - Data avec propriétés en camelCase
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly EmailAddress $emailAddress,  // Value Object en camelCase
    ) {}
}
```

---

## 6. Type de retour unique (⚠️ RÈGLE STRICTE)

> **⚠️ Une Action retourne TOUJOURS `ResponseFactory`. Le type réel de la réponse (JSON, Inertia, redirection) est déterminé par la méthode appelée sur `ResponseFactory`.**

```php
// ✅ BON - Action API retourne ResponseFactory configurée en JSON
final class ListUsersAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $users = $this->userRepository->findAll();
        
        return ResponseFactory::json(UserData::collect($users));
    }
}

// ✅ BON - Action Web retourne ResponseFactory configurée en Inertia
final class ShowDashboardAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        return ResponseFactory::inertia('Dashboard/Index');
    }
}

// ✅ BON - Action retourne ResponseFactory configurée en redirection
final class CreateUserAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = $this->userRepository->create($request);
        
        return ResponseFactory::redirectRoute('users.show', ['id' => $user->user_id]);
    }
}
```

### 6.1. Gestion des erreurs d'accès

> **Utilisez `abort()` pour interrompre l'exécution. Ne changez pas le type de retour.**

```php
// ✅ BON - Utilisation de abort()
final class ShowUserAction extends AbstractAction
{
    protected function before(AbstractRecord $request): void
    {
        /** @var ShowUserRecord $request */
        if (!auth()->user()->can('view', $request->user_id)) {
            abort(403);  // ← Interrompt l'exécution
        }
    }
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        // ...
    }
}
```

---

## 7. Définition des routes avec ActionRoute

> **⚠️ Les routes sont définies avec `ActionRoute` qui fait le lien entre la Request et l'Action via `getRecord()`.**

### 7.1. Route API avec paramètre d'URL

```php
// routes/api.php
use AndyDefer\Actions\Support\ActionRoute;
use App\Http\Requests\Api\Users\ShowUserRequest;
use App\Actions\Api\Users\ShowUserAction;

ActionRoute::get('/api/users/{id}', ShowUserRequest::class, ShowUserAction::class);
```

### 7.2. Route API avec paramètres multiples

```php
ActionRoute::get('/api/users/{userId}/posts/{postId}', ShowUserPostRequest::class, ShowUserPostAction::class);
```

### 7.3. Route API sans paramètre

```php
ActionRoute::get('/api/users', ListUsersRequest::class, ListUsersAction::class);
```

### 7.4. Route POST

```php
ActionRoute::post('/api/users', CreateUserRequest::class, CreateUserAction::class);
```

---

## 8. Convention de nommage

> **Le nom de l'Action reflète l'action HTTP et la ressource. Le nom est au singulier.**

| Méthode | URL | Action |
|---------|-----|--------|
| GET | `/api/users` | `ListUsersAction` |
| GET | `/api/users/{id}` | `ShowUserAction` |
| POST | `/api/users` | `CreateUserAction` |
| PUT | `/api/users/{id}` | `ReplaceUserAction` |
| PATCH | `/api/users/{id}` | `UpdateUserAction` |
| DELETE | `/api/users/{id}` | `DeleteUserAction` |

```php
// ✅ BON
final class ListUsersAction extends AbstractAction { }
final class ShowUserAction extends AbstractAction { }
final class CreateUserAction extends AbstractAction { }

// ❌ MAUVAIS
final class UserAction extends AbstractAction { }
```

---

## 9. Création des Records (snake_case)

> **⚠️ Un Record a ses propriétés en `snake_case` et est hydraté via `StrictDataObject`.**

### 9.1. Le Record (snake_case)

```php
final class ShowUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly bool $include_posts = false,
    ) {}
}
```

### 9.2. La Request construit le Record

```php
final class ShowUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'include_posts' => ['sometimes', 'boolean'],
        ];
    }

    public function getRecord(): AbstractRecord
    {
        return ShowUserRecord::from([
            'user_id' => (int) $this->route('id'),
            'include_posts' => $this->boolean('include_posts'),
        ]);
    }
}
```

### 9.3. Initialisation explicite uniquement

```php
// ✅ BON - via from() (snake_case)
$record = ShowUserRecord::from([
    'user_id' => 123,
    'include_posts' => true,
]);

// ❌ MAUVAIS - Tableau direct interdit
$record = new ShowUserRecord($data);
```

---

## 10. Une Action retourne une Data via ResponseFactory

> **⚠️ Une Action DOIT retourner une Data DTO (propriétés en `camelCase`) quand elle utilise `ResponseFactory::json()`.**

```php
// ✅ BON - Retourne une Data
final class ListUsersAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $users = $this->userRepository->findAll();
        
        return ResponseFactory::json(UserData::collect($users));
    }
}
```

### 10.1. La Data DTO (camelCase)

```php
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
    ) {}
    
    public static function collect(iterable $records): array
    {
        $result = [];
        foreach ($records as $record) {
            $result[] = self::from($record);
        }
        return $result;
    }
}
```

---

## 11. Logique dans les Actions

> **⚠️ Une Action ne doit pas contenir de logique métier complexe. Elle orchestre des Services ou Repositories.**

### 11.1. Exemple : Action qui orchestre un Service

```php
// ✅ BON - Action qui orchestre un Service
final class CalculateOrderTotalAction extends AbstractAction
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculator,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var CalculateOrderTotalRecord $request */
        $total = $this->priceCalculator->calculateTotal($request->order_data);
        
        return ResponseFactory::json(new OrderTotalData(total: $total));
    }
}
```

### 11.2. Exemple : Action qui utilise un Repository

```php
// ✅ BON - Action qui utilise un Repository
final class CreateUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var CreateUserRecord $request */
        $user = $this->userRepository->create($request);
        
        return ResponseFactory::json(UserData::from($user), 201);
    }
}
```

### 11.3. ❌ MAUVAIS - Logique métier dans l'Action

```php
// ❌ MAUVAIS - Logique métier dans l'Action
final class CreateUserAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        // 50 lignes de validation, calcul, persistance...
        // À DÉPLACER DANS UN SERVICE OU UN REPOSITORY
    }
}
```

---

## 12. Règle : Pas de tests unitaires pour les Actions (⚠️ RÈGLE IMPORTANTE)

> **⚠️ On n'écrit JAMAIS de tests unitaires pour les Actions. Les Actions sont testées exclusivement via des tests d'intégration (Feature tests).**

### 12.1. Pourquoi ?

| Raison | Explication |
|--------|-------------|
| **Retour ResponseFactory** | Les Actions retournent une factory qui doit être convertie en réponse HTTP |
| **Dépendance à Laravel** | Les Actions dépendent du framework via ResponseFactory |
| **Test d'intégration suffisant** | Les requêtes HTTP réelles testent le comportement complet |

### 12.2. Test d'intégration (Feature test)

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Api\Users;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ShowUserActionTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_show_user_returns_user_data_when_authorized(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $response = $this->actingAs($user)
            ->getJson("/api/users/{$user->id}");
        
        $response->assertStatus(200);
        $response->assertJson([
            'userId' => $user->id,
            'userName' => 'John Doe',
            'userEmail' => 'john@example.com',
        ]);
    }
}
```

### 12.3. Récapitulatif des types de tests

| Composant | Tests unitaires | Tests d'intégration |
|-----------|----------------|---------------------|
| **Action** | ❌ Jamais | ✅ Toujours |
| **Service** | ✅ Oui | ❌ Rarement |
| **Repository** | ❌ Jamais (requêtes BD) | ✅ Toujours |
| **Request** | ✅ Oui (validation, getRecord) | ✅ Via l'Action |
| **Record** | ✅ Oui | ❌ Non |
| **Data** | ✅ Oui | ❌ Non |
| **Value Object** | ✅ Oui | ❌ Non |
| **Config** | ✅ Oui | ❌ Non |

---

## 13. Organisation des dossiers (2RAD Pattern)

```
app/
├── Actions/
│   ├── Api/
│   │   └── Users/
│   │       ├── ListUsersAction.php
│   │       ├── ShowUserAction.php
│   │       ├── CreateUserAction.php
│   │       └── DeleteUserAction.php
│   └── Web/
│       └── Dashboard/
│           └── ShowDashboardAction.php
├── Data/
│   ├── UserData.php          (camelCase)
│   └── OrderTotalData.php    (camelCase)
├── Records/
│   ├── ListUsersRecord.php   (snake_case)
│   ├── ShowUserRecord.php    (snake_case)
│   └── CreateUserRecord.php  (snake_case)
├── ValueObjects/
│   ├── EmailAddress.php      (camelCase)
│   └── Money.php              (camelCase)
├── Http/
│   └── Requests/
│       ├── Api/
│       │   └── Users/
│       │       ├── ListUsersRequest.php
│       │       ├── ShowUserRequest.php
│       │       └── CreateUserRequest.php
│       └── Web/
│           └── Dashboard/
│               └── ShowDashboardRequest.php
├── Repositories/
│   ├── UserRepository.php
│   └── OrderRepository.php
└── Services/
    ├── PriceCalculatorService.php
    └── UserValidationService.php
```

---

## 14. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractAction` |
| **Méthode principale** | `handle(AbstractRecord $request): ResponseFactory` |
| **Hooks** | `before()` et `after()` (optionnels) |
| **Nommage** | `{Verbe}{Ressource}Action` |
| **Paramètre** | ⚠️ UNIQUEMENT un Record (jamais une Request) |
| **Type de retour** | ⚠️ TOUJOURS `ResponseFactory` |
| **Route unique** | Une Action = une route |
| **Enregistrement** | Via `ActionRoute` |
| **Erreurs d'accès** | Utiliser `abort()` |
| **Logique métier** | Déléguée aux Services |
| **Accès base de données** | Via les Repositories |
| **Tests unitaires** | ❌ Jamais (tests d'intégration uniquement) |
| **Record propriétés** | `snake_case` |
| **Data propriétés** | `camelCase` |
| **Value Object propriétés** | `camelCase` |

---

## 15. Règle d'or

> **Une Action fait une chose : répondre à une route avec un type de réponse unique via `ResponseFactory`. Elle reçoit un Record (jamais une Request), orchestre via les hooks before/handle/after, et est enregistrée avec `ActionRoute`.**
>
> **⚠️ Conventions de casse STRICTES :**
> - **Record** : propriétés en `snake_case`, hydratation avec `StrictDataObject`
> - **Data** : propriétés en `camelCase`, hydratation avec `DataObject`
> - **Value Object** : propriétés en `camelCase`
> - **Enum** : cas en `SCREAMING_SNAKE_CASE`
>
> **Pas de tests unitaires pour les Actions, uniquement des tests d'intégration.**

```php
// L'Action parfaite
final class CreateOrderAction extends AbstractAction
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly PriceCalculatorService $priceCalculator,
    ) {}
    
    protected function before(AbstractRecord $request): void
    {
        /** @var CreateOrderRecord $request */
        if (!auth()->user()->can('create', Order::class)) {
            abort(403);
        }
    }
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var CreateOrderRecord $request */
        
        // Calcul du total (Service)
        $total = $this->priceCalculator->calculateTotal($request->order_items);
        
        // Création en base (Repository)
        $order = $this->orderRepository->create($request, $total);
        
        return ResponseFactory::json(OrderData::from($order), 201);
    }
}

// Record associé (snake_case)
final class CreateOrderRecord extends AbstractRecord
{
    public function __construct(
        public readonly array $order_items,
        public readonly int $user_id,
    ) {}
}

// Data associé (camelCase)
final class OrderData extends AbstractData
{
    public function __construct(
        public readonly int $orderId,
        public readonly float $orderTotal,
        public readonly string $orderStatus,
    ) {}
}

// Enregistrement de la route
ActionRoute::post('/api/orders', CreateOrderRequest::class, CreateOrderAction::class);

// Test d'intégration
final class CreateOrderActionTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_create_order_returns_order_data(): void
    {
        $response = $this->postJson('/api/orders', [
            'order_items' => [
                ['product_id' => 1, 'quantity' => 2],
            ],
        ]);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'orderId',
            'orderTotal',
            'orderStatus',
        ]);
    }
}
```