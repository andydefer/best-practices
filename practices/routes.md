# Principe d'usage des Routes (Version finale)

## 1. Définition

Les **routes** sont la configuration qui fait le lien entre une URL et une Action. Elles sont définies dans les fichiers `web.php` et `api.php`.

```
URL → Route → Action → Response
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
Route::get('/users', fn(Web\Users\ListUsersRequest $request, Web\Users\ListUsersAction $action) => $action->run($request));

// ✅ BON - api.php (toutes les méthodes)
Route::get('/users', fn(Api\Users\ListUsersRequest $request, Api\Users\ListUsersAction $action) => $action->run($request));
Route::post('/users', fn(Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) => $action->run($request));
Route::put('/users/{userId}', fn($userId, Api\Users\ReplaceUserRequest $request, Api\Users\ReplaceUserAction $action) => $action->run($userId, $request));
Route::patch('/users/{userId}', fn($userId, Api\Users\UpdateUserRequest $request, Api\Users\UpdateUserAction $action) => $action->run($userId, $request));
Route::delete('/users/{userId}', fn($userId, Api\Users\DeleteUserRequest $request, Api\Users\DeleteUserAction $action) => $action->run($userId, $request));

// ❌ MAUVAIS - POST dans web.php
Route::post('/users', fn(Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) => $action->run($request));  // ❌ INTERDIT
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
Route::get('/users', fn(Web\Users\ListUsersRequest $request, Web\Users\ListUsersAction $action) => $action->run($request));
Route::get('/users/{userId}', fn($userId, Web\Users\ShowUserRequest $request, Web\Users\ShowUserAction $action) => $action->run($userId, $request));

// api.php
Route::get('/users', fn(Api\Users\ListUsersRequest $request, Api\Users\ListUsersAction $action) => $action->run($request));
Route::post('/users', fn(Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) => $action->run($request));
```

---

## 4. Règle : Pas de paramètre sans être utilisé

> **⚠️ Un paramètre d'URL (`{userId}`) ou un paramètre de requête (query string) ne peut pas exister sans être passé à l'Action. Supprimez-le si vous ne l'utilisez pas.**

```php
// ❌ MAUVAIS - Paramètre d'URL non utilisé
// URL: GET /users/{userId}
Route::get('/users/{userId}', fn(ListUsersAction $action) => $action->run());

// ✅ BON - Paramètre d'URL utilisé
Route::get('/users/{userId}', fn($userId, ShowUserRequest $request, ShowUserAction $action) => $action->run($userId, $request));

// ❌ MAUVAIS - Paramètre query non utilisé
// URL: GET /users?page=1
Route::get('/users', fn(ListUsersAction $action) => $action->run());

// ✅ BON - Paramètre query utilisé via Form Request
Route::get('/users', fn(ListUsersRequest $request, ListUsersAction $action) => $action->run($request));
```

---

## 5. Ordre des paramètres dans la méthode `run()`

> **Les paramètres d'URL sont passés dans l'ordre de la route. La Form Request est toujours le dernier paramètre.**

```php
// URL: PUT /users/{userId}/posts/{postId}
Route::put('/users/{userId}/posts/{postId}', 
    fn($userId, $postId, UpdatePostRequest $request, UpdatePostAction $action) => 
    $action->run($userId, $postId, $request)
);

// La signature de la méthode run() reflète cet ordre
final class UpdatePostAction extends AbstractAction
{
    public function run(int $userId, int $postId, UpdatePostRequest $request): JsonResponse
    {
        // ...
    }
}
```

---

## 6. Logique dans les routes web (⚠️ RÈGLE STRICTE)

> **⚠️ Une route web GET ne peut avoir que de la logique de validation ou de vérification via des Tasks qui retournent `bool` (et `abort()` si échec) ou lèvent une exception.**

### 6.1 Exemple : Validation avec une seule Task qui retourne `bool`

```php
// Task unique qui fait toutes les validations de même nature
final class ValidateUserAccessTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(ValidateUserAccessRecord $record): bool
    {
        // Vérification 1 : l'utilisateur existe
        if (!$this->userRepository->exists($record->userId)) {
            return false;
        }
        
        // Vérification 2 : l'utilisateur est actif
        if (!$this->userRepository->isActive($record->userId)) {
            return false;
        }
        
        // Vérification 3 : l'utilisateur a la permission
        if (!$this->userRepository->hasPermission($record->userId, $record->permission)) {
            return false;
        }
        
        return true;
    }
}

// Task pour logger (effet de bord)
final class LogUnauthorizedAccessTask extends AbstractTask
{
    public function execute(LogUnauthorizedAccessRecord $record): void
    {
        Log::warning('Unauthorized access attempt', [
            'user_id' => $record->userId,
            'attempted_url' => $record->url,
            'ip' => $record->ip,
            'required_permission' => $record->permission,
        ]);
    }
}

// Task pour envoyer un email d'alerte (effet de bord)
final class SendSecurityAlertEmailTask extends AbstractTask
{
    public function execute(SendSecurityAlertEmailRecord $record): void
    {
        Mail::to(config('security.admin_email'))->send(new SecurityAlertEmail(
            userId: $record->userId,
            url: $record->url,
            ip: $record->ip,
            permission: $record->permission,
        ));
    }
}

// Worker qui orchestre les 3 Tasks
final class HandleDashboardAccessWorker extends AbstractWorker
{
    public function __construct(
        private readonly ValidateUserAccessTask $validateAccess,
        private readonly LogUnauthorizedAccessTask $logAccess,
        private readonly SendSecurityAlertEmailTask $sendAlert,
    ) {}
    
    public function execute(HandleDashboardAccessRecord $record): void
    {
        $hasAccess = $this->validateAccess->execute(new ValidateUserAccessRecord(
            userId: $record->userId,
            permission: 'access_dashboard',
        ));
        
        if (!$hasAccess) {
            $this->logAccess->execute(new LogUnauthorizedAccessRecord(
                userId: $record->userId,
                url: $record->url,
                ip: $record->ip,
                permission: 'access_dashboard',
            ));
            
            $this->sendAlert->execute(new SendSecurityAlertEmailRecord(
                userId: $record->userId,
                url: $record->url,
                ip: $record->ip,
                permission: 'access_dashboard',
            ));
            
            abort(403, 'Unauthorized access to dashboard');
        }
    }
}

// Action Web avec Worker
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    public function run(ShowDashboardRequest $request): InertiaResponse
    {
        $record = new HandleDashboardAccessRecord(
            userId: $request->user()->id,
            url: $request->fullUrl(),
            ip: $request->ip(),
        );
        
        $this->handleAccess->execute($record);
        
        return $this->inertia('Dashboard/Index');
    }
}
```

