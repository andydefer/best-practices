Voici le document de principes d'usage du trait Hydratable, avec les Records en snake_case :

# Principe d'usage du trait Hydratable

## 1. Définition

Le trait `Hydratable` est un système d'**hydratation automatique** qui analyse le constructeur d'une classe et remplit ses propriétés à partir de sources diverses (tableaux, objets, JSON, DataObject).

```
Source (array, object, JSON) → Hydratable → Objet typé (Record, Data, Value Object)
```

```php
use AndyDefer\DomainStructures\Traits\Hydratable;

final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// Hydratation automatique (snake_case)
$user = UserRecord::from([
    'user_id' => 123,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com'
]);

echo $user->user_name;  // "John Doe"
```

---

## 2. Problématique à laquelle Hydratable répond

| Problème | Solution |
|----------|----------|
| **Mapping manuel des clés** | Normalisation automatique via DataObject |
| **Conversion de types** | Conversion automatique (int, float, string, bool) |
| **Gestion des enums** | Conversion automatique via tryFrom() |
| **Objets imbriqués** | Hydratation récursive des Transformable |
| **Valeurs par défaut** | Utilisation des defaults du constructeur |
| **Sources multiples** | Interface unifiée (array, object, JSON) |
| **Code répétitif** | Une seule méthode from() pour tout |

### 2.1. Le problème de l'hydratation manuelle

```php
// ❌ SANS Hydratable - Hydratation manuelle et répétitive
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'] ?? 0,
            user_name: $data['user_name'] ?? '',
            user_email: $data['user_email'] ?? ''
        );
    }
    
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true);
        return self::fromArray($data);
    }
}

// ✅ AVEC Hydratable - Automatique et standardisé
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// Une seule méthode pour toutes les sources
$user = UserRecord::from($array);
$user = UserRecord::fromJson($json);
$user = UserRecord::from($object);
```

---

## 3. Fonctionnement général

### 3.1. Architecture

```
Source (array, object, JSON)
    ↓
StrictDataObject::from() → Préservation de la casse (snake_case)
    ↓
Analyse du constructeur via Reflection
    ↓
Pour chaque paramètre :
    ↓
Récupération de la valeur dans StrictDataObject
    ↓
Conversion du type (si nécessaire)
    ↓
Gestion des cas (absent, null, default)
    ↓
Construction de l'objet
```

### 3.2. Flux de décision

```php
// Pour chaque paramètre du constructeur
$rawValue = getValueFromDataObject($paramName);
$isAbsent = ($rawValue === VALUE_ABSENT);

if ($isAbsent && $parameter->isDefaultValueAvailable()) {
    // Cas 1: Valeur absente mais défaut disponible
    $parameters[] = $parameter->getDefaultValue();
} 
elseif ($value === null && $parameter->allowsNull()) {
    // Cas 2: null explicite autorisé
    $parameters[] = null;
}
elseif ($value !== null) {
    // Cas 3: Valeur normale
    $parameters[] = $value;
}
else {
    // Cas 4: Valeur requise manquante → Exception
    throw new RuntimeException(...);
}
```

---

## 4. Méthodes disponibles

### 4.1. `from(mixed $source): static`

Crée une instance à partir de n'importe quelle source.

**Sources acceptées :**
- `array` : Tableau associatif
- `object` : Objet standard ou StrictDataObject
- `StrictDataObject` : Déjà normalisé

```php
// Depuis un tableau (clés snake_case)
$user = UserRecord::from([
    'user_id' => 123,
    'user_name' => 'John Doe'
]);

// Depuis un objet
$obj = (object) ['user_id' => 123, 'user_name' => 'John Doe'];
$user = UserRecord::from($obj);

// Depuis un StrictDataObject
$dataObject = StrictDataObject::from(['user_id' => 123, 'user_name' => 'John Doe']);
$user = UserRecord::from($dataObject);
```

### 4.2. `fromJson(string $json): static`

Crée une instance à partir d'une chaîne JSON.

