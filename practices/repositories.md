# Principe d'usage des Repositories (Version finale)

## 1. Définition

Un **Repository** est un composant qui encapsule toutes les opérations d'accès aux données pour une entité donnée. Il sert d'interface unique entre l'application et la couche de persistence.

```
Repository → Accès aux données → Une seule entité → Cache les détails d'implémentation
```

```php
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}
```

---

## 2. Problématique à laquelle les Repositories répondent

| Problème | Sans Repository | Avec Repository |
|----------|-----------------|-----------------|
| **Logique DB dispersée** | `User::find()` partout dans les Services | Toute la logique DB est centralisée |
| **Tests complexes** | Mocker Eloquent est difficile | On mock le Repository (facile) |
| **Couplage à Laravel** | Impossible de changer de persistence | Un seul Repository à modifier |
| **Duplication de requêtes** | La même requête est écrite à 10 endroits | Une seule méthode dans le Repository |
| **Responsabilité floue** | Qui doit faire la requête ? | Le Repository est la réponse |

### 2.1 Pourquoi ne pas utiliser directement les Models ?

| Problème | Solution |
|----------|----------|
| `User::find($id)` dans un Service est difficile à mocker | Injecter `UserRepository` et mocker ses méthodes |
| Les Models Eloquent sont lourds (comportements, events) | Le Repository retourne des Models simples |
| Changer de base de données ou d'ORM devient impossible | Le Repository est la seule couche modifiée |
| Tester une méthode qui appelle `User::where()->get()` est complexe | Le Repository retourne des Collections mockables |

```php
// ❌ MAUVAIS - Appel direct au Model dans un Service
final class UserService
{
    public function getUser(int $id): ?User
    {
        return User::find($id);  // ❌ Difficile à mocker
    }
}

// ✅ BON - Passage par Repository
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function getUser(int $id): ?User
    {
        return $this->userRepository->find($id);  // ✅ Facile à mocker
    }
}
```

---

## 3. Classe abstraite `AbstractRepository`

> **Tous les Repositories DOIVENT étendre `AbstractRepository` pour bénéficier des méthodes essentielles.**

### 3.1 Ce qu'offre `AbstractRepository`

| Méthode | Description |
|---------|-------------|
| `find(int $id): ?Model` | Trouver un enregistrement par son ID |
| `findBy(FindByRecord $record): Collection` | Trouver plusieurs enregistrements avec filtres |
| `paginate(PaginateRecord $record): LengthAwarePaginator` | Paginer les résultats |
| `create(Recordable $record): Model` | Créer un enregistrement (via Record) |
| `update(int $id, Recordable $record): Model` | Mettre à jour un enregistrement (via Record) |
| `delete(int $id): bool` | Supprimer un enregistrement |
| `count(?Recordable $criteria = null): int` | Compter les enregistrements |
| `exists(Recordable $criteria): bool` | Vérifier l'existence |

### 3.2 Implémentation concrète

```php
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}

// Utilisation
$user = $userRepository->find(1);
$users = $userRepository->findBy(new FindByRecord(limit: 10));
$paginated = $userRepository->paginate(new PaginateRecord(perPage: 15));
$newUser = $userRepository->create(new UserRecord(name: 'John', email: 'john@example.com'));
$updated = $userRepository->update(1, new UserRecord(name: 'Jane'));
$userRepository->delete(1);
```

---

## 4. La Record unique pour Create et Update

