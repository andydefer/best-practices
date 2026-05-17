# Principe d'usage des Interfaces (Version finale)

## 1. Définition

Une **Interface** est un contrat qui définit les méthodes qu'une classe doit implémenter. Elle ne contient aucune implémentation, seulement des signatures de méthodes.

```
Interface → Contrat pur → Pas d'implémentation → Multiples implémentations
```

```php
interface RepositoryInterface
{
    public function find(int $id): ?Model;
    public function create(Recordable $record): Model;
    public function update(int $id, Recordable $record): Model;
    public function delete(int $id): bool;
}
```

---

## 2. Problématique à laquelle les Interfaces répondent

| Problème | Solution |
|----------|----------|
| **Couplage fort** | L'interface découple l'implémentation |
| **Tests complexes** | On peut mocker l'interface facilement |
| **Multiples implémentations** | L'interface permet le polymorphisme |
| **Contrat explicite** | L'interface documente l'API |

```php
// ❌ MAUVAIS - Dépendance concrète
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,  // ❌ Dépendance concrète
    ) {}
}

// ✅ BON - Dépendance abstraite
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,  // ✅ Dépendance abstraite
    ) {}
}
```

---

## 3. Règles fondamentales

### 3.1 Nommage (⚠️ RÈGLE STRICTE)

> **Les interfaces se terminent par `able`. Les traits commencent par `Has`.**

| Type | Convention | Exemple |
|------|------------|---------|
| **Interface** | `{Entity}able` | `Likeable`, `Commentable`, `Rateable` |
| **Trait associé** | `Has{Entity}` | `HasLikes`, `HasComments`, `HasRatings` |

```php
// ✅ BON - Couple Interface + Trait
interface Likeable { ... }     // L'interface
trait HasLikes { ... }         // Le trait associé

interface Commentable { ... }
trait HasComments { ... }

interface Rateable { ... }
trait HasRatings { ... }

// ❌ MAUVAIS - Nommage incorrect
interface ILikeable { ... }     // ❌ Préfixe I
interface HasLikes { ... }      // ❌ Un trait commence par Has, pas une interface
interface LikeableInterface { ... } // ❌ Redondant
```

### 3.2 Localisation

```
app/Contracts/{Entity}able.php
app/Traits/Has{Entity}.php
```

```
app/Contracts/
├── Likeable.php
├── Commentable.php
├── Rateable.php
└── Taggable.php

app/Traits/
├── HasLikes.php
├── HasComments.php
├── HasRatings.php
└── HasTags.php
```

---

## 4. Méthodes d'interface

> **Toutes les méthodes d'une interface sont implicitement abstraites et publiques.**

```php
// ✅ BON - Interface claire
interface Likeable
{
    public function likes(): MorphMany;
}

interface Commentable
{
    public function comments(): MorphMany;
}

interface Rateable
{
    public function ratings(): MorphMany;
    public function averageRating(): float;
}

// ❌ MAUVAIS - Méthode avec implémentation (impossible)
interface Notifiable
{
    public function notify(): void
    {
        // ❌ Pas d'implémentation dans une interface
    }
}
```

---

## 5. Héritage d'interfaces

> **Une interface peut hériter d'autres interfaces.**

```php
// ✅ BON - Héritage d'interfaces
interface ReadRepositoryInterface
{
    public function find(int $id): ?Model;
    public function paginate(PaginateRecord $record): LengthAwarePaginator;
}

interface WriteRepositoryInterface
{
    public function create(Recordable $record): Model;
    public function update(int $id, Recordable $record): Model;
    public function delete(int $id): bool;
}

interface RepositoryInterface extends ReadRepositoryInterface, WriteRepositoryInterface
{
    // Combine les deux interfaces
}
```

---

## 6. Interface vs Abstract Class

| Interface | Abstract Class |
|-----------|----------------|
| ❌ Pas d'implémentation | ✅ Peut contenir de l'implémentation |
| ❌ Pas de propriétés | ✅ Peut avoir des propriétés |
| ✅ Multiples implémentations | ❌ Héritage simple |
| ✅ Multiples (une classe peut implémenter plusieurs) | ❌ Une seule héritée |
| Se termine par `able` | Commence par `Abstract` |

---

