# actions.md

# Principe d'usage des Actions (Version finale)

## 1. Définition

Une **Action** est un composant qui encapsule la logique d'une **route unique**. Elle reçoit les paramètres d'URL et une Form Request, peut créer un Record, orchestre les Tasks/Services/Workers, et retourne une réponse via le trait `SendsHttpResponses`.

**⚠️ Une Action a un type de retour unique. Elle ne peut pas retourner deux types différents (`JsonResponse|InertiaResponse`).**

```
Route → Action → SendsHttpResponses → Response
```

```php
// Action API (retourne JsonResponse)
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserDataFactory $userDataFactory,
    ) {}
    
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $record = new ShowUserRecord(
            id: $userId,
            includeProfile: $request->boolean('include_profile'),
        );
        
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

## 2. Les classes fondamentales : AbstractAction et SendsHttpResponses

### 2.1. AbstractAction

La classe abstraite que **toute Action doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Actions;

use AndyDefer\BestPractices\Traits\Http\SendsHttpResponses;

/**
 * Abstract base class for all Action classes.
 *
 * An Action encapsulates the logic for a single HTTP route. Each Action receives
 * URL parameters and a Form Request, orchestrates services/workers, and returns
 * a consistent HTTP response using the SendsHttpResponses trait.
 *
 * **Important rules:**
 * - One Action = one HTTP route (never reuse the same Action for multiple routes)
 * - Must return a single, unique response type (no union types)
 * - Must receive a Form Request as the last parameter (except for routes without parameters)
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
     * The method signature should include:
     * - URL parameters in the order they appear in the route
     * - A Form Request as the last parameter (when validation is needed)
     *
     * **Return type constraint:** The concrete Action must declare a single,
     * unique return type (e.g., JsonResponse|InertiaResponse|RedirectResponse).
     * Union types are forbidden in concrete implementations.
     *
     * @param mixed ...$parameters URL parameters and Form Request (in that order)
     * @return mixed The HTTP response (concrete type varies by Action)
     */
    abstract public function run(...$parameters): mixed;
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

/**
 * Provides HTTP response helper methods for controllers and actions.
 *
 * This trait offers a consistent interface for generating various HTTP responses
 * including JSON API responses, file downloads, streaming responses, and Inertia views.
 * All methods are designed to be used directly within controller classes or actions.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Http\Traits
 */
trait SendsHttpResponses
{
    /**
     * Creates a JSON response for API endpoints.
     *
     * Automatically converts DataInterface objects to arrays using their toArray() method.
     *
     * @param DataInterface $data The data to return
     * @param int $code HTTP status code (200, 201, 202, etc.)
     * @return JsonResponse JSON formatted HTTP response
     */
    public function json(DataInterface $data, int $code = 200): JsonResponse
    {
        return response()->json($data->toArray(), $code);
    }

    /**
     * Creates a redirect response to another URL.
     *
     * @param string $url Destination URL
     * @param int $code HTTP redirect status code (301, 302, 303, 307, 308)
     * @return RedirectResponse HTTP redirect response
     */
    public function redirect(string $url, int $code = 302): RedirectResponse
    {
        return redirect($url, $code);
    }

    /**
     * Creates a streaming response for real-time data transmission.
     *
     * Useful for streaming large files, real-time CSV generation, or video streaming.
     *
     * @param callable $callback Function that writes output directly to the response stream
     * @param string $contentType MIME type of the streamed content
     * @param int $code HTTP status code
     * @return StreamedResponse Streamed HTTP response
     */
    public function stream(callable $callback, string $contentType = 'application/octet-stream', int $code = 200): StreamedResponse
    {
        return response()->stream($callback, $code, [
            'Content-Type' => $contentType,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Creates a Server-Sent Events (SSE) streaming response.
     *
     * SSE allows servers to push real-time events to clients over a single HTTP connection.
     * Useful for live notifications, real-time dashboards, or progress updates.
     *
     * @param callable $callback Function that emits SSE events using the SSE format
     * @return StreamedResponse SSE streaming response with proper headers
     */
    public function sse(callable $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Creates a 204 No Content response.
     *
     * Used when the request was successful but there's no content to return.
     *
     * @return Response Empty HTTP response with 204 status code
     */
    public function noContent(): Response
    {
        return response('', 204);
    }

    /**
     * Creates an Inertia.js response for modern single-page applications.
     *
     * Renders a React/Vue component with server-side data when using Inertia.js.
     *
     * @param string $component Name of the React/Vue component to render
     * @return InertiaResponse Inertia response that renders the specified component
     */
    public function inertia(string $component): InertiaResponse
    {
        return Inertia::render($component);
    }

    /**
     * Creates a raw HTML response.
     *
     * Use this only for rare cases where Inertia.js is not suitable,
     * such as email previews, legacy views, or external integrations.
     *
     * @param string $html Raw HTML content to return
     * @param int $code HTTP status code
     * @return Response HTML response with proper content type header
     */
    public function html(string $html, int $code = 200): Response
    {
        return response($html, $code, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Returns a file to be displayed inline in the browser.
     *
     * The browser will attempt to display the file (PDF, image, video) directly
     * rather than downloading it.
     *
     * @param string $filePath Absolute or relative path to the file
     * @param string|null $fileName Optional custom filename for inline display
     * @return BinaryFileResponse File response with inline disposition
     */
    public function fileInline(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);

        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Forces a file to be downloaded by the browser.
     *
     * The browser will save the file to disk rather than displaying it.
     *
     * @param string $filePath Absolute or relative path to the file
     * @param string|null $fileName Optional custom filename for the downloaded file
     * @return BinaryFileResponse File response with attachment disposition
     */
    public function fileDownload(string $filePath, ?string $fileName = null): BinaryFileResponse
    {
        $fileName = $fileName ?? basename($filePath);

        return response()->download($filePath, $fileName);
    }

    /**
     * Creates a plain text response.
     *
     * Useful for API endpoints that return raw text, logs, or configuration files.
     *
     * @param string $content Text content to return
     * @param int $code HTTP status code
     * @return Response Plain text response with proper content type
     */
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
| `stream()` | Streaming de données (fichiers, CSV temps réel) | `StreamedResponse` |
| `sse()` | Server-Sent Events pour notifications temps réel | `StreamedResponse` |
| `noContent()` | Réponse vide 204 | `Response` |
| `inertia()` | Réponse Inertia.js pour SPA | `InertiaResponse` |
| `html()` | Réponse HTML brute (cas rares) | `Response` |
| `fileInline()` | Affichage d'un fichier dans le navigateur | `BinaryFileResponse` |
| `fileDownload()` | Téléchargement forcé d'un fichier | `BinaryFileResponse` |
| `text()` | Réponse texte brut | `Response` |

---

## 3. Règle fondamentale

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
| **Testabilité** | Chaque Action se teste indépendamment |
| **Visibilité** | `ShowUserAction` dit clairement ce qu'il fait |

### 3.2. Une Action d'api ne retourne qu'une Data, jamais un tableau brut

> **⚠️ Une Action DOIT retourner une Data DTO quand elle utilise  `$this->json()`. Elle ne peut jamais retourner un tableau brut ou une collection.**

```php
// ✅ BON - Retourne une Data
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        $usersRecord = $this->userService->getUsers($request->validated());
        $usersData = UserData::collect($usersRecord);
        
        return $this->json($usersData);
    }
}

