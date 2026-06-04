Voici le document de principes d'usage des TypedCollection mis à jour :

# Principe d'usage des TypedCollection

## 1. Définition

Une **TypedCollection** est une collection **type-safe** qui remplace les tableaux bruts (`array`) dans l'ensemble de l'architecture (`Record`, `ValueObject`, `Data`). Elle garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.

```
TypedCollection → Collection type-safe → Remplacement des tableaux bruts
```

> ⚠️ **Les tableaux bruts (`array`) sont STRICTEMENT INTERDITS dans les Records, ValueObjects et Data. Utilisez toujours `TypedCollection` pour les collections d'éléments.**

```php
// ✅ BON - Collection typée
final class OrderRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $items,  // TypedCollection<OrderItemRecord>
    ) {}
}
```

---

## 2. Problématique à laquelle TypedCollection répond

| Problème des tableaux | Solution avec TypedCollection |
|----------------------|-------------------------------|
| On ne sait pas ce qu'il contient | Le type est explicite à la construction |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |
| Pas de méthodes utilitaires | Nombreuses méthodes disponibles |

---

## 3. Types supportés

> **Une TypedCollection peut contenir tout objet qui implémente l'interface `Transformable`, ainsi que les scalaires.**

| Type | Description | Exemple |
|------|-------------|---------|
| `'int'` | Entier | `new TypedCollection('int')` |
| `'string'` | Chaîne de caractères | `new TypedCollection('string')` |
| `'float'` | Nombre à virgule flottante | `new TypedCollection('float')` |
| `'bool'` | Booléen | `new TypedCollection('bool')` |
| `'null'` | Valeur nulle | `new TypedCollection('null')` |
| `AbstractRecord::class` | Record (snake_case) | `new TypedCollection(UserRecord::class)` |
| `AbstractValueObject::class` | Value Object (camelCase) | `new TypedCollection(EmailAddress::class)` |
| `AbstractData::class` | Data (camelCase) | `new TypedCollection(UserData::class)` |
| `UnitEnum::class` | Enum | `new TypedCollection(UserRole::class)` |

### 3.1 Types INTERDITS

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| `array` | Non typé, contenu inconnu | `TypedCollection` |
| `object` | Non typé | Type explicite |
| `DateTime` | N'implémente pas `Transformable` | `Iso8601DateTime` VO |
| Classe abstraite | Ne peut pas être instanciée | Classe concrète |

```php
// ❌ INTERDIT - Classe abstraite
new TypedCollection(AbstractRecord::class);

// ✅ BON - Classe concrète
new TypedCollection(UserRecord::class);
```

---

## 4. Règle fondamentale : préférer les collections spécialisées

> **⚠️ Dans les `Record`, `ValueObject` et `Data`, on utilise de préférence des collections spécialisées (qui étendent `TypedCollection`) plutôt que `TypedCollection` générique.**

### 4.1 Pourquoi éviter `TypedCollection` générique ?

```php
// ❌ À ÉVITER - On ne sait pas ce que contient la collection
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $products,  // Quels produits ? On ne sait pas !
    ) {}
}
```

### 4.2 Pourquoi privilégier les collections spécialisées ?

```php
// ✅ RECOMMANDÉ - La collection dit explicitement ce qu'elle contient
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ProductRecordCollection $products,  // TypedCollection<ProductRecord>
        public readonly UserRecordCollection $friends,      // TypedCollection<UserRecord>
    ) {}
}
```

---

## 5. Créer une collection spécialisée

### 5.1 Pour les Records (snake_case)

```php
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;

final class UserRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(UserRecord::class);
    }
    
    public function getAdmins(): self
    {
        return $this->filter(fn(UserRecord $user) => $user->user_role === UserRole::ADMIN);
    }
    
    public function getActive(): self
    {
        return $this->filter(fn(UserRecord $user) => $user->user_status === UserStatus::ACTIVE);
    }
}
```

### 5.2 Pour les Data (camelCase)

```php
use AndyDefer\DomainStructures\Collections\Core\DataCollection;

final class ProductDataCollection extends DataCollection
{
    public function __construct()
    {
        parent::__construct(ProductData::class);
    }
    
    public function getFeatured(): self
    {
        return $this->filter(fn(ProductData $product) => $product->isFeatured === true);
    }
}
```

---

## 6. Méthodes de base

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `add(...$items): static` | Ajoute un ou plusieurs éléments | `$collection->add($item1, $item2)` |
| `count(): int` | Nombre d'éléments | `$collection->count()` |
| `isEmpty(): bool` | Vérifie si vide | `$collection->isEmpty()` |
| `isNotEmpty(): bool` | Vérifie si non vide | `$collection->isNotEmpty()` |
| `toArray(): array` | Retourne tous les éléments | `$collection->toArray()` |
| `all(): static` | Retourne une nouvelle copie | `$collection->all()` |
| `getAllowedTypes(): array` | Types autorisés | `$collection->getAllowedTypes()` |