## 7. Couplage Obligatoire Interface + Trait (⚠️ RÈGLE ABSOLUE)

> **Toute interface DOIT être accompagnée d'un trait correspondant. L'interface `Likeable` est liée au trait `HasLikes`.**

### 7.1 Structure obligatoire

```php
// ✅ BON - Interface (contrat)
interface Likeable
{
    public function likes(): MorphMany;
}

// ✅ BON - Trait (implémentation)
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

// ✅ BON - Utilisation
final class Post extends Model implements Likeable
{
    use HasLikes;
}
```

### 7.2 Pourquoi ce couplage ?

| Avantage | Explication |
|----------|-------------|
| **Contrat explicite** | L'interface définit ce qui doit être fait |
| **Implémentation réutilisable** | Le trait donne comment le faire |
| **Testabilité** | On peut mocker l'interface |
| **Découplage** | On peut changer l'implémentation |

### 7.3 Interface sans trait ? (Rare)

> **Dans de rares cas, une interface peut exister sans trait (ex: RepositoryInterface) où l'implémentation est laissée entièrement aux classes filles.**

```php
// ✅ BON - Interface sans trait (implémentation spécifique)
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function create(UserCreateRecord $record): User;
}

// Implémentation spécifique (pas de trait générique)
final class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }
    
    public function create(UserCreateRecord $record): User
    {
        return DB::transaction(fn() => User::create($record->toArray()));
    }
}
```

### 7.4 Tableau de décision

| Situation | Interface | Trait |
|-----------|-----------|-------|
| Comportement transversal réutilisable (relation polymorphique) | ✅ `Likeable` | ✅ `HasLikes` |
| Contrat sans implémentation commune (Repository) | ✅ `UserRepositoryInterface` | ❌ Pas de trait |
| Comportement avec implémentation par défaut | ✅ `Notifiable` | ✅ `NotifiableTrait` |

---

## 8. Exemples complets

### 8.1 Interface + Trait pour relation polymorphique

```php
// Interface
interface Likeable
{
    public function likes(): MorphMany;
}

// Trait
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

// Model
final class Post extends Model implements Likeable
{
    use HasLikes;
}
```

### 8.2 Interface seule pour Repository

```php
// Interface
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function create(UserCreateRecord $record): User;
}

// Implémentation
final class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }
    
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
    
    public function create(UserCreateRecord $record): User
    {
        return DB::transaction(fn() => User::create($record->toArray()));
    }
}

// Utilisation
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}
}
```

### 8.3 Interface + Trait + Abstract Class

```php
// Interface
interface ActionInterface
{
    public function run(...$parameters): mixed;
}

// Trait
trait SendsHttpResponses
{
    public function json($data, int $code = 200): JsonResponse
    {
        return response()->json($data, $code);
    }
}

// Abstract Class
abstract class AbstractAction implements ActionInterface
{
    use SendsHttpResponses;
    
    abstract public function run(...$parameters): mixed;
}

// Implementation
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        return $this->json($userData);
    }
}
```

---

## 9. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Entity}able` (ex: `Likeable`) |
| **Trait associé** | `Has{Entity}` (ex: `HasLikes`) |
| **Méthodes** | Uniquement signatures (pas d'implémentation) |
| **Propriétés** | ❌ Interdites |
| **Constructeur** | ❌ Interdit |
| **Implémentation** | Une classe peut implémenter plusieurs interfaces |
| **Couplage** | Peut être liée à un trait (recommandé) ou seule (cas spécifiques) |

---

## 10. Règle d'or

> **Une interface est un contrat pur. Elle définit CE QUI doit être fait, pas COMMENT. Pour les comportements transversaux réutilisables (relations polymorphiques), l'interface DOIT être accompagnée d'un trait qui commence par `Has`. Pour les contrats sans implémentation commune (Repository), l'interface peut exister seule.**

```php
// Cas 1: Interface avec trait (relation polymorphique)
interface Likeable
{
    public function likes(): MorphMany;
}

trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

final class Post implements Likeable
{
    use HasLikes;
}

// Cas 2: Interface seule (Repository)
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function create(UserCreateRecord $record): User;
}

final class EloquentUserRepository implements UserRepositoryInterface
{
    // Implémentation spécifique
}
```