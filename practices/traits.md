# Principe d'usage des Traits (Version finale)

## 1. Définition

Un **Trait** est un mécanisme de réutilisation de code qui permet de regrouper des méthodes et des comportements partagés entre plusieurs classes.

```
Trait → Réutilisation horizontale → Composition → Pas d'héritage
```

```php
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

final class Post extends Model
{
    use HasLikes;
}
```

---

## 2. Problématique à laquelle les Traits répondent

| Problème | Solution |
|----------|----------|
| **Héritage unique PHP** | Les traits permettent la composition |
| **Code dupliqué** | Factorisation dans un trait |
| **Relations polymorphiques** | Traits réutilisables |
| **Comportements transversaux** | Traits partagés |

---

## 3. Règles fondamentales

### 3.1 Nommage (⚠️ RÈGLE STRICTE)

> **Les traits commencent par `Has`. L'interface associée se termine par `able`.**

| Type | Convention | Exemple |
|------|------------|---------|
| **Trait** | `Has{Entity}` | `HasLikes`, `HasComments`, `HasRatings` |
| **Interface** | `{Entity}able` | `Likeable`, `Commentable`, `Rateable` |

```php
// ✅ BON - Couple Trait + Interface
trait HasLikes { ... }      // Le trait
interface Likeable { ... }  // L'interface associée

trait HasComments { ... }
interface Commentable { ... }

trait HasRatings { ... }
interface Rateable { ... }

// ❌ MAUVAIS - Nommage incorrect
trait Likeable { ... }      // ❌ Un trait doit commencer par Has
trait HasLike { ... }       // ❌ Doit être HasLikes (pluriel)
interface Likable { ... }   // ❌ Doit être Likeable
```

### 3.2 Localisation

```
app/Traits/Has{Entity}.php
app/Contracts/{Entity}able.php
```

```
app/Traits/
├── HasLikes.php
├── HasComments.php
├── HasRatings.php
└── HasTags.php

app/Contracts/
├── Likeable.php
├── Commentable.php
├── Rateable.php
└── Taggable.php
```

---

## 4. Couplage Obligatoire Trait + Interface (⚠️ RÈGLE ABSOLUE)

> **Tout trait DOIT être accompagné d'une interface correspondante. Le trait `HasLikes` est lié à l'interface `Likeable`.**

### 4.1 Structure obligatoire

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

### 4.2 Pourquoi ce couplage ?

| Avantage | Explication |
|----------|-------------|
| **Contrat explicite** | L'interface définit ce qui doit être fait |
| **Implémentation réutilisable** | Le trait donne comment le faire |
| **Testabilité** | On peut mocker l'interface |
| **Découplage** | On peut changer l'implémentation |

---

## 5. Types de Traits

### 5.1 Traits pour relations polymorphiques

> **Un trait de relation polymorphique ne contient que la déclaration de la relation (morphMany, morphTo). Aucune logique métier.**

```php
// ✅ BON - Interface
interface Likeable
{
    public function likes(): MorphMany;
}

// ✅ BON - Trait (uniquement la relation)
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

// ❌ MAUVAIS - Trait avec logique métier
trait HasLikes
{
    public function likes(): MorphMany { ... }
    
    public function addLike(Model $liker): void  // ❌ Logique métier
    {
        Like::create([
            'likeable_id' => $this->id,
            'likeable_type' => $this->getMorphClass(),
            'liker_id' => $liker->id,
            'liker_type' => $liker->getMorphClass(),
        ]);
    }
}

// ✅ BON - Logique métier dans une Task
final class ToggleLikeTask extends AbstractTask
{
    public function __construct(
        private readonly LikeRepository $likeRepository,
    ) {}
    
    public function execute(ToggleLikeRecord $record): bool
    {
        $exists = $this->likeRepository->exists(new LikeExistsRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        
        if ($exists) {
            $this->likeRepository->delete(new LikeDeleteRecord(
                likeable: $record->likeable,
                liker: $record->liker,
            ));
            return false;
        }
        
        $this->likeRepository->create(new LikeCreateRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        return true;
    }
}
```

### 5.2 Traits pour comportements transversaux

> **Les traits de comportement transversaux suivent aussi la convention `Has{Entity}` + `{Entity}able`.**

```php
// ✅ BON - Interface + Trait pour filtrage
interface Filterable
{
    public function scopeFilter(Builder $query, array $filters): Builder;
}

trait HasFilters
{
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            
            if ($this->hasFilterMethod($key)) {
                $this->applyFilterMethod($query, $key, $value);
            } elseif ($this->isFilterableColumn($key)) {
                $query->where($key, $value);
            }
        }
        
        return $query;
    }
    
    protected function isFilterableColumn(string $column): bool
    {
        return property_exists($this, 'filterable') && in_array($column, $this->filterable);
    }
}

// Utilisation
final class User extends Model implements Filterable
{
    use HasFilters;
    
    protected array $filterable = ['name', 'email', 'status'];
}
```

---

## 6. Règle d'unicité de responsabilité

> **Un trait ne doit avoir qu'UNE SEULE responsabilité.**