// ✅ BON - Retourne une Data paginée
final class PaginateUsersAction extends AbstractAction
{
    public function run(PaginateUsersRequest $request): JsonResponse
    {
        $paginatedRecord = $this->userService->paginateUsers($request->validated());
        
        $paginatedData = PaginatedData::fromRecord(new PaginatedRecord(
            items: UserData::collect($paginatedRecord->items),
            currentPage: $paginatedRecord->currentPage,
            perPage: $paginatedRecord->perPage,
            total: $paginatedRecord->total,
            lastPage: $paginatedRecord->lastPage,
        ));
        
        return $this->json($paginatedData);
    }
}

// ❌ MAUVAIS - Retourne un tableau brut
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        $users = $this->userService->getUsers($request->validated());
        
        // ❌ Tableau brut, pas de structure garantie
        return response()->json([
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ]),
        ]);
    }
}

// ❌ MAUVAIS - Retourne une collection brute
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        $users = $this->userService->getUsers($request->validated());
        
        // ❌ Collection brute, pas de Data
        return $this->json($users);
    }
}
```

### 3.3. Pourquoi une Action ne peut retourner qu'une Data ?

| Raison | Explication |
|--------|-------------|
| **Contrat explicite** | Les clients de l'API savent exactement la structure de la réponse |
| **Sérialisation automatique** | `toArray()` gère les Enums, les dates, les nested Data |
| **Cohérence** | Toutes les réponses ont la même structure garantie |
| **Testabilité** | On teste que la Data est correcte, pas la structure du tableau |
| **Évolution** | On modifie la Data, tous les appels sont mis à jour automatiquement |

```php
// La structure est garantie par la Data
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly string $createdAt,
    ) {}
}

