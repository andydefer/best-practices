Voici ton document mis à jour avec `TypedCollection` à la place de `TypedRecords`.

---

# TypedCollection : La collection type-safe (Documentation complète)

## 1. Définition

**TypedCollection** est une collection type-safe qui remplace les tableaux bruts dans les Records. Elle garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.

```php
use AndyDefer\Records\Collections\TypedCollection;

// ✅ BON - Collection typée
$tags = new TypedCollection('string');
$tags->add('developer', 'laravel', 'php');

// ❌ MAUVAIS - Tableau brut non typé (INTERDIT dans les Records)
public array $tags = [];
```

## 2. Pourquoi remplacer les tableaux par TypedCollection ?

| Problème des tableaux | Solution avec TypedCollection |
|-----------------------|-------------------------------|
| On ne sait pas ce qu'ils contiennent | Le type est explicite (`TypedCollection<string>`) |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |
| Pas de méthodes utilitaires | Nombreuses méthodes de manipulation |

## 3. Types supportés

| Type | Description | Exemple |
|------|-------------|---------|
| `'int'` | Entier | `new TypedCollection('int')` |
| `'string'` | Chaîne de caractères | `new TypedCollection('string')` |
| `'float'` | Nombre à virgule flottante | `new TypedCollection('float')` |
| `'bool'` | Booléen | `new TypedCollection('bool')` |
| `'null'` | Valeur nulle | `new TypedCollection('string', 'null')` |
| `Record::class` | Classe Record | `new TypedCollection(UserRecord::class)` |
| `TypedCollection::class` | Collection imbriquée | `new TypedCollection(TypedCollection::class)` |
| `stdClass::class` | Objet simple (désérialisation JSON) | `new TypedCollection(stdClass::class)` |

⚠️ **Important :** Les objets arbitraires (non stdClass, non AbstractRecord, non TypedCollection) sont **STRICTEMENT INTERDITS**.

### 3.1 Types multiples

```php
// Collection acceptant plusieurs types scalaires
$mixed = new TypedCollection('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection acceptant Records et scalaires
$items = new TypedCollection(ProductRecord::class, 'string');
$items->add(new ProductRecord(name: 'Laptop'), 'Just a description');

// Collection acceptant stdClass
$objects = new TypedCollection(stdClass::class);
$obj = new stdClass();
$obj->name = 'John';
$objects->add($obj);
```

## 4. Création d'une collection

### 4.1 Création d'une collection

```php
// Collection de strings
$tags = new TypedCollection('string');
$tags->add('developer', 'laravel', 'php');

// Collection d'entiers
$ids = new TypedCollection('int');
$ids->add(1, 2, 3, 4, 5);

// Collection avec plusieurs types scalaires
$mixed = new TypedCollection('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection de Records
$products = new TypedCollection(ProductRecord::class);
$products->add(new ProductRecord(name: 'Laptop', price: 999));

// Collection de collections (imbriquée)
$nested = new TypedCollection(TypedCollection::class);
$nested->add($tags, $ids);

// Collection de stdClass (désérialisation JSON)
$objects = new TypedCollection(stdClass::class);
$obj = json_decode('{"name":"John","age":30}');
$objects->add($obj);
```

## 5. Méthodes de base

### 5.1 Ajout d'éléments

```php
// add() - Ajoute un ou plusieurs éléments (supporte le chaînage)
$collection->add('hello');                    // un seul élément
$collection->add('a', 'b', 'c');              // plusieurs éléments
$collection->add(1, 2, 3)->add('end');        // chaînage
```

### 5.2 Lecture des éléments

```php
// all() - Retourne tous les éléments (alias de toArray())
$items = $collection->all(); // array

// firstItem() - Premier élément (ou null)
$first = $collection->firstItem();

// first() - Retourne une nouvelle collection avec les n premiers éléments
$firstTwo = $collection->first(2);

// lastItem() - Dernier élément (ou null)
$last = $collection->lastItem();

// last() - Retourne une nouvelle collection avec les n derniers éléments
$lastTwo = $collection->last(2);
```

### 5.3 Informations sur la collection

```php
// count() - Nombre d'éléments
$count = $collection->count();

// isEmpty() - Vérifie si la collection est vide
if ($collection->isEmpty()) { ... }

// isNotEmpty() - Vérifie si la collection n'est pas vide
if ($collection->isNotEmpty()) { ... }

// getAllowedTypes() - Types autorisés
$types = $collection->getAllowedTypes(); // array
```

