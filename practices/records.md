Voici ton document mis à jour avec `TypedCollection` à la place de `TypedRecords`.

---

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
| `TypedCollection` | `public readonly TypedCollection $items` | Collection typée |

### 4.2 Pourquoi les tableaux bruts sont INTERDITS

```php
// ❌ MAUVAIS - Tableau brut non typé
public readonly array $items;           // INTERDIT
public readonly array $tags;            // INTERDIT
public readonly array $users;           // INTERDIT

// ✅ BON - Utilisation de TypedCollection
public readonly TypedCollection $items;    // TypedCollection<ItemRecord>
public readonly TypedCollection $tags;     // TypedCollection<string>
public readonly TypedCollection $users;    // TypedCollection<UserRecord>
```

| Problème des tableaux bruts | Solution avec `TypedCollection` |
|----------------------------|----------------------------------|
| On ne sait pas ce qu'il contient | Le type est explicite (`TypedCollection<ItemRecord>`) |
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
| `array` brut (non typé) | On ne sait pas ce qu'il contient | `TypedCollection` |
| `Model` (Eloquent) | Contient de la logique et des relations | `UserRecord`, `DoctorRecord` |
| `Data` (DTO API) | Destiné à la couche API uniquement | `UserRecord` |
| `Collection` | Structure non typée | `TypedCollection` |
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
        public readonly TypedCollection $items,    // ✅ TypedCollection<ItemRecord>
        public readonly int $userId,               // ✅ int
        public readonly string $createdAt,         // ✅ string ISO
        public readonly UserRole $role,            // ✅ Backed enum
    ) {}
}
```

### 4.5 Record optionnel avec `EmptyRecord`

> **Pour les cas où un Record peut être optionnel (ex: filtres de recherche), utilisez `EmptyRecord` plutôt que `null`.**

```php
use AndyDefer\Records\EmptyRecord;
use AndyDefer\Records\AbstractRecord;

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

**Pourquoi `EmptyRecord` plutôt que `null` ?**

| Avec `null` | Avec `EmptyRecord` |
|-------------|---------------------|
| `$filters?->toArray() ?? []` | `$filters->toArray()` |
| Condition ternaire partout | Pas de condition |
| Risque d'oubli de `?` | Type-safe garanti |
| `?Recordable` est ambigu | Le Record est toujours présent |

---

## 5. TypedCollection : La collection typée (⚠️ RÈGLE ABSOLUE)

> **Pour remplacer les tableaux bruts, nous utilisons `TypedCollection`. C'est une collection type-safe qui garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.**

### 5.1 Définition

**TypedCollection** est une collection type-safe qui remplace les tableaux bruts dans les Records. Elle garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.

```php
use AndyDefer\Records\Collections\TypedCollection;

// ✅ BON - Collection typée
$tags = new TypedCollection('string');
$tags->add('developer', 'laravel', 'php');

// ❌ MAUVAIS - Tableau brut non typé (INTERDIT dans les Records)
public array $tags = [];
```

### 5.2 Pourquoi remplacer les tableaux par TypedCollection ?

| Problème des tableaux | Solution avec TypedCollection |
|-----------------------|-------------------------------|
| On ne sait pas ce qu'ils contiennent | Le type est explicite (`TypedCollection<string>`) |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |
| Pas de méthodes utilitaires | Nombreuses méthodes de manipulation |

### 5.3 Types supportés

| Type | Description | Exemple |
|------|-------------|---------|
| `'int'` | Entier | `new TypedCollection('int')` |
| `'string'` | Chaîne de caractères | `new TypedCollection('string')` |
| `'float'` | Nombre à virgule flottante | `new TypedCollection('float')` |
| `'bool'` | Booléen | `new TypedCollection('bool')` |
| `'null'` | Valeur nulle | `new TypedCollection('string', 'null')` |
| `Record::class` | Classe Record | `new TypedCollection(UserRecord::class)` |
| `TypedCollection::class` | Collection imbriquée | `new TypedCollection(TypedCollection::class)` |

### 5.4 Types multiples

```php
// Collection acceptant plusieurs types scalaires
$mixed = new TypedCollection('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection acceptant Records et scalaires
$items = new TypedCollection(ProductRecord::class, 'string');
$items->add(new ProductRecord(name: 'Laptop'), 'Just a description');
```