// Le client peut compter sur cette structure
{
    "id": "123",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "admin",
    "createdAt": "2024-01-15T10:30:00Z"
}
```
---

## 4. Convention de nommage

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

## 5. Type de retour unique (⚠️ RÈGLE STRICTE)

> **⚠️ Une Action a un type de retour unique. Elle ne peut pas retourner deux types différents.**

```php
// ✅ BON - Action API retourne JsonResponse
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        return $this->json($usersData);
    }
}

// ✅ BON - Action Web retourne InertiaResponse
final class ShowDashboardAction extends AbstractAction
{
    public function run(ShowDashboardRequest $request): InertiaResponse
    {
        return $this->inertia('Dashboard/Index');
    }
}

// ✅ BON - Action retourne RedirectResponse
final class CreateUserAction extends AbstractAction
{
    public function run(CreateUserRequest $request): RedirectResponse
    {
        return $this->redirect('/users');
    }
}

// ✅ BON - Action retourne Response (204 No Content)
final class DeleteUserAction extends AbstractAction
{
    public function run(int $userId, DeleteUserRequest $request): Response
    {
        return $this->noContent();
    }
}

// ❌ MAUVAIS - Deux types de retour possibles
final class UserAction extends AbstractAction
{
    public function run(Request $request): JsonResponse|InertiaResponse
    {
        if ($request->wantsJson()) {
            return $this->json($data);
        }
        return $this->inertia('View');
    }
}
```
### 5.1. Gestion des erreurs d'accès (abort) et redirection

> **Dans le cas où l'utilisateur n'a pas accès à une ressource, utilisez `abort()` pour interrompre l'exécution. Cela évite d'avoir à retourner différents types de réponses (`JsonResponse` ou `RedirectResponse`) depuis la même Action. Une seule exception : si vous devez rediriger l'utilisateur (par exemple vers une page de connexion), vous pouvez retourner un `RedirectResponse` depuis l'Action.**

```php
// ✅ BON - Utilisation de abort() pour une Action API
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ValidateUserAccessTask $validateAccess,
    ) {}
    
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        // La Task vérifie les droits et appelle abort() si nécessaire
        $this->validateAccess->execute(new ValidateUserAccessRecord(
            userId: $userId,
            permission: Permission::VIEW_USERS,
        ));
        
        $userRecord = $this->userService->getUser($userId);
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}

// Task dédiée à la validation des droits d'accès
final class ValidateUserAccessTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(ValidateUserAccessRecord $record): bool
    {
        $user = $this->userRepository->find($record->userId);
        
        if ($user === null) {
            abort(404, 'User not found');
        }
        
        // Vérification via l'Enum Permission
        $allowedRoles = Permission::getRolesForPermission($record->permission);
        if (!in_array($user->role->value, $allowedRoles)) {
            abort(403, 'Insufficient permissions to access this resource');
        }
        
        return true;
    }
}
```

```php
// ✅ EXCEPTION AUTORISÉE - Redirection pour une Action Web
final class ShowDashboardAction extends AbstractAction
{
    public function __construct(
        private readonly CheckDashboardAccessTask $checkAccess,
        private readonly DashboardService $dashboardService,
    ) {}
    
