# Principe d'usage des Actions (Version finale)

## 1. Définition

Une **Action** est un composant qui encapsule la logique d'une **route unique**. Elle reçoit les paramètres d'URL et **un Record** (créé par la Form Request), orchestre les Tasks/Services/Workers, et retourne une réponse via le trait `SendsHttpResponses`.

**⚠️ Une Action a un type de retour unique. Elle ne peut pas retourner deux types différents (`JsonResponse|InertiaResponse`). Sauf en cas de redirection avec une (`RedirectResponse`)**

**⚠️ Une Action ne reçoit JAMAIS une Form Request. Elle reçoit TOUJOURS un Record.**

```
Route → Form Request → toRecord() → Record → Action → SendsHttpResponses → Response
```

```php
// Action API (retourne JsonResponse)
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    public function run(ShowUserRecord $record): JsonResponse
    {
        $userRecord = $this->userService->getUser($record);
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}

// Action Web (retourne InertiaResponse)
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

---

## 2. Les classes fondamentales : AbstractAction et SendsHttpResponses

### 2.1. AbstractAction

La classe abstraite que **toute Action doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Actions;

use AndyDefer\BestPractices\Records\Recordable;
use AndyDefer\BestPractices\Traits\Http\SendsHttpResponses;

/**
 * Abstract base class for all Action classes.
 *
 * An Action encapsulates the logic for a single HTTP route. Each Action receives
 * a Record (created by the Form Request), orchestrates services/workers, and returns
 * a consistent HTTP response using the SendsHttpResponses trait.
 *
 * **Important rules:**
 * - One Action = one HTTP route (never reuse the same Action for multiple routes)
 * - Must return a single, unique response type (no union types)
 * - Must receive a Record as parameter (NEVER receive a Form Request directly)
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Actions
 */
abstract class AbstractAction
{
    use SendsHttpResponses;

    /**
     * Executes the action logic for a specific HTTP route.
     *
     * This method must be implemented by each concrete Action class.
     * The method signature must include a Record that contains ALL the data
     * needed by the Action (URL parameters, query strings, user data, etc.).
     *
     * **Return type constraint:** The concrete Action must declare a single,
     * unique return type (e.g., JsonResponse|InertiaResponse|RedirectResponse).
     * Union types are forbidden in concrete implementations.
     *
     * @param Recordable $record The Record containing all request data
     * @return mixed The HTTP response (concrete type varies by Action)
     */
    abstract public function run(Recordable $record): mixed;
}
```

### 2.2. SendsHttpResponses (trait)

Le trait qui fournit toutes les méthodes de réponse HTTP aux Actions :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits\Http;