### 5.5 Création d'une collection

```php
// ✅ BON - Via le constructeur uniquement
$tags = new TypedCollection('string');
$tags->add('developer', 'laravel', 'php');

// ✅ BON - Avec plusieurs types
$mixed = new TypedCollection('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// ✅ BON - Collection de Records
$products = new TypedCollection(ProductRecord::class);
$products->add(new ProductRecord(name: 'Laptop', price: 999));

// ✅ BON - Collection de collections (nested)
$nested = new TypedCollection(TypedCollection::class);
$nested->add($tags, $ids);
```

### 5.6 Méthodes de base

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `add(...$items)` | Ajoute un ou plusieurs éléments | `$tags->add('developer', 'laravel')` |
| `all(): TypedCollection` | Retourne tous les éléments | `$tags->all()` |
| `count(): int` | Nombre d'éléments | `$tags->count()` |
| `isEmpty(): bool` | Vérifie si vide | `$tags->isEmpty()` |
| `isNotEmpty(): bool` | Vérifie si non vide | `$tags->isNotEmpty()` |
| `firstItem(): mixed` | Premier élément | `$tags->firstItem()` |
| `first(int $limit): TypedCollection` | Nouvelle collection avec n premiers éléments | `$tags->first(3)` |
| `lastItem(): mixed` | Dernier élément | `$tags->lastItem()` |
| `last(int $limit): TypedCollection` | Nouvelle collection avec n derniers éléments | `$tags->last(3)` |
| `getAllowedTypes(): array` | Types autorisés | `$tags->getAllowedTypes()` |

### 5.7 Méthodes de transformation

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `map(Closure $callback): TypedCollection` | Transforme chaque élément | `$tags->map(fn($tag) => strtoupper($tag))` |
| `filter(Closure $callback): TypedCollection` | Filtre les éléments | `$tags->filter(fn($tag) => strlen($tag) > 3)` |
| `reject(Closure $callback): TypedCollection` | Rejette les éléments | `$tags->reject(fn($tag) => strlen($tag) > 3)` |
| `each(Closure $callback): TypedCollection` | Exécute une action sur chaque élément | `$collection->each(fn($item) => $sum += $item)` |
| `sort(int $flags = SORT_REGULAR): TypedCollection` | Trie les éléments | `$numbers->sort()` |
| `sortBy(Closure|string $callback, bool $descending = false): TypedCollection` | Trie par clé ou fonction | `$products->sortBy('price')` |
| `reverse(): TypedCollection` | Inverse l'ordre | `$collection->reverse()` |
| `shuffle(): TypedCollection` | Mélange aléatoirement | `$collection->shuffle()` |

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
| `ofType(string $type): TypedCollection` | Filtrer par type | `$collection->ofType('string')` |
| `exceptType(string $type): TypedCollection` | Exclure un type | `$collection->exceptType('int')` |
| `records(): TypedCollection` | Filtrer les Records | `$collection->records()` |
| `scalars(): TypedCollection` | Filtrer les scalaires | `$collection->scalars()` |
| `ofRecord(string $recordClass): TypedCollection` | Filtrer par classe Record | `$collection->ofRecord(UserRecord::class)` |
| `anyRecord(): TypedCollection` | Tous les Records (alias) | `$collection->anyRecord()` |
| `getTypes(): TypedCollection` | Types distincts présents | `$collection->getTypes()` |

### 5.10 Méthodes de recherche

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `where(string $property, mixed $value): TypedCollection` | Filtrer par propriété | `$products->where('price', 100)` |
| `whereNotNull(string $property): TypedCollection` | Propriété non nulle | `$products->whereNotNull('price')` |
| `whereNull(string $property): TypedCollection` | Propriété nulle | `$products->whereNull('price')` |
| `contains(mixed $value): bool` | Vérifie si un élément existe | `$tags->contains('laravel')` |
| `containsType(string $type): bool` | Vérifie si un type est présent | `$collection->containsType('int')` |
| `isOnlyType(string $type): bool` | Vérifie si tous sont d'un type | `$collection->isOnlyType('int')` |