```php
// ✅ BON - Un trait par responsabilité
trait HasLikes { ... }
trait HasComments { ... }
trait HasRatings { ... }

// ❌ MAUVAIS - Multiple responsabilités
trait HasLikesAndComments
{
    public function likes(): MorphMany { ... }
    public function comments(): MorphMany { ... }
}
```

---

## 7. Interaction avec le Repository

> **Un trait ne doit jamais interagir directement avec la base de données. Les opérations complexes sont déléguées aux Repositories via des Tasks.**

```php
// ❌ MAUVAIS - Trait qui interagit directement avec la DB
trait HasLikes
{
    public function addLike(Model $liker): void
    {
        Like::create([...]);  // ❌ DB directe
    }
}

// ✅ BON - Trait sans interaction DB
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
```

---

## 8. Exemples complets

### 8.1 Trait HasLikes + Interface Likeable

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

// Task pour la logique métier
final class ToggleLikeTask extends AbstractTask
{
    public function __construct(
        private readonly LikeRepository $likeRepository,
    ) {}
    
    public function execute(ToggleLikeRecord $record): bool
    {
        $exists = $this->likeRepository->exists(new LikeExistsRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        
        if ($exists) {
            $this->likeRepository->delete(new LikeDeleteRecord(
                likeable: $record->likeable,
                liker: $record->liker,
            ));
            return false;
        }
        
        $this->likeRepository->create(new LikeCreateRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        return true;
    }
}

// Action
final class TogglePostLikeAction extends AbstractAction
{
    public function __construct(
        private readonly ToggleLikeTask $toggleLike,
        private readonly PostRepository $postRepository,
    ) {}
    
    public function run(int $postId, ToggleLikeRequest $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);
        
        if (!$post) {
            return $this->json(null, 404);
        }
        
        $liked = $this->toggleLike->execute(new ToggleLikeRecord(
            likeable: $post,
            liker: auth()->user(),
        ));
        
        return $this->json(['liked' => $liked]);
    }
}
```

### 8.2 Trait HasComments + Interface Commentable

```php
// Interface
interface Commentable
{
    public function comments(): MorphMany;
}

// Trait
trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// Model
final class Post extends Model implements Commentable
{
    use HasComments;
}
```

---

## 9. Conflits de noms

> **En cas de conflit, utiliser `insteadof` pour préciser la priorité.**

```php
trait HasLikes
{
    public function count(): int
    {
        return $this->likes()->count();
    }
}

trait HasComments
{
    public function count(): int
    {
        return $this->comments()->count();
    }
}

final class Post extends Model implements Likeable, Commentable
{
    use HasLikes, HasComments {
        HasLikes::count insteadof HasComments;
        HasComments::count as commentCount;
    }
}
```

---

## 10. Tests des Traits

> **Les traits doivent être testés via une classe anonyme qui les utilise.**

```php
final class HasLikesTraitTest extends TestCase
{
    private $model;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->model = new class extends Model implements Likeable {
            use HasLikes;
            protected $table = 'posts';
        };
    }
    
    public function test_likes_returns_morphMany_relation(): void
    {
        $relation = $this->model->likes();
        
        $this->assertInstanceOf(MorphMany::class, $relation);
        $this->assertSame(Like::class, $relation->getRelated());
        $this->assertSame('likeable', $relation->getMorphType());
    }
}
```

---

## 11. Ce qu'un Trait NE peut PAS faire

| Interdiction | Pourquoi | Alternative |
|--------------|----------|-------------|
| **Logique métier** | Violation SRP | Déplacer dans Task |
| **Interaction DB directe** | Couplage dangereux | Utiliser Repository |
| **Être seul sans interface** | Pas de contrat | Ajouter l'interface associée |
| **Nommage sans Has** | Convention violée | Renommer avec `Has` |
| **Constructeur sans appel parent** | Risque de casse | Toujours appeler parent |

---

## 12. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage trait** | `Has{Entity}` (ex: `HasLikes`) |
| **Nommage interface** | `{Entity}able` (ex: `Likeable`) |
| **Couplage** | ✅ Trait + Interface obligatoire |
| **Responsabilité** | Une seule par trait |
| **Logique métier** | ❌ Interdit (déplacer dans Task) |
| **Interaction DB** | ❌ Interdit (utiliser Repository) |
| **Tests** | Via classe anonyme |
| **Conflits** | Utiliser `insteadof` |

---

## 13. Règle d'or

> **Un trait commence par `Has`, son interface associée se termine par `able`. Ils sont inséparables. Le trait ne contient PAS de logique métier, PAS d'interaction directe avec la base de données. La logique métier est déplacée dans des Tasks.**

```php
// Interface (contrat)
interface Likeable
{
    public function likes(): MorphMany;
}

// Trait (implémentation)
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

// Task (logique métier)
final class ToggleLikeTask extends AbstractTask
{
    public function execute(ToggleLikeRecord $record): bool
    {
        // Logique métier ici
        return $this->likeRepository->toggle($record);
    }
}

// Action
final class TogglePostLikeAction extends AbstractAction
{
    public function run(int $postId, ToggleLikeRequest $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);
        $liked = $this->toggleLikeTask->execute(new ToggleLikeRecord(
            likeable: $post,
            liker: auth()->user(),
        ));
        
        return $this->json(['liked' => $liked]);
    }
}
```