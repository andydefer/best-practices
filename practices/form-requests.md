# Form Request

## Principe d'usage des Form Requests (Version finale)

## 1. Définition

Une **Form Request** est une classe qui encapsule les règles de validation pour une **route unique**. Elle est utilisée à la fois par les routes web et API.

```
Route → Form Request → Action
```

```php
final class ListUsersRequest extends FormRequest
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
}
```

---

## 2. Règle fondamentale (⚠️ IMMUABLE)

> **Une Form Request est dédiée à UNE SEULE route. On ne peut pas réutiliser la même Form Request pour deux routes différentes.**

```php
// ✅ BON - Form Request dédiée à une route
final class ListUsersRequest extends FormRequest
{
    // Utilisée uniquement pour GET /users
}

// ❌ MAUVAIS - Form Request réutilisée
final class UserRequest extends FormRequest
{
    // Utilisée pour GET /users, POST /users, etc.  // ❌
}
```

### 2.1 Pourquoi une Form Request par route ?

| Raison | Explication |
|--------|-------------|
| **SRP** | Chaque route a ses propres règles de validation |
| **Évolution** | Modification d'une route sans impacter les autres |
| **Lisibilité** | `ListUsersRequest` dit clairement à quelle route il est associé |

---

## 3. Convention de nommage

> **Le nom de la Form Request doit correspondre à la route et à l'Action associée.**

| Route | Action | Form Request |
|-------|--------|--------------|
| `GET /users` | `ListUsersAction` | `ListUsersRequest` |
| `GET /users/{userId}` | `ShowUserAction` | `ShowUserRequest` |
| `POST /users` | `CreateUserAction` | `CreateUserRequest` |
| `PUT /users/{userId}` | `ReplaceUserAction` | `ReplaceUserRequest` |
| `PATCH /users/{userId}` | `UpdateUserAction` | `UpdateUserRequest` |
| `DELETE /users/{userId}` | `DeleteUserAction` | `DeleteUserRequest` |

```php
// ✅ BON - Nom correspond à la route
final class ListUsersRequest extends FormRequest { ... }
final class ShowUserRequest extends FormRequest { ... }

// ❌ MAUVAIS - Nom trop générique
final class UserRequest extends FormRequest { ... }
```

---

## 4. Règles de validation

### 4.1 Paramètres d'URL vs Paramètres de requête

| Type | Emplacement | Convention | Validation |
|------|-------------|------------|------------|
| **Paramètre d'URL** | `{userId}` | `camelCase` | ❌ Non validé par Form Request |
| **Paramètre de requête** | `?user_slug=&page=` | `snake_case` | ✅ Validé par Form Request |

```php
// URL: GET /users?user_slug=john&page=2&per_page=15

final class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Paramètres de requête (snake_case)
            'user_slug' => 'nullable|string|exists:users,slug',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}

// URL: GET /users/{userId}?include_profile=true

final class ShowUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Paramètre de requête (snake_case)
            'include_profile' => 'nullable|boolean',
        ];
    }
    
    // Le paramètre d'URL {userId} n'est pas dans les règles
    // Il est récupéré directement dans l'Action via le paramètre de la méthode run()
}
```

### 4.2 Règle : Un paramètre d'URL n'est pas dans les règles

> **⚠️ Les paramètres d'URL (`{userId}`, `{postId}`) ne sont PAS validés par la Form Request. Ils sont typés directement dans la méthode `run()` de l'Action.**

```php
// La Form Request ne valide pas les paramètres d'URL
final class ShowUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Seulement les paramètres de requête
            'include_profile' => 'nullable|boolean',
        ];
    }
}

// L'Action type le paramètre d'URL
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        // $userId est déjà typé et validé (int)
        // $request contient les paramètres de requête validés
    }
}
```

---
## 5. Méthode `authorize()` (⚠️ RÈGLES STRICTES)

> **La méthode `authorize()` doit rester LÉGÈRE et SIMPLE. Pas de logique complexe, pas d'effets de bord, pas d'injection de dépendances. Les FormRequests sont des DTOs de validation, pas des orchestrateurs.**

### 5.1 Cas simples (AUTORISÉS)

```php
final class UpdateUserRequest extends FormRequest
{
    // ✅ AUTORISÉ - Comparaison directe
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        
        return $this->user()->id === $userId;
    }
}
```

```php
final class UpdateUserRequest extends FormRequest
{
    // ✅ AUTORISÉ - Vérification de rôle simple
    public function authorize(): bool
    {
        return $this->user()->status->isAdmin();
    }
}
```