    public function run(ShowDashboardRequest $request): JsonResponse|RedirectResponse
    {
        // Cas 1 : Utilisateur non authentifié → redirection
        if (!auth()->check()) {
            return $this->redirect('/login');
        }
        
        // Cas 2 : Vérification des droits dans une Task (abort si échec)
        $this->checkAccess->execute(new CheckDashboardAccessRecord(
            userId: auth()->id(),
        ));
        
        // Cas 3 : Succès → retour JSON
        $record = new ShowDashboardRecord(
            userId: auth()->id(),
            period: $request->input('period', 'daily'),
        );
        
        $dashboardRecord = $this->dashboardService->getDashboard($record);
        $dashboardData = DashboardData::fromRecord($dashboardRecord);
        
        return $this->json($dashboardData);
    }
}

// Task dédiée à la vérification des droits d'accès au dashboard
final class CheckDashboardAccessTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(CheckDashboardAccessRecord $record): bool
    {
        $user = $this->userRepository->find($record->userId);
        
        // Récupération des rôles autorisés depuis l'Enum Permission
        $allowedRoles = Permission::getRolesForPermission(Permission::ACCESS_DASHBOARD);
        if (!in_array($user->role->value, $allowedRoles)) {
            abort(403, 'Unauthorized access to dashboard');
        }
        
        return true;
    }
}
```

### L'Enum Permission

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum Permission: string
{
    use Enumerable;
    
    case VIEW_USERS = 'view_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';
    case ACCESS_DASHBOARD = 'access_dashboard';
    case MANAGE_SETTINGS = 'manage_settings';
    
    /**
     * Get roles that have a specific permission.
     *
     * @return array<int, string>
     */
    public static function getRolesForPermission(self $permission): array
    {
        return match ($permission) {
            self::VIEW_USERS => [
                UserRole::ADMIN->value,
                UserRole::MANAGER->value,
                UserRole::USER->value,
            ],
            self::EDIT_USERS => [
                UserRole::ADMIN->value,
                UserRole::MANAGER->value,
            ],
            self::DELETE_USERS => [
                UserRole::ADMIN->value,
            ],
            self::ACCESS_DASHBOARD => [
                UserRole::ADMIN->value,
                UserRole::MANAGER->value,
            ],
            self::MANAGE_SETTINGS => [
                UserRole::ADMIN->value,
            ],
        };
    }
}
```

### Résumé des règles

| Situation | Action à prendre | Type de retour |
|-----------|------------------|----------------|
| Ressource non trouvée | `abort(404)` | Uniquement `JsonResponse` |
| Droits insuffisants (API) | `abort(403)` | Uniquement `JsonResponse` |
| Non authentifié (Web) | `return $this->redirect('/login')` | `JsonResponse\|RedirectResponse` |
| Succès (API) | `return $this->json($data)` | Uniquement `JsonResponse` |
| Succès (Web) | `return $this->redirect('/url')` | Uniquement `RedirectResponse` |

### 5.2. Pourquoi utiliser `abort()` plutôt qu'un retour conditionnel ?

| Approche | Problème | Solution |
|----------|----------|----------|
| `return $this->redirect()` | Change le type de retour de l'Action | ❌ Interdit |
| `return $this->json(null, 403)` | Retourne toujours `JsonResponse` mais logique métier dans l'Action | ❌ Violation SRP |
| `abort(403)` | Interrompt l'exécution, type de retour inchangé | ✅ Propre et net |

**Exemple de ce qu'il ne faut PAS faire :**

```php
// ❌ MAUVAIS - Type de retour ambigu
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse|RedirectResponse
    {
        if (!$this->userService->hasAccess($userId)) {
            return $this->redirect('/login');  // Type différent !
        }
        
        $userData = $this->userService->getUser($userId);
        return $this->json($userData);
    }
}
```

