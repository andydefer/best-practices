# Principe du routing (Version finale)

## 1. Définition

Les **routes** sont la configuration qui fait le lien entre une URL et une Action. Elles sont définies dans les fichiers `web.php` et `api.php` en utilisant la fonction helper `action_route()`.

```
URL → action_route() → Request → getRecord() → Record → Action → Response
```

```php
use function action_route;

// Enregistrement d'une route API
Route::get('/api/users/{id}', action_route(ShowUserRequest::class, ShowUserAction::class));
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
Route::get('/dashboard', action_route(Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class));

// ✅ BON - api.php (toutes les méthodes)
Route::get('/users', action_route(Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class));
Route::post('/users', action_route(Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class));
Route::put('/users/{userId}', action_route(Api\Users\ReplaceUserRequest::class, Api\Users\ReplaceUserAction::class));
Route::patch('/users/{userId}', action_route(Api\Users\UpdateUserRequest::class, Api\Users\UpdateUserAction::class));
Route::delete('/users/{userId}', action_route(Api\Users\DeleteUserRequest::class, Api\Users\DeleteUserAction::class));

// ❌ MAUVAIS - POST dans web.php (INTERDIT)
Route::post('/users', action_route(Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class));
```

### 2.1 Pourquoi cette séparation ?

| Raison | Explication |
|--------|-------------|
| **Migrabilité** | Les routes web ne sont que des GET qui retournent des vues Inertia |
| **API unique** | Toute la logique d'écriture est dans l'API, réutilisable par web et mobile |
| **Strangler Pattern** | Migration progressive possible |

---

## 3. `action_route()` : La fonction helper d'enregistrement

> **⚠️ `action_route()` remplace l'ancienne façade `ActionRoute` (dépréciée). Elle retourne une closure qui assure la liaison entre une Request et une Action, tout en préservant l'API fluide de Laravel.**

### 3.1 Utilisation avec `Route`

```php
use function action_route;

// La fonction s'utilise directement comme callback de route
Route::get('/users/{id}', action_route(ShowUserRequest::class, ShowUserAction::class))
    ->name('users.show')
    ->middleware('auth');
```

### 3.2 Ce que fait `action_route()` automatiquement

```php
// Cette ligne :
Route::get('/users/{id}', action_route(ShowUserRequest::class, ShowUserAction::class));

// Génère cette closure interne :
function ($id, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->getRecord());
}
```

### 3.3 Contrainte d'extension (Type Safety)

**Toute classe passée à `action_route()` DOIT étendre les classes abstraites appropriées.**

| Paramètre | Doit étendre | Raison |
|-----------|--------------|--------|
| `$requestClass` | `AbstractRequest` | Fournit la méthode `getRecord()` |
| `$actionClass` | `AbstractAction` | Fournit la méthode `run()` |

```php
// ✅ Valide
final class GetUserRequest extends AbstractRequest { ... }
final class GetUserAction extends AbstractAction { ... }

Route::get('/users/{id}', action_route(GetUserRequest::class, GetUserAction::class));

// ❌ Invalide - Lance une exception
Route::get('/users/{id}', action_route(stdClass::class, GetUserAction::class));
// Exception: "Request class "stdClass" must extend AbstractRequest"
```

---

## 4. TOUTE route a une Form Request

> **⚠️ TOUTE route DOIT avoir une Form Request associée, même les routes GET. La Form Request contient les règles de validation et les paramètres de requête (`?page`, `?filter`, etc.).**

```php
// web.php
Route::get('/users', action_route(Web\Users\ListUsersRequest::class, Web\Users\ListUsersAction::class));
Route::get('/users/{userId}', action_route(Web\Users\ShowUserRequest::class, Web\Users\ShowUserAction::class));

// api.php
Route::get('/users', action_route(Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class));
Route::post('/users', action_route(Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class));
```

---

## 5. Règle : Une Action reçoit JAMAIS une Request (⚠️ RÈGLE ABSOLUE)

> **⚠️ Une Action reçoit TOUJOURS un Record créé par la méthode `getRecord()` de la Form Request. `action_route()` est responsable d'appeler `getRecord()`.**

