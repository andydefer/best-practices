Voici la dernière version de `form-requests.md` que nous avons écrite ensemble :

---

# Principe d'usage des Form Requests (Version finale)

## 1. Définition

Une **Form Request** est une classe qui encapsule les règles de validation pour une **route unique**. Elle est utilisée à la fois par les routes web et API.

**⚠️ Toute Form Request DOIT étendre `AbstractRequest` et implémenter la méthode `toRecord()`.**

```
Route → Form Request → toRecord() → Record → Action
```

```php
final class ListUsersRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:admin,user',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
    
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            search: $this->input('search'),
            role: $this->input('role'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
        );
    }
}
```

---

## 2. Les classes fondamentales : AbstractRequest et Recordable

### 2.1. Interface Recordable

L'interface que tout Record doit implémenter (via `AbstractRecord`) :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

interface Recordable
{
    public function toArray(): array;
    public function toDatabase(): array;
    public function toJson(): string;
}
```

### 2.2. AbstractRequest

La classe abstraite que **toute Form Request doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Http\Requests;

use AndyDefer\BestPractices\Records\Recordable;
use Illuminate\Foundation\Http\FormRequest;

abstract class AbstractRequest extends FormRequest
{
    abstract public function toRecord(): Recordable;
}
```

### 2.3. Ce qu'offre AbstractRequest

| Méthode | Description |
|---------|-------------|
| `toRecord()` | Transforme la requête validée en Record (obligatoire) |
| Hérite de toutes les méthodes de `FormRequest` | `validated()`, `input()`, `boolean()`, `integer()`, etc. |

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

> **⚠️ Une Action ne peut jamais recevoir une Form Request en paramètre. Elle reçoit TOUJOURS un Record créé par la méthode `toRecord()` de la Request.**

```php
// ✅ BON - L'Action reçoit un Record
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRecord $record): JsonResponse
    {
        // $record contient TOUT ce dont l'Action a besoin
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

### 4.1 Pourquoi cette règle ?

| Raison | Explication |
|--------|-------------|
| **Testabilité** | Un Record se crée facilement, une Request se mocke difficilement |
| **Pureté** | L'Action ne dépend plus de Laravel |
| **Contrat explicite** | Le Record dit exactement ce dont l'Action a besoin |
| **Réutilisabilité** | Le Record peut être créé par d'autres moyens |

---

## 5. La méthode `toRecord()` (⚠️ OBLIGATOIRE)

> **⚠️ Toute Form Request DOIT implémenter la méthode `toRecord()`. Cette méthode transforme la requête validée en Record contenant TOUTES les données dont l'Action aura besoin.**

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
            'include_profile' => 'nullable|boolean',
            'timezone' => 'nullable|timezone',
        ];
    }
    
    public function toRecord(): ShowUserRecord
    {
        return new ShowUserRecord(
            id: (int) $this->route('userId'),
            currentUserId: auth()->id(),
            includeProfile: $this->boolean('include_profile'),
            timezone: $this->input('timezone', 'UTC'),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }
}
```

### 5.3 Règle : Un Record contient TOUT ce dont l'Action a besoin

> **⚠️ Le Record DOIT contenir l'intégralité des données nécessaires à l'Action. L'Action ne doit jamais aller chercher des données ailleurs.**

```php
// ✅ BON - Toutes les données sont dans le Record
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRecord $record): JsonResponse
    {
        $userId = $record->id;
        $currentUserId = $record->currentUserId;
    }
}

// ❌ MAUVAIS - L'Action va chercher des données ailleurs
final class ShowUserAction extends AbstractAction
{
    public function run(ShowUserRecord $record): JsonResponse
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
```

### 6.2 Règle : Un paramètre d'URL n'est pas dans les règles

> **⚠️ Les paramètres d'URL (`{userId}`, `{postId}`) ne sont PAS validés par la Form Request. Ils sont directement intégrés dans le Record via `$this->route()`.**

```php
final class ShowUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'include_profile' => 'nullable|boolean',
        ];
    }
    
    public function toRecord(): ShowUserRecord
    {
        return new ShowUserRecord(
            id: (int) $this->route('userId'),
            includeProfile: $this->boolean('include_profile'),
        );
    }
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

> **⚠️ Si l'autorisation nécessite plus de 3 conditions, des appels repository, ou des règles métier complexes, la logique d'autorisation doit être déléguée à l'Action qui utilisera une Task.**

```php
// ✅ BON - Logique simple dans authorize()
final class UpdateUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->route('userId') !== null;
    }
    
    public function toRecord(): UpdateUserRecord
    {
        return new UpdateUserRecord(
            userId: (int) $this->route('userId'),
            currentUserId: auth()->id(),
            name: $this->input('name'),
        );
    }
}

// La logique complexe est dans l'Action
final class UpdateUserAction extends AbstractAction
{
    public function run(UpdateUserRecord $record): JsonResponse
    {
        if (!$this->userCanUpdateTask->execute(new UserCanUpdateRecord(
            currentUserId: $record->currentUserId,
            targetUserId: $record->userId,
        ))) {
            throw new UnauthorizedException();
        }
        // ...
    }
}
```

### 7.3 Ce qui est INTERDIT dans `authorize()`

```php
// ❌ INTERDIT - Pas de constructeur avec dépendances
public function __construct(private readonly UserCanUpdateTask $task) { ... }

