# DataObject & StrictDataObject - Documentation Officielle

## Table des matières

1. [Définition et rôle](#1-définition-et-rôle)
2. [Les deux implémentations](#2-les-deux-implémentations)
3. [Règle fondamentale : Quel DataObject pour quelle classe ?](#3-règle-fondamentale--quel-dataobject-pour-quelle-classe-)
4. [Pourquoi DataObject ?](#4-pourquoi-dataobject-)
5. [Normalisation des clés](#5-normalisation-des-clés)
6. [Conversion des tableaux imbriqués](#6-conversion-des-tableaux-imbriqués)
7. [Opérations de transformation](#7-opérations-de-transformation)
8. [Rôle dans Hydratable](#8-rôle-dans-hydratable)
9. [Rôle dans les réponses API et JSON](#9-rôle-dans-les-réponses-api-et-json)
10. [API complète](#10-api-complète)
11. [Exemples concrets](#11-exemples-concrets)
12. [Ce que DataObject n'est PAS](#12-ce-que-dataobject-nest-pas)
13. [Récapitulatif](#13-récapitulatif)

---

## 1. Définition et rôle

`AbstractDataObject` est une classe abstraite qui sert de base à des conteneurs de données **immutables**. Elle fournit :
- Une **normalisation des clés** personnalisable
- Une **conversion récursive** des tableaux imbriqués
- Des **méthodes de transformation** (`with`, `merge`, `without`)
- L'implémentation de l'interface `Transformable`

Le package fournit **deux implémentations concrètes** avec des comportements de normalisation différents :

```php
use AndyDefer\DomainStructures\Utils\DataObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// DataObject : normalise les clés en camelCase
$data = DataObject::from([
    'user_id' => 123,
    'first_name' => 'John'
]);
echo $data->userId;      // 123 (normalisé)
echo $data->first_name;  // "John" (recherche normalisée)

// StrictDataObject : préserve la casse originale
$strict = StrictDataObject::from([
    'user_id' => 123,
    'first_name' => 'John'
]);
echo $strict->user_id;     // 123 (casse préservée)
echo $strict->userId;      // null (n'existe pas)
```

---

## 2. Les deux implémentations

### 2.1. DataObject (normalisation camelCase)

`DataObject` normalise toutes les clés en `camelCase`. Idéal pour les **Data** (DTO de réponse API).

```php
use AndyDefer\DomainStructures\Utils\DataObject;

$data = new DataObject([
    'user_id' => 123,
    'user_email' => 'john@example.com',
    'user_first_name' => 'John',
]);

// Accès en camelCase
echo $data->userId;        // 123
echo $data->userEmail;     // 'john@example.com'
echo $data->userFirstName; // 'John'

// Accès snake_case fonctionne aussi (recherche normalisée)
echo $data->user_id;       // 123

// Stockage interne en camelCase
$data->toArray();
// ['userId' => 123, 'userEmail' => 'john@example.com', 'userFirstName' => 'John']
```

### 2.2. StrictDataObject (préservation casse)

`StrictDataObject` préserve la casse originale des clés. Idéal pour les **Records** (entités métier en snake_case).

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$data = new StrictDataObject([
    'user_id' => 123,
    'user_email' => 'john@example.com',
    'user_first_name' => 'John',
]);

// Accès uniquement avec la casse originale
echo $data->user_id;        // 123
echo $data->user_email;     // 'john@example.com'
echo $data->user_first_name;// 'John'

// Accès camelCase ne fonctionne PAS
echo $data->userId;         // null (n'existe pas)

// Stockage interne inchangé
$data->toArray();
// ['user_id' => 123, 'user_email' => 'john@example.com', 'user_first_name' => 'John']
```

---

## 3. Règle fondamentale : Quel DataObject pour quelle classe ? (⚠️ RÈGLE ABSOLUE)

> **⚠️ Le choix du DataObject est DICTÉ par le type de classe que vous hydratez.**

| Type de classe | Convention | DataObject à utiliser | Raison |
|----------------|------------|----------------------|--------|
| **`AbstractRecord`** | `snake_case` | `StrictDataObject` | Les clés doivent correspondre EXACTEMENT aux noms des paramètres |
| **`AbstractData`** | `camelCase` | `DataObject` | Normalisation automatique snake_case → camelCase |
| **`AbstractValueObject`** | `camelCase` | `DataObject` | Normalisation automatique snake_case → camelCase |

### 3.1. Pourquoi cette distinction ?

```php
// Record (snake_case) - DOIT utiliser StrictDataObject
final class UserRecord extends AbstractRecord
{
    use Hydratable;  // from() utilise StrictDataObject
    
    public function __construct(
        public readonly int $user_id,      // snake_case
        public readonly string $user_name, // snake_case
    ) {}
}

// Hydratation : les clés doivent correspondre EXACTEMENT
$user = UserRecord::from([
    'user_id' => 123,   // ✅ exact match
    'user_name' => 'John', // ✅ exact match
]);

// ❌ Cela ne fonctionnerait pas avec DataObject (normalisation en camelCase)
// car DataObject transformerait 'user_id' en 'userId', qui ne correspond pas
```

```php
// Data (camelCase) - DOIT utiliser DataObject
final class UserData extends AbstractData
{
    use Hydratable;  // from() utilise DataObject
    
    public function __construct(
        public readonly int $userId,    // camelCase
        public readonly string $userName, // camelCase
    ) {}
}

// Hydratation : les clés peuvent être en snake_case (normalisées)
$userData = UserData::from([
    'user_id' => 123,    // sera normalisé en 'userId'
    'user_name' => 'John', // sera normalisé en 'userName'
]);

// ✅ Cela fonctionne grâce à DataObject qui normalise snake_case → camelCase
```

### 3.2. Tableau récapitulatif

| Si vous hydratez... | Utilisez... | Parce que... |
|---------------------|-------------|---------------|
| `UserRecord` (snake_case) | `StrictDataObject` | Préserve `user_id`, `user_name` |
| `ProductRecord` (snake_case) | `StrictDataObject` | Préserve `product_id`, `product_name` |
| `UserData` (camelCase) | `DataObject` | Normalise `user_id` → `userId` |
| `ProductData` (camelCase) | `DataObject` | Normalise `product_id` → `productId` |
| `EmailAddress` (camelCase) | `DataObject` | Normalise `email_value` → `emailValue` |

---

## 4. Pourquoi DataObject ?

### 4.1. Le problème des sources hétérogènes

```php
// ❌ SANS DataObject - Hydratation manuelle et fragile
class UserRecord extends AbstractRecord
{
    public static function fromApi(array $data): static
    {
        return new static(
            user_id: $data['user_id'] ?? 0,
            user_name: $data['user_name'] ?? '',  // mapping manuel
            user_email: $data['user_email'] ?? '' // répétitif
        );
    }
}

// ✅ AVEC DataObject - Hydratation automatique
class UserRecord extends AbstractRecord
{
    use Hydratable;  // from() fonctionne automatiquement avec StrictDataObject
}

// Une seule méthode pour toutes les sources
$user = UserRecord::from($apiData);           // array
$user = UserRecord::fromJson($apiJson);       // string JSON
$user = UserRecord::from($apiObject);         // stdClass
```

### 4.2. Ce que DataObject résout

| Problème | Solution |
|----------|----------|
| Sources multiples (array, object, JSON) | `from()` accepte tout type |
| Accès sécurisé aux clés | `get()` avec valeur par défaut |
| Tableaux imbriqués difficiles à manipuler | Conversion récursive en objet |
| Pas de méthodes utilitaires | `with()`, `merge()`, `without()` |

---

## 5. Normalisation des clés

### 5.1. DataObject (normalisation camelCase)

```php
$data = new DataObject([
    'user_id' => 123,
    'first_name' => 'John',
    'email_verified_at' => '2024-01-01'
]);

// Stockage interne en camelCase
$data->toArray();
// ['userId' => 123, 'firstName' => 'John', 'emailVerifiedAt' => '2024-01-01']

// Accès normalisé (camelCase et snake_case fonctionnent)
echo $data->userId;           // 123 (camelCase)
echo $data->user_id;          // 123 (snake_case → recherche)
echo $data->firstName;        // "John"
echo $data->first_name;       // "John"
echo $data->emailVerifiedAt;  // "2024-01-01"
echo $data->email_verified_at;// "2024-01-01"
```

### 5.2. StrictDataObject (préservation casse)

```php
$data = new StrictDataObject([
    'user_id' => 123,
    'first_name' => 'John',
    'email_verified_at' => '2024-01-01'
]);

// Stockage interne inchangé
$data->toArray();
// ['user_id' => 123, 'first_name' => 'John', 'email_verified_at' => '2024-01-01']

// Accès uniquement avec la casse originale
echo $data->user_id;           // 123
echo $data->first_name;        // "John"
echo $data->email_verified_at; // "2024-01-01"

// Accès camelCase ne fonctionne PAS
echo $data->userId;            // null
echo $data->firstName;         // null
```

---

## 6. Conversion des tableaux imbriqués

Les deux implémentations convertissent récursivement les tableaux associatifs imbriqués en instances de la même classe.

```php
// DataObject
$data = new DataObject([
    'user' => [
        'profile' => [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]
    ],
    'tags' => ['premium', 'vip'],  // Liste indexée → reste array
]);

echo $data->user->profile->firstName; // "John"
echo $data->user->profile->last_name; // "Doe"

// StrictDataObject
$strict = new StrictDataObject([
    'user' => [
        'profile' => [
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]
    ],
]);

echo $strict->user->profile->first_name; // "John"
echo $strict->user->profile->last_name;  // "Doe"
```

---

## 7. Opérations de transformation

Les deux implémentations fournissent les mêmes méthodes de transformation (immutables).

```php
$user = new DataObject(['name' => 'John', 'age' => 30]);

// with() - Ajouter/Modifier une propriété
$updated = $user->with('age', 31);
$withEmail = $user->with('email', 'john@example.com');

// merge() - Fusionner avec un tableau
$merged = $user->merge([
    'email' => 'john@example.com',
    'age' => 31
]);

// without() - Supprimer des propriétés
$reduced = $user->without('email', 'age');

// L'original reste inchangé
echo $user->age;     // 30
echo $updated->age;  // 31
```

---

## 8. Rôle dans Hydratable

**C'est l'utilisation la plus importante.** Le trait `Hydratable` utilise **automatiquement** le bon DataObject selon la classe qui l'utilise.

### 8.1. Comment `Hydratable` choisit le bon DataObject

Le trait `Hydratable` est intelligent : il détecte automatiquement quelle classe l'utilise et applique le DataObject approprié.

```php
trait Hydratable
{
    public static function from(mixed $source): static
    {
        // 🔍 Détection automatique du type de classe
        if (is_subclass_of(static::class, AbstractRecord::class)) {
            // Les Records utilisent StrictDataObject (snake_case)
            $dataObject = StrictDataObject::from($source);
        } else {
            // Les Data et Value Objects utilisent DataObject (camelCase)
            $dataObject = DataObject::from($source);
        }
        
        // Récupération des valeurs par nom de paramètre
        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();
            $rawValue = $dataObject->get($paramName);
            $parameters[] = self::convertToType($rawValue, $paramType);
        }
        
        return new static(...$parameters);
    }
}
```

### 8.2. Exemple concret : Record (StrictDataObject)

```php
// Record (snake_case) - StrictDataObject est utilisé automatiquement
final class UserRecord extends AbstractRecord
{
    use Hydratable;  // from() détecte AbstractRecord → StrictDataObject
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
}

// Hydratation (les clés doivent correspondre exactement)
$user = UserRecord::from([
    'user_id' => 123,      // ✅ exact match
    'user_name' => 'John', // ✅ exact match
]);
```

### 8.3. Exemple concret : Data (DataObject)

```php
// Data (camelCase) - DataObject est utilisé automatiquement
final class UserData extends AbstractData
{
    use Hydratable;  // from() détecte AbstractData → DataObject
    
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
    ) {}
}

// Hydratation (clés normalisées automatiquement)
$userData = UserData::from([
    'user_id' => 123,    // sera normalisé en 'userId'
    'user_name' => 'John', // sera normalisé en 'userName'
]);
```

### 8.4. Schéma récapitulatif

| Type de classe | Hérite de | DataObject utilisé | Convention |
|----------------|-----------|-------------------|------------|
| **Record** | `AbstractRecord` | `StrictDataObject` | `snake_case` (préservée) |
| **Data** | `AbstractData` | `DataObject` | `camelCase` (normalisée) |
| **Value Object** | `AbstractValueObject` | `DataObject` | `camelCase` (normalisée) |

---

## 9. Rôle dans les réponses API et JSON

### 9.1. Les 3 méthodes d'hydratation depuis JSON

```php
$jsonResponse = '{
    "user_id": 123,
    "user_name": "John",
    "user_email": "john@example.com"
}';

// Méthode 1 : Décodage manuel + from() (RECOMMANDÉ)
$data = json_decode($jsonResponse, true);
$user = UserRecord::from($data);

// Méthode 2 : Via le bon DataObject
$user = UserRecord::from(StrictDataObject::fromJson($jsonResponse));

// Méthode 3 : Via Hydratable (LE PLUS SIMPLE)
$user = UserRecord::fromJson($jsonResponse);
```

### 9.2. Pourquoi la méthode 3 est recommandée

```php
// La méthode fromJson() offerte par Hydratable :
// 1. Valide le JSON
// 2. Décode automatiquement
// 3. Utilise le bon DataObject (StrictDataObject pour Record, DataObject pour Data)
// 4. Une ligne de code

// Pour les Records (snake_case)
$user = UserRecord::fromJson($jsonResponse);

// Pour les Data (camelCase)
$userData = UserData::fromJson($jsonResponse);
```

### 9.3. APIs externes

```php
class ExternalApiService
{
    public function getUser(int $id): UserRecord
    {
        $response = $this->httpClient->get("/users/{$id}");
        
        // Hydratation directe depuis JSON (snake_case avec StrictDataObject)
        return UserRecord::fromJson($response);
    }
    
    public function getProductData(int $id): ProductData
    {
        $response = $this->httpClient->get("/products/{$id}");
        
        // Hydratation directe depuis JSON (camelCase avec DataObject)
        return ProductData::fromJson($response);
    }
}
```

---

## 10. API complète

### 10.1. Méthodes statiques (héritées de Transformable)

```php
// Crée une instance depuis n'importe quelle source
public static function from(mixed $source): static

// Crée une instance depuis JSON
public static function fromJson(string $json): static

// Hydrate une collection d'objets
public static function collect(
    iterable $sources, 
    string $collectionClass = TypedCollection::class
): AbstractTypedCollection
```

### 10.2. Méthodes d'instance

```php
// Transformation (créent de nouvelles instances)
public function with(string $key, mixed $value): static
public function merge(array $data): static
public function without(string ...$keys): static

// Lecture
public function get(string $name, mixed $default = null): mixed
public function has(string $name): bool
public function toArray(): array

// Magic methods
public function __get(string $name): mixed
public function __isset(string $name): bool
public function __toString(): string

// ArrayAccess (lecture seule)
public function offsetExists(mixed $offset): bool
public function offsetGet(mixed $offset): mixed
```

---

## 11. Exemples concrets

### 11.1. Record avec StrictDataObject (snake_case)

```php
final class UserRecord extends AbstractRecord
{
    use Hydratable;  // from() utilise StrictDataObject
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly UserRole $user_role,
    ) {}
}

// Hydratation (les clés doivent correspondre exactement)
$user = UserRecord::from([
    'user_id' => 123,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com',
    'user_role' => 'admin'
]);

// Accès direct (snake_case)
echo $user->user_id;     // 123
echo $user->user_name;   // "John Doe"
echo $user->user_role;   // UserRole::ADMIN
```

### 11.2. Data avec DataObject (camelCase)

```php
final class UserData extends AbstractData
{
    use Hydratable;  // from() utilise DataObject
    
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $userRole,
    ) {}
    
    public static function fromRecord(UserRecord $record): self
    {
        return new self(
            userId: $record->user_id,
            userName: $record->user_name,
            userEmail: $record->user_email,
            userRole: $record->user_role->value,
        );
    }
}

// Hydratation (clés normalisées automatiquement)
$userData = UserData::from([
    'user_id' => 123,     // sera normalisé en 'userId'
    'user_name' => 'John Doe', // sera normalisé en 'userName'
    'user_email' => 'john@example.com',
    'user_role' => 'admin'
]);

// Accès camelCase
echo $userData->userId;    // 123
echo $userData->userName;  // "John Doe"
```

### 11.3. API REST complète

```php
class UserController
{
    public function store(Request $request): JsonResponse
    {
        $json = $request->getContent();
        
        // Hydratation du Record (snake_case avec StrictDataObject)
        $user = UserRecord::fromJson($json);
        
        // Sauvegarde
        $saved = $this->userRepository->save($user);
        
        // Transformation en Data (camelCase) pour la réponse
        $userData = UserData::fromRecord($saved);
        
        return ResponseFactory::json($userData, 201);
    }
    
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if ($user === null) {
            abort(404, 'User not found');
        }
        
        // Transformation Record → Data (snake_case → camelCase)
        $userData = UserData::fromRecord($user);
        
        return ResponseFactory::json($userData);
    }
}
```

---

## 12. Ce que DataObject n'est PAS

### 12.1. ❌ Pas un mécanisme d'immutabilité totale

```php
// DataObject ne protège PAS les objets imbriqués
$nestedObject = new stdClass();
$nestedObject->value = 42;

$data = new DataObject(['nested' => $nestedObject]);

// ⚠️ Ceci est possible !
$data->nested->value = 100;  // Modifie l'objet imbriqué !

// La seule protection concerne l'assignation directe
$data->newProperty = 'value';  // ❌ RuntimeException
```

### 12.2. ❌ Pas un Value Object

```php
// DataObject n'a pas d'égalité structurelle
$data1 = new DataObject(['name' => 'John']);
$data2 = new DataObject(['name' => 'John']);

$data1 == $data2;   // false (pas de comparaison structurelle)
$data1 === $data2;  // false (instances différentes)
```

### 12.3. ❌ Pas un Record ou Data métier

```php
// Pour les entités métier (snake_case) : utilisez AbstractRecord
// Pour les DTO de réponse (camelCase) : utilisez AbstractData
// Pour les Value Objects (camelCase) : utilisez AbstractValueObject

// DataObject/StrictDataObject sont UNIQUEMENT pour :
// - Normalisation de sources externes
// - Pont entre API et hydratation
// - Configuration dynamique
// - Ne sont JAMAIS exposés directement dans l'API
```

---

## 13. Récapitulatif

### 13.1. Ce que DataObject fait

| Fonctionnalité | DataObject | StrictDataObject |
|----------------|------------|------------------|
| Normalisation camelCase | ✅ Oui | ❌ Non |
| Préservation casse | ❌ Non | ✅ Oui |
| Accès camelCase | ✅ Oui | ❌ Non |
| Accès snake_case | ✅ Oui (recherche) | ✅ Oui (exact) |
| Conversion tableaux imbriqués | ✅ Oui | ✅ Oui |
| Méthodes with/merge/without | ✅ Oui | ✅ Oui |
| get() avec valeur par défaut | ✅ Oui | ✅ Oui |
| Support JSON via fromJson() | ✅ Oui | ✅ Oui |

### 13.2. Bonnes pratiques pour l'hydratation

```php
// ✅ RECOMMANDÉ - Record (snake_case) avec fromJson()
$user = UserRecord::fromJson($jsonResponse);

// ✅ RECOMMANDÉ - Data (camelCase) avec fromJson()
$userData = UserData::fromJson($jsonResponse);

// ✅ ACCEPTABLE - Décodage manuel si besoin de validation
$data = json_decode($jsonResponse, true);
if (json_last_error() === JSON_ERROR_NONE) {
    $user = UserRecord::from($data);
}

// ❌ À ÉVITER - Passer JSON directement à from() (ne fonctionne pas)
$user = UserRecord::from($jsonResponse);  // String JSON != array
```

### 13.3. Règle d'or (⚠️ STRICTISSIME)

> **`StrictDataObject` est UNIQUEMENT pour les `AbstractRecord` (propriétés en snake_case).**
>
> **`DataObject` est UNIQUEMENT pour les `AbstractData` et `AbstractValueObject` (propriétés en camelCase).**
>
> **⚠️ Ne jamais utiliser `DataObject` pour hydrater un Record.**
> **⚠️ Ne jamais utiliser `StrictDataObject` pour hydrater un Data ou un Value Object.**
>
> **Le trait `Hydratable` détecte automatiquement le bon DataObject. Utilisez `fromJson()` ou `from()` sans vous soucier du DataObject sous-jacent.**

```php
// ✅ Record → StrictDataObject (automatique)
final class UserRecord extends AbstractRecord
{
    use Hydratable;  // StrictDataObject est utilisé automatiquement
    
    public function __construct(
        public readonly int $user_id,   // snake_case
        public readonly string $user_name,
    ) {}
}

// ✅ Data → DataObject (automatique)
final class UserData extends AbstractData
{
    use Hydratable;  // DataObject est utilisé automatiquement
    
    public function __construct(
        public readonly int $userId,    // camelCase
        public readonly string $userName,
    ) {}
}

// ✅ Value Object → DataObject (automatique)
final class EmailAddress extends AbstractValueObject
{
    use Hydratable;  // DataObject est utilisé automatiquement
    
    public function __construct(
        public readonly string $value,  // camelCase
    ) {}
}

// Hydratation (le bon DataObject est choisi automatiquement)
$user = UserRecord::from(['user_id' => 123, 'user_name' => 'John']);
$userData = UserData::from(['user_id' => 123, 'user_name' => 'John']);
$email = EmailAddress::from(['value' => 'john@example.com']);
```
---