---

## 7. Méthodes de transformation

### 7.1 `map()` - Auto-détection des types

Détecte automatiquement les types des éléments transformés et retourne une `TypedCollection` générique.

```php
$collection = new IntTypedCollection();
$collection->add(1, 2, 3, 4, 5);

// Retourne une TypedCollection générique avec le type auto-détecté 'string'
$stringCollection = $collection->map(fn($item) => "Number: {$item}");
```

### 7.2 `mapPreserveType()` - Préserve le type de collection

Garde la même classe de collection. Une exception est levée si les éléments transformés ne sont pas compatibles.

```php
$collection = new IntTypedCollection();
$collection->add(1, 2, 3, 4, 5);

// Retourne une IntTypedCollection (même type)
$doubled = $collection->mapPreserveType(fn($item) => $item * 2);

// ❌ Lance une exception - 'string' n'est pas compatible avec IntTypedCollection
$collection->mapPreserveType(fn($item) => "Number: {$item}");
```

### 7.3 `mapToType()` - Change vers un type de collection spécifique

Transforme la collection vers une classe de collection cible.

```php
$collection = new IntTypedCollection();
$collection->add(1, 2, 3, 4, 5);

// Retourne une StringTypedCollection
$stringCollection = $collection->mapToType(
    fn($item) => "Number: {$item}",
    StringTypedCollection::class
);
```

### 7.4 `filter()` - Filtre les éléments

```php
$evenNumbers = $collection->filter(fn($item) => $item % 2 === 0);
```

### 7.5 `each()` - Exécute une action

```php
$collection->each(fn($item) => $sum += $item);
```

---

## 8. Méthodes de tri

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `sort(int $flags): static` | Trie les éléments | `$collection->sort()` |
| `sortBy($callback, int $flags, bool $descending): static` | Trie par clé ou fonction | `$collection->sortBy('name')` |
| `usort(Closure $callback): static` | Trie avec fonction personnalisée | `$collection->usort(fn($a,$b) => $a <=> $b)` |
| `reverse(): static` | Inverse l'ordre | `$collection->reverse()` |

```php
// Tri par propriété
$sorted = $users->sortBy('user_name');

// Tri personnalisé
$sorted = $users->usort(fn($a, $b) => strcmp($a->name, $b->name));
```

---

## 9. Méthodes de calcul sur collections numériques

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `sum(?Closure $callback): int\|float` | Calcule la somme | `$numbers->sum()` |
| `avg(?Closure $callback): ?float` | Calcule la moyenne | `$numbers->avg()` |
| `max(?Closure $callback): mixed` | Valeur maximale | `$numbers->max()` |
| `min(?Closure $callback): mixed` | Valeur minimale | `$numbers->min()` |

```php
$orders = new TypedCollection(OrderRecord::class);
$total = $orders->sum(fn($order) => $order->order_total);
$average = $orders->avg(fn($order) => $order->order_total);
```

---

## 10. Méthodes de recherche

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `contains(mixed $value): bool` | Vérifie si un élément existe | `$tags->contains('laravel')` |
| `every(Closure $callback): bool` | Tous les éléments satisfont | `$numbers->every(fn($n) => $n > 0)` |
| `some(Closure $callback): bool` | Au moins un élément satisfait | `$numbers->some(fn($n) => $n > 100)` |
| `find(Closure $callback): mixed` | Trouve le premier élément | `$users->find(fn($u) => $u->user_id === 42)` |
| `first(): mixed` | Premier élément | `$collection->first()` |
| `last(): mixed` | Dernier élément | `$collection->last()` |

---

## 11. Méthodes de manipulation

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `unique(?Closure $callback): static` | Supprime les doublons | `$collection->unique()` |
| `merge(TypedCollectionInterface $collection): static` | Fusionne deux collections | `$collection1->merge($collection2)` |
| `reduce(Closure $callback, mixed $initial): mixed` | Réduit la collection | `$collection->reduce(fn($c, $i) => $c + $i, 0)` |

---

## 12. Collections utilitaires prédéfinies

| Collection | Type | Exemple |
|------------|------|---------|
| `StringTypedCollection` | `string` | `new StringTypedCollection()` |
| `IntTypedCollection` | `int` | `new IntTypedCollection()` |
| `FloatTypedCollection` | `float` | `new FloatTypedCollection()` |
| `BoolTypedCollection` | `bool` | `new BoolTypedCollection()` |
| `NumberTypedCollection` | `int\|float` | `new NumberTypedCollection()` |
| `ScalarTypedCollection` | `string\|int\|bool\|null` | `new ScalarTypedCollection()` |