**À la place, utilisez `abort()` dans une Task dédiée :**

```php
// ✅ BON - Type de retour unique
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $this->validateUserAccessTask->execute(new ValidateUserAccessRecord(
            userId: $userId,
            permission: 'view_users',
        ));
        
        $userData = $this->userService->getUser($userId);
        return $this->json($userData);
    }
}
```

### 5.3. Codes HTTP standards avec `abort()`

| Situation | Code | Utilisation |
|-----------|------|-------------|
| Non authentifié | `abort(401)` | Utilisateur non connecté |
| Non autorisé | `abort(403)` | Connecté mais droits insuffisants |
| Ressource non trouvée | `abort(404)` | Donnée inexistante |
| Conflit | `abort(409)` | État inattendu |
| Erreur serveur | `abort(500)` | Exception non gérée |

### 5.4. Résumé des règles pour les erreurs

| Règle | Explication |
|-------|-------------|
| **Une Action = un type de retour** | Jamais de `JsonResponse\|RedirectResponse` |
| **Les erreurs d'accès = `abort()`** | Interrompt l'exécution, pas de retour conditionnel |
| **Vérification = déléguée à une Task** | La Task contient la logique de validation |
| **L'Action ne gère pas les erreurs HTTP** | Elle retourne uniquement le succès |

---

## 6. Signature de la méthode `run()`

> **La méthode `run()` reçoit les paramètres d'URL (dans l'ordre de la route) et une Form Request en dernier paramètre.**

```php
// Action GET sans paramètre d'URL
public function run(ListUsersRequest $request): JsonResponse

// Action GET avec paramètre d'URL
public function run(int $userId, ShowUserRequest $request): JsonResponse

// Action POST (sans paramètre d'URL)
public function run(CreateUserRequest $request): JsonResponse

// Action PUT avec paramètre d'URL
public function run(int $userId, ReplaceUserRequest $request): JsonResponse

// Action DELETE avec paramètre d'URL
public function run(int $userId, DeleteUserRequest $request): Response
```

---

## 7. Form Request dans les Actions (⚠️ RÈGLE IMPORTANTE)

> **Toute Action qui accède à la requête HTTP (query strings, corps de la requête) DOIT recevoir une Form Request. Si l'Action n'a pas besoin d'accéder à la requête, la Form Request n'est pas nécessaire, c'est à votre disposition.**

### 7.1. Règle

| Cas | Form Request nécessaire ? | Exemple |
|-----|--------------------------|---------|
| L'Action lit des paramètres d'URL (`userId`, `postId`) | ❓ Optionnel | `GET /users/{userId}` |
| L'Action lit des query strings (`?page=1&per_page=15`) | ✅ Oui | `GET /users?page=1` |
| L'Action lit le corps de la requête (POST, PUT, PATCH) | ✅ Oui | `POST /users` |
| L'Action n'a besoin d'aucune donnée de la requête | ❌ Non | `GET /ping`, `GET /health` |

### 7.2. Exemples

```php
// ✅ BON - Action avec paramètre d'URL → Form Request nécessaire
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        // $userId vient de l'URL, $request contient les query strings
        $record = new ShowUserRecord(
            id: $userId,
            includeProfile: $request->boolean('include_profile'),
        );
        // ...
    }
}

// ✅ BON - Action avec lecture de query strings → Form Request nécessaire
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        $record = new ListUsersRecord(
            search: $request->input('search'),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        );
        // ...
    }
}

// ❌ MAUVAIS - Action sans Form Request alors qu'elle lit la requête
final class ListUsersAction extends AbstractAction
{
    public function run(Request $request): JsonResponse  // ❌ FormRequest spécifique
    {
        // ...
    }
}

// ✅ BON - Action sans besoin de la requête → Pas de Form Request
final class PingAction extends AbstractAction
{
    public function run(): JsonResponse
    {
        return $this->json(new PingData(status: 'ok'));
    }
}

// ✅ BON - Action sans besoin de la requête → Pas de Form Request
final class HealthCheckAction extends AbstractAction
{
    public function run(): JsonResponse
    {
        $data = new HealthCheckData(status: 'healthy');
        return $this->json($data);
    }
}

final class HealthCheckData extends AbstractData
{
    public function __construct(
        public readonly string $status,
        public readonly string $timestamp = '2024-01-15T10:00:00Z',
    ) {}
}
```