```php
// ✅ BON - action_route() appelle getRecord() automatiquement
Route::get('/users/{userId}', action_route(ShowUserRequest::class, ShowUserAction::class));

// ❌ MAUVAIS - Ne pas utiliser action_route() (INTERDIT)
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

## 6. Ordre des paramètres dans `action_route()`

> **L'ordre des paramètres d'URL est géré automatiquement par `action_route()`. La Request peut les récupérer via `$this->route('nom')`.**

```php
// URL: PUT /users/{userId}/posts/{postId}
Route::put('/users/{userId}/posts/{postId}', action_route(UpdatePostRequest::class, UpdatePostAction::class));

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
Route::get('/users', action_route(ListUsersRequest::class, ListUsersAction::class));

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
Route::get('/users/{userId}', action_route(ShowUserRequest::class, ShowUserAction::class));

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
Route::get('/users/{userId}/posts/{postId}', action_route(ShowPostRequest::class, ShowPostAction::class));

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
Route::post('/users', action_route(CreateUserRequest::class, CreateUserAction::class));

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

## 8. Utilisation avec les middlewares, préfixes et nommage

> **`action_route()` s'intègre parfaitement avec l'API fluide des routes Laravel.**

```php
use function action_route;

// Routes avec middleware et nom
Route::get('/dashboard', action_route(DashboardRequest::class, DashboardAction::class))
    ->name('dashboard')
    ->middleware(['auth', 'verified']);

// Routes avec préfixe
Route::prefix('admin')->group(function () {
    Route::get('/users', action_route(AdminListUsersRequest::class, AdminListUsersAction::class))
        ->name('admin.users.index');
    Route::post('/users', action_route(AdminCreateUserRequest::class, AdminCreateUserAction::class))
        ->name('admin.users.store');
});

// Routes avec préfixe, middleware et nom
Route::prefix('api/v1')->middleware('throttle:api')->group(function () {
    Route::get('/products', action_route(ListProductsRequest::class, ListProductsAction::class))
        ->name('api.v1.products.index');
    Route::get('/products/{id}', action_route(ShowProductRequest::class, ShowProductAction::class))
        ->name('api.v1.products.show');
});
```

---

## 9. Logique dans les routes web (⚠️ RÈGLE STRICTE)

> **⚠️ Une route web GET ne peut avoir que de la logique de validation ou de vérification via des Workers qui retournent `bool` (et `abort()` si échec) ou lèvent une exception.**

```php
// routes/web.php
Route::get('/dashboard', action_route(ShowDashboardRequest::class, ShowDashboardAction::class));

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
Route::get('/dashboard', action_route(Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class));

// api.php
Route::get('/dashboard', action_route(Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class));
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
        $dashboardData = DashboardData::from($dashboardRecord);
        
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
Route::get('/users', action_route(ListUsersRequest::class, ListUsersAction::class));

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

## 12. Convention de nommage (⚠️ OBLIGATOIRE)

> **Le nom de la route détermine le nom des dossiers et des classes associées. Cette convention est impérative pour la cohérence du code.**

### 12.1 Règle fondamentale

La route détermine la convention de nommage complète :

```bash
# La route détermine la convention
api.users.show → Api/Users/Show
api.users.index → Api/Users/Index
```

### 12.2 Correspondance complète

| Composant | Convention | Exemple (`api.users.show`) |
|-----------|------------|---------------------------|
| **Action** | `Actions\{Chemin}Action` | `App\Actions\Api\Users\ShowAction` |
| **Request** | `Http\Requests\{Chemin}Request` | `App\Http\Requests\Api\Users\ShowRequest` |
| **Record** | `Records\{Chemin}Record` | `App\Records\Api\Users\ShowRecord` |
| **Data** | `Data\{Chemin}Data` | `App\Data\Api\Users\ShowData` |

### 12.3 Exemples concrets

| Nom de route | Dossier | Action | Request | Record | Data |
|--------------|---------|--------|---------|--------|------|
| `api.users.show` | `Api/Users` | `ShowAction` | `ShowRequest` | `ShowRecord` | `ShowData` |
| `api.users.index` | `Api/Users` | `IndexAction` | `IndexRequest` | `IndexRecord` | `IndexData` |
| `api.users.store` | `Api/Users` | `StoreAction` | `StoreRequest` | `StoreRecord` | `StoreData` |
| `api.doctors.show` | `Api/Doctors` | `ShowAction` | `ShowRequest` | `ShowRecord` | `ShowData` |
| `web.dashboard.index` | `Web/Dashboard` | `IndexAction` | `IndexRequest` | `IndexRecord` | `IndexData` |

### 12.4 Génération automatique avec Directive Forge

```bash
# La commande crée automatiquement les 4 classes avec les bons noms
./vendor/bin/directive make-action api/users/show --fully

