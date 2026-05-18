# Principe d'usage des Records (Version finale)

## 1. Définition

Un **Record** est une structure de données typée, utilisée pour la communication **interne** entre les couches de l'application (Services, Repositories, Tasks, Workers).

```
Record → Remplace les tableaux bruts par des structures typées et immutables
```

### 1.1 Pourquoi les Records ?

| Problème | Solution |
|----------|----------|
| `array $credentials` → On ne sait pas ce qu'il contient | `UserCredentialsRecord $record` → Structure claire et typée |
| Pas de typage sur les clés | Propriétés typées |
| Documentation implicite | Documentation explicite dans le constructeur |
| Refactoring dangereux | Le compilateur guide les modifications |

```php
// ❌ MAUVAIS - On ne sait pas ce qu'il y a dans le tableau
function updateField(array $credentials): void { ... }

// ✅ BON - On sait exactement ce qu'on reçoit
function updateField(UserCredentialsRecord $credentials): void { ... }
```

### 1.2 La philosophie des Records

> **Un Record est un sac de données typé, sans aucune logique. Il remplace les tableaux bruts pour rendre le code plus sûr et plus lisible.**

---

## 2. Séparation des responsabilités (⚠️ IMPORTANT)

> **Un Record est STRICTEMENT réservé à l'usage interne. Il ne peut en aucun cas être retourné comme réponse HTTP.**

| Composant | Usage | Réponse API |
|-----------|-------|-------------|
| **Record** | Communication interne (Services, Repositories) | ❌ **INTERDIT** |
| **Data** | Réponse API (Actions) | ✅ **OBLIGATOIRE** |

```php
// ❌ MAUVAIS - Un Record ne répond pas à une API
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $record = $this->userService->getUser($userId);
        return $this->json($record);  // ← INTERDIT !
    }
}

// ✅ BON - L'Action transforme le Record en Data
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $record = $this->userService->getUser($userId);  // ← Record (interne)
        $data = UserData::fromRecord($record);           // ← Data (API)
        return $this->json($data);                       // ✅ Autorisé
    }
}
```

**Règle d'or :** Si tu es dans une Action et que tu veux retourner une réponse HTTP, tu utilises une **Data**, jamais un Record. Le Record s'arrête à la porte de l'Action.

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Description}Record
```

| Record | Utilisation |
|--------|-------------|
| `UserContextRecord` | Contexte utilisateur |
| `PaymentResultRecord` | Résultat de traitement de paiement |
| `DashboardFilterRecord` | Filtres pour un tableau de bord |

### 3.2 Localisation

```
app/Records/{Description}Record.php
```

```
app/Records/
├── UserContextRecord.php
├── PaymentResultRecord.php
├── DashboardFilterRecord.php
└── AppointmentFilterRecord.php
```

### 3.3 Héritage

> **Tous les Records doivent étendre `AbstractRecord`**

```php
final class UserContextRecord extends AbstractRecord
{
    // ...
}
```

---

## 4. Types autorisés dans un Record (⚠️ RÈGLE STRICTISSIME)

> **Un Record ne peut contenir que des types spécifiques. Les tableaux bruts (`array`) sont STRICTEMENT INTERDITS.**

### 4.1 Types autorisés

| Type | Exemple | Notes |
|------|---------|-------|
| `int` | `public readonly int $id` | Scalaire |
| `string` | `public readonly string $name` | Scalaire |
| `float` | `public readonly float $price` | Scalaire |
| `bool` | `public readonly bool $isActive` | Scalaire |
| `null` | `public readonly ?string $value` | Nullable |
| `Enum` (Backed de préférence) | `public readonly UserRole $role` | Backed enum recommandé |
| `Record` (autre Record) | `public readonly AddressRecord $address` | Record imbriqué |
| `TypedRecords` | `public readonly TypedRecords $items` | Collection typée |

### 4.2 Pourquoi les tableaux bruts sont INTERDITS

```php
// ❌ MAUVAIS - Tableau brut non typé
public readonly array $items;           // INTERDIT
public readonly array $tags;            // INTERDIT
public readonly array $users;           // INTERDIT

// ✅ BON - Utilisation de TypedRecords
public readonly TypedRecords $items;    // TypedRecords<ItemRecord>
public readonly TypedRecords $tags;     // TypedRecords<string>
public readonly TypedRecords $users;    // TypedRecords<UserRecord>
```

| Problème des tableaux bruts | Solution avec `TypedRecords` |
|----------------------------|------------------------------|
| On ne sait pas ce qu'il contient | Le type est explicite (`TypedRecords<ItemRecord>`) |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |

### 4.3 Les Énums : préférez les Backed Enums

```php
// ⚠️ Acceptable mais moins pratique (pure enum)
enum UserStatus
{
    case ACTIVE;
    case INACTIVE;
}

// ✅ Recommandé (backed enum)
enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case GUEST = 'guest';
}
```

**Pourquoi préférer les Backed Enums ?**
- Sérialisation automatique vers une valeur scalaire (`string` ou `int`)
- Compatible avec les bases de données
- Plus facile à manipuler dans les conditions

### 4.4 À ne PAS mettre dans un Record

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| `array` brut (non typé) | On ne sait pas ce qu'il contient | `TypedRecords` |
| `Model` (Eloquent) | Contient de la logique et des relations | `UserRecord`, `DoctorRecord` |
| `Data` (DTO API) | Destiné à la couche API uniquement | `UserRecord` |
| `Collection` | Structure non typée | `TypedRecords` |
| `Carbon` / `DateTime` | Contient de la logique et des comportements | `string` ISO 8601 |
| `mixed` | Pas de typage | Type explicite |
| `object` | Pas de typage | Type explicite |

```php
// ❌ MAUVAIS - Types interdits
final class BadRecord extends AbstractRecord
{
    public function __construct(
        public array $items,                    // ❌ array brut
        public User $user,                      // ❌ Model
        public UserData $userData,              // ❌ Data
        public Collection $users,               // ❌ Collection
        public Carbon $createdAt,               // ❌ Carbon
        public mixed $value,                    // ❌ mixed
        public object $anything,                // ❌ object
    ) {}
}

