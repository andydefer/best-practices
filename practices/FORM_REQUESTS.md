# Principe d'usage des Form Requests

## 1. Définition

Une **Form Request** est une classe qui encapsule les règles de validation pour une **route unique**. Elle est utilisée à la fois par les routes web et API.

**⚠️ Toute Form Request DOIT étendre `AbstractRequest` et implémenter la méthode `getRecord()`.**

```
Route → Form Request → getRecord() → AbstractRecord → Action
```

```php
use AndyDefer\Actions\Http\Requests\AbstractRequest;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use App\Records\ListUsersRecord;

final class ListUsersRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:admin,user'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
    
    public function getRecord(): AbstractRecord
    {
        return ListUsersRecord::from([
            'search' => $this->input('search'),
            'role' => $this->input('role'),
            'page' => (int) $this->input('page', 1),
            'per_page' => (int) $this->input('per_page', 15),
        ]);
    }
}
```

---

## 2. Les classes fondamentales : AbstractRequest

### 2.1. AbstractRequest

La classe abstraite que **toute Form Request doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Actions\Http\Requests;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * Transform the validated request into a Record object.
     *
     * This method creates a Record containing ALL the data needed by the Action:
     * - URL parameters (route parameters)
     * - Query string parameters
     * - Request body data
     * - Authenticated user information
     * - Request metadata
     *
     * @return AbstractRecord The Record object containing all request data
     */
    abstract public function getRecord(): AbstractRecord;
}
```

### 2.2. Ce qu'offre AbstractRequest

| Méthode | Description |
|---------|-------------|
| `getRecord()` | Transforme la requête validée en Record (obligatoire) |
| Hérite de toutes les méthodes de `FormRequest` | `validated()`, `input()`, `boolean()`, `integer()`, etc. |
| `authorize()` | Peut être surchargée pour l'autorisation |
| `rules()` | Peut être surchargée pour les règles de validation |

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **Une Form Request est dédiée à UNE SEULE route. On ne peut pas réutiliser la même Form Request pour deux routes différentes.**

```php
// ✅ BON - Form Request dédiée à une route
final class ListUsersRequest extends AbstractRequest
{
    // Utilisée uniquement pour GET /users
}

// ❌ MAUVAIS - Form Request réutilisée
final class UserRequest extends AbstractRequest
{
    // Utilisée pour GET /users, POST /users, etc.
}
```

### 3.1 Pourquoi une Form Request par route ?

| Raison | Explication |
|--------|-------------|
| **SRP** | Chaque route a ses propres règles de validation |
| **Évolution** | Modification d'une route sans impacter les autres |
| **Lisibilité** | `ListUsersRequest` dit clairement à quelle route il est associé |

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

### 4.1 Pourquoi cette règle ?

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Réutilisabilité** | Le Record peut être créé par d'autres moyens (console, job, test) |

---

## 5. La méthode `getRecord()` (⚠️ OBLIGATOIRE)

> **⚠️ Toute Form Request DOIT implémenter la méthode `getRecord()`. Cette méthode transforme la requête validée en Record contenant TOUTES les données dont l'Action aura besoin.**

### 5.1 Ce que doit contenir le Record

| Source | Exemple | Récupération |
|--------|---------|--------------|
| **Paramètres d'URL** | `userId` | `(int) $this->route('userId')` |
| **Paramètres de requête** | `include_profile` | `$this->boolean('include_profile')` |
| **Corps de la requête** | `name`, `email` | `$this->input('name')` |
| **Authentification** | `currentUserId` | `auth()->id()` ou `$this->user()->id` |
| **Métadonnées** | `ip`, `userAgent` | `$this->ip()`, `$this->userAgent()` |

### 5.2 Exemple complet

```php
final class ShowUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'include' => ['nullable', 'string'],
        ];
    }
    
    public function getRecord(): AbstractRecord
    {
        return ShowUserRecord::from([
            'user_id' => (int) $this->route('userId'),
            'current_user_id' => auth()->id(),
            'include_profile' => $this->boolean('include_profile'),
            'timezone' => $this->input('timezone', 'UTC'),
            'ip_address' => $this->ip(),
            'user_agent' => $this->userAgent(),
        ]);
    }
}
```

### 5.3 Règle : Un Record contient TOUT ce dont l'Action a besoin

> **⚠️ Le Record DOIT contenir l'intégralité des données nécessaires à l'Action. L'Action ne doit jamais aller chercher des données ailleurs.**

```php
// ✅ BON - Toutes les données sont dans le Record (snake_case)
final class ShowUserAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowUserRecord $request */
        $userId = $request->user_id;
        $currentUserId = $request->current_user_id;
    }
}