## 6. Méthodes de transformation

### 6.1 map() - Transformer chaque élément

```php
// Transformer des entiers en chaînes
$numbers = new TypedCollection('int');
$numbers->add(1, 2, 3);

$strings = $numbers->map(fn($item) => "Number: {$item}");
// Résultat : TypedCollection<string> avec ['Number: 1', 'Number: 2', 'Number: 3']

// Transformer des chaînes en Records
$names = new TypedCollection('string');
$names->add('Product A', 'Product B');

$products = $names->map(fn($name) => new ProductRecord(name: $name));
// Résultat : TypedCollection<ProductRecord>

// Transformer des stdClass en scalaires
$objects = new TypedCollection(stdClass::class);
$obj1 = (object) ['value' => 10];
$obj2 = (object) ['value' => 20];
$objects->add($obj1, $obj2);

$values = $objects->map(fn($item) => $item->value * 2);
// Résultat : TypedCollection<int> avec [20, 40]
```

### 6.2 filter() - Filtrer les éléments

```php
$numbers = new TypedCollection('int');
$numbers->add(1, 2, 3, 4, 5);

$filtered = $numbers->filter(fn($item) => $item > 3);
// Résultat : [4, 5]
```

### 6.3 reject() - Rejeter les éléments

```php
$rejected = $numbers->reject(fn($item) => $item > 3);
// Résultat : [1, 2, 3]
```

### 6.4 each() - Exécuter une action sur chaque élément

```php
$sum = 0;
$collection->each(function ($item) use (&$sum) {
    $sum += $item;
});
```

### 6.5 sort() - Trier les éléments

```php
$numbers = new TypedCollection('int');
$numbers->add(3, 1, 2);

$sorted = $numbers->sort(); // TypedCollection [1, 2, 3]
```

### 6.6 sortBy() - Trier par clé ou fonction

```php
// Par fonction
$sorted = $products->sortBy(fn($item) => $item->price);

// Par propriété (pour les Records ou stdClass)
$sorted = $products->sortBy('price');

// Ordre décroissant
$sorted = $products->sortBy('price', descending: true);
```

### 6.7 reverse() - Inverser l'ordre

```php
$reversed = $collection->reverse();
```

### 6.8 shuffle() - Mélanger aléatoirement

```php
$shuffled = $collection->shuffle();
```

## 7. Méthodes de calcul

### 7.1 sum() - Somme des éléments

```php
// Somme directe
$total = $numbers->sum();

// Somme avec callback
$total = $products->sum(fn($product) => $product->price);
```

### 7.2 avg() - Moyenne des éléments

```php
$average = $numbers->avg();
$averagePrice = $products->avg(fn($product) => $product->price);
```

### 7.3 max() / min() - Valeur max/min

```php
$max = $numbers->max();
$min = $numbers->min();

$maxPrice = $products->max(fn($product) => $product->price);
```

## 8. Méthodes de filtrage par type

### 8.1 ofType() - Filtrer par type

```php
$collection = new TypedCollection('int', 'string', 'float', stdClass::class);
$collection->add(42, 'hello', 3.14, 100, 'world', (object) ['test' => true]);

$strings = $collection->ofType('string');
// Résultat : ['hello', 'world']

$records = $collection->ofType(ProductRecord::class);
// Résultat : uniquement les ProductRecord

$stdClasses = $collection->ofType(stdClass::class);
// Résultat : uniquement les stdClass
```

### 8.2 exceptType() - Exclure un type

```php
$withoutInts = $collection->exceptType('int');
// Résultat : tous les éléments sauf les entiers
```

### 8.3 records() - Filtrer les Records

```php
$onlyRecords = $collection->records();
// Résultat : uniquement les éléments qui sont des AbstractRecord
```

### 8.4 scalars() - Filtrer les scalaires

```php
$onlyScalars = $collection->scalars();
// Résultat : uniquement les scalaires (int, string, float, bool, null)
// stdClass et Records sont exclus
```

### 8.5 ofRecord() - Filtrer par classe Record spécifique

```php
$users = $collection->ofRecord(UserRecord::class);
// Résultat : uniquement les UserRecord
```

### 8.6 anyRecord() - Tous les Records (alias de records())

```php
$allRecords = $collection->anyRecord();
```