// ✅ BON - Types autorisés
final class GoodRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedRecords $items,    // ✅ TypedRecords<ItemRecord>
        public readonly int $userId,            // ✅ int
        public readonly string $createdAt,      // ✅ string ISO
        public readonly UserRole $role,         // ✅ Backed enum
    ) {}
}
```

### 4.5 Record optionnel avec `EmptyRecord`

> **Pour les cas où un Record peut être optionnel (ex: filtres de recherche), utilisez `EmptyRecord` plutôt que `null`.**

```php
use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly Recordable $filters = new EmptyRecord(),
        public readonly ?int $limit = 100,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
    ) {}
}
```

**Code du `EmptyRecord` :**

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

/**
 * Empty record for optional filter parameters.
 *
 * This record is used when a Service or Repository needs to accept a Record
 * parameter but no actual data is required. It provides a type-safe way to
 * handle optional filtering without using null or empty arrays.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Records
 */
final class EmptyRecord extends AbstractRecord
{
    public function __construct() {}
}
```

**Pourquoi `EmptyRecord` plutôt que `null` ?**

| Avec `null` | Avec `EmptyRecord` |
|-------------|---------------------|
| `$filters?->toArray() ?? []` | `$filters->toArray()` |
| Condition ternaire partout | Pas de condition |
| Risque d'oubli de `?` | Type-safe garanti |
| `?Recordable` est ambigu | Le Record est toujours présent |

```php
// ✅ Avec EmptyRecord - Pas de condition spéciale
$filtersArray = $record->filters->toArray(); // Retourne [] si EmptyRecord

// ❌ Avec null - Il faut gérer le cas null
$filtersArray = $record->filters?->toArray() ?? [];
```

`EmptyRecord` est une implémentation concrète d'`AbstractRecord` qui ne contient aucune propriété. Elle garantit que l'appel à `toArray()` retourne toujours un tableau vide `[]`.

---

## 5. TypedRecords : La collection typée (⚠️ RÈGLE ABSOLUE)

> **Pour remplacer les tableaux bruts, nous utilisons `TypedRecords`. C'est une collection type-safe qui garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.**

### 5.1 Définition

**TypedRecords** est une collection type-safe qui remplace les tableaux bruts dans les Records. Elle garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.

```php
use AndyDefer\BestPractices\Collections\TypedRecords;

// ✅ BON - Collection typée
$tags = new TypedRecords('string');
$tags->add('developer', 'laravel', 'php');

// ❌ MAUVAIS - Tableau brut non typé (INTERDIT dans les Records)
public array $tags = [];
```

### 5.2 Pourquoi remplacer les tableaux par TypedRecords ?

| Problème des tableaux | Solution avec TypedRecords |
|-----------------------|---------------------------|
| On ne sait pas ce qu'ils contiennent | Le type est explicite (`TypedRecords<string>`) |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |
| Pas de méthodes utilitaires | Nombreuses méthodes de manipulation |

### 5.3 Types supportés

| Type | Description | Exemple |
|------|-------------|---------|
| `'int'` | Entier | `new TypedRecords('int')` |
| `'string'` | Chaîne de caractères | `new TypedRecords('string')` |
| `'float'` | Nombre à virgule flottante | `new TypedRecords('float')` |
| `'bool'` | Booléen | `new TypedRecords('bool')` |
| `'null'` | Valeur nulle | `new TypedRecords('string', 'null')` |
| `Record::class` | Classe Record | `new TypedRecords(UserRecord::class)` |
| `TypedRecords::class` | Collection imbriquée | `new TypedRecords(TypedRecords::class)` |

### 5.4 Types multiples

```php
// Collection acceptant plusieurs types scalaires
$mixed = new TypedRecords('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection acceptant Records et scalaires
$items = new TypedRecords(ProductRecord::class, 'string');
$items->add(new ProductRecord(name: 'Laptop'), 'Just a description');
```

### 5.5 Création d'une collection

```php
// Via le constructeur
$tags = new TypedRecords('string');
$tags->add('developer', 'laravel', 'php');

// Via le helper typed_records() (usage ponctuel recommandé)
$tags = typed_records('string');
$tags->add('developer', 'laravel', 'php');

// Avec plusieurs types
$mixed = typed_records('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection de Records
$products = typed_records(ProductRecord::class);
$products->add(new ProductRecord(name: 'Laptop', price: 999));

// Collection de collections (nested)
$nested = typed_records(TypedRecords::class);
$nested->add($tags, $ids);
```

### 5.6 Méthodes de base

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `add(...$items)` | Ajoute un ou plusieurs éléments | `$tags->add('developer', 'laravel')` |
| `concat(array $items)` | Ajoute plusieurs éléments depuis un tableau | `$tags->concat(['a', 'b', 'c'])` |
| `all(): array` | Retourne tous les éléments | `$tags->all()` |
| `count(): int` | Nombre d'éléments | `$tags->count()` |
| `isEmpty(): bool` | Vérifie si vide | `$tags->isEmpty()` |
| `isNotEmpty(): bool` | Vérifie si non vide | `$tags->isNotEmpty()` |
| `firstItem(): mixed` | Premier élément | `$tags->firstItem()` |
| `first(int $limit): TypedRecords` | Nouvelle collection avec n premiers éléments | `$tags->first(3)` |
| `lastItem(): mixed` | Dernier élément | `$tags->lastItem()` |
| `last(int $limit): TypedRecords` | Nouvelle collection avec n derniers éléments | `$tags->last(3)` |
| `getAllowedTypes(): TypedRecords` | Types autorisés | `$tags->getAllowedTypes()` |