// ❌ INTERDIT - Appel à des Tasks
public function authorize(): bool
{
    return $this->task->execute(...);
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

### 8.1 Pourquoi pas de tests unitaires pour les Form Requests ?

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
    
    public function test_action_receives_correct_record_on_success(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
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

## 10. Méthodes INTERDITES (⚠️ STRICTEMENT INTERDITES)

> **⚠️ Les méthodes `prepareForValidation()` et `after()` sont STRICTEMENT INTERDITES. Elles rendent l'application ambiguë et masquent la logique.**

### 10.1 Pourquoi ces méthodes sont interdites ?

| Problème | Explication |
|----------|-------------|
| **Ambiguïté** | On ne sait plus où les données sont modifiées |
| **Logique cachée** | La modification des données est invisible à l'appelant |
| **Violation SRP** | La Form Request ne doit que valider, pas modifier les données |
| **Difficulté de test** | La logique est noyée dans la Form Request |

### 10.2 Ce qu'il faut faire à la place

```php
// ✅ BON - La transformation est faite dans toRecord()
final class CreateUserRequest extends AbstractRequest
{
    public function toRecord(): CreateUserRecord
    {
        return new CreateUserRecord(
            name: trim($this->input('name')),
            email: strtolower(trim($this->input('email'))),
        );
    }
}

// ✅ BON - La validation complexe est faite dans l'Action via une Task
final class CreateUserAction extends AbstractAction
{
    public function run(CreateUserRecord $record): JsonResponse
    {
        $this->validateEmailNotTaken->execute(new ValidateEmailNotTakenRecord(
            email: $record->email,
        ));
        // ...
    }
}
```

---

## 11. Règles de validation complexes

> **Pour les règles de validation complexes, créez une `Rule` personnalisée.**

```php
// App\Rules\ValidPhoneNumber.php
final class ValidPhoneNumber implements Rule
{
    public function __construct(private readonly ?string $code = null) {}
    
    public function passes(string $attribute, mixed $value): bool
    {
        $clean = preg_replace('/[\s\.\-]/', '', $value);
        
        if ($this->code) {
            return (bool) preg_match('/^\+?' . preg_quote($this->code) . '\d{8,12}$/', $clean);
        }
        
        return (bool) preg_match('/^\+\d{10,15}$/', $clean);
    }
    
    public function message(): string
    {
        $message = 'The :attribute must be a valid phone number';
        if ($this->code) {
            $message .= " with country code {$this->code}";
        }
        return $message . '.';
    }
}

// Form Request
final class CreateUserRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', new ValidPhoneNumber],
            'phone_fr' => ['required', new ValidPhoneNumber(code: '33')],
        ];
    }
    
    public function toRecord(): CreateUserRecord
    {
        return new CreateUserRecord(
            phone: $this->input('phone'),
            phoneFr: $this->input('phone_fr'),
        );
    }
}
```

---

## 12. Form Request pour les routes web et API

> **⚠️ Une route web et sa route miroir API utilisent des Form Requests séparées, même si leurs règles sont identiques.**

```php
// Web
final class ListUsersRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
    
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            search: $this->input('search'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
        );
    }
}

// API
final class ListUsersRequest extends AbstractRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
    
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            search: $this->input('search'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
        );
    }
}
```

### 12.1 Pourquoi des Form Requests séparées ?

| Raison | Explication |
|--------|-------------|
| **Évolution indépendante** | Les règles web peuvent diverger des règles API |
| **Autorisation différente** | Web utilise session, API utilise token |
| **Métadonnées différentes** | Web n'a pas besoin d'API token, API n'a pas besoin de session |

---

## 13. Organisation des dossiers

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
│   │   │       ├── ShowUserRequest.php
│   │   │       ├── CreateUserRequest.php
│   │   │       ├── UpdateUserRequest.php
│   │   │       └── DeleteUserRequest.php
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
│   ├── UpdateUserRecord.php
│   └── DeleteUserRecord.php
└── ...
```

---

## 14. Exemples complets

### 14.1 Form Request pour GET /users (liste)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use AndyDefer\BestPractices\Http\Requests\AbstractRequest;
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
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,name,email,created_at',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }
    
    public function toRecord(): ListUsersRecord
    {
        return new ListUsersRecord(
            search: $this->input('search'),
            page: $this->integer('page', 1),
            perPage: $this->integer('per_page', 15),
            sortBy: $this->input('sort_by', 'id'),
            sortDirection: $this->input('sort_direction', 'asc'),
        );
    }
}
```

### 14.2 Form Request pour GET /users/{userId} (détail)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use AndyDefer\BestPractices\Http\Requests\AbstractRequest;
use App\Records\ShowUserRecord;

final class ShowUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        return $this->user()->id === $userId || $this->user()->isAdmin();
    }
    
    public function rules(): array
    {
        return [
            'include_profile' => 'nullable|boolean',
            'include_orders' => 'nullable|boolean',
        ];
    }
    
    public function toRecord(): ShowUserRecord
    {
        return new ShowUserRecord(
            id: (int) $this->route('userId'),
            currentUserId: auth()->id(),
            includeProfile: $this->boolean('include_profile'),
            includeOrders: $this->boolean('include_orders'),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }
}
```

### 14.3 Form Request pour POST /users (création)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use AndyDefer\BestPractices\Http\Requests\AbstractRequest;
use App\Records\CreateUserRecord;
use App\Rules\ValidUserRole;

final class CreateUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->isAdmin();
    }
    
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', new ValidUserRole],
            'is_active' => 'nullable|boolean',
        ];
    }
    
    public function toRecord(): CreateUserRecord
    {
        return new CreateUserRecord(
            name: trim($this->input('name')),
            email: strtolower(trim($this->input('email'))),
            password: $this->input('password'),
            role: UserRole::from($this->input('role')),
            isActive: $this->boolean('is_active', true),
            createdBy: auth()->id(),
            ip: $this->ip(),
        );
    }
}
```

### 14.4 Form Request pour PATCH /users/{userId} (mise à jour partielle)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use AndyDefer\BestPractices\Http\Requests\AbstractRequest;
use App\Records\UpdateUserRecord;
use App\Rules\ValidUserRole;

final class UpdateUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        return $this->user()->id === $userId || $this->user()->role->isAdmin();
    }
    
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $this->route('userId'),
            'role' => ['nullable', new ValidUserRole],
            'is_active' => 'nullable|boolean',
        ];
    }
    
    public function toRecord(): UpdateUserRecord
    {
        return new UpdateUserRecord(
            userId: (int) $this->route('userId'),
            currentUserId: auth()->id(),
            name: $this->input('name'),
            email: $this->input('email') ? strtolower(trim($this->input('email'))) : null,
            role: $this->input('role') ? UserRole::from($this->input('role')) : null,
            isActive: $this->boolean('is_active'),
        );
    }
}
```

### 14.5 Form Request pour DELETE /users/{userId} (suppression)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use AndyDefer\BestPractices\Http\Requests\AbstractRequest;
use App\Records\DeleteUserRecord;

final class DeleteUserRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        return $this->user()->status->isAdmin() && $this->user()->id !== $userId;
    }
    
    public function rules(): array
    {
        return [
            'hard_delete' => 'nullable|boolean',
        ];
    }
    
    public function toRecord(): DeleteUserRecord
    {
        return new DeleteUserRecord(
            userId: (int) $this->route('userId'),
            currentUserId: auth()->id(),
            hardDelete: $this->boolean('hard_delete', false),
        );
    }
}
```