### 8.7 getTypes() - Analyser les types présents

```php
$types = $collection->getTypes();
// Retourne une TypedCollection<string> des types distincts présents
```

## 9. Méthodes de recherche

### 9.1 where() - Filtrer par propriété

```php
// Pour les Records
$products = new TypedCollection(ProductRecord::class);
$products->add(
    new ProductRecord(name: 'Product A', price: 100),
    new ProductRecord(name: 'Product B', price: 200),
    new ProductRecord(name: 'Product C', price: 100)
);

$cheapProducts = $products->where('price', 100);
// Résultat : Product A et Product C

// Pour les stdClass
$objects = new TypedCollection(stdClass::class);
$obj1 = (object) ['status' => 'active', 'id' => 1];
$obj2 = (object) ['status' => 'inactive', 'id' => 2];
$obj3 = (object) ['status' => 'active', 'id' => 3];
$objects->add($obj1, $obj2, $obj3);

$activeObjects = $objects->where('status', 'active');
// Résultat : $obj1 et $obj3
```

### 9.2 whereNotNull() - Propriété non nulle

```php
$productsWithPrice = $products->whereNotNull('price');
```

### 9.3 whereNull() - Propriété nulle

```php
$productsWithoutPrice = $products->whereNull('price');
```

### 9.4 contains() - Vérifier si un élément existe

```php
if ($collection->contains('banana')) {
    // ...
}

if ($collection->contains($product)) {
    // ...
}
```

### 9.5 containsType() - Vérifier si un type est présent

```php
if ($collection->containsType('int')) {
    // La collection contient au moins un entier
}
```

### 9.6 isOnlyType() - Vérifier si tous les éléments sont d'un type

```php
if ($collection->isOnlyType('int')) {
    // Tous les éléments sont des entiers
}
```

## 10. Méthodes de slicing et pagination

### 10.1 take() - Prendre les n premiers éléments

```php
$firstThree = $collection->take(3);
```

### 10.2 skip() - Ignorer les n premiers éléments

```php
$afterFirstTwo = $collection->skip(2);
```

### 10.3 slice() - Extraire une plage

```php
$middle = $collection->slice(2, 3);
```

### 10.4 nth() - Prendre un élément sur n

```php
$everyOther = $collection->nth(2);   // indices 0, 2, 4...
$offsetOne = $collection->nth(2, 1); // indices 1, 3, 5...
```

### 10.5 values() - Réindexer les clés

```php
$reindexed = $collection->values();
```

## 11. Méthodes de manipulation avancées

### 11.1 unique() - Supprimer les doublons

```php
$unique = $collection->unique();

// Avec callback personnalisé
$uniqueByPrice = $products->unique(fn($item) => $item->price);
```

### 11.2 merge() - Fusionner deux collections

```php
$merged = $collection1->merge($collection2);
```

### 11.3 intersect() - Éléments communs

```php
$common = $collection1->intersect($collection2);
```

### 11.4 diff() - Éléments uniques à la première collection

```php
$unique = $collection1->diff($collection2);
```

### 11.5 flatMap() - Aplatir les collections imbriquées

```php
$nested = new TypedCollection(TypedCollection::class);
$nested->add(new TypedCollection('int')->add(1, 2));
$nested->add(new TypedCollection('int')->add(3, 4));

$flattened = $nested->flatMap(fn($item) => $item);
// Résultat : [1, 2, 3, 4]
```

### 11.6 filterNull() - Supprimer les valeurs null

```php
$withoutNull = $collection->filterNull();
```

### 11.7 random() - Éléments aléatoires

```php
$randomItems = $collection->random(3);
```

## 12. Méthodes de validation et assertions

### 12.1 isHomogeneous() - Tous les éléments du même type ?

```php
if ($collection->isHomogeneous()) {
    // Tous les éléments sont du même type
}
```

### 12.2 isHeterogeneous() - Types différents ?

```php
if ($collection->isHeterogeneous()) {
    // La collection contient des types différents
}
```

### 12.3 assertAllOfType() - Vérifier que tous les éléments sont d'un type

```php
// Retourne la collection si vrai, sinon exception
$collection->assertAllOfType('int');
```

### 12.4 assertNotEmpty() - Vérifier que la collection n'est pas vide

```php
$collection->assertNotEmpty();
```

### 12.5 assertContainsType() - Vérifier qu'un type est présent