```php
$json = '{"user_id": 123, "user_name": "John Doe"}';
$user = UserRecord::fromJson($json);

// Gère les erreurs JSON
try {
    $user = UserRecord::fromJson('invalid json');
} catch (RuntimeException $e) {
    // Invalid JSON: Syntax error
}
```

### 4.3. `collect(iterable $sources, string $collectionClass = TypedCollection::class): AbstractTypedCollection`

Hydrate une collection d'objets.

```php
$sources = [
    ['user_id' => 1, 'user_name' => 'John'],
    ['user_id' => 2, 'user_name' => 'Jane'],
    ['user_id' => 3, 'user_name' => 'Bob']
];

// Collection standard
$users = UserRecord::collect($sources);

// Collection personnalisée
$users = UserRecord::collect($sources, UserCollection::class);
```

---

## 5. Règle fondamentale : Convention snake_case (⚠️ STRICTE)

> **⚠️ Les Records utilisent `StrictDataObject` qui préserve la casse. Les clés de la source DOIVENT correspondre EXACTEMENT aux noms des paramètres du constructeur (en `snake_case`).**

```php
// ✅ BON - Record avec propriétés snake_case
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// ✅ BON - Hydratation avec clés snake_case
$user = UserRecord::from([
    'user_id' => 123,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com'
]);

// ❌ MAUVAIS - Clés camelCase (ne correspondent pas)
$user = UserRecord::from([
    'userId' => 123,      // ❌ ne correspond pas à $user_id
    'userName' => 'John Doe', // ❌ ne correspond pas à $user_name
    'userEmail' => 'john@example.com'  // ❌ ne correspond pas à $user_email
]);
```

---

## 6. Gestion des types

### 6.1. Types scalaires

```php
final class ProductRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $product_id,      // int
        public readonly string $product_name, // string
        public readonly float $product_price, // float
        public readonly bool $is_active       // bool
    ) {}
}

// Hydratation avec conversion automatique
$product = ProductRecord::from([
    'product_id' => '123',        // string → int
    'product_name' => 'Laptop',
    'product_price' => '99.99',   // string → float
    'is_active' => 'true'         // string → bool
]);
```

### 6.2. Enums

```php
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly UserStatus $user_status
    ) {}
}

// Hydratation automatique de l'enum
$user = UserRecord::from([
    'user_id' => 123,
    'user_status' => 'active'  // string → UserStatus::ACTIVE
]);

echo $user->user_status->value;  // 'active'
```

### 6.3. Value Objects (Transformable)

```php
final class EmailAddress extends AbstractValueObject implements Transformable
{
    public function __construct(private readonly string $value) {}
    
    public static function from(mixed $source): static
    {
        return new static((string) $source);
    }
    
    public function getValue(): string { return $this->value; }
}

final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly EmailAddress $user_email  // Transformable
    ) {}
}

// Hydratation récursive
$user = UserRecord::from([
    'user_id' => 123,
    'user_email' => 'john@example.com'  // string → EmailAddress
]);

echo get_class($user->user_email);  // EmailAddress
```

### 6.4. Collections typées

```php
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly StringTypedCollection $user_tags  // Collection
    ) {}
}

// Hydratation
$user = UserRecord::from([
    'user_id' => 123,
    'user_tags' => ['premium', 'vip']  // array → StringTypedCollection
]);
```

### 6.5. Records imbriqués

```php
final class AddressRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country
    ) {}
}

final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly AddressRecord $user_address
    ) {}
}

// Hydratation récursive
$user = UserRecord::from([
    'user_id' => 123,
    'user_address' => [
        'street' => '123 Main St',
        'city' => 'Paris',
        'country' => 'France'
    ]
]);

echo $user->user_address->city;  // 'Paris'
```

---

## 7. Gestion des valeurs absentes et null

### 7.1. Quatre cas de figure