```php
final class UpdateUserRequest extends FormRequest
{
    // ✅ AUTORISÉ - Conditions combinées simples
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        
        return $this->user()->id === $userId || $this->user()->status->isAdmin();
    }
}
```

### 5.2 Cas complexes (DÉLÉGUER À L'ACTION)

> **⚠️ RÈGLE D'OR : Si l'autorisation nécessite plus de 3 conditions, des appels repository, ou des règles métier complexes, la logique d'autorisation doit être déléguée à l'Action qui utilisera une Task.**

```php
// ✅ BON - Logique simple dans authorize()
final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Juste vérifier que l'ID est présent
        return $this->route('userId') !== null;
    }
}

// La logique complexe est dans l'Action
final class UpdateUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserCanUpdateTask $userCanUpdateTask,
    ) {}
    
    public function execute(UpdateUserRequest $request): UserResource
    {
        $userId = (int) $request->route('userId');
        
        // ✅ La Task contient la logique métier complexe
        if (!$this->userCanUpdateTask->execute(new UserCanUpdateRecord(
            currentUserId: $request->user()->id,
            targetUserId: $userId,
        ))) {
            throw new UnauthorizedException('You cannot update this user');
        }
        
        // ... suite de l'action
    }
}
```

### 5.3 Ce qui est INTERDIT dans `authorize()`

```php
final class UpdateUserRequest extends FormRequest
{
    // ❌ INTERDIT - Pas de constructeur avec dépendances
    public function __construct(
        private readonly UserCanUpdateTask $userCanUpdateTask,
    ) {
        parent::__construct();
    }
    
    // ❌ INTERDIT - Logique métier complexe
    // ❌ INTERDIT - Appel à des Tasks
    // ❌ INTERDIT - Effets de bord
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        
        // ❌ Appel à une Task (trop lourd)
        return $this->userCanUpdateTask->execute(new UserCanUpdateRecord(
            currentUserId: $this->user()->id,
            targetUserId: $userId,
        ));
    }
}
```

```php
final class UpdateUserRequest extends FormRequest
{
    // ❌ INTERDIT - Tout ce qui suit est interdit
    public function authorize(): bool
    {
        // ❌ Effet de bord (log)
        Log::info('Authorization check');
        
        // ❌ Appel API externe
        Http::post('https://api.audit.com/log', [...]);
        
        // ❌ Transaction DB
        DB::beginTransaction();
        
        // ❌ Envoi d'email
        Mail::send(...);
        
        // ❌ Logique imbriquée complexe (plus de 3 niveaux)
        $user = User::find($this->route('userId'));
        if ($user && $user->role === 'admin') {
            if ($this->user()->status->isSuperAdmin()) {
                if ($user->status === 'active') {
                   ....
                }
            }
        }
        
        return false;
    }
}
```

### 5.4 Récapitulatif des responsabilités

| Composant | Responsabilité | Exemple |
|-----------|---------------|---------|
| **FormRequest** | Validation basique et présence des données | `return $this->route('userId') !== null` |
| **Action** | Orchestration et gestion des erreurs d'autorisation | Vérifie les droits via Task, lance des exceptions |
| **Task** | Logique métier et testable | Vérifie si un utilisateur peut en modifier un autre |
| **Worker** | Groupement de Tasks | Envoi d'email, log, notification |

### 5.5 Pourquoi ces interdictions ?

| Problème | Solution |
|----------|----------|
| **FormRequest trop lourde** → Rester simple | Les FormRequests sont des DTOs, pas des services |
| **Logique complexe** → Déplacer dans l'Action + Task | L'Action orchestre, la Task exécute la logique |
| **Injection de dépendances** → À éviter dans FormRequest | Les FormRequests sont instanciées automatiquement par Laravel |
| **Effets de bord** → Déplacer dans l'Action | Le Worker orchestre les effets de bord via des Tasks |
| **Non testable facilement** → Logique dans Action/Task | Les Actions et Tasks sont facilement mockables |

### 5.6 Structure recommandée

```
FormRequest (authorize simple)
    ↓
Action (orchestration)
    ↓
Task (logique métier)
    ↓
Repository (persistance)
```

### 5.7 Exemple complet et conforme

