# Principe d'usage des Routes (Version finale)

## 1. Définition

Les **routes** sont la configuration qui fait le lien entre une URL et une Action. Elles sont définies dans les fichiers `web.php` et `api.php`.

```
URL → Route → Form Request → toRecord() → Record → Action → Response
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
Route::get('/users', function (Web\Users\ListUsersRequest $request, Web\Users\ListUsersAction $action) {
    return $action->run($request->toRecord());
});

// ✅ BON - api.php (toutes les méthodes)
Route::get('/users', function (Api\Users\ListUsersRequest $request, Api\Users\ListUsersAction $action) {
    return $action->run($request->toRecord());
});

Route::post('/users', function (Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) {
    return $action->run($request->toRecord());
});

Route::put('/users/{userId}', function ($userId, Api\Users\ReplaceUserRequest $request, Api\Users\ReplaceUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

Route::patch('/users/{userId}', function ($userId, Api\Users\UpdateUserRequest $request, Api\Users\UpdateUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

Route::delete('/users/{userId}', function ($userId, Api\Users\DeleteUserRequest $request, Api\Users\DeleteUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// ❌ MAUVAIS - POST dans web.php
Route::post('/users', function (Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) {
    return $action->run($request->toRecord());
});  // ❌ INTERDIT
```

### 2.1 Pourquoi cette séparation ?

| Raison | Explication |
|--------|-------------|
| **Migrabilité** | Les routes web ne sont que des GET qui retournent des vues Inertia |
| **API unique** | Toute la logique d'écriture est dans l'API, réutilisable par web et mobile |
| **Strangler Pattern** | Migration progressive possible |

---

## 3. Règle : TOUTE route a une Form Request

> **⚠️ TOUTE route DOIT avoir une Form Request associée, même les routes GET. La Form Request contient les règles de validation et les paramètres de requête (`?page`, `?filter`, etc.).**

```php
// web.php
Route::get('/users', function (Web\Users\ListUsersRequest $request, Web\Users\ListUsersAction $action) {
    return $action->run($request->toRecord());
});

Route::get('/users/{userId}', function ($userId, Web\Users\ShowUserRequest $request, Web\Users\ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// api.php
Route::get('/users', function (Api\Users\ListUsersRequest $request, Api\Users\ListUsersAction $action) {
    return $action->run($request->toRecord());
});

Route::post('/users', function (Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) {
    return $action->run($request->toRecord());
});
```

---

## 4. Règle : Une Action ne reçoit JAMAIS une Request (⚠️ RÈGLE ABSOLUE)

> **⚠️ Une Action reçoit TOUJOURS un Record créé par la méthode `toRecord()` de la Form Request. La route est responsable d'appeler `toRecord()`.**

```php
// ✅ BON - La route appelle toRecord() et passe un Record à l'Action
Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// ❌ MAUVAIS - La route passe la Request directement à l'Action (INTERDIT)
Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($userId, $request);  // ❌
});
```

### 4.1 Pourquoi cette règle ?

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Responsabilité claire** | La route transforme la Request en Record, l'Action ne connaît pas la Request |

---

## 5. Règle : Pas de paramètre sans être utilisé

> **⚠️ Un paramètre d'URL (`{userId}`) ou un paramètre de requête (query string) ne peut pas exister sans être passé à l'Action via le Record. Supprimez-le si vous ne l'utilisez pas.**

```php
// ❌ MAUVAIS - Paramètre d'URL non utilisé
// URL: GET /users/{userId}
Route::get('/users/{userId}', function (ListUsersAction $action) {
    return $action->run();
});

// ✅ BON - Paramètre d'URL utilisé
Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// ❌ MAUVAIS - Paramètre query non utilisé
// URL: GET /users?page=1
Route::get('/users', function (ListUsersAction $action) {
    return $action->run();
});

// ✅ BON - Paramètre query utilisé via Form Request
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->toRecord());
});
```

---

