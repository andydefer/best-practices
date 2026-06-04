# Principe du routing (Version finale)

## 1. Définition

Les **routes** sont la configuration qui fait le lien entre une URL et une Action. Elles sont définies dans les fichiers `web.php` et `api.php` en utilisant la façade `ActionRoute`.

```
URL → ActionRoute → Request → getRecord() → Record → Action → Response
```

```php
use AndyDefer\Actions\Support\ActionRoute;

// Enregistrement d'une route API
ActionRoute::get('/api/users/{id}', ShowUserRequest::class, ShowUserAction::class);
```

---

## 2. Règle fondamentale (⚠️ IMMUABLE)

> **Les routes `GET` sont dans `web.php`. Toutes les autres méthodes (`POST`, `PUT`, `PATCH`, `DELETE`) sont dans `api.php`.**

| Fichier | Méthodes HTTP autorisées | Usage |
|---------|--------------------------|-------|
| `web.php` | `GET` uniquement | Pages web (rendues via Inertia) |
| `api.php` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` | API endpoints |

```php
// ✅ BON - web.php (GET uniquement)
ActionRoute::get('/dashboard', Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class);

// ✅ BON - api.php (toutes les méthodes)
ActionRoute::get('/users', Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class);
ActionRoute::post('/users', Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class);
ActionRoute::put('/users/{userId}', Api\Users\ReplaceUserRequest::class, Api\Users\ReplaceUserAction::class);
ActionRoute::patch('/users/{userId}', Api\Users\UpdateUserRequest::class, Api\Users\UpdateUserAction::class);
ActionRoute::delete('/users/{userId}', Api\Users\DeleteUserRequest::class, Api\Users\DeleteUserAction::class);

// ❌ MAUVAIS - POST dans web.php (INTERDIT)
ActionRoute::post('/users', Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class);
```

### 2.1 Pourquoi cette séparation ?

| Raison | Explication |
|--------|-------------|
| **Migrabilité** | Les routes web ne sont que des GET qui retournent des vues Inertia |
| **API unique** | Toute la logique d'écriture est dans l'API, réutilisable par web et mobile |
| **Strangler Pattern** | Migration progressive possible |

---

## 3. ActionRoute : La façade d'enregistrement

> **⚠️ `ActionRoute` remplace les closures manuelles. Elle assure la liaison entre une Request et une Action.**

### 3.1 Méthodes disponibles

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `get($uri, $requestClass, $actionClass)` | Route GET | `ActionRoute::get('/users', ListUsersRequest::class, ListUsersAction::class)` |
| `post($uri, $requestClass, $actionClass)` | Route POST | `ActionRoute::post('/users', CreateUserRequest::class, CreateUserAction::class)` |
| `put($uri, $requestClass, $actionClass)` | Route PUT | `ActionRoute::put('/users/{id}', UpdateUserRequest::class, UpdateUserAction::class)` |
| `patch($uri, $requestClass, $actionClass)` | Route PATCH | `ActionRoute::patch('/users/{id}', PatchUserRequest::class, PatchUserAction::class)` |
| `delete($uri, $requestClass, $actionClass)` | Route DELETE | `ActionRoute::delete('/users/{id}', DeleteUserRequest::class, DeleteUserAction::class)` |
| `match($methods, $uri, $requestClass, $actionClass)` | Multi-méthodes | `ActionRoute::match(['GET','POST'], '/resource', ResourceRequest::class, ResourceAction::class)` |
| `any($uri, $requestClass, $actionClass)` | Toutes méthodes | `ActionRoute::any('/webhook', WebhookRequest::class, WebhookAction::class)` |

### 3.2 Contrainte d'extension (Type Safety)

**Toute classe passée à `ActionRoute` DOIT étendre les classes abstraites appropriées.**

| Paramètre | Doit étendre | Raison |
|-----------|--------------|--------|
| `$requestClass` | `AbstractRequest` | Fournit la méthode `getRecord()` |
| `$actionClass` | `AbstractAction` | Fournit la méthode `run()` |

```php
// ✅ Valide
final class GetUserRequest extends AbstractRequest { ... }
final class GetUserAction extends AbstractAction { ... }

ActionRoute::get('/users/{id}', GetUserRequest::class, GetUserAction::class);