### 5.7 Méthodes de transformation

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `map(Closure $callback): TypedRecords` | Transforme chaque élément | `$tags->map(fn($tag) => strtoupper($tag))` |
| `filter(Closure $callback): TypedRecords` | Filtre les éléments | `$tags->filter(fn($tag) => strlen($tag) > 3)` |
| `reject(Closure $callback): TypedRecords` | Rejette les éléments | `$tags->reject(fn($tag) => strlen($tag) > 3)` |
| `each(Closure $callback): TypedRecords` | Exécute une action sur chaque élément | `$collection->each(fn($item) => $sum += $item)` |
| `sort(int $flags = SORT_REGULAR): TypedRecords` | Trie les éléments | `$numbers->sort()` |
| `sortBy(Closure|string $callback, bool $descending = false): TypedRecords` | Trie par clé ou fonction | `$products->sortBy('price')` |
| `reverse(): TypedRecords` | Inverse l'ordre | `$collection->reverse()` |
| `shuffle(): TypedRecords` | Mélange aléatoirement | `$collection->shuffle()` |

### 5.8 Méthodes de calcul

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `sum(?Closure $callback = null): int|float` | Calcule la somme | `$numbers->sum()` ou `$orders->sum(fn($o) => $o->price)` |
| `avg(?Closure $callback = null): ?float` | Calcule la moyenne | `$numbers->avg()` |
| `max(?Closure $callback = null): mixed` | Valeur maximale | `$numbers->max()` |
| `min(?Closure $callback = null): mixed` | Valeur minimale | `$numbers->min()` |

### 5.9 Méthodes de filtrage par type

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `ofType(string $type): TypedRecords` | Filtrer par type | `$collection->ofType('string')` |
| `exceptType(string $type): TypedRecords` | Exclure un type | `$collection->exceptType('int')` |
| `records(): TypedRecords` | Filtrer les Records | `$collection->records()` |
| `scalars(): TypedRecords` | Filtrer les scalaires | `$collection->scalars()` |
| `ofRecord(string $recordClass): TypedRecords` | Filtrer par classe Record | `$collection->ofRecord(UserRecord::class)` |
| `anyRecord(): TypedRecords` | Tous les Records (alias) | `$collection->anyRecord()` |
| `getTypes(): TypedRecords` | Types distincts présents | `$collection->getTypes()` |

### 5.10 Méthodes de recherche

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `where(string $property, mixed $value): TypedRecords` | Filtrer par propriété | `$products->where('price', 100)` |
| `whereNotNull(string $property): TypedRecords` | Propriété non nulle | `$products->whereNotNull('price')` |
| `whereNull(string $property): TypedRecords` | Propriété nulle | `$products->whereNull('price')` |
| `contains(mixed $value): bool` | Vérifie si un élément existe | `$tags->contains('laravel')` |
| `containsType(string $type): bool` | Vérifie si un type est présent | `$collection->containsType('int')` |
| `isOnlyType(string $type): bool` | Vérifie si tous sont d'un type | `$collection->isOnlyType('int')` |

### 5.11 Méthodes de slicing et pagination

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `take(int $limit): TypedRecords` | Prendre les n premiers | `$collection->take(10)` |
| `skip(int $offset): TypedRecords` | Ignorer les n premiers | `$collection->skip(5)` |
| `slice(int $offset, ?int $length = null): TypedRecords` | Extraire une plage | `$collection->slice(2, 3)` |
| `nth(int $step, int $offset = 0): TypedRecords` | Un élément sur n | `$collection->nth(2)` |
| `values(): TypedRecords` | Réindexer les clés | `$filtered->values()` |

### 5.12 Méthodes de manipulation avancées

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `unique(?Closure $callback = null): TypedRecords` | Supprimer les doublons | `$collection->unique()` |
| `merge(TypedRecords $collection): TypedRecords` | Fusionner deux collections | `$collection1->merge($collection2)` |
| `intersect(TypedRecords $collection): TypedRecords` | Éléments communs | `$collection1->intersect($collection2)` |
| `diff(TypedRecords $collection): TypedRecords` | Éléments uniques | `$collection1->diff($collection2)` |
| `flatMap(Closure $callback): TypedRecords` | Aplatir les collections imbriquées | `$nested->flatMap(fn($item) => $item)` |
| `filterNull(): TypedRecords` | Supprimer les valeurs null | `$collection->filterNull()` |
| `random(int $number = 1): TypedRecords` | Éléments aléatoires | `$collection->random(3)` |

### 5.13 Méthodes de validation et assertions

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `isHomogeneous(): bool` | Tous les éléments du même type ? | `$collection->isHomogeneous()` |
| `isHeterogeneous(): bool` | Types différents ? | `$collection->isHeterogeneous()` |
| `assertAllOfType(string $type): TypedRecords` | Vérifie que tous sont d'un type | `$collection->assertAllOfType('int')` |
| `assertNotEmpty(): TypedRecords` | Vérifie que non vide | `$collection->assertNotEmpty()` |
| `assertContainsType(string $type): TypedRecords` | Vérifie qu'un type est présent | `$collection->assertContainsType('int')` |
| `assertAllImplement(string $interface): TypedRecords` | Vérifie l'implémentation d'interface | `$collection->assertAllImplement(AbstractRecord::class)` |
| `assertScalar(): TypedRecords` | Vérifie que tous sont scalaires | `$collection->assertScalar()` |
| `assertRecords(): TypedRecords` | Vérifie que tous sont des Records | `$collection->assertRecords()` |
| `validate(Closure $validator): TypedRecords` | Validation personnalisée | `$collection->validate(fn($item) => $item > 0)` |

---

## 6. Association Records ↔ TypedRecords (⚠️ RÈGLE IMPORTANTE)

> **Règle d'or : Un Record représente un ÉLÉMENT UNIQUE. Une collection d'éléments utilise TOUJOURS `TypedRecords`.**

### 6.1 Principe fondamental

| Situation | Type à utiliser | Exemple |
|-----------|-----------------|---------|
| **Un seul élément** | `Record` | `UserRecord $user` |
| **Plusieurs éléments** | `TypedRecords` | `TypedRecords $users` |

```php
// ✅ BON - Un utilisateur = Record
public function getUser(UserRecord $user): void { ... }

// ✅ BON - Plusieurs utilisateurs = TypedRecords
public function getUsers(TypedRecords $users): void { ... }

// ❌ MAUVAIS - Un seul utilisateur dans une collection
public function getUser(TypedRecords $users): void { ... }

// ❌ MAUVAIS - Plusieurs utilisateurs dans un Record
public readonly UserRecord $users;  // ← Devrait être TypedRecords
```