```php
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly string $user_name,      // Requis
        public readonly string $user_email,     // Requis
        public readonly ?string $user_phone,    // Nullable
        public readonly string $user_country = 'FR'  // Default
    ) {}
}
```

#### Cas 1: Valeur absente avec défaut

```php
$user = UserRecord::from([
    'user_name' => 'John',
    'user_email' => 'john@example.com'
]);
// $user_country → 'FR' (valeur par défaut)
```

#### Cas 2: null explicite autorisé

```php
$user = UserRecord::from([
    'user_name' => 'John',
    'user_email' => 'john@example.com',
    'user_phone' => null  // null explicite
]);
// $user_phone → null
```

#### Cas 3: Valeur normale

```php
$user = UserRecord::from([
    'user_name' => 'John',
    'user_email' => 'john@example.com',
    'user_country' => 'BE'  // Valeur fournie
]);
// $user_country → 'BE' (surcharge le default)
```

#### Cas 4: Requis manquant → Exception

```php
// ❌ Exception: Missing required parameter "user_email"
$user = UserRecord::from(['user_name' => 'John']);
```

### 7.2. Distinction null vs absent

```php
// Source avec null explicite
$data = ['user_name' => 'John', 'user_email' => null];
$user = UserRecord::from($data);
// $user_email → null (OK car nullable)

// Source sans la clé
$data = ['user_name' => 'John'];
$user = UserRecord::from($data);
// ❌ Exception car user_email requis et pas de default
```

---

## 8. Cas d'utilisation

### 8.1. API Response

```php
final class ApiService
{
    public function getUser(int $id): UserRecord
    {
        $response = $this->httpClient->get("/users/{$id}");
        
        // Hydratation directe depuis JSON (snake_case)
        return UserRecord::fromJson($response->getBody());
    }
}
```

### 8.2. Repository

```php
final class UserRepository
{
    public function find(int $id): ?UserRecord
    {
        $row = $this->db->fetchAssoc(
            'SELECT user_id, user_name, user_email FROM users WHERE user_id = ?',
            [$id]
        );
        
        return $row ? UserRecord::from($row) : null;
    }
    
    public function findAll(): UserCollection
    {
        $rows = $this->db->fetchAllAssoc('SELECT user_id, user_name, user_email FROM users');
        
        return UserRecord::collect($rows, UserCollection::class);
    }
}
```

### 8.3. Formulaire

```php
final class UserController
{
    public function store(Request $request): JsonResponse
    {
        // Hydratation depuis les données du formulaire
        $user = UserRecord::from($request->all());
        
        $this->userRepository->save($user);
        
        return response()->json($user);
    }
}
```

---

## 9. Exemples concrets

### 9.1. Structure complète

```php
// Définitions
final class AddressRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $country
    ) {}
}

final class OrderItemRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $product_id,
        public readonly int $quantity,
        public readonly float $price
    ) {}
}

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
}

final class OrderRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $order_id,
        public readonly AddressRecord $shipping_address,
        public readonly TypedCollection $items,  // OrderItemRecord[]
        public readonly OrderStatus $status,
        public readonly ?string $notes = null
    ) {}
}

// Source JSON (snake_case)
$json = '{
    "order_id": 12345,
    "shipping_address": {
        "street": "123 Main St",
        "city": "Paris",
        "country": "France"
    },
    "items": [
        {"product_id": 1, "quantity": 2, "price": 49.99},
        {"product_id": 2, "quantity": 1, "price": 99.99}
    ],
    "status": "paid",
    "notes": "Gift wrap please"
}';

// Hydratation complète
$order = OrderRecord::fromJson($json);

echo $order->order_id;                     // 12345
echo $order->shipping_address->city;       // "Paris"
echo $order->items->count();               // 2
echo $order->status->value;                // "paid"
```

### 9.2. Gestion des erreurs

```php
try {
    $user = UserRecord::from($malformedData);
} catch (RuntimeException $e) {
    logger()->error('Hydration failed', [
        'error' => $e->getMessage(),
        'data' => $malformedData
    ]);
    
    throw new ValidationException('Invalid user data');
}
```