// ❌ Invalide - Lance une exception
ActionRoute::get('/users/{id}', stdClass::class, GetUserAction::class);
// Exception: "Request class "stdClass" must extend AbstractRequest"
```

---

## 4. TOUTE route a une Form Request

> **⚠️ TOUTE route DOIT avoir une Form Request associée, même les routes GET. La Form Request contient les règles de validation et les paramètres de requête (`?page`, `?filter`, etc.).**

```php
// web.php
ActionRoute::get('/users', Web\Users\ListUsersRequest::class, Web\Users\ListUsersAction::class);
ActionRoute::get('/users/{userId}', Web\Users\ShowUserRequest::class, Web\Users\ShowUserAction::class);

// api.php
ActionRoute::get('/users', Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class);
ActionRoute::post('/users', Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class);
```

---

## 5. Règle : Une Action reçoit JAMAIS une Request (⚠️ RÈGLE ABSOLUE)

> **⚠️ Une Action reçoit TOUJOURS un Record créé par la méthode `getRecord()` de la Form Request. `ActionRoute` est responsable d'appeler `getRecord()`.**

```php
// ✅ BON - ActionRoute appelle getRecord() automatiquement
ActionRoute::get('/users/{userId}', ShowUserRequest::class, ShowUserAction::class);

// La closure interne générée par ActionRoute
function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->getRecord());  // ✅ Appel automatique
}

// ❌ MAUVAIS - Ne pas utiliser ActionRoute (INTERDIT)
Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request);  // ❌ Passe la Request, pas le Record
});
```

### 5.1 Pourquoi cette règle ?

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Responsabilité claire** | La route transforme la Request en Record, l'Action ne connaît pas la Request |

---

## 6. Ordre des paramètres dans ActionRoute

> **L'ordre des paramètres d'URL est géré automatiquement par ActionRoute. La Request peut les récupérer via `$this->route('nom')`.**

```php
// URL: PUT /users/{userId}/posts/{postId}
ActionRoute::put('/users/{userId}/posts/{postId}', UpdatePostRequest::class, UpdatePostAction::class);

// La Request récupère les paramètres
final class UpdatePostRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return UpdatePostRecord::from([
            'user_id' => (int) $this->route('userId'),   // ← paramètre d'URL
            'post_id' => (int) $this->route('postId'),   // ← paramètre d'URL
            'content' => $this->input('content'),        // ← corps de la requête
        ]);
    }
}

// L'Action reçoit un Record typé
final class UpdatePostAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var UpdatePostRecord $request */
        $post = $this->postRepository->update($request->post_id, $request->content);
        return ResponseFactory::json(PostData::from($post));
    }
}
```

---

## 7. La méthode `getRecord()` selon le type de route

### 7.1 Route sans paramètre d'URL

```php
// Route
ActionRoute::get('/users', ListUsersRequest::class, ListUsersAction::class);

// Form Request
final class ListUsersRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return ListUsersRecord::from([
            'search' => $this->input('search'),
            'page' => (int) $this->input('page', 1),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }
}
```

### 7.2 Route avec un paramètre d'URL

```php
// Route
ActionRoute::get('/users/{userId}', ShowUserRequest::class, ShowUserAction::class);

// Form Request
final class ShowUserRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return ShowUserRecord::from([
            'user_id' => (int) $this->route('userId'),
            'current_user_id' => auth()->id(),
            'include_profile' => $this->boolean('include_profile'),
        ]);
    }
}
```

### 7.3 Route avec plusieurs paramètres d'URL

```php
// Route
ActionRoute::get('/users/{userId}/posts/{postId}', ShowPostRequest::class, ShowPostAction::class);

// Form Request
final class ShowPostRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return ShowPostRecord::from([
            'user_id' => (int) $this->route('userId'),
            'post_id' => (int) $this->route('postId'),
            'current_user_id' => auth()->id(),
            'include_comments' => $this->boolean('include_comments'),
        ]);
    }
}
```

### 7.4 Route POST (sans paramètre d'URL)

```php
// Route
ActionRoute::post('/users', CreateUserRequest::class, CreateUserAction::class);