### 6.2 Dans un Record

```php
final class DashboardDataRecord extends AbstractRecord
{
    public function __construct(
        // ✅ BON - Un seul utilisateur (Record)
        public readonly UserRecord $currentUser,
        
        // ✅ BON - Plusieurs commandes (TypedRecords)
        public readonly TypedRecords $recentOrders,     // TypedRecords<OrderRecord>
        
        // ✅ BON - Plusieurs notifications (TypedRecords)
        public readonly TypedRecords $notifications,    // TypedRecords<NotificationRecord>
        
        // ✅ BON - Tags simples (TypedRecords de scalaires)
        public readonly TypedRecords $tags,             // TypedRecords<string>
    ) {}
}
```

### 6.3 Utilisation de `TypedRecords` générique vs spécifique

| Situation | Solution | Exemple |
|-----------|----------|---------|
| **Collection simple, usage unique** | `TypedRecords` générique | `new TypedRecords(OrderRecord::class)` |
| **Collection réutilisable, méthodes métier** | Classe spécifique | `OrderCollection` |

```php
// ✅ BON - Usage unique, pas besoin de classe dédiée
public readonly TypedRecords $orders = new TypedRecords(OrderRecord::class);

// ✅ BON - Collection réutilisée partout, avec méthodes métier
final class OrderCollection extends TypedRecords
{
    public function __construct()
    {
        parent::__construct(OrderRecord::class);
    }
    
    public function getTotal(): float
    {
        return $this->sum(fn($order) => $order->total);
    }
}

public readonly OrderCollection $orders = new OrderCollection();
```

### 6.4 Exemple complet

```php
// Record Order (élément unique)
final class OrderRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly float $total,
        public readonly string $status,
    ) {}
}

// Collection OrderCollection (plusieurs éléments)
final class OrderCollection extends TypedRecords
{
    public function __construct()
    {
        parent::__construct(OrderRecord::class);
    }
    
    public function getTotal(): float
    {
        return $this->sum(fn($order) => $order->total);
    }
    
    public function getPending(): self
    {
        return $this->filter(fn($order) => $order->status === 'pending');
    }
}

// Record User (élément unique)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly OrderCollection $orders = new OrderCollection(), // Plusieurs commandes
    ) {}
}

// Utilisation
$user = new UserRecord(
    id: 1,
    name: 'John Doe',
    orders: (new OrderCollection())->concat([
        new OrderRecord(id: 1, total: 100, status: 'paid'),
        new OrderRecord(id: 2, total: 200, status: 'pending'),
        new OrderRecord(id: 3, total: 150, status: 'paid'),
    ]),
);

$total = $user->orders->getTotal();  // 450
$pending = $user->orders->getPending();  // Commande #2
```

---

## 7. La classe `AbstractRecord`

`AbstractRecord` est une classe abstraite que **tous les Records doivent étendre**. Elle fournit des méthodes utilitaires pour la sérialisation.

### 7.1 Ce que `AbstractRecord` offre (les méthodes héritées)

| Méthode | Description | Usage typique |
|---------|-------------|----------------|
| `toArray(): array` | Convertit automatiquement le Record en tableau normalisé (conserve les `null`) | Insertion base de données |
| `toDatabase(): array` | Convertit le Record en tableau (exclut les valeurs `null`) | Update base de données |
| `toJson(): string` | Convertit le Record en chaîne JSON | Envoi à API externe |

**La sérialisation est automatique :**
- Toutes les propriétés **publiques** sont automatiquement incluses
- Les clés sont automatiquement converties en `snake_case`
- Les `TypedRecords` sont automatiquement convertis en `array`
- Les énums sont convertis en leurs valeurs scalaires
- Aucune méthode `jsonSerialize()` à implémenter

### 7.2 Normalisation automatique effectuée par `toArray()`

| Type d'entrée | Sortie |
|---------------|--------|
| `Record` | `array` (appel récursif de `toArray()`) |
| `TypedRecords` | `array` typé |
| `Traversable` (Collection, ArrayIterator, etc.) | `array` normalisé |
| `BackedEnum` | Valeur scalaire (`$enum->value`) |
| `PureEnum` | Nom de l'enum (`$enum->name`) |
| `DateTimeInterface` / `Carbon` | String ISO 8601 (`Y-m-d\TH:i:s\Z`) |
| `array` (interne à `TypedRecords`) | Normalisation récursive |
| `scalaire` (int, float, string, bool) | Retour brut |

### 7.3 Exemple d'utilisation

```php
$record = new UserRecord(
    id: 1,
    name: 'Andy',
    email: 'andy@example.com',
    createdAt: '2024-01-15T14:30:00Z'
);

// ✅ toArray() pour la base de données
DB::table('users')->insert($record->toArray());
// Résultat : ['id' => 1, 'name' => 'Andy', 'email' => 'andy@example.com', 'created_at' => '2024-01-15T14:30:00Z']

// ✅ toJson() pour une API externe
$response = Http::post('https://api.external.com/users', $record->toJson());
```

### 7.4 Ce que `AbstractRecord` ne fait PAS

| Méthode | Pourquoi ce n'est PAS dans AbstractRecord |
|---------|-------------------------------------------|
| `fromArray()` | La création n'est pas la responsabilité du Record (c'est le rôle du constructeur) |
| `validate()` | Un Record ne doit contenir aucune logique de validation |
| `toData()` | La transformation en Data se fait dans l'Action via `UserData::fromRecord($record)` |
| `save()` | Un Record ne doit jamais interagir avec la base de données |
| `collect()` | La création de collections de Records n'est pas une méthode générique |

### 7.5 Code complet de `AbstractRecord`

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;
use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use Traversable;
use UnitEnum;

/**
 * Abstract base class for all Record DTOs.
 *
 * Provides pure data transformation capabilities including array conversion,
 * snake_case key normalization, nested record handling, enum conversion,
 * and date formatting. Records are immutable structures used exclusively for
 * internal communication between Services and Repositories.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Records
 */