```php
$collection->assertContainsType('int');
```

### 12.6 assertAllImplement() - Vérifier que tous implémentent une interface

```php
$collection->assertAllImplement(AbstractRecord::class);
```

### 12.7 assertScalar() - Vérifier que tous sont scalaires

```php
$collection->assertScalar();
```

### 12.8 assertRecords() - Vérifier que tous sont des Records

```php
$collection->assertRecords();
```

### 12.9 validate() - Validation personnalisée

```php
$collection->validate(fn($item, $index) => $item > 0);
```

## 13. Exemples complets d'utilisation

### 13.1 Dans un Record

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly TypedCollection $tags = new TypedCollection('string'),
        public readonly TypedCollection $orders = new TypedCollection(OrderRecord::class),
        public readonly ?TypedCollection $metadata = null,
    ) {}
}

// Utilisation
$user = new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    tags: (new TypedCollection('string'))->add('developer', 'laravel'),
    orders: (new TypedCollection(OrderRecord::class))
        ->add(new OrderRecord(total: 100), new OrderRecord(total: 200)),
);
```

### 13.2 Manipulation avancée

```php
// Calculer la somme des commandes
$total = $user->orders->sum(fn($order) => $order->total);

// Filtrer les commandes > 150
$expensiveOrders = $user->orders->filter(fn($order) => $order->total > 150);

// Trier par prix
$sorted = $user->orders->sortBy('total');

// Vérifier si l'utilisateur a des commandes
if ($user->orders->isNotEmpty()) {
    // ...
}
```

### 13.3 Pipeline de transformations

```php
$result = (new TypedCollection('int'))
    ->add(5, 2, 8, 1, 3)
    ->filter(fn($item) => $item > 2)
    ->sort()
    ->map(fn($item) => $item * 2)
    ->all();

// Résultat : [6, 10, 16]
```

### 13.4 Utilisation avec stdClass (désérialisation JSON)

```php
// Simuler des données désérialisées
$data = json_decode('[{"id":1,"name":"John"},{"id":2,"name":"Jane"}]');

$collection = new TypedCollection(stdClass::class);
foreach ($data as $item) {
    $collection->add($item);
}

// Filtrer par propriété
$filtered = $collection->where('id', 1);
// Résultat : l'objet avec id=1
```

## 14. Récapitulatif des méthodes

| Catégorie | Méthodes |
|-----------|----------|
| **Ajout** | `add()` |
| **Lecture** | `all()`, `toArray()`, `firstItem()`, `first()`, `lastItem()`, `last()` |
| **État** | `count()`, `isEmpty()`, `isNotEmpty()`, `getAllowedTypes()` |
| **Transformation** | `map()`, `filter()`, `reject()`, `each()`, `sort()`, `sortBy()`, `reverse()`, `shuffle()` |
| **Calcul** | `sum()`, `avg()`, `max()`, `min()` |
| **Filtrage par type** | `ofType()`, `exceptType()`, `records()`, `scalars()`, `ofRecord()`, `anyRecord()`, `getTypes()` |
| **Recherche** | `where()`, `whereNotNull()`, `whereNull()`, `contains()`, `containsType()`, `isOnlyType()` |
| **Slicing** | `take()`, `skip()`, `slice()`, `nth()`, `values()` |
| **Manipulation** | `unique()`, `merge()`, `intersect()`, `diff()`, `flatMap()`, `filterNull()`, `random()` |
| **Validation** | `isHomogeneous()`, `isHeterogeneous()` |
| **Assertions** | `assertAllOfType()`, `assertNotEmpty()`, `assertContainsType()`, `assertAllImplement()`, `assertScalar()`, `assertRecords()`, `validate()` |

## 15. Bonnes pratiques

### 15.1 Utiliser `add()` avec plusieurs paramètres

```php
// ✅ Recommandé - Plusieurs éléments en une fois
$collection->add('a', 'b', 'c');

// ⚠️ Acceptable mais plus verbeux
$collection->add('a')->add('b')->add('c');
```

### 15.2 Typer explicitement dans les Records

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $tags = new TypedCollection('string'),  // ✅
        public readonly TypedCollection $orders = new TypedCollection(OrderRecord::class),  // ✅
    ) {}
}
```

### 15.3 Utiliser les assertions pour valider les données

```php
$collection
    ->assertNotEmpty()
    ->assertAllOfType('int')
    ->assertScalar();
```