// ❌ MAUVAIS - L'Action va chercher des données ailleurs
final class ShowUserAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $currentUserId = auth()->id();  // ❌ À mettre dans le Record
        $ip = request()->ip();          // ❌ À mettre dans le Record
    }
}
```

---

## 6. Règles de validation

### 6.1 Paramètres d'URL vs Paramètres de requête

| Type | Emplacement | Convention | Validation |
|------|-------------|------------|------------|
| **Paramètre d'URL** | `{userId}` | `camelCase` | ❌ Non validé par Form Request |
| **Paramètre de requête** | `?user_slug=&page=` | `snake_case` | ✅ Validé par Form Request |

```php
// URL: GET /users?user_slug=john&page=2&per_page=15

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

### 6.2 Règle : Un paramètre d'URL n'est pas dans les règles

> **⚠️ Les paramètres d'URL (`{userId}`, `{postId}`) ne sont PAS validés par la Form Request. Ils sont directement intégrés dans le Record via `$this->route()`.**

```php
final class ShowUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'include' => ['nullable', 'string'],
        ];
    }
    
    public function getRecord(): AbstractRecord
    {
        return ShowUserRecord::from([
            'user_id' => (int) $this->route('userId'),
            'include_profile' => $this->boolean('include'),
        ]);
    }
}
```

### 6.3 Règle : Les propriétés du Record sont en `snake_case`

> **⚠️ Les Records ont leurs propriétés en `snake_case`. La Form Request doit hydrater le Record avec des clés en `snake_case`.**

```php
// ✅ BON - Record en snake_case
final class CreateUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly string $user_password,
    ) {}
}

// ✅ BON - Hydratation avec des clés snake_case
final class CreateUserRequest extends AbstractRequest
{
    public function getRecord(): AbstractRecord
    {
        return CreateUserRecord::from([
            'user_name' => $this->input('name'),
            'user_email' => $this->input('email'),
            'user_password' => $this->input('password'),
        ]);
    }
}

// ❌ MAUVAIS - Hydratation avec des clés camelCase
public function getRecord(): AbstractRecord
{
    return CreateUserRecord::from([
        'userName' => $this->input('name'),   // ❌ camelCase
        'userEmail' => $this->input('email'), // ❌ camelCase
    ]);
}
```

---

## 7. Méthode `authorize()` (⚠️ RÈGLES STRICTES)

> **La méthode `authorize()` doit rester LÉGÈRE et SIMPLE. Pas de logique complexe, pas d'effets de bord, pas d'injection de dépendances.**

### 7.1 Cas simples (AUTORISÉS)

```php
// ✅ AUTORISÉ - Comparaison directe
public function authorize(): bool
{
    $userId = (int) $this->route('userId');
    return $this->user()->id === $userId;
}

// ✅ AUTORISÉ - Vérification de rôle simple
public function authorize(): bool
{
    return $this->user()->status->isAdmin();
}

// ✅ AUTORISÉ - Conditions combinées simples
public function authorize(): bool
{
    $userId = (int) $this->route('userId');
    return $this->user()->id === $userId || $this->user()->status->isAdmin();
}
```

### 7.2 Cas complexes (DÉLÉGUER À L'ACTION)

> **⚠️ Si l'autorisation nécessite plus de 3 conditions, des appels repository, ou des règles métier complexes, la logique d'autorisation doit être déléguée à l'Action.**

```php
// ✅ BON - Logique simple dans authorize()
final class UpdateUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;  // L'autorisation sera vérifiée dans l'Action
    }
    
    public function getRecord(): AbstractRecord
    {
        return UpdateUserRecord::from([
            'user_id' => (int) $this->route('userId'),
            'current_user_id' => auth()->id(),
            'user_name' => $this->input('name'),
        ]);
    }
}

// La logique complexe est dans l'Action
final class UpdateUserAction extends AbstractAction
{
    protected function before(AbstractRecord $request): void
    {
        /** @var UpdateUserRecord $request */
        if ($request->current_user_id !== $request->user_id && !auth()->user()->isAdmin()) {
            abort(403);
        }
    }
}
```

### 7.3 Ce qui est INTERDIT dans `authorize()`

```php
// ❌ INTERDIT - Pas de constructeur avec dépendances
public function __construct(private readonly UserService $userService) { ... }

// ❌ INTERDIT - Appel à des services
public function authorize(): bool
{
    return $this->userService->canUpdate(...);
}

// ❌ INTERDIT - Effets de bord
public function authorize(): bool
{
    Log::info('Authorization check');
    return true;
}
```

---

## 8. Règle : Pas de tests unitaires pour les Form Requests (⚠️ RÈGLE IMPORTANTE)

> **⚠️ On n'écrit JAMAIS de tests unitaires pour les Form Requests. Ce que l'on veut tester sur la Form Request (validation, transformation en Record) est vérifié dans les tests d'intégration (Feature tests) des Actions.**