// Form Request
final class CreateUserRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return CreateUserRecord::from([
            'user_name' => $this->input('name'),
            'user_email' => $this->input('email'),
            'user_password' => $this->input('password'),
            'created_by' => auth()->id(),
            'ip_address' => $this->ip(),
        ]);
    }
}
```

---

## 8. Utilisation avec les middlewares et préfixes

> **`ActionRoute` s'intègre parfaitement avec les groupes de routes Laravel.**

```php
// Routes avec middleware
Route::middleware(['auth', 'verified'])->group(function () {
    ActionRoute::get('/dashboard', DashboardRequest::class, DashboardAction::class);
    ActionRoute::get('/profile', ProfileRequest::class, ProfileAction::class);
});

// Routes avec préfixe
Route::prefix('admin')->group(function () {
    ActionRoute::get('/users', AdminListUsersRequest::class, AdminListUsersAction::class);
    ActionRoute::post('/users', AdminCreateUserRequest::class, AdminCreateUserAction::class);
});

// Routes avec préfixe et middleware
Route::prefix('api/v1')->middleware('throttle:api')->group(function () {
    ActionRoute::get('/products', ListProductsRequest::class, ListProductsAction::class);
    ActionRoute::get('/products/{id}', ShowProductRequest::class, ShowProductAction::class);
});
```

---

## 9. Logique dans les routes web (⚠️ RÈGLE STRICTE)

> **⚠️ Une route web GET ne peut avoir que de la logique de validation ou de vérification via des Workers qui retournent `bool` (et `abort()` si échec) ou lèvent une exception.**

```php
// routes/web.php
ActionRoute::get('/dashboard', ShowDashboardRequest::class, ShowDashboardAction::class);

// Action Web
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowDashboardRecord $request */
        // Le Worker contient la logique de validation
        // Il appelle abort(403) si l'accès est refusé
        $this->handleAccess->execute($request);
        
        return ResponseFactory::inertia('Dashboard/Index');
    }
}
```

---

## 10. Séparation Web vs API

> **Une Action web et une Action API sont deux classes différentes. La logique métier est dans l'API. La route web GET ne fait que valider et rendre la vue.**

```php
// web.php
ActionRoute::get('/dashboard', Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class);

// api.php
ActionRoute::get('/dashboard', Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class);
```

### 10.1 Action Web (validation uniquement)

```php
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $this->handleAccess->execute($request);
        return ResponseFactory::inertia('Dashboard/Index');
    }
}
```

### 10.2 Action API (logique métier complète)

```php
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $dashboardRecord = $this->dashboardService->getDashboard($request);
        $dashboardData = DashboardData::fromRecord($dashboardRecord);
        
        return ResponseFactory::json($dashboardData);
    }
}
```

---

## 11. Paramètres d'URL vs Query Parameters

| Type | Emplacement | Convention | Récupération |
|------|-------------|------------|--------------|
| **Paramètre d'URL** | `{userId}` | `camelCase` | `$this->route('userId')` |
| **Paramètre de requête** | `?user_slug=&page=` | `snake_case` | `$this->input('user_slug')` |

```php
// URL: GET /users?user_slug=john&page=2&per_page=15

// Route
ActionRoute::get('/users', ListUsersRequest::class, ListUsersAction::class);