use AndyDefer\BestPractices\Data\DataInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait SendsHttpResponses
{
    public function json(DataInterface $data, int $code = 200): JsonResponse
    {
        return response()->json($data->toArray(), $code);
    }

    public function redirect(string $url, int $code = 302): RedirectResponse
    {
        return redirect($url, $code);
    }

    public function stream(callable $callback, string $contentType = 'application/octet-stream', int $code = 200): StreamedResponse
    {
        return response()->stream($callback, $code, [
            'Content-Type' => $contentType,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function sse(callable $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function noContent(): Response
    {
        return response('', 204);
    }

    public function inertia(string $component): InertiaResponse
    {
        return Inertia::render($component);
    }

    public function html(string $html, int $code = 200): Response
    {
        return response($html, $code, [
            'Content-Type' => 'text/html',
        ]);
    }

    public function fileInline(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);
        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    public function fileDownload(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);
        return response()->download($filePath, $fileName);
    }

    public function text(string $content, int $code = 200): Response
    {
        return response($content, $code, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
```

### 2.3. Ce qu'offre SendsHttpResponses

| Méthode | Description | Retour |
|---------|-------------|--------|
| `json()` | Réponse JSON pour API (convertit automatiquement les Data DTO) | `JsonResponse` |
| `redirect()` | Redirection HTTP (301, 302, etc.) | `RedirectResponse` |
| `stream()` | Streaming de données | `StreamedResponse` |
| `sse()` | Server-Sent Events | `StreamedResponse` |
| `noContent()` | Réponse vide 204 | `Response` |
| `inertia()` | Réponse Inertia.js pour SPA | `InertiaResponse` |
| `html()` | Réponse HTML brute | `Response` |
| `fileInline()` | Affichage d'un fichier dans le navigateur | `BinaryFileResponse` |
| `fileDownload()` | Téléchargement forcé d'un fichier | `BinaryFileResponse` |
| `text()` | Réponse texte brut | `Response` |

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **Une Action est dédiée à UNE SEULE route. On ne peut pas réutiliser la même Action pour deux routes différentes.**

```php
// ✅ BON - Action dédiée à une route
final class ShowUserAction extends AbstractAction
{
    // Utilisée uniquement pour GET /users/{userId}
}

// ❌ MAUVAIS - Action réutilisée pour plusieurs routes
final class UserAction extends AbstractAction
{
    public function list() { ... }   // GET /users
    public function show() { ... }   // GET /users/{userId}
    public function create() { ... } // POST /users
}
```

### 3.1. Pourquoi une Action par route ?

| Raison | Explication |
|--------|-------------|
| **SRP** | Chaque route a sa propre logique |
| **Évolution** | Modification d'une route sans impacter les autres |
| **Visibilité** | `ShowUserAction` dit clairement ce qu'il fait |

---

## 4. Règle : Une Action ne reçoit JAMAIS une Request (⚠️ RÈGLE ABSOLUE)

> **⚠️ Une Action ne peut jamais recevoir une Form Request en paramètre. Elle reçoit TOUJOURS un Record créé par la méthode `toRecord()` de la Request.**

```php
// ✅ BON - L'Action reçoit un Record
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRecord $record): JsonResponse
    {
        // $record contient TOUT ce dont l'Action a besoin
        // id, currentUserId, includeProfile, etc.
    }
}

// ❌ MAUVAIS - L'Action reçoit une Request (INTERDIT)
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRequest $request): JsonResponse  // ❌
    {
        // ...
    }
}
```

### 4.1. Pourquoi cette règle ?

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Réutilisabilité** | Le Record peut être créé par d'autres moyens (console, job, test) |

---

## 5. Type de retour unique (⚠️ RÈGLE STRICTE)

> **⚠️ Une Action a un type de retour unique. Elle ne peut pas retourner deux types différents.**

```php
// ✅ BON - Action API retourne JsonResponse
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRecord $record): JsonResponse
    {
        return $this->json($usersData);
    }
}

// ✅ BON - Action Web retourne InertiaResponse
final class ShowDashboardAction extends AbstractAction
{
    public function run(ShowDashboardRecord $record): InertiaResponse
    {
        return $this->inertia('Dashboard/Index');
    }
}

// ✅ BON - Action retourne RedirectResponse
final class CreateUserAction extends AbstractAction
{
    public function run(CreateUserRecord $record): RedirectResponse
    {
        return $this->redirect('/users');
    }
}

// ✅ BON - Action retourne Response (204 No Content)
final class DeleteUserAction extends AbstractAction
{
    public function run(DeleteUserRecord $record): Response
    {
        return $this->noContent();
    }
}

// ❌ MAUVAIS - Deux types de retour possibles
final class UserAction extends AbstractAction
{
    public function run(UserRecord $record): JsonResponse|InertiaResponse  // ❌
    {
        // ...
    }
}
```

### 5.1. Gestion des erreurs d'accès (abort)

> **Dans le cas où l'utilisateur n'a pas accès à une ressource, utilisez `abort()` pour interrompre l'exécution.**

```php
// ✅ BON - Utilisation de abort() pour une Action API
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ValidateUserAccessTask $validateAccess,
    ) {}
    
    public function run(ShowUserRecord $record): JsonResponse
    {
        $this->validateAccess->execute(new ValidateUserAccessRecord(
            userId: $record->id,
            permission: Permission::VIEW_USERS,
        ));
        
        $userRecord = $this->userService->getUser($record);
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}
```

### 5.2. Pourquoi utiliser `abort()` plutôt qu'un retour conditionnel ?

| Approche | Problème | Solution |
|----------|----------|----------|
| `return $this->redirect()` | Change le type de retour de l'Action | ❌ Interdit |
| `abort(403)` | Interrompt l'exécution, type de retour inchangé | ✅ Propre et net |

---

## 6. Définition des routes (web.php et api.php)

> **⚠️ Les routes sont définies dans `web.php` ou `api.php` et font le lien entre la Request et l'Action via `toRecord()`.**

### 6.1. Route API avec paramètre d'URL

```php
// routes/api.php
use App\Http\Requests\Api\Users\ShowUserRequest;
use App\Actions\Api\Users\ShowUserAction;

Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});
```

### 6.2. Route API sans paramètre d'URL

```php
// routes/api.php
use App\Http\Requests\Api\Users\ListUsersRequest;
use App\Actions\Api\Users\ListUsersAction;