### 8.1 Pourquoi pas de tests unitaires ?

| Raison | Explication |
|--------|-------------|
| **Dépendance à Laravel** | Les Form Requests dépendent fortement de l'environnement HTTP |
| **Tests d'intégration suffisants** | Les requêtes HTTP réelles testent la validation et la transformation |
| **Éviter la duplication** | Les règles de validation sont testées via les endpoints réels |

### 8.2 Où tester la validation ?

```php
// ✅ BON - Test d'intégration (Feature test)
final class CreateUserActionTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_validation_fails_when_email_is_missing(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John Doe',
            'password' => 'SecurePass123!',
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}
```

---

## 9. Convention de nommage

> **Le nom de la Form Request doit correspondre à la route et à l'Action associée.**

| Route | Action | Form Request | Record |
|-------|--------|--------------|--------|
| `GET /users` | `ListUsersAction` | `ListUsersRequest` | `ListUsersRecord` |
| `GET /users/{userId}` | `ShowUserAction` | `ShowUserRequest` | `ShowUserRecord` |
| `POST /users` | `CreateUserAction` | `CreateUserRequest` | `CreateUserRecord` |
| `PUT /users/{userId}` | `ReplaceUserAction` | `ReplaceUserRequest` | `ReplaceUserRecord` |
| `PATCH /users/{userId}` | `UpdateUserAction` | `UpdateUserRequest` | `UpdateUserRecord` |
| `DELETE /users/{userId}` | `DeleteUserAction` | `DeleteUserRequest` | `DeleteUserRecord` |

```php
// ✅ BON - Nom correspond à la route
final class ListUsersRequest extends AbstractRequest { ... }
final class ShowUserRequest extends AbstractRequest { ... }

// ❌ MAUVAIS - Nom trop générique
final class UserRequest extends AbstractRequest { ... }
```

---

## 10. Organisation des dossiers

```
app/
├── Http/
│   ├── Requests/
│   │   ├── AbstractRequest.php
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
│   │           ├── UpdateUserRequest.php
│   │           └── DeleteUserRequest.php
│   └── ...
├── Records/
│   ├── ListUsersRecord.php
│   ├── ShowUserRecord.php
│   ├── CreateUserRecord.php
│   └── ...
└── ...
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | ✅ DOIT étendre `AbstractRequest` |
| **Méthode getRecord()** | ✅ DOIT être implémentée (obligatoire) |
| **Nommage** | `{Action}Request` (ex: `ListUsersRequest`) |
| **Route unique** | Une Form Request = une route |
| **Web vs API** | Form Requests séparées |
| **Paramètre URL** | ❌ Non dans les règles, intégré dans `getRecord()` via `$this->route()` |
| **Paramètre requête** | ✅ Validé par Form Request (`snake_case`) |
| **Record propriétés** | ✅ `snake_case` (hydratation avec clés `snake_case`) |
| **`prepareForValidation()`** | ❌ STRICTEMENT INTERDIT |
| **`afterValidation()`** | ❌ STRICTEMENT INTERDIT |
| **`authorize()`** | Conditions simples uniquement (pas d'effets de bord) |
| **Tests unitaires** | ❌ Pas de tests unitaires pour les Form Requests |

---

## 12. Règle d'or

> **Une Form Request ne fait que valider les paramètres et créer un Record. Pas de transformation complexe, pas de validation métier, pas d'effets de bord. L'Action reçoit un Record et ne connaît pas la Request. On ne teste pas les Form Requests unitairement : la validation est vérifiée dans les tests d'intégration des Actions.**
>
> **⚠️ Les Records ont leurs propriétés en `snake_case`. La Form Request doit hydrater le Record avec des clés en `snake_case`.**

```php
// La Form Request parfaite
final class PerfectRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'field_one' => ['required', 'string'],
            'field_two' => ['nullable', 'integer', 'min:1'],
        ];
    }
    
    public function getRecord(): AbstractRecord
    {
        return PerfectRecord::from([
            'field_one' => $this->input('field_one'),
            'field_two' => $this->integer('field_two'),
            'current_user_id' => auth()->id(),
            'ip_address' => $this->ip(),
        ]);
    }
}

// Le Record associé (snake_case)
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $field_one,
        public readonly ?int $field_two,
        public readonly int $current_user_id,
        public readonly string $ip_address,
    ) {}
}

// L'Action parfaite (ne connaît pas la Request)
final class PerfectAction extends AbstractAction
{
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var PerfectRecord $request */
        $result = $this->service->execute($request);
        return ResponseFactory::json($result);
    }
}

// La route
ActionRoute::get('/perfect', PerfectRequest::class, PerfectAction::class);
```