// Form Request
final class ListUsersRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'user_slug' => ['nullable', 'string', 'exists:users,slug'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
    
    public function getRecord(): AbstractRecord
    {
        return ListUsersRecord::from([
            'user_slug' => $this->input('user_slug'),
            'page' => (int) $this->input('page', 1),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }
}
```

---

## 12. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **`web.php`** | `GET` uniquement (via `ActionRoute::get()`) |
| **`api.php`** | Toutes méthodes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) |
| **ActionRoute** | ✅ Utilisation OBLIGATOIRE (pas de closures manuelles) |
| **Request** | ✅ DOIT étendre `AbstractRequest` |
| **Action** | ✅ DOIT étendre `AbstractAction` |
| **Appel à l'Action** | ✅ `ActionRoute` appelle `getRecord()` automatiquement |
| **Paramètre URL** | ✅ Récupéré via `$this->route('nom')` |
| **Paramètre requête** | ✅ Récupéré via `$this->input('nom')` |
| **Web vs API** | ✅ Actions séparées |

---

## 13. Règle d'or

> **Une route web GET ne fait que valider et rendre une vue Inertia. La logique métier est dans l'API. Toute route utilise `ActionRoute`. Toute Request étend `AbstractRequest`. Toute Action étend `AbstractAction`. Les paramètres d'URL sont récupérés via `$this->route()` dans la Request.**

```php
// ✅ BON - web.php
ActionRoute::get('/dashboard', Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class);

// ✅ BON - api.php (GET)
ActionRoute::get('/dashboard', Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class);

// ✅ BON - api.php (POST)
ActionRoute::post('/users', Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class);

// ✅ BON - api.php (avec paramètre d'URL)
ActionRoute::get('/users/{userId}', Api\Users\ShowUserRequest::class, Api\Users\ShowUserAction::class);

// ✅ BON - api.php (avec plusieurs paramètres d'URL)
ActionRoute::put('/users/{userId}/posts/{postId}', Api\Posts\UpdatePostRequest::class, Api\Posts\UpdatePostAction::class);

// ✅ BON - Avec middleware
Route::middleware('auth')->group(function () {
    ActionRoute::get('/profile', ProfileRequest::class, ProfileAction::class);
});

// ✅ BON - Avec préfixe
Route::prefix('admin')->group(function () {
    ActionRoute::get('/users', AdminListUsersRequest::class, AdminListUsersAction::class);
});

// ❌ MAUVAIS - Ne pas utiliser ActionRoute
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->toRecord());  // ❌ Utiliser ActionRoute
});

// ❌ MAUVAIS - Passe la Request à l'Action
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request);  // ❌ DOIT être $request->getRecord()
});
```

---

## 14. Exemple complet : Fichiers de routes

### routes/web.php

```php
<?php

declare(strict_types=1);

use AndyDefer\Actions\Support\ActionRoute;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| ⚠️ GET UNIQUEMENT ! Toutes les méthodes d'écriture sont dans api.php
|
*/

ActionRoute::get('/', Web\Home\ShowHomeRequest::class, Web\Home\ShowHomeAction::class);

ActionRoute::get('/dashboard', Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class);

ActionRoute::get('/users', Web\Users\ListUsersRequest::class, Web\Users\ListUsersAction::class);
ActionRoute::get('/users/{userId}', Web\Users\ShowUserRequest::class, Web\Users\ShowUserAction::class);

ActionRoute::get('/products', Web\Products\ListProductsRequest::class, Web\Products\ListProductsAction::class);
ActionRoute::get('/products/{productId}', Web\Products\ShowProductRequest::class, Web\Products\ShowProductAction::class);
```

### routes/api.php

```php
<?php

declare(strict_types=1);

use AndyDefer\Actions\Support\ActionRoute;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Toutes les méthodes HTTP sont autorisées ici.
| Le préfixe /api est automatiquement ajouté par Laravel.
|
*/

// Users
ActionRoute::get('/users', Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class);
ActionRoute::post('/users', Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class);
ActionRoute::get('/users/{userId}', Api\Users\ShowUserRequest::class, Api\Users\ShowUserAction::class);
ActionRoute::put('/users/{userId}', Api\Users\ReplaceUserRequest::class, Api\Users\ReplaceUserAction::class);
ActionRoute::patch('/users/{userId}', Api\Users\UpdateUserRequest::class, Api\Users\UpdateUserAction::class);
ActionRoute::delete('/users/{userId}', Api\Users\DeleteUserRequest::class, Api\Users\DeleteUserAction::class);

// Products
ActionRoute::get('/products', Api\Products\ListProductsRequest::class, Api\Products\ListProductsAction::class);
ActionRoute::get('/products/{productId}', Api\Products\ShowProductRequest::class, Api\Products\ShowProductAction::class);
ActionRoute::post('/products', Api\Products\CreateProductRequest::class, Api\Products\CreateProductAction::class);
ActionRoute::put('/products/{productId}', Api\Products\ReplaceProductRequest::class, Api\Products\ReplaceProductAction::class);
ActionRoute::delete('/products/{productId}', Api\Products\DeleteProductRequest::class, Api\Products\DeleteProductAction::class);

// Dashboard
ActionRoute::get('/dashboard', Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class);
```
---