> **Chaque Model a UNE SEULE Record qui définit tous ses champs `fillable` (tous optionnels pour l'update). Cette Record est utilisée à la fois pour la création et la mise à jour.**

```php
// La Record unique pour User
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?UserRole $role = null,
        public readonly ?UserStatus $status = null,
    ) {}
}

// Création - tous les champs nécessaires
$user = $userRepository->create(new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    password: 'secret',
    role: UserRole::USER,
));

// Mise à jour - seuls les champs modifiés sont fournis
$user = $userRepository->update(1, new UserRecord(
    name: 'Jane Doe',  // seul le nom change
));
```

**Avantages :**
- ✅ Une seule Record par Model, pas de duplication
- ✅ Pas de `UpdateNameUserRecord`, `UpdateStatusUserRecord`, etc.
- ✅ Cohérence garantie dans toute l'application

---

## 5. Méthodes personnalisées : find et check

> **Un Repository n'autorise que 2 types d'opérations personnalisées avec des prefixes stricts : `find` (lecture) et `check` (vérification).**

### 5.1 Règle fondamentale

**Les méthodes personnalisées (`find` et `check`) sont réservées aux cas complexes où vous avez besoin d'interagir avec d'autres Models ou relations.**

Pour les cas simples (recherche par email, par rôle, vérification d'existence), utilisez les méthodes héritées d'`AbstractRepository` (`findBy`, `exists`, `count`).

```php
// ✅ BON - Cas simple : utilisation de findBy()
$users = $userRepository->findBy(new FindByRecord(
    filters: new UserFiltersRecord(email: 'john@example.com'),
    limit: 1,
));

// ✅ BON - Cas simple : utilisation de exists()
$exists = $userRepository->exists(new UserFiltersRecord(
    id: 1,
    isActive: true,
));

// ✅ BON - Cas complexe : méthode personnalisée avec relation
public function findUserWithRecentPosts(int $userId): ?User
{
    return $this->newModel()
        ->with(['posts' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(5);
        }])
        ->find($userId);
}

// ✅ BON - Cas complexe : méthode personnalisée avec autre Model
public function findUsersByProduct(Product $product): Collection
{
    return $this->newModel()
        ->whereHas('orders', function ($query) use ($product) {
            $query->whereHas('items', function ($q) use ($product) {
                $q->where('product_id', $product->id);
            });
        })
        ->get();
}

// ✅ BON - Cas complexe : vérification impliquant une autre entité
public function checkUserHasOrderedProduct(int $userId, int $productId): bool
{
    return $this->newModel()
        ->where('id', $userId)
        ->whereHas('orders', function ($query) use ($productId) {
            $query->whereHas('items', fn($q) => $q->where('product_id', $productId));
        })
        ->exists();
}

// ❌ MAUVAIS - Méthode inutile (findBy peut le faire)
public function findUserByEmail(string $email): ?User  // ❌
{
    return $this->newModel()->where('email', $email)->first();
}

// ❌ MAUVAIS - Méthode inutile (exists peut le faire)
public function checkUserIsActive(int $userId): bool  // ❌
{
    return $this->newModel()->where('id', $userId)->where('is_active', true)->exists();
}
```

### 5.2 Quand créer une méthode personnalisée ?

| Situation | Solution | Exemple |
|-----------|----------|---------|
| Recherche simple (email, rôle, status, id) | Utiliser `findBy()` | `$repo->findBy(new FindByRecord(filters: new UserFiltersRecord(email: 'test@test.com')))` |
| Vérification simple (existence, statut) | Utiliser `exists()` | `$repo->exists(new UserFiltersRecord(isActive: true))` |
| Comptage simple | Utiliser `count()` | `$repo->count(new UserFiltersRecord(role: UserRole::ADMIN))` |
| Recherche avec relations (with, whereHas) | Créer méthode `find` personnalisée | `findUserWithRecentPosts()` |
| Recherche impliquant d'autres Models | Créer méthode `find` personnalisée | `findUsersByProduct(Product $product)` |
| Vérification impliquant d'autres Models | Créer méthode `check` personnalisée | `checkUserHasOrderedProduct()` |

### 5.3 Convention de nommage

```php
// Lecture (find)
public function findUserWithRecentPosts(int $userId): ?User
public function findUsersByProduct(Product $product): Collection
public function findActiveUsersWithOrders(): Collection

// Vérification (check) - UNIQUEMENT si implique d'autres Models
public function checkUserHasOrderedProduct(int $userId, int $productId): bool
// PAS de checkUserIsActive (exists suffit)
```

### 5.4 Ce qu'AbstractRepository offre déjà

| Méthode | Usage |
|---------|-------|
| `findBy(FindByRecord $record)` | Recherche avec filtres, limite et tri |
| `count(?Recordable $criteria)` | Comptage avec filtres |
| `exists(Recordable $criteria)` | Vérification d'existence avec filtres |

```php
// ✅ BON - Pas besoin de méthode supplémentaire
$user = $userRepository->findBy(new FindByRecord(
    filters: new UserFiltersRecord(email: 'john@example.com'),
    limit: 1,
));

// ✅ BON - Pas besoin de méthode supplémentaire
$exists = $userRepository->exists(new UserFiltersRecord(email: 'john@example.com'));

// ✅ BON - Pas besoin de méthode supplémentaire
$count = $userRepository->count(new UserFiltersRecord(role: UserRole::ADMIN));
```

---

## 6. Transaction et atomicité (⚠️ RÈGLE D'OR)

> **Toute méthode qui modifie l'état de la base de données (create, update, delete) DOIT être exécutée dans une transaction.**

```php
// ✅ BON - Transaction dans AbstractRepository
public function create(Recordable $record): Model
{
    return DB::transaction(fn() => $this->newModel()->create($record->toArray()));
}

// ❌ MAUVAIS - Pas de transaction
public function create(Recordable $record): Model
{
    return $this->newModel()->create($record->toArray());
}
```

### 6.1 Pas de retry ou de catch silencieux

```php
// ❌ MAUVAIS - Catch silencieux
public function create(Recordable $record): ?User
{
    try {
        return DB::transaction(fn() => User::create([...]));
    } catch (\Exception $e) {
        return null;  // ❌ Silence dangereux
    }
}

// ✅ BON - L'exception remonte
public function create(Recordable $record): User
{
    return DB::transaction(fn() => User::create([...]));
}
```

---

## 7. Éviter les magic numbers

> **Les valeurs "magiques" (nombres, chaînes, etc.) doivent venir de l'extérieur via Record ou Enum.**

```php
// ❌ MAUVAIS - Magic number
public function findUserWithRecentPosts(int $userId): ?User
{
    return $this->newModel()
        ->with(['posts' => fn($q) => $q->limit(5)])  // ❌ 5 est un magic number
        ->find($userId);
}

// ✅ BON - La valeur vient du Record
final class FindUserWithRecentPostsRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $userId,
        public readonly int $limit = 5,
    ) {}
}

public function findUserWithRecentPosts(FindUserWithRecentPostsRecord $record): ?User
{
    return $this->newModel()
        ->with(['posts' => fn($q) => $q->limit($record->limit)])
        ->find($record->userId);
}
```

---

## 8. Gestion des relations et opérations multi-modèles (⚠️ RÈGLE IMPORTANTE)

> **Pour les opérations impliquant plusieurs Models (création, mise à jour, suppression) ou des relations complexes, utilisez une TASK qui encapsule cette logique. Un Repository ne peut pas écrire dans un Model qui n'est pas lié à lui.**

### 8.1 Problématique

Un Repository est responsable d'UNE SEULE entité. Il ne doit pas :
- Écrire dans d'autres Models
- Orchestrer des opérations multi-tables

```php
// ❌ MAUVAIS - UserRepository écrit dans OrderRepository
final class UserRepository extends AbstractRepository
{
    public function createUserWithOrder(UserRecord $userRecord, OrderRecord $orderRecord): User
    {
        return DB::transaction(function () use ($userRecord, $orderRecord) {
            $user = $this->create($userRecord);
            
            // ❌ UserRepository écrit dans Order (violation SRP)
            $this->orderRepository->create($orderRecord);
            
            return $user;
        });
    }
}
```

### 8.2 La solution : Une Task dédiée

```php
// ✅ BON - Task qui encapsule la logique multi-modèles
final class CreateUserWithOrderTask extends AbstractTask
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
    ) {}
    
    public function execute(CreateUserWithOrderRecord $record): User
    {
        return DB::transaction(function () use ($record) {
            $user = $this->userRepository->create($record->user);
            
            $this->orderRepository->create(new OrderRecord(
                userId: $user->id,
                total: $record->order->total,
                items: $record->order->items,
            ));
            
            return $user;
        });
    }
}
```

### 8.3 Hiérarchie des responsabilités

| Composant | Responsabilité | Gestion des relations |
|-----------|----------------|----------------------|
| **Repository** | Une seule entité (CRUD de base) | ❌ Aucune |
| **Task** | Actions unitaires (peut utiliser plusieurs Repositories) | ✅ Orchestration multi-modèles |
| **Worker** | Orchestration de plusieurs Tasks | ✅ Workflows complexes |
| **Service** | Logique métier pure | ❌ (délègue aux Tasks) |

### 8.4 Récapitulatif des règles

| Situation | Solution |
|-----------|----------|
| Création d'une seule entité | `Repository::create()` |
| Création de plusieurs entités liées | `Task` dédiée |
| Mise à jour avec relations | `Task` dédiée |
| Suppression en cascade | `Task` dédiée |
| Logique métier avec plusieurs entités | `Service` (qui appelle des Tasks) |

---

## 9. Ce qu'un Repository NE peut PAS faire

| Interdiction | Pourquoi | Alternative |
|--------------|----------|-------------|
| **Logique métier** | Violation SRP | Déplacer dans Service |
| **Propriétés statiques** | Les données doivent venir de l'extérieur | Passer en paramètre ou Record |
| **Paramètres optionnels** | L'optionnel cache l'intention | Utiliser Record |
| **Méthodes `createWith` ou `createAnd`** | Violation SRP | Créer une Task |
| **Retour bool pour update** | Ne sait pas ce qui a échoué | Retourner Model ou exception |
| **Paramètre `array $with`** | Implicite, non typé | Utiliser Record explicite |
| **Magic numbers / strings** | Cache l'intention | Utiliser Record ou Enum |
| **Plus d'un paramètre** | Violation de la convention | Utiliser Record |
| **Méthodes triviales (`findUserByEmail`)** | `findBy` peut le faire | Utiliser `findBy()` |
| **Méthodes triviales (`checkUserIsActive`)** | `exists` peut le faire | Utiliser `exists()` |
| **Écrire dans un autre Model** | Violation SRP | Créer une Task |

---

## 10. Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Models\Product;
use App\Records\UserRecord;
use App\Records\UserFiltersRecord;
use App\Records\FindUserWithRecentPostsRecord;
use App\Enums\UserRole;
use Illuminate\Support\Collection;
use AndyDefer\BestPractices\Repositories\AbstractRepository;
use AndyDefer\BestPractices\Records\Repositories\FindByRecord;

final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
    
    // ========== Cas simples : utilisation de findBy() / exists() ==========
    
    // $user = $userRepository->findBy(new FindByRecord(
    //     filters: new UserFiltersRecord(email: 'john@example.com'),
    //     limit: 1,
    // ));
    
    // $exists = $userRepository->exists(new UserFiltersRecord(isActive: true));
    
    // $count = $userRepository->count(new UserFiltersRecord(role: UserRole::ADMIN));
    
    // ========== Cas complexes : méthodes personnalisées ==========
    
    public function findUserWithRecentPosts(FindUserWithRecentPostsRecord $record): ?User
    {
        return $this->newModel()
            ->with(['posts' => function ($query) use ($record) {
                $query->orderBy('created_at', 'desc')->limit($record->limit);
            }])
            ->find($record->userId);
    }
    
    public function findUsersByProduct(Product $product): Collection
    {
        return $this->newModel()
            ->whereHas('orders', function ($query) use ($product) {
                $query->whereHas('items', function ($q) use ($product) {
                    $q->where('product_id', $product->id);
                });
            })
            ->get();
    }
    
    public function findActiveUsersWithOrders(): Collection
    {
        return $this->newModel()
            ->where('is_active', true)
            ->whereHas('orders', fn($q) => $q->where('status', 'completed'))
            ->get();
    }
    
    // ========== Méthodes de vérification complexes ==========
    
    public function checkUserHasOrderedProduct(int $userId, int $productId): bool
    {
        return $this->newModel()
            ->where('id', $userId)
            ->whereHas('orders', function ($query) use ($productId) {
                $query->whereHas('items', fn($q) => $q->where('product_id', $productId));
            })
            ->exists();
    }
}
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | DOIT étendre `AbstractRepository` |
| **Nommage** | `{Entity}Repository` |
| **Record unique** | Une seule Record par Model pour create/update |
| **Cas simples** | Utiliser `findBy()`, `count()`, `exists()` hérités |
| **Méthodes personnalisées** | Uniquement pour cas complexes (relations, autres Models) |
| **Prefixes autorisés** | `find` (lecture) ou `check` (vérification) |
| **Transaction** | Toute méthode qui modifie la DB DOIT être dans une transaction |
| **Multi-modèles** | Utiliser une Task, jamais le Repository |
| **Magic numbers** | ❌ Interdit (utiliser Record ou Enum) |
| **`array $with`** | ❌ Interdit (utiliser Record explicite) |
| **`createWith` / `createAnd`** | ❌ Interdit (créer une Task) |
| **Logique métier** | ❌ Interdit (déplacer dans Service) |
| **Méthodes triviales (`findUserByEmail`)** | ❌ Interdit (`findBy` fait le travail) |
| **Méthodes triviales (`checkUserIsActive`)** | ❌ Interdit (`exists` fait le travail) |

---

## 12. Règle d'or

> **Un Repository étend `AbstractRepository` et utilise une seule Record par Model pour create/update. Pour les cas simples (recherche par email, par rôle, vérification d'existence), utilisez les méthodes héritées (`findBy`, `exists`, `count`). Créez des méthodes personnalisées (`find`, `check`) uniquement pour les cas complexes impliquant des relations ou d'autres Models. Pour les opérations multi-modèles, utilisez une TASK.**

```php
// Le Repository parfait
final class PerfectRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return PerfectModel::class;
    }
    
    // Cas simple : findBy / exists suffisent
    // $result = $repository->findBy(new FindByRecord(filters: new PerfectFiltersRecord(email: 'test@test.com')));
    // $exists = $repository->exists(new PerfectFiltersRecord(isActive: true));
    
    // Cas complexe : méthode personnalisée avec relation
    public function findPerfectModelWithRelations(int $id): ?PerfectModel
    {
        return $this->newModel()->with(['children', 'parent'])->find($id);
    }
    
    // Cas complexe : vérification impliquant une autre entité
    public function checkPerfectModelHasChildren(int $id): bool
    {
        return $this->newModel()->where('id', $id)->whereHas('children')->exists();
    }
}

// La Task parfaite pour les opérations multi-modèles
final class PerfectTask extends AbstractTask
{
    public function __construct(
        private readonly PerfectRepository $perfectRepository,
        private readonly AnotherRepository $anotherRepository,
    ) {}
    
    public function execute(PerfectRecord $record): PerfectModel
    {
        return DB::transaction(function () use ($record) {
            $perfect = $this->perfectRepository->create($record->perfect);
            $this->anotherRepository->create($record->another);
            return $perfect;
        });
    }
}
```