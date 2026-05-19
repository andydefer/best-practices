# TypedRecords : La collection type-safe (Documentation complète)

## 1. Définition

**TypedRecords** est une collection type-safe qui remplace les tableaux bruts dans les Records. Elle garantit que tous les éléments qu'elle contient sont du type déclaré à la construction.

```php
use AndyDefer\BestPractices\Collections\TypedRecords;

// ✅ BON - Collection typée
$tags = new TypedRecords('string');
$tags->add('developer', 'laravel', 'php');

// ❌ MAUVAIS - Tableau brut non typé (INTERDIT dans les Records)
public array $tags = [];
```

## 2. Pourquoi remplacer les tableaux par TypedRecords ?

| Problème des tableaux | Solution avec TypedRecords |
|-----------------------|---------------------------|
| On ne sait pas ce qu'ils contiennent | Le type est explicite (`TypedRecords<string>`) |
| Pas de validation à l'ajout | Validation automatique du type |
| Modification dangereuse | Type-safe garanti |
| Documentation implicite | Documentation explicite |
| Pas de méthodes utilitaires | Nombreuses méthodes de manipulation |

## 3. Types supportés

| Type | Description | Exemple |
|------|-------------|---------|
| `'int'` | Entier | `new TypedRecords('int')` |
| `'string'` | Chaîne de caractères | `new TypedRecords('string')` |
| `'float'` | Nombre à virgule flottante | `new TypedRecords('float')` |
| `'bool'` | Booléen | `new TypedRecords('bool')` |
| `'null'` | Valeur nulle | `new TypedRecords('string', 'null')` |
| `Record::class` | Classe Record | `new TypedRecords(UserRecord::class)` |
| `TypedRecords::class` | Collection imbriquée | `new TypedRecords(TypedRecords::class)` |

### 3.1 Types multiples

```php
// Collection acceptant plusieurs types scalaires
$mixed = new TypedRecords('int', 'float', 'string');
$mixed->add(42, 3.14, 'text');

// Collection acceptant Records et scalaires
$items = new TypedRecords(ProductRecord::class, 'string');
$items->add(new ProductRecord(name: 'Laptop'), 'Just a description');
```

## 4. Création d'une collection

### 4.1 Via le constructeur

```php
$tags = new TypedRecords('string');
$tags->add('developer', 'laravel', 'php');
```

### 4.2 Via le helper `typed_records()`

```php
$tags = typed_records('string');
$tags->add('developer', 'laravel', 'php');

// Avec plusieurs types
$mixed = typed_records('int', 'float', 'string');
```

## 5. Méthodes de base

### 5.1 Ajout d'éléments

```php
// add() - Ajoute un ou plusieurs éléments
$collection->add('hello');                    // un seul élément
$collection->add('a', 'b', 'c');              // plusieurs éléments
```

### 5.2 Lecture des éléments

```php
// all() - Retourne tous les éléments
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
$types = $collection->getAllowedTypes(); // TypedRecords<string>
```

## 6. Méthodes de transformation

### 6.1 map() - Transformer chaque élément

```php
// Transformer des entiers en chaînes
$numbers = new TypedRecords('int');
$numbers->add(1, 2, 3);

$strings = $numbers->map(fn($item) => "Number: {$item}");
// Résultat : TypedRecords<string> avec ['Number: 1', 'Number: 2', 'Number: 3']

// Transformer des chaînes en Records
$names = new TypedRecords('string');
$names->add('Product A', 'Product B');

$products = $names->map(fn($name) => new ProductRecord(name: $name));
// Résultat : TypedRecords<ProductRecord>
```

### 6.2 filter() - Filtrer les éléments

```php
$numbers = new TypedRecords('int');
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
$numbers = new TypedRecords('int');
$numbers->add(3, 1, 2);

$sorted = $numbers->sort(); // [1, 2, 3]
```

### 6.6 sortBy() - Trier par clé ou fonction

```php
// Par fonction
$sorted = $products->sortBy(fn($item) => $item->price);

// Par propriété (pour les Records)
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
$collection = new TypedRecords('int', 'string', 'float');
$collection->add(42, 'hello', 3.14, 100, 'world');

$strings = $collection->ofType('string');
// Résultat : ['hello', 'world']

$records = $collection->ofType(ProductRecord::class);
// Résultat : uniquement les ProductRecord
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
// Retourne une TypedRecords<string> des types distincts présents
```

## 9. Méthodes de recherche

### 9.1 where() - Filtrer par propriété (pour Records)