```php
// ✅ FormRequest - Ultra léger
final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Juste vérifier qu'un ID est fourni
        return $this->route('userId') !== null;
    }
    
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $this->route('userId')],
        ];
    }
}

// ✅ Action - Orchestration et délégation
final class UpdateUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserCanUpdateTask $userCanUpdateTask,
        private readonly UpdateUserTask $updateUserTask,
    ) {}
    
    public function execute(UpdateUserRequest $request): UserResource
    {
        $userId = (int) $request->route('userId');
        $currentUserId = $request->user()->id;
        
        // La logique complexe est dans une Task
        if (!$this->userCanUpdateTask->execute(new UserCanUpdateRecord(
            currentUserId: $currentUserId,
            targetUserId: $userId,
        ))) {
            throw new UnauthorizedException('You cannot update this user');
        }
        
        // Mise à jour dans une autre Task
        $user = $this->updateUserTask->execute(new UpdateUserRecord(
            userId: $userId,
            data: $request->validated(),
        ));
        
        return new UserResource($user);
    }
}

// ✅ Task - Logique métier pure
final class UserCanUpdateTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(UserCanUpdateRecord $record): bool
    {
        // Logique complexe ici...
        if ($record->currentUserId === $record->targetUserId) {
            return true;
        }
        
        $currentUser = $this->userRepository->find($record->currentUserId);
        
        return $currentUser?->status->isAdmin() ?? false;
    }
}
```
---

## 6. Méthodes INTERDITES (⚠️ STRICTEMENT INTERDITES)

> **⚠️ Les méthodes `prepareForValidation()` et `after()` sont STRICTEMENT INTERDITES. Elles rendent l'application ambiguë et masquent la logique.**

### 6.1 Pourquoi ces méthodes sont interdites ?

| Problème | Explication |
|----------|-------------|
| **Ambiguïté** | On ne sait plus où les données sont modifiées |
| **Logique cachée** | La modification des données est invisible à l'appelant |
| **Violation SRP** | La Form Request ne doit que valider, pas modifier les données |
| **Difficulté de test** | La logique est noyée dans la Form Request |

### 6.2 Ce qui est INTERDIT

```php
final class CreateUserRequest extends FormRequest
{
    // ❌ STRICTEMENT INTERDIT
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim($this->email)),
        ]);
    }
    
    // ❌ STRICTEMENT INTERDIT
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (User::where('email', $this->email)->exists()) {
                    $validator->errors()->add('email', 'Email already taken.');
                }
            },
        ];
    }
}
```

### 6.3 Ce qu'il faut faire à la place

```php
// ✅ BON - La transformation est faite dans l'Action lors de la création du Record
final class CreateUserAction extends AbstractAction
{
    public function run(CreateUserRequest $request): JsonResponse
    {
        // Transformation explicite dans l'Action
        $record = new CreateUserRecord(
            name: $request->input('name'),
            email: strtolower(trim($request->input('email'))),
            password: $request->input('password'),
            role: UserRole::from($request->input('role')),
        );
        
        // ...
    }
}

// ✅ BON - La validation complexe est faite dans l'Action via une Task
final class CreateUserAction extends AbstractAction
{
    public function __construct(
        private readonly ValidateEmailNotTakenTask $validateEmailNotTaken,
    ) {}
    
    public function run(CreateUserRequest $request): JsonResponse
    {
        $record = new CreateUserRecord(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            role: UserRole::from($request->input('role')),
        );
        
        // Validation complexe via Task
        $this->validateEmailNotTaken->execute(new ValidateEmailNotTakenRecord(
            email: $record->email,
        ));
        
        // ...
    }
}
```
---

## 7. Règles de validation complexes

> **Pour les règles de validation complexes, créez une `Rule` personnalisée ou utilisez une Task dans l'Action.**
Voici un exemple plus complexe pour la validation d'un numéro de téléphone :

### 7.1 Règle personnalisée (recommandée)

```php
// App\Rules\ValidPhoneNumber.php
final class ValidPhoneNumber implements Rule
{
    public function __construct(
        private readonly ?string $code = null,
    ) {}
    
    public function passes(string $attribute, mixed $value): bool
    {
        // Supprime les espaces, points, tirets
        $clean = preg_replace('/[\s\.\-]/', '', $value);
        
        // Vérifie le format international ou local
        if ($this->code) {
            return (bool) preg_match('/^\+?' . preg_quote($this->code) . '\d{8,12}$/', $clean);
        }
        
        // Format international standard (+XX...)
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
final class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', new ValidPhoneNumber],
            'phone_fr' => ['required', new ValidPhoneNumber(code: '33')],
        ];
    }
}
```

### 7.2 Validation via Task (pour les cas métier)