## 6. Ordre des paramètres dans la route

> **Les paramètres d'URL sont passés dans l'ordre de la route. La Form Request est toujours avant l'Action.**

```php
// URL: PUT /users/{userId}/posts/{postId}
Route::put('/users/{userId}/posts/{postId}', 
    function ($userId, $postId, UpdatePostRequest $request, UpdatePostAction $action) {
        return $action->run($request->toRecord((int) $userId, (int) $postId));
    }
);

// La signature de la méthode run() reçoit un Record
final class UpdatePostAction extends AbstractAction
{
    public function run(UpdatePostRecord $record): JsonResponse
    {
        // $record->userId et $record->postId sont disponibles
    }
}
```

---

## 7. La méthode `toRecord()` selon le type de route

### 7.1 Route sans paramètre d'URL

```php
// Route
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->toRecord());
});

// Form Request
final class ListUsersRequest extends AbstractRequest
{
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            search: $this->input('search'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
        );
    }
}

// Action
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRecord $record): JsonResponse { ... }
}
```

### 7.2 Route avec un paramètre d'URL

```php
// Route
Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// Form Request
final class ShowUserRequest extends AbstractRequest
{
    public function toRecord(int $userId): ShowUserRecord
    {
        return new ShowUserRecord(
            id: $userId,
            currentUserId: auth()->id(),
            includeProfile: $this->boolean('include_profile'),
        );
    }
}

// Action
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRecord $record): JsonResponse { ... }
}
```

### 7.3 Route avec plusieurs paramètres d'URL

```php
// Route
Route::get('/users/{userId}/posts/{postId}', function ($userId, $postId, ShowPostRequest $request, ShowPostAction $action) {
    return $action->run($request->toRecord((int) $userId, (int) $postId));
});

// Form Request
final class ShowPostRequest extends AbstractRequest
{
    public function toRecord(int $userId, int $postId): ShowPostRecord
    {
        return new ShowPostRecord(
            userId: $userId,
            postId: $postId,
            currentUserId: auth()->id(),
            includeComments: $this->boolean('include_comments'),
        );
    }
}

// Action
final class ShowPostAction extends AbstractAction
{
    public function run(ShowPostRecord $record): JsonResponse { ... }
}
```

### 7.4 Route POST (sans paramètre d'URL)

```php
// Route
Route::post('/users', function (CreateUserRequest $request, CreateUserAction $action) {
    return $action->run($request->toRecord());
});

// Form Request
final class CreateUserRequest extends AbstractRequest
{
    public function toRecord(): CreateUserRecord
    {
        return new CreateUserRecord(
            name: $this->input('name'),
            email: $this->input('email'),
            password: $this->input('password'),
            createdBy: auth()->id(),
            ip: $this->ip(),
        );
    }
}

// Action
final class CreateUserAction extends AbstractAction
{
    public function run(CreateUserRecord $record): JsonResponse { ... }
}
```

---

## 8. Logique dans les routes web (⚠️ RÈGLE STRICTE)

> **⚠️ Une route web GET ne peut avoir que de la logique de validation ou de vérification via des Tasks qui retournent `bool` (et `abort()` si échec) ou lèvent une exception.**

```php
// routes/web.php
Route::get('/dashboard', function (ShowDashboardRequest $request, ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});

// Form Request
final class ShowDashboardRequest extends AbstractRequest
{
    public function toRecord(): ShowDashboardRecord
    {
        return new ShowDashboardRecord(
            userId: auth()->id(),
            url: $this->fullUrl(),
            ip: $this->ip(),
        );
    }
}

// Action Web
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    public function run(ShowDashboardRecord $record): InertiaResponse
    {
        // Le Worker contient la logique de validation
        // Il appelle abort(403) si l'accès est refusé
        $this->handleAccess->execute($record);
        
        return $this->inertia('Dashboard/Index');
    }
}
```

---

## 9. Séparation Web vs API