### 9.3. Validation post-hydratation

```php
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly string $user_email
    ) {
        // Validation post-hydratation
        if (!filter_var($this->user_email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }
    }
}
```

---

## 10. Bonnes pratiques

### 10.1. Toujours utiliser les types

```php
// ✅ BON - Types explicites
public function __construct(
    public readonly int $user_id,
    public readonly string $user_name
) {}

// ❌ MAUVAIS - Pas de type
public function __construct(
    public readonly $user_id,
    public readonly $user_name
) {}
```

### 10.2. Convention snake_case

```php
// ✅ BON - Propriétés snake_case
public function __construct(
    public readonly int $user_id,
    public readonly string $user_name,
    public readonly string $user_email
) {}

// ❌ MAUVAIS - camelCase
public function __construct(
    public readonly int $userId,
    public readonly string $userName
) {}
```

### 10.3. Définir des valeurs par défaut quand pertinent

```php
// ✅ BON - Valeurs par défaut
public function __construct(
    public readonly string $user_name,
    public readonly string $user_country = 'FR',
    public readonly bool $is_active = true
) {}

// ❌ MAUVAIS - Propriétés requises qui pourraient être optionnelles
public function __construct(
    public readonly string $user_name,
    public readonly ?string $middle_name  // Devrait être nullable avec default null
) {}
```

### 10.4. Utiliser les bonnes méthodes

```php
// ✅ Pour JSON → fromJson()
$user = UserRecord::fromJson($jsonString);

// ✅ Pour tableau → from()
$user = UserRecord::from($array);

// ✅ Pour collection → collect()
$users = UserRecord::collect($sources);

// ❌ Éviter de passer du JSON à from()
$user = UserRecord::from($jsonString);  // Traite JSON comme string
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Trait** | ✅ DOIT utiliser `Hydratable` |
| **Constructeur** | ✅ Public avec paramètres typés |
| **Propriétés** | `public readonly` |
| **Convention** | `snake_case` pour les propriétés |
| **Hydratation** | `StrictDataObject` (préserve casse) |
| **Clés source** | Doivent correspondre exactement aux noms des paramètres |
| **Types supportés** | scalaires, Enum, Transformable, TypedCollection |
| **Nullabilité** | Gérée automatiquement |
| **Valeurs par défaut** | Utilisées automatiquement |
| **Erreurs** | `RuntimeException` si conversion impossible |

---

## 12. Règle d'or

> **`Hydratable` analyse le constructeur et hydrate automatiquement les propriétés à partir de la source. Les Records utilisent `StrictDataObject` donc les clés DOIVENT correspondre EXACTEMENT aux noms des paramètres (en `snake_case`).**
>
> **⚠️ Convention `snake_case` obligatoire pour les Records.**
> **⚠️ Utilisez `fromJson()` pour le JSON, `from()` pour les tableaux/objets.**
> **⚠️ Définissez des types explicites et des valeurs par défaut quand pertinent.**

```php
// Le Record parfait (snake_case)
final class PerfectRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $record_id,
        public readonly string $record_name,
        public readonly EmailAddress $record_email,
        public readonly UserStatus $record_status,
        public readonly ?string $record_notes = null,
        public readonly StringTypedCollection $record_tags = new StringTypedCollection,
    ) {}
}

// Hydratation (clés snake_case)
$record = PerfectRecord::from([
    'record_id' => 123,
    'record_name' => 'Perfect',
    'record_email' => 'perfect@example.com',
    'record_status' => 'active',
    'record_notes' => 'Some notes',
    'record_tags' => ['perfect', 'awesome']
]);

// Depuis JSON
$record = PerfectRecord::fromJson('{
    "record_id": 123,
    "record_name": "Perfect",
    "record_email": "perfect@example.com",
    "record_status": "active"
}');

// Collection
$records = PerfectRecord::collect($sources, PerfectCollection::class);
```
---