```php
$products = new TypedRecords(ProductRecord::class);
$products->add(
    new ProductRecord(name: 'Product A', price: 100),
    new ProductRecord(name: 'Product B', price: 200),
    new ProductRecord(name: 'Product C', price: 100)
);

$cheapProducts = $products->where('price', 100);
// Résultat : Product A et Product C
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
$nested = new TypedRecords(TypedRecords::class);
$nested->add(new TypedRecords('int')->add(1, 2));
$nested->add(new TypedRecords('int')->add(3, 4));

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
        public readonly TypedRecords $tags = new TypedRecords('string'),
        public readonly TypedRecords $orders = new TypedRecords(OrderRecord::class),
        public readonly ?TypedRecords $metadata = null,
    ) {}
}

// Utilisation
$user = new UserRecord(
    name: 'John Doe',
    email: 'john@example.com',
    tags: (new TypedRecords('string'))->add('developer', 'laravel'),
    orders: (new TypedRecords(OrderRecord::class))
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
$result = (new TypedRecords('int'))
    ->add(5, 2, 8, 1, 3)
    ->filter(fn($item) => $item > 2)
    ->sort()
    ->map(fn($item) => $item * 2)
    ->all();

// Résultat : [6, 10, 16]
```

## 14. Récapitulatif des méthodes

| Catégorie | Méthodes |
|-----------|----------|
| **Ajout** | `add()`, `concat()` |
| **Lecture** | `all()`, `firstItem()`, `first()`, `lastItem()`, `last()` |
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

### 15.2 Utiliser `concat()` pour les listes dynamiques

```php
// ✅ Pour une liste dynamique
$tags = ['developer', 'laravel', 'php', 'mysql'];
$collection->concat($tags);

// ✅ Pour 3+ éléments statiques
$collection->concat(['a', 'b', 'c', 'd', 'e']);
```

### 15.3 Typer explicitement dans les Records

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedRecords $tags = new TypedRecords('string'),  // ✅
        public readonly TypedRecords $orders = new TypedRecords(OrderRecord::class),  // ✅
    ) {}
}
```

### 15.4 Utiliser les assertions pour valider les données

```php
$collection
    ->assertNotEmpty()
    ->assertAllOfType('int')
    ->assertScalar();