### Génération de séquences avec `range()`

```php
// IntTypedCollection::range()
$evenNumbers = IntTypedCollection::range(2, 20, 2);
// [2, 4, 6, 8, 10, 12, 14, 16, 18, 20]

// FloatTypedCollection::range()
$floats = FloatTypedCollection::range(0, 1, 0.25);
// [0.0, 0.25, 0.5, 0.75, 1.0]
```

---

## 13. Hydratation

### 13.1 `from(mixed $source): static`

```php
// Depuis un tableau
$collection = UserRecordCollection::from([
    ['user_id' => 1, 'user_name' => 'John'],
    ['user_id' => 2, 'user_name' => 'Jane'],
]);

// Depuis une collection existante
$newCollection = UserRecordCollection::from($existingCollection);
```

### 13.2 `fromJson(string $json): static`

```php
$json = '[{"user_id":1,"user_name":"John"},{"user_id":2,"user_name":"Jane"}]';
$collection = UserRecordCollection::fromJson($json);
```

### 13.3 Ambiguïté type - utilisation de `_type`

```php
$collection = new TypedCollection(UserData::class, ProductData::class);

$source = [
    ['_type' => UserData::class, 'user_id' => 1, 'user_name' => 'John'],
    ['_type' => ProductData::class, 'product_id' => 1, 'product_name' => 'Laptop'],
];

$result = $collection->from($source);
```

---

## 14. Normalisation

```php
// Normalisation pour les réponses JSON
$normalized = NormalizerChain::get()->normalize($collection);
```

---

## 15. Exemple complet

```php
// Record (snake_case)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly UserRole $user_role,
    ) {}
}

// Collection spécialisée pour UserRecord
final class UserRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(UserRecord::class);
    }
    
    public function getAdmins(): self
    {
        return $this->filter(fn(UserRecord $user) => $user->user_role === UserRole::ADMIN);
    }
}

// Data (camelCase)
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userRole,
    ) {}
    
    public static function fromRecord(UserRecord $record): self
    {
        return new self(
            userId: $record->user_id,
            userName: $record->user_name,
            userRole: $record->user_role->value,
        );
    }
}

// Collection spécialisée pour UserData
final class UserDataCollection extends DataCollection
{
    public function __construct()
    {
        parent::__construct(UserData::class);
    }
}

// Utilisation
$users = UserRecordCollection::from([
    ['user_id' => 1, 'user_name' => 'John', 'user_role' => 'admin'],
    ['user_id' => 2, 'user_name' => 'Jane', 'user_role' => 'user'],
]);

$admins = $users->getAdmins();

// Transformation Record → Data
$usersData = $users->mapToType(
    fn(UserRecord $record) => UserData::fromRecord($record),
    UserDataCollection::class
);
```

---

## 16. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Tableaux bruts** | ❌ INTERDITS dans Records, ValueObjects, Data |
| **Collections spécialisées** | ✅ Privilégier pour les types métier |
| **TypedCollection générique** | ⚠️ À éviter (sauf sources externes) |
| **Types autorisés** | scalaires, Enum, Transformable |
| **Classes abstraites** | ❌ INTERDITES |
| **`map()`** | Retourne `TypedCollection` générique |
| **`mapPreserveType()`** | Préserve le type de collection |
| **`mapToType()`** | Change vers un type spécifique |

---

## 17. Règle d'or

> **ZÉRO tableau brut dans les Records, ValueObjects et Data. TOUTES les collections sont typées avec `TypedCollection` ou une collection spécialisée.**
>
> **⚠️ Préférez les collections spécialisées (`UserRecordCollection`, `ProductDataCollection`) plutôt que `TypedCollection` générique.**
>
> **Les Records sont en `snake_case`, les Data en `camelCase`.**

```php
// ✅ La collection parfaite
final class UserRecordCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(UserRecord::class);
    }
    
    public function getAdmins(): self
    {
        return $this->filter(fn(UserRecord $user) => $user->user_role === UserRole::ADMIN);
    }
}

// ✅ Utilisation
$users = UserRecordCollection::from([
    ['user_id' => 1, 'user_name' => 'John', 'user_role' => 'admin'],
    ['user_id' => 2, 'user_name' => 'Jane', 'user_role' => 'user'],
]);

$admins = $users->getAdmins();

// Transformation avec mapToType
$userNames = $users->mapToType(
    fn(UserRecord $user) => $user->user_name,
    StringTypedCollection::class
);
```
---