# Résultat :
# ✅ Action:  App\Actions\Api\Users\ShowAction
# ✅ Request: App\Http\Requests\Api\Users\ShowRequest  
# ✅ Record:  App\Records\Api\Users\ShowRecord
# ✅ Data:    App\Data\Api\Users\ShowData
```

### 12.5 Pourquoi cette convention ?

| Raison | Explication |
|--------|-------------|
| **Prédictible** | Le développeur sait immédiatement où chercher chaque classe |
| **Auto-documenté** | Le chemin du fichier indique la route qu'il sert |
| **IDE-friendly** | La complétion automatique fonctionne parfaitement |
| **Sans collision** | Plusieurs endpoints avec le même nom dans des dossiers différents coexistent |

### 12.6 À respecter impérativement

```php
// ✅ Bon : Le namespace correspond au chemin
namespace App\Actions\Api\Users;
final class ShowAction extends AbstractAction {}

// ❌ Mauvais : Namespace incohérent avec le chemin
namespace App\Actions\Doctors;
final class ShowDoctorAction extends AbstractAction {}
```

> **Important :** Cette convention de nommage est obligatoire pour que l'autoloading, les outils d'analyse statique (PHPStan, Psalm) et la maintenance à long terme fonctionnent correctement.

---

## 13. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **`web.php`** | `GET` uniquement (via `Route::get()` + `action_route()`) |
| **`api.php`** | Toutes méthodes (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) |
| **`action_route()`** | ✅ Utilisation OBLIGATOIRE (pas de closures manuelles) |
| **Request** | ✅ DOIT étendre `AbstractRequest` |
| **Action** | ✅ DOIT étendre `AbstractAction` |
| **Appel à l'Action** | ✅ `action_route()` appelle `getRecord()` automatiquement |
| **Paramètre URL** | ✅ Récupéré via `$this->route('nom')` |
| **Paramètre requête** | ✅ Récupéré via `$this->input('nom')` |
| **Web vs API** | ✅ Actions séparées |
| **Convention nommage** | ✅ Le nom de la route détermine les dossiers et classes |

---

## 14. Règle d'or

> **Une route web GET ne fait que valider et rendre une vue Inertia. La logique métier est dans l'API. Toute route utilise `action_route()`. Toute Request étend `AbstractRequest`. Toute Action étend `AbstractAction`. Les paramètres d'URL sont récupérés via `$this->route()` dans la Request. Le nom de la route détermine la convention de nommage des classes.**

```php
// ✅ BON - web.php
Route::get('/dashboard', action_route(Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class))
    ->name('dashboard');

// ✅ BON - api.php (GET)
Route::get('/dashboard', action_route(Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class))
    ->name('api.dashboard.show');

// ✅ BON - api.php (POST)
Route::post('/users', action_route(Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class))
    ->name('api.users.store');

// ✅ BON - api.php (avec paramètre d'URL)
Route::get('/users/{userId}', action_route(Api\Users\ShowUserRequest::class, Api\Users\ShowUserAction::class))
    ->name('api.users.show');

// ✅ BON - Avec middleware
Route::middleware('auth')->group(function () {
    Route::get('/profile', action_route(ProfileRequest::class, ProfileAction::class))
        ->name('profile');
});

// ✅ BON - Avec préfixe
Route::prefix('admin')->group(function () {
    Route::get('/users', action_route(AdminListUsersRequest::class, AdminListUsersAction::class))
        ->name('admin.users.index');
});