abstract class AbstractRecord implements Recordable
{
    /**
     * Converts the Record to an associative array with snake_case keys.
     *
     * Recursively processes all public properties, converting nested Record objects,
     * traversable structures, enums, arrays, and date objects to their array/string
     * representations. All array keys are automatically converted from camelCase
     * to snake_case for database compatibility.
     *
     * @return array<string, mixed> Associative array representation of the Record
     */
    public function toArray(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();

        return $this->normalizeArray($properties);
    }

    /**
     * Converts the Record to an associative array for database operations.
     *
     * Only includes non-null values, making it ideal for update operations
     * where you only want to set provided fields. Keys are converted to snake_case.
     *
     * @return array<string, mixed> Associative array with only non-null values
     */
    public function toDatabase(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();
        $normalized = $this->normalizeArray($properties);

        // Remove null values recursively
        return $this->removeNullValues($normalized);
    }

    /**
     * Recursively removes null values from an array.
     *
     * @param array<string, mixed> $array Array to clean
     * @return array<string, mixed> Array without null values
     */
    private function removeNullValues(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->removeNullValues($value);
                if (empty($value)) {
                    continue;
                }
                $result[$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Converts the Record to a JSON string.
     *
     * @return string JSON representation of the Record
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Extracts all public properties with keys converted to snake_case.
     *
     * Uses reflection to access all public properties of the record,
     * converts property names from camelCase to snake_case, and preserves
     * the original values for further normalization.
     *
     * @return array<string, mixed> Associative array with snake_case keys
     */
    private function extractPublicPropertiesWithSnakeKeys(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);
            $key = $this->convertCamelToSnake($property->getName());
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Converts a camelCase string to snake_case.
     *
     * @param string $input CamelCase string to convert
     * @return string snake_case representation
     */
    private function convertCamelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Recursively normalizes array values for serialization.
     *
     * @param array<string, mixed> $array Array to normalize
     * @return array<string, mixed> Normalized array
     */
    private function normalizeArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Recursively normalizes a single value for serialization.
     *
     * Handles:
     * - Nested Record objects (converted via their toArray method)
     * - Traversable objects (converted to arrays recursively)
     * - Enums (converted to scalar values or names)
     * - DateTime objects (formatted as ISO 8601 UTC)
     * - Arrays (recursively processed)
     * - Null values (passed through)
     * - Other scalars (passed through unchanged)
     *
     * @param mixed $value The value to normalize
     * @return mixed Normalized value ready for array/JSON output
     */
    private function normalizeValue(mixed $value): mixed
    {
        // Record object → recursively convert to array
        if ($value instanceof self) {
            return $value->toArray();
        }

        // TypedRecords → convert to array
        if ($value instanceof TypedRecords) {
            return $this->normalizeTypedRecords($value);
        }

        // Traversable (Collection, ArrayIterator, etc.) → convert to array recursively
        if ($value instanceof Traversable) {
            return $this->normalizeTraversable($value);
        }

        // Enum → convert to scalar value (backed) or case name (pure)
        if ($value instanceof UnitEnum) {
            return $this->normalizeEnum($value);
        }

        // DateTimeInterface → convert to UTC ISO 8601 string
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        // Array → recursively normalize each element
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        // Null or scalar → return as-is
        return $value;
    }

    /**
     * Converts a TypedRecords collection to a normalized array.
     *
     * @param TypedRecords $collection The collection to normalize
     * @return array<int, mixed> Normalized array
     */
    private function normalizeTypedRecords(TypedRecords $collection): array
    {
        $result = [];

        foreach ($collection->all() as $item) {
            $result[] = $this->normalizeValue($item);
        }

        return $result;
    }

    /**
     * Converts a Traversable object to a normalized array.
     *
     * Recursively processes each element of the traversable structure,
     * applying the same normalization rules to nested values.
     *
     * @param Traversable $traversable The traversable object to convert
     * @return array<int|string, mixed> Normalized array representation
     */
    private function normalizeTraversable(Traversable $traversable): array
    {
        $result = [];

        foreach ($traversable as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Converts an Enum to its serializable representation.
     *
     * For backed enums (string/int backed), returns the backing value.
     * For pure enums (non-backed), returns the enum case name.
     *
     * @param UnitEnum $enum The enum instance to convert
     * @return string|int Scalar representation of the enum
     */
    private function normalizeEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }
}
```

### 7.6 Récapitulatif

| Ce que ça offre |
|-----------------|
| `toArray(): array` (sérialisation automatique + normalisation) |
| `toDatabase(): array` (exclut les valeurs null) |
| `toJson(): string` |
| Conversion camelCase → snake_case automatique |
| Normalisation récursive des types complexes |
| Support de `TypedRecords` |

---

## 8. Records et Repositories : Le lien (⚠️ IMPORTANT)

> **Chaque Repository est associé à un Record unique pour les opérations de création et de mise à jour.**

### 8.1 Le `RepositoryInfoRecord`

```php
use AndyDefer\BestPractices\Records\Repositories\RepositoryInfoRecord;

final class RepositoryInfoRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $modelClass,   // Ex: User::class
        public readonly string $recordClass,  // Ex: UserRecord::class
    ) {}
}
```

### 8.2 Implémentation d'un Repository

```php
final class UserRepository extends AbstractRepository
{
    public function info(): RepositoryInfoRecord
    {
        return new RepositoryInfoRecord(
            modelClass: User::class,
            recordClass: UserRecord::class,
        );
    }
}
```

### 8.3 Utilisation cohérente

```php
// Création - utilise le Record associé
$userRecord = new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    role: UserRole::ADMIN,
);

$user = $userRepository->create($userRecord);