Route::get('/users', function (ListUsersRequest $request, ListUsersAction $action) {
    return $action->run($request->toRecord());
});
```

### 6.3. Route Web (Inertia)

```php
// routes/web.php
use App\Http\Requests\Web\Dashboard\ShowDashboardRequest;
use App\Actions\Web\Dashboard\ShowDashboardAction;

Route::get('/dashboard', function (ShowDashboardRequest $request, ShowDashboardAction $action) {
    return $action->run($request->toRecord());
});
```

### 6.4. Route Web avec paramètre d'URL

```php
// routes/web.php
use App\Http\Requests\Web\Users\ShowUserRequest;
use App\Actions\Web\Users\ShowUserAction;

Route::get('/users/{userId}', function ($userId, ShowUserRequest $request, ShowUserAction $action) {
    return $action->run($request->toRecord((int) $userId));
});
```

### 6.5. Route POST (API)

```php
// routes/api.php
use App\Http\Requests\Api\Users\CreateUserRequest;
use App\Actions\Api\Users\CreateUserAction;

Route::post('/users', function (CreateUserRequest $request, CreateUserAction $action) {
    return $action->run($request->toRecord());
});
```

---

## 7. Convention de nommage

> **Le nom de l'Action reflète l'action HTTP et la ressource. Le nom est au singulier.**

| Méthode | URL | Action |
|---------|-----|--------|
| GET | `/users` | `ListUsersAction` |
| GET | `/users/{userId}` | `ShowUserAction` |
| POST | `/users` | `CreateUserAction` |
| PUT | `/users/{userId}` | `ReplaceUserAction` |
| PATCH | `/users/{userId}` | `UpdateUserAction` |
| DELETE | `/users/{userId}` | `DeleteUserAction` |
| GET | `/doctors/availability` | `ShowDoctorsAvailabilityAction` |

```php
// ✅ BON - Nom reflète l'action
final class ListUsersAction extends AbstractAction { ... }
final class ShowUserAction extends AbstractAction { ... }
final class CreateUserAction extends AbstractAction { ... }

// ❌ MAUVAIS - Nom ne reflète pas l'action
final class UserAction extends AbstractAction { ... }
```

---

## 8. Création des Records

> **⚠️ Rappel : un Record ne peut jamais être initialisé avec un tableau. Toutes les propriétés doivent être passées explicitement par nom.**

```php
// ✅ BON - Initialisation explicite
$record = new ListUsersRecord(
    search: $request->input('search'),
    page: $request->integer('page', 1),
);

// ❌ MAUVAIS - Initialisation avec tableau
$record = new ListUsersRecord($request->validated());
```

---

## 9. Une Action d'API ne retourne qu'une Data

> **⚠️ Une Action DOIT retourner une Data DTO quand elle utilise `$this->json()`. Elle ne peut jamais retourner un tableau brut ou une collection.**

```php
// ✅ BON - Retourne une Data
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRecord $record): JsonResponse
    {
        $usersRecord = $this->userService->getUsers($record);
        $usersData = UserData::collect($usersRecord);
        
        return $this->json($usersData);
    }
}

// ❌ MAUVAIS - Retourne un tableau brut
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRecord $record): JsonResponse
    {
        $users = $this->userService->getUsers($record);
        
        return response()->json([
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ]),
        ]);
    }
}
```

---

## 10. Logique dans les Actions

> **⚠️ Une Action ne doit pas contenir de logique métier complexe. Elle orchestre des Services, Tasks ou Workers.**

```php
// ✅ BON - Action qui orchestre
final class CreateUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly CreateUserWorker $createUserWorker,
    ) {}
    
    public function run(CreateUserRecord $record): JsonResponse
    {
        // Logique métier déléguée au Service
        $this->userService->validate($record);
        
        // Orchestration déléguée au Worker
        $user = $this->createUserWorker->execute($record);
        
        $userData = UserData::fromRecord($user);
        
        return $this->json($userData, 201);
    }
}
```

---

## 11. Règle : Pas de tests unitaires pour les Actions (⚠️ RÈGLE IMPORTANTE)

> **⚠️ On n'écrit JAMAIS de tests unitaires pour les Actions. Les Actions sont testées exclusivement via des tests d'intégration (Feature tests) car elles retournent des réponses HTTP complètes.**

### 11.1. Pourquoi pas de tests unitaires pour les Actions ?

| Raison | Explication |
|--------|-------------|
| **Retour HTTP** | Les Actions retournent des réponses HTTP complètes |
| **Dépendance à Laravel** | Les Actions dépendent du framework pour les réponses |
| **Test d'intégration suffisant** | Les requêtes HTTP réelles testent le comportement complet |

### 11.2. Test d'intégration (Feature test) d'une Action

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
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $response = $this->actingAs($user)
            ->getJson("/api/users/{$user->id}");
        
        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'email']);
    }
    
    public function test_show_user_returns_404_when_user_not_found(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);
        
        $response = $this->actingAs($user)
            ->getJson('/api/users/99999');
        
        $response->assertStatus(404);
    }
}
```

