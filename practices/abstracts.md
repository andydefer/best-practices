# Principe d'usage des Abstract Class (Version finale)

## 1. Définition

Une **Abstract Class** est une classe qui ne peut pas être instanciée directement. Elle sert de modèle pour d'autres classes, fournissant une implémentation partielle et des méthodes abstraites que les classes filles doivent implémenter.

```
Abstract Class → Modèle partiel → Implémentation commune → Contrat partiel
```

```php
abstract class AbstractRepository
{
    abstract protected function getModelClass(): string;
    
    public function find(int $id): ?Model
    {
        return $this->newModel()->find($id);
    }
    
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
}
```

---

## 2. Problématique à laquelle les Abstract Class répondent

| Problème | Solution |
|----------|----------|
| **Code dupliqué** | Factorisation dans la classe abstraite |
| **Contrat sans implémentation** | Méthodes abstraites imposées |
| **Comportement commun** | Méthodes concrètes réutilisables |
| **Template method** | Structure d'algorithme définie |

---

## 3. Règles fondamentales

### 3.1 Nommage

```
Abstract{Entity}
```

| Classe abstraite | Classe fille |
|------------------|--------------|
| `AbstractRepository` | `UserRepository` |
| `AbstractAction` | `ShowUserAction` |
| `AbstractRecord` | `UserRecord` |
| `AbstractData` | `UserData` |

```php
// ✅ BON
abstract class AbstractRepository { ... }
abstract class AbstractAction { ... }
abstract class AbstractRecord { ... }

// ❌ MAUVAIS
class BaseRepository { ... }
class RepositoryBase { ... }
```

### 3.2 Localisation

```
app/Abstracts/Abstract{Entity}.php
ou
app/{Domain}/Abstracts/Abstract{Entity}.php
```

```
app/Abstracts/
├── AbstractRepository.php
├── AbstractAction.php
├── AbstractRecord.php
└── AbstractData.php
```

---

## 4. Méthodes abstraites

> **Une méthode abstraite définit un contrat que les classes filles DOIVENT implémenter.**

```php
// ✅ BON - Méthode abstraite obligatoire
abstract class AbstractRepository
{
    abstract protected function getModelClass(): string;
    
    abstract public function find(int $id): ?Model;
}

// ✅ BON - Méthode abstraite avec paramètres typés
abstract class AbstractTask
{
    abstract public function execute(AbstractRecord $record): mixed;
}

// ❌ MAUVAIS - Méthode abstraite trop spécifique
abstract class AbstractRepository
{
    abstract public function findUserByEmail(string $email): ?User;  // ❌ Trop spécifique
}
```

---

## 5. Méthodes finales

> **Les méthodes finales ne peuvent pas être surchargées par les classes filles. Elles sont utilisées pour les comportements qui ne doivent pas changer.**

```php
// ✅ BON - Méthode finale pour un comportement critique
abstract class AbstractRepository
{
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
    
    final public function create(Recordable $record): Model
    {
        return DB::transaction(fn() => $this->newModel()->create($record->toArray()));
    }
}

// ❌ MAUVAIS - Méthode finale qui devrait être personnalisable
abstract class AbstractRepository
{
    final public function find(int $id): ?Model  // ❌ Ne devrait pas être final
    {
        return $this->newModel()->find($id);
    }
}
```

---

## 6. Propriétés protégées

> **Les propriétés protégées sont accessibles aux classes filles. Elles permettent de partager des données communes.**

```php
// ✅ BON - Propriétés protégées pour configuration
abstract class AbstractRepository
{
    protected array $with = [];
    protected array $withCount = [];
    protected int $perPage = 15;
    
    public function with(array $relations): static
    {
        $this->with = $relations;
        return $this;
    }
}

final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}

// ❌ MAUVAIS - Propriété privée dans une classe abstraite
abstract class AbstractRepository
{
    private array $with = [];  // ❌ Les filles ne peuvent pas y accéder
}
```

---

## 7. Template Method Pattern

> **La classe abstraite définit l'algorithme, les classes filles implémentent les étapes variables.**

```php
// ✅ BON - Template method
abstract class AbstractTask
{
    final public function execute(AbstractRecord $record): mixed
    {
        $validated = $this->validate($record);
        $result = $this->process($validated);
        $this->log($result);
        
        return $result;
    }
    
    protected function validate(AbstractRecord $record): AbstractRecord
    {
        return $record;
    }
    
    abstract protected function process(AbstractRecord $record): mixed;
    
    protected function log(mixed $result): void
    {
        Log::info('Task executed', ['result' => $result]);
    }
}
```

---

## 8. Couplage d'une Abstract Class (⚠️ RÈGLE IMPORTANTE)

> **Une classe abstraite peut être couplée à une interface ou à un trait selon le besoin.**

### 8.1 Abstract Class + Interface

> **Utilisez ce couplage quand vous avez besoin d'un contrat commun ET d'une implémentation partielle.**

```php
// ✅ BON - Abstract class + Interface
interface RepositoryInterface
{
    public function find(int $id): ?Model;
    public function create(Recordable $record): Model;
}

abstract class AbstractRepository implements RepositoryInterface
{
    abstract protected function getModelClass(): string;
    
    public function find(int $id): ?Model
    {
        return $this->newModel()->find($id);
    }
    
    public function create(Recordable $record): Model
    {
        return DB::transaction(fn() => $this->newModel()->create($record->toArray()));
    }
    
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
}

final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}
```