// Update - utilise le MÊME Record
$updateRecord = new UserRecord(name: 'Jane Doe');
$user = $userRepository->update(1, $updateRecord);
```

**Pourquoi une seule Record par Repository ?**
- ✅ Cohérence : création et update utilisent la même structure
- ✅ Simplicité : pas de duplication de code
- ✅ Type-safety : le Record garantit les types
- ✅ `toDatabase()` exclut automatiquement les champs `null` pour l'update

---

## 9. Appendice : Bonnes pratiques recommandées

> **Bien qu'`AbstractRecord` normalise automatiquement les types, nous recommandons vivement de suivre ces bonnes pratiques.**

### 9.1 Pourquoi suivre les bonnes pratiques ?

Même si la normalisation automatique convertit les types interdits (comme `Carbon` en `string`), suivre les règles rend le code :

- **Plus explicite** : on voit immédiatement le type attendu
- **Plus performant** : on évite la réflexion et les conversions inutiles
- **Plus prévisible** : pas de surprise sur le format des données
- **Plus facile à tester** : les valeurs sont directement dans le bon format

### 9.2 Recommandations

| Au lieu de... | Faire... | Pourquoi ? |
|---------------|----------|-------------|
| `public Carbon $createdAt` | `public string $createdAt` | Le type est explicite, pas de conversion cachée |
| `public DateTime $updatedAt` | `public string $updatedAt` | ISO 8601 est le standard d'échange |
| `public Collection $items` | `public TypedRecords $items` | Type-safe garanti |
| `public ?array $metadata` | `public array $metadata = []` | Évite les nulls inutiles |
| `public array $data` (brut) | `public TypedRecords $data` | Le contenu est typé |

### 9.3 Exemple : Bonnes pratiques vs. Dépendance à la normalisation

```php
// ⚠️ ACCEPTABLE (normalisation fonctionne, mais moins explicite)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public Carbon $createdAt,      // Sera converti en string
        public Collection $tags,       // Sera converti en array (non typé)
    ) {}
}

// ✅ RECOMMANDÉ (explicite, pas de conversion cachée, type-safe)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,                       // ✅ Déjà en string ISO
        public TypedRecords $tags = new TypedRecords('string'), // ✅ Type-safe
    ) {}
}
```

### 9.4 Où faire la conversion ?

La conversion des types complexes (Model → Record, Carbon → string, Collection → TypedRecords) doit être faite **avant** la construction du Record :

```php
// ✅ BON - Conversion avant la création du Record
final class UserService
{
    public function getUser(int $id): UserRecord
    {
        $user = User::find($id);
        
        // Conversion des tags en TypedRecords
        $tags = new TypedRecords('string');
        foreach ($user->tags as $tag) {
            $tags->add($tag);
        }
        
        return new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->created_at->toISOString(),  // Carbon → string
            tags: $tags,                                   // Collection → TypedRecords
        );
    }
}
```

### 9.5 Récapitulatif des bonnes pratiques

| Pratique | Niveau |
|----------|--------|
| Utiliser `TypedRecords` au lieu de `array` | ✅ OBLIGATOIRE |
| Utiliser `string` pour les dates (ISO 8601) | ✅ Recommandé |
| Éviter `Carbon`, `DateTime` dans les Records | ✅ Recommandé |
| Éviter `Collection` dans les Records | ✅ Recommandé |
| Éviter les `?array` (préférer `array = []`) | ✅ Recommandé |
| `readonly` est facultatif (la normalisation fonctionne sans) | ℹ️ Optionnel |

---

## 10. Utilisation de `toArray()`, `toDatabase()` et `toJson()`

| Méthode | Usage | Exemple |
|---------|-------|---------|
| `toArray()` | Insertion/Export (avec valeurs null conservées) | `DB::table('users')->insert($record->toArray())` |
| `toDatabase()` | Update (retire les champs null) | `DB::table('users')->where('id', 1)->update($record->toDatabase())` |
| `toJson()` | Envoi via HTTP Client (API externe) | `Http::post('https://api.external.com', $record->toJson())` |

```php
// ✅ Insertion en base de données (toArray conserve les null)
$record = new UserRecord(...);
DB::table('users')->insert($record->toArray());

// ✅ Update many rows en base de données (toDatabase retire les null)
$record = new UserRecord(name: 'New Name'); // seul name est modifié
DB::table('users')->where('role', 'admin')->update($record->toDatabase());

// ✅ Envoi à une API externe
$record = new PaymentRecord(...);
$response = Http::post('https://api.external.com/payment', $record->toJson());
```

### 10.1 Ce que les Records NE font PAS

```php
// ❌ JAMAIS - Un Record ne répond pas à une API
return response()->json($record);  // ← C'est le travail des Data class !

// ✅ BON - Les Data class sont pour les réponses API
return response()->json($userData);  // UserData, pas UserRecord
```

---

## 11. Exemples complets

### 11.1 Record simple avec scalaires

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class UserCredentialsRecord extends AbstractRecord
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $rememberMe,
    ) {}
}
```

### 11.2 Record avec Enum, TypedRecords et autre Record

```php
<?php

declare(strict_types=1);

namespace App\Records;

use App\Enums\UserRole;
use AndyDefer\BestPractices\Collections\TypedRecords;

final class UserListFilterRecord extends AbstractRecord
{
    public function __construct(
        public ?UserRole $role,
        public ?bool $isActive,
        public ?string $search,
        public TypedRecords $excludedIds = new TypedRecords('int'),
        public ?DashboardFilterRecord $dashboardFilters = null,
    ) {}
}
```

### 11.3 Record avec d'autres Records

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class DashboardContextRecord extends AbstractRecord
{
    public function __construct(
        public UserContextRecord $user,
        public DashboardFilterRecord $filters,
        public string $timezone,
    ) {}
}
```

### 11.4 Record avec liste de Records (via TypedRecords)

```php
<?php

declare(strict_types=1);

namespace App\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;

final class BatchProcessRecord extends AbstractRecord
{
    public function __construct(
        public string $batchId,
        public TypedRecords $users = new TypedRecords(UserRecord::class),
        public int $chunkSize,
    ) {}
}
```

### 11.5 Record pour les données brutes (API externe)

```php
<?php

declare(strict_types=1);

namespace App\Records;

use AndyDefer\BestPractices\Collections\TypedRecords;