### 7.3. Avantage : Contrat explicite pour les consommateurs de l'API

> **Les Form Request fournissent un contrat explicite entre le serveur et les clients, quel que soit le langage du client.**

#### Client TypeScript

```typescript
// Structure miroir de la Form Request
interface ShowUserRequest {
    include_profile?: boolean;  // query string
}

interface CreateUserRequest {
    name: string;
    email: string;
    password: string;
    role?: 'admin' | 'user' | 'doctor';
}

// Appel à l'API
const response = await fetch('/users/123?include_profile=true');
const user = await response.json();
```

#### Client Kotlin (Android)

```kotlin
// Structure miroir de la Form Request
@Serializable
data class CreateUserRequest(
    val name: String,
    val email: String,
    val password: String,
    val role: UserRole? = null,
)

@Serializable
enum class UserRole {
    @SerialName("admin") ADMIN,
    @SerialName("user") USER,
    @SerialName("doctor") DOCTOR
}

// Appel à l'API avec Retrofit
interface ApiService {
    @POST("users")
    suspend fun createUser(@Body request: CreateUserRequest): UserData
}
```

#### Client Swift (iOS)

```swift
// Structure miroir de la Form Request
struct CreateUserRequest: Codable {
    let name: String
    let email: String
    let password: String
    let role: UserRole?
}

enum UserRole: String, Codable {
    case admin = "admin"
    case user = "user"
    case doctor = "doctor"
}

// Appel à l'API
let request = CreateUserRequest(
    name: "John Doe",
    email: "john@example.com",
    password: "secure123",
    role: .user
)

let response = try await URLSession.shared.upload(for: urlRequest, from: requestData)
```

#### Client Rust

```rust
// Structure miroir de la Form Request
#[derive(Serialize, Deserialize)]
struct CreateUserRequest {
    name: String,
    email: String,
    password: String,
    role: Option<UserRole>,
}

#[derive(Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
enum UserRole {
    Admin,
    User,
    Doctor,
}

// Appel à l'API
let client = reqwest::Client::new();
let response = client
    .post("https://api.example.com/users")
    .json(&CreateUserRequest {
        name: "John Doe".to_string(),
        email: "john@example.com".to_string(),
        password: "secure123".to_string(),
        role: Some(UserRole::User),
    })
    .send()
    .await?;
```

### 7.4. Bénéfices des Form Request

| Bénéfice | Explication |
|----------|-------------|
| **Contrat explicite** | Les clients savent exactement quels paramètres envoyer |
| **Validation centralisée** | Les règles de validation sont définies dans un seul endroit |
| **Documentation vivante** | La Form Request documente elle-même l'API |
| **Génération de code** | Permet de générer automatiquement les structures client (TypeScript, Kotlin, Swift) |
| **Type-safety** | Les paramètres sont typés (string, int, boolean, enum) |

### 7.5. Récapitulatif

| Situation | Form Request | Exemple |
|-----------|--------------|---------|
| Accès à la requête (URL, query, body) | ✅ OBLIGATOIRE | `ShowUserAction`, `ListUsersAction`, `CreateUserAction` |
| Pas d'accès à la requête | ❌ Optionnel | `PingAction`, `HealthCheckAction` |

```php
// ✅ BON - Action avec accès requête → Form Request
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse { ... }
}

// ✅ BON - Action sans accès requête → Pas de Form Request
final class PingAction extends AbstractAction
{
    public function run(): JsonResponse { ... }
}
```
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

### 8.1. Définition des Records associés