### 8.2 Abstract Class + Trait

> **Utilisez ce couplage quand vous voulez réutiliser un comportement dans plusieurs classes abstraites.**

```php
// ✅ BON - Abstract class + Trait
trait HasTimestamps
{
    public function getCreatedAt(): string
    {
        return $this->created_at->toIso8601String();
    }
}

abstract class AbstractModel extends Model
{
    use HasTimestamps;
    
    abstract public function getDisplayName(): string;
}

final class User extends AbstractModel
{
    public function getDisplayName(): string
    {
        return $this->name;
    }
}
```

### 8.3 Abstract Class + Interface + Trait

> **Utilisez ce couplage quand vous avez besoin des trois : contrat, implémentation partielle et comportement transversal.**

```php
<?php

declare(strict_types=1);

/**
 * ✅ BON - Abstract class + Interface + Trait
 * 
 * Conventions appliquées :
 * - Interface : {Entity}able → Actionable
 * - Trait : Has{Entity} → HasHttpResponses
 * - Abstract class : Abstract{Entity} → AbstractAction
 */

// ✅ Interface (contrat) - Se termine par 'able'
interface Actionable
{
    public function run(...$parameters): mixed;
}

// ✅ Trait (implémentation) - Commence par 'Has'
trait HasHttpResponses
{
    public function json($data, int $code = 200): JsonResponse
    {
        return response()->json($data, $code);
    }
    
    public function success($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
    
    public function error(string $message, int $code = 400, $errors = null): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}

// ✅ Abstract class (respecte l'interface ET utilise le trait)
abstract class AbstractAction implements Actionable
{
    use HasHttpResponses;  // ✅ Trait avec convention HasHttpResponses
    
    // Interface requirement
    abstract public function run(...$parameters): mixed;
    
    // Méthode utilitaire commune à toutes les Actions
    protected function validateRequest(FormRequest $request): void
    {
        $request->validated();
    }
    
    // Protection CSRF pour les actions web (optionnel)
    protected function authorize(string $ability, $arguments = []): void
    {
        Gate::authorize($ability, $arguments);
    }
}

// ✅ Implementation finale
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $userRecord = $this->userService->getUser($userId);
        
        if ($userRecord === null) {
            return $this->error('User not found', 404);
        }
        
        $userData = UserData::fromRecord($userRecord);
        
        return $this->success($userData);
    }
}

// ✅ Autre exemple - Action de création
final class CreateUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    public function run(CreateUserRequest $request): JsonResponse
    {
        $this->validateRequest($request);
        
        $userRecord = $this->userService->createUser(
            name: $request->input('name'),
            email: $request->input('email'),
            role: UserRole::fromValue($request->input('role')),
        );
        
        $userData = UserData::fromRecord($userRecord);
        
        return $this->success($userData, 'User created successfully', 201);
    }
}
```

### 8.4 Tableau de décision

| Besoin | Solution |
|--------|----------|
| Contrat commun + implémentation partielle | Abstract Class + Interface |
| Comportement réutilisable entre abstracts | Abstract Class + Trait |
| Contrat + implémentation partielle + comportement transversal | Abstract Class + Interface + Trait |
| Pas d'implémentation commune | Interface seule |
| Pas de contrat commun | Trait seul (mais avec interface associée) |

---

## 9. Abstract Class vs Interface

| Abstract Class | Interface |
|----------------|-----------|
| Peut contenir de l'implémentation | ❌ Pas d'implémentation |
| Peut avoir des propriétés | ❌ Pas de propriétés |
| Une classe ne peut hériter que d'une seule | Une classe peut implémenter plusieurs |
| Peut avoir des méthodes privées/protégées | Toutes les méthodes sont publiques |
| Constructeur autorisé | ❌ Pas de constructeur |

---

## 10. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `Abstract{Entity}` |
| **Méthodes abstraites** | DOIVENT être implémentées |
| **Méthodes finales** | NE peuvent PAS être surchargées |
| **Propriétés** | Préférer `protected` |
| **Constructeur** | Peut être défini |
| **Instanciation** | ❌ Impossible |
| **Couplage** | Peut être lié à une Interface, un Trait, ou les deux |

---

## 11. Règle d'or

> **Une classe abstraite fournit une implémentation partielle commune. Elle peut être couplée à une interface (pour un contrat commun) et/ou à un trait (pour un comportement transversal). Elle définit ce qui est commun et impose ce qui doit être implémenté.**

```php
<?php

/**
 * ✅ VARIANTE AVEC INTERFACE SPÉCIFIQUE POUR LA VALIDATION
 */

// Interface pour l'exécution
interface Executable
{
    public function execute(mixed $input): mixed;
}

// Interface pour la validation (optionnelle)
interface Validatable
{
    public function validate(mixed $input): bool;
}

// ✅ Trait HasValidation (implémentation de Validatable)
trait HasValidations
{
    protected function validate(mixed $input): bool
    {
        return !empty($input);
    }
}

// ✅ Abstract class qui implémente Executable ET Validatable
abstract class AbstractExecutor implements Executable, Validatable
{
    use HasValidations;  // ✅ Implémente Validatable via le trait
    
    protected array $config = [];
    
    abstract public function execute(mixed $input): mixed;
    
    final public function run(mixed $input): mixed
    {
        if (!$this->validate($input)) {
            throw new InvalidArgumentException('Invalid input');
        }
        
        return $this->execute($input);
    }
}
```