> **Une Action web et une Action API sont deux classes différentes. La logique métier est dans l'API. La route web GET ne fait que valider et rendre la vue.**

```php
// web.php
Route::get('/dashboard', function (Web\Dashboard\ShowDashboardRequest $request, Web\Dashboard\ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});

// api.php
Route::get('/dashboard', function (Api\Dashboard\ShowDashboardRequest $request, Api\Dashboard\ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});
```

### 9.1 Action Web (validation uniquement)

```php
// App\Actions\Web\Dashboard\ShowDashboardAction
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    public function run(ShowDashboardRecord $record): InertiaResponse
    {
        $this->handleAccess->execute($record);
        return $this->inertia('Dashboard/Index');
    }
}
```

### 9.2 Action API (logique métier complète)

```php
// App\Actions\Api\Dashboard\ShowDashboardAction
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}
    
    public function run(ShowDashboardRecord $record): JsonResponse
    {
        $dashboardRecord = $this->dashboardService->getDashboard($record);
        $dashboardData = DashboardData::fromRecord($dashboardRecord);
        
        return $this->json($dashboardData);
    }
}
```

---

## 10. Paramètres d'URL vs Query Parameters

| Type | Emplacement | Convention | Récupération |
|------|-------------|------------|--------------|
| **Paramètre d'URL** | `{userId}` | `camelCase` | Passé à `toRecord()` comme paramètre |
| **Paramètre de requête** | `?user_slug=&page=` | `snake_case` | Via `$this->input()` dans `toRecord()` |

```php
// URL: GET /users?user_slug=john&page=2&per_page=15

// Form Request
final class ListUsersRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'user_slug' => 'nullable|string|exists:users,slug',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
    
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            userSlug: $this->input('user_slug'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
        );
    }
}

// Route
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->toRecord());
});
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **`web.php`** | `GET` uniquement |
| **`api.php`** | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| **Action par route** | Une route = une Action (pas de réutilisation) |
| **Web vs API** | Actions séparées |
| **Logique web** | Uniquement validations/vérifications via Worker |
| **Logique API** | Logique métier complète (Services) |
| **Form Request** | ⚠️ TOUTE route DOIT avoir une Form Request |
| **Appel à l'Action** | ⚠️ DOIT passer un Record via `$request->toRecord()` |
| **Paramètre URL** | ⚠️ Doit être utilisé et passé à `toRecord()` |
| **Paramètre requête** | ⚠️ Doit être utilisé, validé dans Form Request |
| **Nommage Action** | `{Verbe}{Ressource}Action` (ex: `ListUsersAction`) |

---

## 12. Règle d'or

> **Une route web GET ne fait que valider et rendre une vue Inertia. La logique métier est dans l'API. Toute route a une Form Request. Toute route appelle `toRecord()` et passe un Record à l'Action. L'Action ne connaît jamais la Request.**

```php
// ✅ BON - web.php
Route::get('/dashboard', function (Web\Dashboard\ShowDashboardRequest $request, Web\Dashboard\ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});

// ✅ BON - api.php (GET)
Route::get('/dashboard', function (Api\Dashboard\ShowDashboardRequest $request, Api\Dashboard\ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});

// ✅ BON - api.php (POST)
Route::post('/users', function (Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) {
    return $action->run($request->toRecord());
});

// ✅ BON - api.php (avec paramètre d'URL)
Route::get('/users/{userId}', function ($userId, Api\Users\ShowUserRequest $request, Api\Users\ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});

// ✅ BON - api.php (avec plusieurs paramètres d'URL)
Route::put('/users/{userId}/posts/{postId}', function ($userId, $postId, Api\Posts\UpdatePostRequest $request, Api\Posts\UpdatePostAction $action) {
    return $action->run($request->toRecord((int) $userId, (int) $postId));
});

// ❌ MAUVAIS - Ne passe PAS la Request à l'Action
Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request);  // ❌ DOIT être $request->toRecord()
});
```