// ❌ MAUVAIS - Ne pas utiliser action_route()
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->getRecord());
});

// ❌ MAUVAIS - Passe la Request à l'Action
Route::get('/users', action_route(ListUsersRequest::class, function($request) {
    // Ceci n'est pas possible avec action_route()
}));
```

---

## 15. Exemple complet : Fichiers de routes

### routes/web.php

```php
<?php

declare(strict_types=1);

use function action_route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| ⚠️ GET UNIQUEMENT ! Toutes les méthodes d'écriture sont dans api.php
|
*/

Route::get('/', action_route(Web\Home\ShowHomeRequest::class, Web\Home\ShowHomeAction::class))
    ->name('home');

Route::get('/dashboard', action_route(Web\Dashboard\ShowDashboardRequest::class, Web\Dashboard\ShowDashboardAction::class))
    ->name('dashboard')
    ->middleware('auth');

Route::get('/users', action_route(Web\Users\ListUsersRequest::class, Web\Users\ListUsersAction::class))
    ->name('users.index');

Route::get('/users/{userId}', action_route(Web\Users\ShowUserRequest::class, Web\Users\ShowUserAction::class))
    ->name('users.show');

Route::get('/products', action_route(Web\Products\ListProductsRequest::class, Web\Products\ListProductsAction::class))
    ->name('products.index');

Route::get('/products/{productId}', action_route(Web\Products\ShowProductRequest::class, Web\Products\ShowProductAction::class))
    ->name('products.show');
```

### routes/api.php

```php
<?php

declare(strict_types=1);

use function action_route;

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
Route::get('/users', action_route(Api\Users\ListUsersRequest::class, Api\Users\ListUsersAction::class))
    ->name('api.users.index');

Route::post('/users', action_route(Api\Users\CreateUserRequest::class, Api\Users\CreateUserAction::class))
    ->name('api.users.store');

Route::get('/users/{userId}', action_route(Api\Users\ShowUserRequest::class, Api\Users\ShowUserAction::class))
    ->name('api.users.show');

Route::put('/users/{userId}', action_route(Api\Users\ReplaceUserRequest::class, Api\Users\ReplaceUserAction::class))
    ->name('api.users.replace');

Route::patch('/users/{userId}', action_route(Api\Users\UpdateUserRequest::class, Api\Users\UpdateUserAction::class))
    ->name('api.users.update');

Route::delete('/users/{userId}', action_route(Api\Users\DeleteUserRequest::class, Api\Users\DeleteUserAction::class))
    ->name('api.users.destroy');

// Products
Route::get('/products', action_route(Api\Products\ListProductsRequest::class, Api\Products\ListProductsAction::class))
    ->name('api.products.index');

Route::get('/products/{productId}', action_route(Api\Products\ShowProductRequest::class, Api\Products\ShowProductAction::class))
    ->name('api.products.show');

Route::post('/products', action_route(Api\Products\CreateProductRequest::class, Api\Products\CreateProductAction::class))
    ->name('api.products.store');

Route::put('/products/{productId}', action_route(Api\Products\ReplaceProductRequest::class, Api\Products\ReplaceProductAction::class))
    ->name('api.products.replace');

Route::delete('/products/{productId}', action_route(Api\Products\DeleteProductRequest::class, Api\Products\DeleteProductAction::class))
    ->name('api.products.destroy');

// Dashboard
Route::get('/dashboard', action_route(Api\Dashboard\ShowDashboardRequest::class, Api\Dashboard\ShowDashboardAction::class))
    ->name('api.dashboard.show');
```

---

## 16. Migration depuis ActionRoute (dépréciée)

Si vous utilisiez l'ancienne façade `ActionRoute`, voici comment migrer :

```php
// Ancienne syntaxe (dépréciée)
ActionRoute::get('/api/users', ListUsersRequest::class, ListUsersAction::class);

// Nouvelle syntaxe (recommandée)
use function action_route;

Route::get('/api/users', action_route(ListUsersRequest::class, ListUsersAction::class))
    ->name('api.users.index');
```
---