final class ExternalApiResponseRecord extends AbstractRecord
{
    public function __construct(
        public string $transactionId,
        public string $status,
        public TypedRecords $errors = new TypedRecords(ErrorRecord::class),
        public ?string $rawPayload = null,
    ) {}
}
```

---

## 12. Flux d'utilisation

```
┌─────────────────────────────────────────────────────────────────┐
│                         ACTION                                  │
│                   (orchestration des appels)                    │
│                                                                 │
│   Reçoit une Request → construit des Records → appelle Service  │
│   Reçoit un Record du Service → transforme en Data → répond     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          SERVICE                                │
│                    (logique métier pure)                        │
│                                                                 │
│   Reçoit des RECORDS et retourne des RECORDS                    │
│   ❌ Ne connaît pas les Data                                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       REPOSITORY                                │
│                    (accès aux données)                          │
│                                                                 │
│   Reçoit des RECORDS et retourne des RECORDS ou des MODELS      │
│   Associé à un Record via RepositoryInfoRecord                  │
└─────────────────────────────────────────────────────────────────┘
```

**⚠️ Rappel :** Les Records ne sont **JAMAIS** utilisés pour les réponses API. C'est le rôle des **Data class**. La transformation Record → Data se fait directement dans l'Action via `UserData::fromRecord($record)`.

---

## 13. Utilisation concrète

### 13.1 Service qui reçoit un Record

```php
final class UserService
{
    public function updateUserField(UserCredentialsRecord $credentials): UserUpdateResultRecord
    {
        // On sait exactement ce qu'on reçoit
        $user = User::where('email', $credentials->email)->first();
        
        // Traitement...
        
        return new UserUpdateResultRecord(
            success: true,
            userId: $user->id,
        );
    }
}
```

### 13.2 Transformation d'un Record en Data

```php
// ✅ BON - Transformation directe dans l'Action
final class ShowUserProfileAction extends AbstractAction
{
    public function run(int $userId, ShowUserProfileRequest $request): JsonResponse
    {
        $record = $this->userService->getUserWithContext(
            userId: $userId,
            currentUserId: auth()->id(),
            timezone: $request->input('timezone', 'UTC'),
        );
        
        if ($record === null) {
            return $this->json(null, 404);
        }
        
        // Transformation directe Record → Data
        $userData = UserProfileData::fromRecord($record);
        
        return $this->json($userData);
    }
}

// La Data avec sa propre méthode fromRecord()
final class UserProfileData extends AbstractData
{
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $timezone = null,
        public readonly ?bool $canEdit = null,
    ) {}
    
    public static function fromRecord(UserWithContextRecord $record): self
    {
        return new self(
            id: (string) $record->user->id,
            name: $record->user->name,
            timezone: $record->context->timezone,
            canEdit: $record->canEdit,
        );
    }
}
```

### 13.3 Insertion en base de données

```php
final class UserRepository
{
    public function create(UserRecord $record): User
    {
        // ✅ Utilisation de toArray() pour l'insertion
        $id = DB::table('users')->insertGetId($record->toArray());
        
        return User::find($id);
    }
    
    public function update(int $id, UserRecord $record): User
    {
        // ✅ Utilisation de toDatabase() pour l'update (exclut les null)
        DB::table('users')->where('id', $id)->update($record->toDatabase());
        
        return User::find($id);
    }
}
```

### 13.4 Appel à une API externe

```php
final class PaymentGatewayService
{
    public function createPayment(PaymentRequestRecord $request): PaymentResponseRecord
    {
        // ✅ Utilisation de toJson() pour l'envoi HTTP
        $response = Http::post(
            'https://api.payment.com/v1/payments',
            $request->toJson()
        );
        
        return new PaymentResponseRecord(
            transactionId: $response->json('transaction_id'),
            status: $response->json('status'),
        );
    }
}
```

### 13.5 Manipulation de TypedRecords dans un Service

```php
final class OrderService
{
    public function calculateTotal(OrderRecord $order): float
    {
        return $order->items->sum(fn($item) => $item->price * $item->quantity);
    }
    
    public function getExpensiveItems(OrderRecord $order, float $threshold): TypedRecords
    {
        return $order->items->filter(fn($item) => $item->price > $threshold);
    }
    
    public function getProductNames(OrderRecord $order): TypedRecords
    {
        return $order->items->map(fn($item) => $item->productName);
    }
}
```

---

## 14. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Description}Record` |
| **Héritage** | Étend `AbstractRecord` |
| **Propriétés** | `public` (l'important est d'être public, `readonly` est optionnel) |
| **Types autorisés** | `int`, `string`, `float`, `bool`, `null`, `Enum`, `Record`, `TypedRecords` |
| **TypedRecords** | Utiliser `new TypedRecords('type')` ou `typed_records()` helper |
| **Types interdits** | `array` brut, `Model`, `Data`, `Collection`, `Carbon`, `DateTime`, `mixed`, `object` |
| **Sérialisation** | Automatique via `toArray()`, `toDatabase()`, `toJson()` |
| **Convention** | Les clés sont automatiquement converties en `snake_case` |
| **Logique** | ❌ AUCUNE méthode métier |
| **Utilisation** | Communication interne UNIQUEMENT (pas de réponse API) |
| **Repository** | Un Record associé par Repository (via `RepositoryInfoRecord`) |
| **Élément vs Collection** | Un seul élément = Record, plusieurs éléments = TypedRecords |

---

## 15. Ce que les Records NE peuvent PAS faire

```php
// ❌ JAMAIS - Pas de logique métier
public function isValid(): bool { ... }

// ❌ JAMAIS - Pas de méthodes de validation
public function isActive(): bool { ... }

// ❌ JAMAIS - Pas de calculs
public function getTotal(): float { ... }

// ❌ JAMAIS - Pas de réponse API
return response()->json($record);

// ❌ JAMAIS - Pas de transformation en Data (c'est le rôle de la Data dans l'Action)
public function toData(): UserData { ... }

// ❌ JAMAIS - Pas de tableau brut
public array $items;  // Utiliser TypedRecords
```

---

## 16. Checklist d'acceptance