### 15.4 Chaîner les opérations pour plus de lisibilité

```php
$result = $collection
    ->filter(fn($item) => $item > 0)
    ->sort()
    ->map(fn($item) => $item * 2)
    ->take(5)
    ->all();
```

## 16. Erreurs courantes et solutions

| Erreur | Solution |
|--------|----------|
| `Expected type(s) string, got int` | Vérifier que le type de l'élément correspond au type déclaré |
| `Enum is not allowed in TypedCollection` | Utiliser la valeur scalaire de l'enum (`$enum->value`) |
| `Cannot exclude all allowed types` | S'assurer qu'au moins un type reste après `exceptType()` |
| `Type "X" must extend AbstractRecord` | La classe doit étendre `AbstractRecord` ou être `stdClass` |
| `Object of type "DateTime" is not allowed` | Seuls `stdClass`, `AbstractRecord` et `TypedCollection` sont autorisés |

## 17. Collections utilitaires prêtes à l'emploi

> **Le package fournit des collections utilitaires pré-typées pour les cas d'usage les plus courants.**

### 17.1 Liste des collections utilitaires

| Classe | Type | Description |
|--------|------|-------------|
| `StringTypedCollection` | `string` | Collection de chaînes de caractères |
| `IntTypedCollection` | `int` | Collection d'entiers |
| `FloatTypedCollection` | `float` | Collection de nombres décimaux |
| `BoolTypedCollection` | `bool` | Collection de booléens |
| `NumberTypedCollection` | `int\|float` | Collection de nombres (entiers ou décimaux) |

### 17.2 Utilisation dans un Record

```php
use AndyDefer\Records\Collections\Utility\StringTypedCollection;
use AndyDefer\Records\Collections\Utility\IntTypedCollection;
use AndyDefer\Records\Collections\Utility\FloatTypedCollection;
use AndyDefer\Records\Collections\Utility\BoolTypedCollection;
use AndyDefer\Records\Collections\Utility\NumberTypedCollection;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly StringTypedCollection $tags = new StringTypedCollection(),
        public readonly IntTypedCollection $counts = new IntTypedCollection(),
        public readonly FloatTypedCollection $prices = new FloatTypedCollection(),
        public readonly BoolTypedCollection $flags = new BoolTypedCollection(),
        public readonly NumberTypedCollection $numbers = new NumberTypedCollection(),
    ) {}
}
```

## 18. Création de vos propres collections utilitaires

```php
use AndyDefer\Records\Collections\TypedCollection;
use App\Records\ProductRecord;

final class ProductCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(ProductRecord::class);
    }
    
    public function getTotalPrice(): float
    {
        return $this->sum(fn($product) => $product->price);
    }
    
    public function getInStock(): self
    {
        return $this->filter(fn($product) => $product->stock > 0);
    }
    
    public function filterByCategory(string $category): self
    {
        return $this->filter(fn($product) => $product->category === $category);
    }
}

// Utilisation
$products = new ProductCollection();
$products->add(
    new ProductRecord(name: 'Laptop', price: 999, stock: 5, category: 'electronics'),
    new ProductRecord(name: 'Mouse', price: 29, stock: 0, category: 'electronics'),
    new ProductRecord(name: 'Book', price: 19, stock: 10, category: 'books'),
);

$totalValue = $products->getTotalPrice();  // 1047
$availableProducts = $products->getInStock();  // Laptop et Book
$electronics = $products->filterByCategory('electronics');  // Laptop et Mouse
```

## 19. Règle d'or

> **Dans un Record, les tableaux bruts sont STRICTEMENT INTERDITS. Utilisez TOUJOURS `TypedCollection` pour les collections. La collection garantit le type de chaque élément et offre des méthodes puissantes pour les manipuler.**
>
> **⚠️ Seuls les types suivants sont autorisés : scalaires (int, float, string, bool, null), `AbstractRecord`, `TypedCollection` et `stdClass`. Aucun autre objet n'est accepté.**

```php
// La collection parfaite
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedCollection $tags = new TypedCollection('string'),
        public readonly TypedCollection $items = new TypedCollection(ItemRecord::class),
        public readonly TypedCollection $mixed = new TypedCollection('int', 'float', 'string'),
        public readonly TypedCollection $objects = new TypedCollection(stdClass::class),
    ) {}
}
```