```

### 15.5 Chaîner les opérations pour plus de lisibilité

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
| `Enum is not allowed in TypedRecords` | Utiliser la valeur scalaire de l'enum (`$enum->value`) |
| `Cannot exclude all allowed types` | S'assurer qu'au moins un type reste après `exceptType()` |
| `Type "X" must extend AbstractRecord` | La classe doit étendre `AbstractRecord` |

## 17. Collections utilitaires prêtes à l'emploi

> **Le package fournit des collections utilitaires pré-typées pour les cas d'usage les plus courants.**

### 17.1 Liste des collections utilitaires

| Classe | Type | Description |
|--------|------|-------------|
| `StringTypedRecords` | `string` | Collection de chaînes de caractères |
| `IntTypedRecords` | `int` | Collection d'entiers |
| `FloatTypedRecords` | `float` | Collection de nombres décimaux |
| `BoolTypedRecords` | `bool` | Collection de booléens |
| `NumberTypedRecords` | `int\|float` | Collection de nombres (entiers ou décimaux) |

### 17.2 Utilisation dans un Record

```php
use AndyDefer\BestPractices\Collections\Utility\StringTypedRecords;
use AndyDefer\BestPractices\Collections\Utility\IntTypedRecords;
use AndyDefer\BestPractices\Collections\Utility\FloatTypedRecords;
use AndyDefer\BestPractices\Collections\Utility\BoolTypedRecords;
use AndyDefer\BestPractices\Collections\Utility\NumberTypedRecords;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly StringTypedRecords $tags = new StringTypedRecords(),
        public readonly IntTypedRecords $counts = new IntTypedRecords(),
        public readonly FloatTypedRecords $prices = new FloatTypedRecords(),
        public readonly BoolTypedRecords $flags = new BoolTypedRecords(),
        public readonly NumberTypedRecords $numbers = new NumberTypedRecords(),
    ) {}
}
```

### 17.3 Méthodes spécifiques StringTypedRecords

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `toLowercase(): self` | Convertit toutes les chaînes en minuscules | `$tags->toLowercase()` |
| `toUppercase(): self` | Convertit toutes les chaînes en majuscules | `$tags->toUppercase()` |
| `containsSubstring(string $search): self` | Filtre les chaînes contenant une sous-chaîne | `$tags->containsSubstring('dev')` |
| `startsWith(string $prefix): self` | Filtre les chaînes commençant par un préfixe | `$tags->startsWith('a')` |
| `endsWith(string $suffix): self` | Filtre les chaînes se terminant par un suffixe | `$tags->endsWith('.php')` |
| `filterEmpty(): self` | Supprime les chaînes vides | `$tags->filterEmpty()` |
| `trim(): self` | Supprime les espaces en début et fin | `$tags->trim()` |
| `truncate(int $length, string $suffix = '...'): self` | Limite la longueur des chaînes | `$tags->truncate(10)` |

### 17.4 Méthodes spécifiques IntTypedRecords

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `positive(): self` | Filtre les nombres positifs | `$counts->positive()` |
| `negative(): self` | Filtre les nombres négatifs | `$counts->negative()` |
| `zero(): self` | Filtre les nombres zéro | `$counts->zero()` |
| `nonNegative(): self` | Filtre les nombres >= 0 | `$counts->nonNegative()` |
| `between(int $min, int $max): self` | Filtre les nombres dans un intervalle | `$counts->between(10, 100)` |
| `even(): self` | Filtre les nombres pairs | `$counts->even()` |
| `odd(): self` | Filtre les nombres impairs | `$counts->odd()` |
| `median(): float` | Calcule la médiane | `$counts->median()` |

### 17.5 Méthodes spécifiques FloatTypedRecords

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `positive(): self` | Filtre les nombres positifs | `$prices->positive()` |
| `negative(): self` | Filtre les nombres négatifs | `$prices->negative()` |
| `between(float $min, float $max): self` | Filtre les nombres dans un intervalle | `$prices->between(10.5, 99.9)` |
| `round(int $precision = 0): self` | Arrondit chaque nombre | `$prices->round(2)` |
| `ceil(): self` | Arrondit à l'entier supérieur | `$prices->ceil()` |
| `floor(): self` | Arrondit à l'entier inférieur | `$prices->floor()` |
| `format(int $decimals = 2): self` | Arrondit avec un nombre spécifique de décimales | `$prices->format(2)` |

### 17.6 Méthodes spécifiques BoolTypedRecords

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `trueOnly(): self` | Garde uniquement les valeurs `true` | `$flags->trueOnly()` |
| `falseOnly(): self` | Garde uniquement les valeurs `false` | `$flags->falseOnly()` |
| `countTrue(): int` | Compte le nombre de `true` | `$flags->countTrue()` |
| `countFalse(): int` | Compte le nombre de `false` | `$flags->countFalse()` |
| `allTrue(): bool` | Vérifie si toutes les valeurs sont `true` | `$flags->allTrue()` |
| `allFalse(): bool` | Vérifie si toutes les valeurs sont `false` | `$flags->allFalse()` |
| `anyTrue(): bool` | Vérifie si au moins une valeur est `true` | `$flags->anyTrue()` |
| `anyFalse(): bool` | Vérifie si au moins une valeur est `false` | `$flags->anyFalse()` |

### 17.7 Méthodes spécifiques NumberTypedRecords

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `positive(): self` | Filtre les nombres positifs | `$numbers->positive()` |
| `negative(): self` | Filtre les nombres négatifs | `$numbers->negative()` |
| `zero(): self` | Filtre les nombres zéro (0 ou 0.0) | `$numbers->zero()` |
| `nonNegative(): self` | Filtre les nombres >= 0 | `$numbers->nonNegative()` |
| `between(int\|float $min, int\|float $max): self` | Filtre les nombres dans un intervalle | `$numbers->between(10, 100)` |
| `average(): float` | Calcule la moyenne | `$numbers->average()` |

## 18. Création de vos propres collections utilitaires

```php
use AndyDefer\BestPractices\Collections\TypedRecords;
use App\Records\ProductRecord;

final class ProductCollection extends TypedRecords
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

> **Dans un Record, les tableaux bruts sont STRICTEMENT INTERDITS. Utilisez TOUJOURS `TypedRecords` pour les collections. La collection garantit le type de chaque élément et offre des méthodes puissantes pour les manipuler.**

```php
// La collection parfaite
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly TypedRecords $tags = new TypedRecords('string'),
        public readonly TypedRecords $items = new TypedRecords(ItemRecord::class),
        public readonly TypedRecords $mixed = new TypedRecords('int', 'float', 'string'),
    ) {}
}
```