- [ ] La classe étend `AbstractRecord`
- [ ] Le nom se termine par `Record`
- [ ] Les propriétés sont `public` (accessibles pour la sérialisation automatique)
- [ ] Les propriétés sont en `camelCase` (seront converties en `snake_case`)
- [ ] **AUCUNE propriété de type `array`** (utiliser `TypedRecords`)
- [ ] **AUCUNE propriété de type `Model`, `Data`, `Collection`, `Carbon` ou `DateTime`**
- [ ] **AUCUNE méthode métier** (ni `isValid()`, ni `isActive()`, etc.)
- [ ] **JAMAIS utilisé pour les réponses API** (c'est le rôle des Data)
- [ ] Utilisé uniquement pour la communication interne
- [ ] Les collections utilisent `TypedRecords`

---

## 17. Anti-patterns à éviter

```php
// ❌ N'étend pas AbstractRecord
final class UserRecord { ... }

// ❌ Contient un array brut non typé
public array $items;  // INTERDIT ! Utiliser TypedRecords

// ❌ Contient un Model
public User $user;  // INTERDIT !

// ❌ Contient un Data
public UserData $userData;  // INTERDIT !

// ❌ Contient une Collection
public Collection $users;  // INTERDIT ! Utiliser TypedRecords

// ❌ Contient Carbon
public Carbon $createdAt;  // INTERDIT ! Utiliser string

// ❌ Méthode métier
public function isActive(): bool { ... }

// ❌ Utilisation en réponse API (INTERDICTION ABSOLUE)
return response()->json($userRecord);

// ❌ Transformation en Data dans le Record
public function toData(): UserData { ... }

// ❌ Plusieurs éléments dans un Record (au lieu de TypedRecords)
public readonly array $users;  // Utiliser TypedRecords<UserRecord>
```

---

## 18. Rappel fondamental

> **Un Record est un sac de données typé, sans aucune logique. Il remplace les tableaux bruts pour rendre le code plus sûr et plus lisible. La sérialisation est entièrement automatique. Les tableaux (`array`) sont STRICTEMENT INTERDITS : utilisez `TypedRecords` à la place.**

```php
// Le Record parfait
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly UserRole $role,
        public readonly TypedRecords $tags = new TypedRecords('string'),
        public readonly TypedRecords $items = new TypedRecords(ItemRecord::class),
        public readonly ?string $createdAt = null,
    ) {}
}
```

### 18.1 Séparation des responsabilités

| Composant | Rôle | Logique | Réponse API |
|-----------|------|---------|-------------|
| **Record** | Communication interne (Services, Repositories) | ❌ Aucune | ❌ **INTERDIT** |
| **Data** | Réponse API (Actions) | ❌ Aucune | ✅ **OBLIGATOIRE** |
| **Service** | Logique métier | ✅ Oui | ❌ Non |

### 18.2 Schéma récapitulatif

```
Record → Communication interne (Services, Repositories)
Data   → Réponse API (Actions)
Service → Logique métier (ne connaît ni Record ni Data)
```

> **Règle :** La transformation Record → Data se fait directement dans l'Action via `UserData::fromRecord($record)`. Les tableaux bruts sont remplacés par `TypedRecords`.

---

## Appendice A : Règle de définition des `TypedRecords` dans un Record

### A.1 Règle fondamentale

> **Toute propriété de type `TypedRecords` dans un Record DOIT avoir une valeur par défaut utilisant `new TypedRecords()`. Cette valeur par défaut sert à déclarer explicitement le(s) type(s) que la collection peut contenir.**

```php
// ✅ BON - Valeur par défaut explicite
public readonly TypedRecords $tags = new TypedRecords('string');

// ✅ BON - Collection de Records avec valeur par défaut
public readonly TypedRecords $items = new TypedRecords(ItemRecord::class);

// ✅ BON - Types multiples avec valeur par défaut
public readonly TypedRecords $mixed = new TypedRecords('int', 'float', 'string');

// ❌ MAUVAIS - Pas de valeur par défaut (type implicite)
public readonly TypedRecords $tags;

// ❌ MAUVAIS - Null comme valeur par défaut (perd l'information de type)
public readonly ?TypedRecords $tags = null;

// ❌ MAUVAIS - Tableau brut
public readonly array $tags = [];
```

### A.2 Pourquoi une valeur par défaut ?

| Raison | Explication |
|--------|-------------|
| **Documentation explicite** | Le type est visible immédiatement dans la signature |
| **Type-safety** | Le Record sait exactement quels types il peut contenir |
| **Initialisation automatique** | Pas besoin de vérifier les nulls |
| **Évite les nulls** | La collection est toujours disponible |
| **Cohérence** | Tous les Records suivent la même convention |

### A.3 Règle d'initialisation

| Situation | Recommandation | Exemple |
|-----------|----------------|---------|
| **1-2 éléments** | `add()` avec paramètres multiples | `->add('a', 'b')` |
| **3+ éléments** | `concat()` avec tableau | `->concat(['a', 'b', 'c'])` |
| **Liste dynamique** | `concat($arrayVariable)` | `->concat($tagsArray)` |

```php
// ❌ À éviter pour beaucoup d'éléments
->add('a')->add('b')->add('c')->add('d')->add('e')

// ✅ À privilégier
->concat(['a', 'b', 'c', 'd', 'e'])
```

### A.4 Exemple complet

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TypedRecords $tags = new TypedRecords('string'),
        public readonly TypedRecords $orders = new TypedRecords(OrderRecord::class),
    ) {}
}

// Utilisation
$user = new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    tags: (new TypedRecords('string'))->concat(['developer', 'laravel', 'php']),
    orders: (new TypedRecords(OrderRecord::class))->concat([
        new OrderRecord(total: 100),
        new OrderRecord(total: 200),
    ]),
);
```

### A.5 Règle de cohérence des types

> **Une fois le type d'une collection défini dans la valeur par défaut, tous les éléments ajoutés DOIVENT être de ce type. La collection elle-même garantit cette contrainte.**

```php
// ✅ BON - Respect du type
$user->tags->add('developer');           // string ✅
$user->tags->concat(['vip', 'premium']); // strings ✅
$user->orders->add($order);              // OrderRecord ✅

// ❌ MAUVAIS - Violation du type
$user->tags->add(123);                   // ❌ int au lieu de string → exception
$user->orders->add('text');              // ❌ string au lieu de OrderRecord → exception
```