```php
// Task de validation
final class ValidateEmailNotTakenTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function execute(ValidateEmailNotTakenRecord $record): void
    {
        if ($this->userRepository->existsByEmail($record->email)) {
            throw new ValidationException('Email already taken.');
        }
    }
}

// Dans l'Action
final class CreateUserAction extends AbstractAction
{
    public function __construct(
        private readonly ValidateEmailNotTakenTask $validateEmailNotTaken,
    ) {}
    
    public function run(CreateUserRequest $request): JsonResponse
    {
        $this->validateEmailNotTaken->execute(new ValidateEmailNotTakenRecord(
            email: $request->input('email'),
        ));
        
        // ...
    }
}
```

---

## 8. Form Request pour les routes web et API

> **⚠️ Une route web et sa route miroir API utilisent des Form Requests séparées, même si leurs règles sont identiques.**

```php
// Web
final class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}

// API
final class ListUsersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
```

### 8.1 Pourquoi des Form Requests séparées ?

| Raison | Explication |
|--------|-------------|
| **Évolution indépendante** | Les règles web peuvent diverger des règles API |
| **Autorisation différente** | Web utilise session, API utilise token |
| **Messages personnalisés** | Messages différents pour web et API |

---

## 9. Organisation des dossiers

```
app/
├── Http/
│   ├── Requests/
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
├── Rules/
│   └── ValidUserRole.php
└── ...
```

---

## 10. Exemples complets

### 10.1 Form Request pour GET /users (liste)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use App\Rules\ValidUserRole;
use Illuminate\Foundation\Http\FormRequest;

final class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'role' => ['nullable', new ValidUserRole],
            'status' => 'nullable|string|in:active,inactive,pending',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:id,name,email,created_at',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ];
    }
}
```

### 10.2 Form Request pour GET /users/{userId} (détail)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

final class ShowUserRequest extends FormRequest
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
            'include_addresses' => 'nullable|boolean',
        ];
    }
}
```

### 10.3 Form Request pour POST /users (création)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use App\Rules\ValidUserRole;
use Illuminate\Foundation\Http\FormRequest;

final class CreateUserRequest extends FormRequest
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
}
```

### 10.4 Form Request pour PATCH /users/{userId} (mise à jour partielle)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use App\Rules\ValidUserRole;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
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
}
```

### 10.5 Form Request pour DELETE /users/{userId} (suppression)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;

final class DeleteUserRequest extends FormRequest
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
}
```

### 10.6 Form Request avec Task dans authorize (cas complexe)

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Users;

use App\Records\UserCanUpdateRecord;
use App\Tasks\UserCanUpdateTask;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    public function __construct(
        private readonly UserCanUpdateTask $userCanUpdate,
    ) {
        parent::__construct();
    }
    
    public function authorize(): bool
    {
        $userId = (int) $this->route('userId');
        
        // Délégation à une Task (sans effet de bord)
        return $this->userCanUpdate->execute(new UserCanUpdateRecord(
            currentUserId: $this->user()->id,
            targetUserId: $userId,
        ));
    }
    
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $this->route('userId'),
        ];
    }
}
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Action}Request` (ex: `ListUsersRequest`) |
| **Route unique** | Une Form Request = une route |
| **Web vs API** | Form Requests séparées |
| **Paramètre URL** | ❌ Non validé par Form Request (typé dans l'Action) |
| **Paramètre requête** | ✅ Validé par Form Request (`snake_case`) |
| **`prepareForValidation()`** | ❌ STRICTEMENT INTERDIT |
| **`after()`** | ❌ STRICTEMENT INTERDIT |
| **`authorize()`** | Conditions simples uniquement (pas d'effets de bord) |
| **Validation complexe** | Via `Rule` personnalisée ou Task dans l'Action |

---

## 12. Règle d'or

> **Une Form Request ne fait que valider les paramètres de requête. Pas de transformation, pas de validation complexe, pas d'effets de bord. La logique est dans l'Action.**

```php
// La Form Request parfaite
final class PerfectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Simple condition
        return $this->user()->status->isAdmin();
    }
    
    public function rules(): array
    {
        return [
            'field_one' => 'required|string',
            'field_two' => 'nullable|integer|min:1',
        ];
    }
}

// La logique de transformation et validation complexe est dans l'Action
final class PerfectAction extends AbstractAction
{
    public function __construct(
        private readonly SomeValidationTask $validation,
    ) {}
    
    public function run(PerfectRequest $request): JsonResponse
    {
        // Transformation explicite
        $value = strtolower(trim($request->input('field_one')));
        
        // Validation complexe
        $this->validation->execute($value);
        
        // ...
    }
}