```php
// Record pour ListUsersAction
final class ListUsersRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $search,
        public readonly ?string $role,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}

// Record pour ShowUserAction
final class ShowUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly bool $includeProfile,
    ) {}
}

// Record pour ValidateUserAccessTask
final class ValidateUserAccessRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $userId,
        public readonly string $permission,
    ) {}
}
```

---

## 9. Logique dans les Actions

> **⚠️ Une Action web (Inertia) ne doit pas contenir de logique métier complexe. Elle peut seulement faire des validations/vérifications via des Workers qui orchestrent des Tasks (retournent `bool`).**

### 9.1. Exemple complet : Action Web avec validation via Worker

```php
// Task unique de validation (retourne bool)
final class ValidateUserAccessTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(ValidateUserAccessRecord $record): bool
    {
        if (!$this->userRepository->exists($record->userId)) {
            return false;
        }
        
        if (!$this->userRepository->isActive($record->userId)) {
            return false;
        }
        
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

// Worker qui orchestre les Tasks
final class HandleDashboardAccessWorker extends AbstractWorker
{
    public function __construct(
        private readonly ValidateUserAccessTask $validateAccess,
        private readonly LogUnauthorizedAccessTask $logAccess,
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
            
            abort(403, 'Unauthorized access to dashboard');
        }
    }
}

// Action Web
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

### 9.2. Action API (logique métier complète)

```php
final class ListUsersAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    public function run(ListUsersRequest $request): JsonResponse
    {
        $record = new ListUsersRecord(
            search: $request->input('search'),
            role: $request->input('role'),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        );
        
        $usersRecord = $this->userService->getUsers($record);
        $usersData = UserData::collect($usersRecord);
        
        return $this->json($usersData);
    }
}
```

---

## 10. Organisation des dossiers

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

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractAction` |
| **Trait** | Utilise `SendsHttpResponses` (via AbstractAction) |
| **Nommage** | `{Verbe}{Ressource}Action` |
| **Méthode** | Une seule : `run()` |
| **Type de retour** | ⚠️ UNIQUE (pas de union type) |
| **Route unique** | Une Action = une route |
| **Form Request** | ⚠️ TOUTE Action DOIT recevoir une Form Request |
| **Retour** | Via les méthodes du trait `SendsHttpResponses` |
| **Records** | Création explicite (pas avec tableau) |
| **Erreurs d'accès** | Utiliser `abort()` dans une Task |
| **Logique web** | Uniquement via Worker qui orchestre des Tasks |

---

## 12. Règle d'or

> **Une Action fait une chose : répondre à une route avec un type de réponse unique. Elle reçoit une Form Request, crée un Record explicitement, orchestre, et retourne une réponse via SendsHttpResponses.**

```php
// Le pattern parfait avec Task et Worker
final class PerfectApiAction extends AbstractAction
{
    public function __construct(
        private readonly ValidateAccessTask $validateAccess,
        private readonly FindResourceTask $findResource,
    ) {}
    
    public function run(int $id, PerfectRequest $request): JsonResponse
    {
        // Étape 1 : Vérification des droits (abort si échec)
        $this->validateAccess->execute(new ValidateAccessRecord(
            userId: $request->user()->id,
            resourceId: $id,
            permission: 'view',
        ));
        
        // Étape 2 : Récupération des données (abort si non trouvé)
        $record = $this->findResource->execute($id);
        
        // Étape 3 : Transformation et retour (type unique)
        $data = PerfectData::fromRecord($record);
        return $this->json($data);
    }
}

// Action Web parfaite
final class PerfectWebAction extends AbstractAction
{
    public function __construct(
        private readonly SomeWorker $worker,
    ) {}
    
    public function run(PerfectRequest $request): InertiaResponse
    {
        $record = new PerfectWorkerRecord(
            userId: $request->user()->id,
            url: $request->fullUrl(),
            ip: $request->ip(),
        );
        
        $this->worker->execute($record);
        
        return $this->inertia('Component/Name');
    }
}
```