### 11.3. Récapitulatif des types de tests

| Composant | Tests unitaires | Tests d'intégration (Feature) |
|-----------|----------------|------------------------------|
| **Action** | ❌ Jamais | ✅ Toujours |
| **Service** | ✅ Oui | ❌ Rarement |
| **Task** | ✅ Oui | ❌ Rarement |
| **Worker** | ✅ Oui | ❌ Rarement |
| **Repository** | ❌ Jamais | ✅ Oui |
| **Form Request** | ❌ Jamais | ✅ Via l'Action |

---

## 12. Organisation des dossiers

```
app/
├── Actions/
│   ├── Web/
│   │   ├── Dashboard/
│   │   │   └── ShowDashboardAction.php
│   │   └── Users/
│   │       ├── ListUsersAction.php
│   │       └── ShowUserAction.php
│   └── Api/
│       ├── Dashboard/
│       │   └── ShowDashboardAction.php
│       └── Users/
│           ├── ListUsersAction.php
│           ├── ShowUserAction.php
│           ├── CreateUserAction.php
│           ├── ReplaceUserAction.php
│           ├── UpdateUserAction.php
│           └── DeleteUserAction.php
├── Http/
│   ├── Requests/
│   │   ├── Web/
│   │   │   ├── Dashboard/
│   │   │   │   └── ShowDashboardRequest.php
│   │   │   └── Users/
│   │   │       ├── ListUsersRequest.php
│   │   │       └── ShowUserRequest.php
│   │   └── Api/
│   │       ├── Dashboard/
│   │       │   └── ShowDashboardRequest.php
│   │       └── Users/
│   │           ├── ListUsersRequest.php
│   │           ├── ShowUserRequest.php
│   │           ├── CreateUserRequest.php
│   │           ├── ReplaceUserRequest.php
│   │           ├── UpdateUserRequest.php
│   │           └── DeleteUserRequest.php
│   └── Traits/
│       └── SendsHttpResponses.php
├── Records/
│   ├── ListUsersRecord.php
│   ├── ShowUserRecord.php
│   ├── CreateUserRecord.php
│   ├── UpdateUserRecord.php
│   └── DeleteUserRecord.php
├── Services/
│   └── UserService.php
├── Tasks/
│   ├── ValidateUserAccessTask.php
│   └── LogUnauthorizedAccessTask.php
├── Workers/
│   └── HandleDashboardAccessWorker.php
└── ...
```

---

## 13. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractAction` |
| **Trait** | Utilise `SendsHttpResponses` (via AbstractAction) |
| **Nommage** | `{Verbe}{Ressource}Action` |
| **Méthode** | Une seule : `run(Record $record)` |
| **Paramètre** | ⚠️ UNIQUEMENT un Record (jamais une Request) |
| **Type de retour** | ⚠️ UNIQUE (pas de union type) |
| **Route unique** | Une Action = une route |
| **Retour** | Via les méthodes du trait `SendsHttpResponses` |
| **Erreurs d'accès** | Utiliser `abort()` dans une Task |
| **Logique métier** | Déléguée aux Services, Tasks, Workers |
| **Tests unitaires** | ❌ Jamais (uniquement tests d'intégration) |

---

## 14. Règle d'or

> **Une Action fait une chose : répondre à une route avec un type de réponse unique. Elle reçoit un Record (jamais une Request), orchestre, et retourne une réponse via SendsHttpResponses. Pas de tests unitaires, uniquement des tests d'intégration.**

```php
// L'Action parfaite
final class PerfectApiAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ValidateAccessTask $validateAccess,
    ) {}
    
    public function run(PerfectRecord $record): JsonResponse
    {
        $this->validateAccess->execute(new ValidateAccessRecord(
            userId: $record->userId,
            permission: 'view',
        ));
        
        $data = $this->userService->getUser($record);
        
        return $this->json($data);
    }
}

// Route associée
Route::get('/resources/{id}', function ($id, PerfectRequest $request, PerfectApiAction $action) {
    return $action->run($request->toRecord((int) $id));
});

// Test d'intégration
final class PerfectApiActionTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_action_returns_data_when_authorized(): void
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->getJson("/resources/{$user->id}");
        
        $response->assertStatus(200);
    }
}
```