---

## 7. Séparation Web vs API

> **Une Action web et une Action API sont deux classes différentes. La logique métier est dans l'API. La route web GET ne fait que valider et rendre la vue.**

```php
// web.php
Route::get('/dashboard', fn(Web\Dashboard\ShowDashboardRequest $request, Web\Dashboard\ShowDashboardAction $action) => $action->run($request));

// api.php
Route::get('/dashboard', fn(Api\Dashboard\ShowDashboardRequest $request, Api\Dashboard\ShowDashboardAction $action) => $action->run($request));
```

### 7.1 Action Web (validation uniquement)

```php
// App\Actions\Web\Dashboard\ShowDashboardAction
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly HandleDashboardAccessWorker $handleAccess,
    ) {}
    
    public function run(ShowDashboardRequest $request): InertiaResponse
    {
        $record = new HandleDashboardAccessRecord(
            userId: $request->user()->id,
            url: $request->fullUrl(),
            ip: $request->ip(),
        );
        
        $this->handleAccess->execute($record);
        
        return $this->inertia('Dashboard/Index');
    }
}
```

### 7.2 Action API (logique métier complète)

```php
// App\Actions\Api\Dashboard\ShowDashboardAction
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}
    
    public function run(ShowDashboardRequest $request): JsonResponse
    {
        $record = new ShowDashboardRecord(
            userId: $request->user()->id,
            period: $request->input('period', 'daily'),
        );
        
        $dashboardRecord = $this->dashboardService->getDashboard($record);
        $dashboardData = DashboardData::fromRecord($dashboardRecord);
        
        return $this->json($dashboardData);
    }
}
```

---

## 8. Paramètres d'URL vs Query Parameters

| Type | Emplacement | Convention | Utilisation |
|------|-------------|------------|-------------|
| **Paramètre d'URL** | `{userId}` | `camelCase` | Identifiant de ressource (ex: `userId`, `postId`) |
| **Paramètre de requête** | `?user_slug=&page=` | `snake_case` | Filtres, pagination, tri → dans la Form Request |

```php
// URL: GET /users?user_slug=john&page=2&per_page=15

// Form Request
final class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_slug' => 'nullable|string|exists:users,slug',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}

// Route
Route::get('/users', fn(ListUsersRequest $request, ListUsersAction $action) => $action->run($request));

// Dans l'Action
$record = new ListUsersRecord(
    userSlug: $request->input('user_slug'),
    page: $request->integer('page', 1),
    perPage: $request->integer('per_page', 15),
);
```

---

## 9. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **`web.php`** | `GET` uniquement |
| **`api.php`** | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` |
| **Action par route** | Une route = une Action (pas de réutilisation) |
| **Web vs API** | Actions séparées |
| **Logique web** | Uniquement validations/vérifications via Tasks (retournent `bool`) → Worker orchestre |
| **Logique API** | Logique métier complète (Services) → retourne Data |
| **Form Request** | ⚠️ TOUTE route DOIT avoir une Form Request |
| **Paramètre URL** | ⚠️ Doit être utilisé, ordre respecté, `camelCase` |
| **Paramètre requête** | ⚠️ Doit être utilisé, dans Form Request, `snake_case` |
| **Nommage Action** | `{Verbe}{Ressource}Action` (ex: `ListUsersAction`) |

---

## 10. Règle d'or

> **Une route web GET ne fait que valider et rendre une vue Inertia. La logique métier est dans l'API. Toute route a une Form Request. Tout paramètre doit être utilisé.**

```php
// web.php
Route::get('/dashboard', fn(Web\Dashboard\ShowDashboardRequest $request, Web\Dashboard\ShowDashboardAction $action) => $action->run($request));

// api.php
Route::get('/dashboard', fn(Api\Dashboard\ShowDashboardRequest $request, Api\Dashboard\ShowDashboardAction $action) => $action->run($request));
Route::post('/users', fn(Api\Users\CreateUserRequest $request, Api\Users\CreateUserAction $action) => $action->run($request));
```