### 5.11 Méthodes de slicing et pagination

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `take(int $limit): TypedCollection` | Prendre les n premiers | `$collection->take(10)` |
| `skip(int $offset): TypedCollection` | Ignorer les n premiers | `$collection->skip(5)` |
| `slice(int $offset, ?int $length = null): TypedCollection` | Extraire une plage | `$collection->slice(2, 3)` |
| `nth(int $step, int $offset = 0): TypedCollection` | Un élément sur n | `$collection->nth(2)` |
| `values(): TypedCollection` | Réindexer les clés | `$filtered->values()` |

### 5.12 Méthodes de manipulation avancées

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `unique(?Closure $callback = null): TypedCollection` | Supprimer les doublons | `$collection->unique()` |
| `merge(TypedCollection $collection): TypedCollection` | Fusionner deux collections | `$collection1->merge($collection2)` |
| `intersect(TypedCollection $collection): TypedCollection` | Éléments communs | `$collection1->intersect($collection2)` |
| `diff(TypedCollection $collection): TypedCollection` | Éléments uniques | `$collection1->diff($collection2)` |
| `flatMap(Closure $callback): TypedCollection` | Aplatir les collections imbriquées | `$nested->flatMap(fn($item) => $item)` |
| `filterNull(): TypedCollection` | Supprimer les valeurs null | `$collection->filterNull()` |
| `random(int $number = 1): TypedCollection` | Éléments aléatoires | `$collection->random(3)` |

### 5.13 Méthodes de validation et assertions

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `isHomogeneous(): bool` | Tous les éléments du même type ? | `$collection->isHomogeneous()` |
| `isHeterogeneous(): bool` | Types différents ? | `$collection->isHeterogeneous()` |
| `assertAllOfType(string $type): TypedCollection` | Vérifie que tous sont d'un type | `$collection->assertAllOfType('int')` |
| `assertNotEmpty(): TypedCollection` | Vérifie que non vide | `$collection->assertNotEmpty()` |
| `assertContainsType(string $type): TypedCollection` | Vérifie qu'un type est présent | `$collection->assertContainsType('int')` |
| `assertAllImplement(string $interface): TypedCollection` | Vérifie l'implémentation d'interface | `$collection->assertAllImplement(AbstractRecord::class)` |
| `assertScalar(): TypedCollection` | Vérifie que tous sont scalaires | `$collection->assertScalar()` |
| `assertRecords(): TypedCollection` | Vérifie que tous sont des Records | `$collection->assertRecords()` |
| `validate(Closure $validator): TypedCollection` | Validation personnalisée | `$collection->validate(fn($item) => $item > 0)` |

---

## 6. Association Records ↔ TypedCollection (⚠️ RÈGLE IMPORTANTE)

> **Règle d'or : Un Record représente un ÉLÉMENT UNIQUE. Une collection d'éléments utilise TOUJOURS `TypedCollection`.**

### 6.1 Principe fondamental

| Situation | Type à utiliser | Exemple |
|-----------|-----------------|---------|
| **Un seul élément** | `Record` | `UserRecord $user` |
| **Plusieurs éléments** | `TypedCollection` | `TypedCollection $users` |

```php
// ✅ BON - Un utilisateur = Record
public function getUser(UserRecord $user): void { ... }

// ✅ BON - Plusieurs utilisateurs = TypedCollection
public function getUsers(TypedCollection $users): void { ... }

// ❌ MAUVAIS - Un seul utilisateur dans une collection
public function getUser(TypedCollection $users): void { ... }

// ❌ MAUVAIS - Plusieurs utilisateurs dans un Record
public readonly UserRecord $users;  // ← Devrait être TypedCollection
```

### 6.2 Dans un Record

```php
final class DashboardDataRecord extends AbstractRecord
{
    public function __construct(
        // ✅ BON - Un seul utilisateur (Record)
        public readonly UserRecord $currentUser,
        
        // ✅ BON - Plusieurs commandes (TypedCollection)
        public readonly TypedCollection $recentOrders,     // TypedCollection<OrderRecord>
        
        // ✅ BON - Plusieurs notifications (TypedCollection)
        public readonly TypedCollection $notifications,    // TypedCollection<NotificationRecord>
        
        // ✅ BON - Tags simples (TypedCollection de scalaires)
        public readonly TypedCollection $tags,             // TypedCollection<string>
    ) {}
}
```

### 6.3 Utilisation de `TypedCollection` générique vs spécifique