---

## 15. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | ✅ DOIT étendre `AbstractRequest` |
| **Méthode toRecord()** | ✅ DOIT être implémentée (obligatoire) |
| **Nommage** | `{Action}Request` (ex: `ListUsersRequest`) |
| **Route unique** | Une Form Request = une route |
| **Web vs API** | Form Requests séparées |
| **Paramètre URL** | ❌ Non dans les règles, intégré dans `toRecord()` via `$this->route()` |
| **Paramètre requête** | ✅ Validé par Form Request (`snake_case`) |
| **`prepareForValidation()`** | ❌ STRICTEMENT INTERDIT |
| **`after()`** | ❌ STRICTEMENT INTERDIT |
| **`authorize()`** | Conditions simples uniquement (pas d'effets de bord) |
| **Validation complexe** | Via `Rule` personnalisée |
| **Tests unitaires** | ❌ Pas de tests unitaires pour les Form Requests |

---

## 16. Règle d'or

> **Une Form Request ne fait que valider les paramètres et créer un Record. Pas de transformation complexe, pas de validation métier, pas d'effets de bord. L'Action reçoit un Record et ne connaît pas la Request. On ne teste pas les Form Requests unitairement : la validation est vérifiée dans les tests d'intégration des Actions.**

```php
// La Form Request parfaite
final class PerfectRequest extends AbstractRequest
{
    public function authorize(): bool
    {
        return $this->user()->status->isAdmin();
    }
    
    public function rules(): array
    {
        return [
            'field_one' => 'required|string',
            'field_two' => 'nullable|integer|min:1',
        ];
    }
    
    public function toRecord(): PerfectRecord
    {
        return new PerfectRecord(
            fieldOne: $this->input('field_one'),
            fieldTwo: $this->integer('field_two'),
            currentUserId: auth()->id(),
            ip: $this->ip(),
        );
    }
}

// L'Action parfaite (ne connaît pas la Request)
final class PerfectAction extends AbstractAction
{
    public function run(PerfectRecord $record): JsonResponse
    {
        $result = $this->service->execute($record);
        return $this->json($result);
    }
}

// La route
Route::get('/perfect', function (PerfectRequest $request, PerfectAction $action) {
    return $action->run($request->toRecord());
});
```