| Situation | Solution | Exemple |
|-----------|----------|---------|
| **Collection simple, usage unique** | `TypedCollection` générique | `new TypedCollection(OrderRecord::class)` |
| **Collection réutilisable, méthodes métier** | Classe spécifique | `OrderCollection` |

```php
// ✅ BON - Usage unique, pas besoin de classe dédiée
public readonly TypedCollection $orders = new TypedCollection(OrderRecord::class);

// ✅ BON - Collection réutilisée partout, avec méthodes métier
final class OrderCollection extends TypedCollection
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

---

## 7. La classe `AbstractRecord`

`AbstractRecord` est une classe abstraite que **tous les Records doivent étendre**. Elle fournit des méthodes utilitaires pour la sérialisation.

### 7.1 Ce que `AbstractRecord` offre

| Méthode | Description | Usage typique |
|---------|-------------|----------------|
| `toArray(): array` | Convertit automatiquement le Record en tableau normalisé (conserve les `null`) | Insertion base de données |
| `toDatabase(): array` | Convertit le Record en tableau (exclut les valeurs `null`) | Update base de données |
| `toJson(): string` | Convertit le Record en chaîne JSON | Envoi à API externe |

**La sérialisation est automatique :**
- Toutes les propriétés **publiques** sont automatiquement incluses
- Les clés sont automatiquement converties en `snake_case`
- Les `TypedCollection` sont automatiquement converties en `array`
- Les énums sont convertis en leurs valeurs scalaires

### 7.2 Normalisation automatique

| Type d'entrée | Sortie |
|---------------|--------|
| `Record` | `array` (appel récursif de `toArray()`) |
| `TypedCollection` | `array` typé |
| `Traversable` | `array` normalisé |
| `BackedEnum` | Valeur scalaire (`$enum->value`) |
| `PureEnum` | Nom de l'enum (`$enum->name`) |
| `DateTimeInterface` | String ISO 8601 (`Y-m-d\TH:i:s\Z`) |
| `array` (interne à `TypedCollection`) | Normalisation récursive |
| `scalaire` (int, float, string, bool) | Retour brut |

### 7.3 Utilisation

```php
// ✅ Insertion en base de données
DB::table('users')->insert($record->toArray());

// ✅ Update (exclut automatiquement les champs null)
DB::table('users')->where('id', 1)->update($record->toDatabase());

// ✅ Envoi à une API externe
Http::post('https://api.external.com/users', $record->toJson());
```

---

## 8. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Description}Record` |
| **Héritage** | Étend `AbstractRecord` |
| **Propriétés** | `public` (readonly optionnel) |
| **Types autorisés** | `int`, `string`, `float`, `bool`, `null`, `Enum`, `Record`, `TypedCollection` |
| **TypedCollection** | Utiliser `new TypedCollection('type')` |
| **Types interdits** | `array` brut, `Model`, `Data`, `Collection`, `Carbon`, `DateTime`, `mixed`, `object` |
| **Logique** | ❌ AUCUNE méthode métier |
| **Utilisation** | Communication interne UNIQUEMENT (pas de réponse API) |
| **Élément vs Collection** | Un seul élément = Record, plusieurs éléments = TypedCollection |

---

## 9. Anti-patterns à éviter

```php
// ❌ N'étend pas AbstractRecord
final class UserRecord { ... }

// ❌ Contient un array brut
public array $items;  // Utiliser TypedCollection

// ❌ Contient un Model
public User $user;

// ❌ Contient du Carbon
public Carbon $createdAt;  // Utiliser string ISO

// ❌ Méthode métier
public function isActive(): bool { ... }

// ❌ Utilisation en réponse API
return response()->json($userRecord);
```

---

## 10. Rappel fondamental

> **Un Record est un sac de données typé, sans aucune logique. Il remplace les tableaux bruts pour rendre le code plus sûr et plus lisible. Les tableaux (`array`) sont STRICTEMENT INTERDITS : utilisez `TypedCollection` à la place.**

```php
// Le Record parfait
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly UserRole $role,
        public readonly TypedCollection $tags = new TypedCollection('string'),
        public readonly TypedCollection $items = new TypedCollection(ItemRecord::class),
        public readonly ?string $createdAt = null,
    ) {}
}
```