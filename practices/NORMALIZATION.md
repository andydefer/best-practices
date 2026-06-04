# Principe d'usage du système de normalisation

## 1. Définition

Le système de normalisation est un mécanisme qui convertit récursivement des objets complexes (Records, ValueObjects, Data, Collections, DataObject) en structures de données simples (tableaux, scalaires, null).

```
Objet complexe → Normalisation → Structure portable (array/scalaire) → JSON/Stockage/API
```

```php
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;

// Avant normalisation (objets complexes)
$user = new UserRecord(
    user_id: 123,
    user_name: 'John Doe',
    user_email: EmailAddress::from('john@example.com'),
    user_tags: new StringTypedCollection(['premium', 'vip'])
);

// Après normalisation (structure simple)
$normalized = NormalizerChain::get()->normalize($user);
// Résultat :
// [
//     'user_id' => 123,
//     'user_name' => 'John Doe',
//     'user_email' => 'john@example.com',
//     'user_tags' => ['premium', 'vip']
// ]
```

---

## 2. Problématique à laquelle le système répond

| Problème | Solution |
|----------|----------|
| Sérialisation d'objets complexes | Normalisation automatique |
| ValueObjects (wrapper classes) | Extraction de la valeur brute |
| Enums (Backed/Pure) | Conversion en valeur scalaire |
| Collections typées | Conversion en tableau |
| camelCase → snake_case (Records pour API) | Conversion automatique |
| camelCase conservé (Data pour API) | Conservation des clés |
| Récursivité des structures imbriquées | Traitement profond automatique |

### 2.1. Le problème de la sérialisation manuelle

```php
// ❌ SANS normalisation - Sérialisation manuelle répétitive
function userToArray(UserRecord $user): array
{
    return [
        'user_id' => $user->user_id,
        'user_name' => $user->user_name,
        'user_email' => $user->user_email->getValue(),
        'user_tags' => $user->user_tags->toArray()
    ];
}

// ✅ AVEC normalisation - Automatique et récursif
$normalized = NormalizerChain::get()->normalize($user);
```

---

## 3. Architecture du système

### 3.1. Structure des classes

```
NormalizerInterface (contrat)
    ↓
AbstractNormalizer (implémentation de base)
    ↓
Normalizers spécifiques :
    ├── NullNormalizer
    ├── ScalarNormalizer
    ├── EnumNormalizer
    ├── RecordNormalizer (camelCase → snake_case)
    ├── ValueObjectNormalizer
    ├── DataNormalizer (conserve camelCase)
    ├── TypedCollectionNormalizer
    ├── DataObjectNormalizer
    └── ArrayNormalizer
    ↓
RootNormalizer (normaliseur racine)
    ↓
NormalizerChain (point d'entrée unique - singleton)
```

### 3.2. Flux de traitement

```
Valeur d'entrée
    ↓
RootNormalizer
    ↓
Parcourt chaque normaliseur dans l'ordre
    ↓
Normaliseur.supports(value)? → OUI → normalize(value) → résultat
    ↓                            ↓
    NON                          Fait appel à next() pour les sous-valeurs
    ↓
Normaliseur suivant
    ↓
Résultat final (array/scalaire/null)
```

### 3.3. Point d'entrée unique

```php
// Point d'entrée unique (recommandé - singleton)
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;

$normalizer = NormalizerChain::get();
$normalized = $normalizer->normalize($anyValue);
```

---

## 4. Les normaliseurs disponibles

### 4.1. NullNormalizer

**Rôle** : Gère les valeurs `null`

```php
$normalized = $normalizer->normalize(null);  // null
```

**Ordre** : 1er

### 4.2. ScalarNormalizer

**Rôle** : Passe les valeurs scalaires telles quelles

```php
$normalized = $normalizer->normalize(42);      // 42
$normalized = $normalizer->normalize(3.14);    // 3.14
$normalized = $normalizer->normalize('text');  // 'text'
$normalized = $normalizer->normalize(true);    // true
```

**Ordre** : 2ème

### 4.3. EnumNormalizer

**Rôle** : Convertit les enums en valeurs scalaires

```php
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

// BackedEnum → retourne la valeur
$normalized = $normalizer->normalize(UserStatus::ACTIVE);  // 'active'
```

**Ordre** : 3ème

### 4.4. RecordNormalizer (⚠️ RÈGLE STRICTE)

**Rôle** : Convertit les `AbstractRecord` en tableau avec conversion camelCase → snake_case

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,      // snake_case dans le Record
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

$user = new UserRecord(user_id: 123, user_name: 'John', user_email: 'john@example.com');
$normalized = $normalizer->normalize($user);

// Résultat : les clés restent en snake_case
// [
//     'user_id' => 123,
//     'user_name' => 'John',
//     'user_email' => 'john@example.com'
// ]
```

**Ordre** : 4ème
**Spécificité** : Les Records ont déjà leurs propriétés en snake_case

### 4.5. ValueObjectNormalizer

**Rôle** : Extrait la valeur brute d'un `AbstractValueObject`

```php
final class EmailAddress extends AbstractValueObject
{
    public function __construct(private readonly string $value) {}
    public function getValue(): string { return $this->value; }
}

$email = EmailAddress::from('john@example.com');
$normalized = $normalizer->normalize($email);  // 'john@example.com'
```

**Ordre** : 5ème

### 4.6. DataNormalizer

**Rôle** : Convertit les `AbstractData` en tableau (conserve camelCase)

```php
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,      // camelCase pour l'API
        public readonly string $userName,
        public readonly string $userEmail,
    ) {}
}

$data = new UserData(userId: 123, userName: 'John', userEmail: 'john@example.com');
$normalized = $normalizer->normalize($data);

// Résultat : les clés restent en camelCase
// [
//     'userId' => 123,
//     'userName' => 'John',
//     'userEmail' => 'john@example.com'
// ]
```

**Ordre** : 6ème
**Spécificité** : Conserve les noms de propriétés d'origine (camelCase)

### 4.7. TypedCollectionNormalizer

**Rôle** : Convertit les collections typées en tableau

```php
$tags = new StringTypedCollection(['php', 'laravel', 'typescript']);
$normalized = $normalizer->normalize($tags);  // ['php', 'laravel', 'typescript']
```

**Ordre** : 7ème

### 4.8. DataObjectNormalizer

**Rôle** : Convertit `StrictDataObject` en tableau associatif

```php
$data = new StrictDataObject([
    'user_id' => 123,
    'user_name' => 'John',
    'profile' => new StrictDataObject(['age' => 30])
]);

$normalized = $normalizer->normalize($data);
// [
//     'user_id' => 123,
//     'user_name' => 'John',
//     'profile' => ['age' => 30]
// ]
```

**Ordre** : 8ème

### 4.9. ArrayNormalizer

**Rôle** : Parcourt récursivement les tableaux pour normaliser chaque élément

```php
$data = [
    'user' => new UserRecord(user_id: 123, user_name: 'John'),
    'tags' => new StringTypedCollection(['php', 'js'])
];

$normalized = $normalizer->normalize($data);
// [
//     'user' => ['user_id' => 123, 'user_name' => 'John'],
//     'tags' => ['php', 'js']
// ]
```

**Ordre** : 9ème (dernier)

---

## 5. Ordre de traitement (critique)

L'ordre des normaliseurs est **critique** et défini dans `RootNormalizer::initialize()` :

```php
$normalizers = [
    $null,        // 1. Null
    $scalar,      // 2. Scalaires
    $enum,        // 3. Enums
    $record,      // 4. Records (snake_case)
    $vo,          // 5. ValueObjects
    $data,        // 6. Data (camelCase)
    $collection,  // 7. Collections
    $dataObject,  // 8. StrictDataObject
    $array        // 9. Tableaux (doit être dernier)
];
```

### 5.1. Pourquoi cet ordre ?

| Règle | Explication |
|-------|-------------|
| **Null et Scalar en premier** | Cas de base, les plus simples |
| **Enum avant Record** | Un Record peut contenir des enums |
| **Record avant Data** | Détection spécifique avant DataObject |
| **Array en dernier** | Capture tout ce qui reste et normalise récursivement |

---

## 6. Règle : Record vs Data - casse différente

> **⚠️ Les Records (AbstractRecord) ont leurs propriétés en `snake_case` et sont normalisés tels quels. Les Data (AbstractData) ont leurs propriétés en `camelCase` et sont normalisés tels quels.**

```php
// Record (snake_case) - utilisé en interne
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
}

// Data (camelCase) - utilisé pour les réponses API
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
    ) {}
}

// Normalisation
$recordNormalized = NormalizerChain::get()->normalize($userRecord);
// ['user_id' => 123, 'user_name' => 'John']

$dataNormalized = NormalizerChain::get()->normalize($userData);
// ['userId' => 123, 'userName' => 'John']
```

---

## 7. Cas d'utilisation

### 7.1. Réponse API avec Data (camelCase)

```php
final class UserController
{
    public function show(int $id): JsonResponse
    {
        $userRecord = $this->userRepository->find($id);
        
        // Transformation Record → Data
        $userData = new UserData(
            userId: $userRecord->user_id,
            userName: $userRecord->user_name,
            userEmail: $userRecord->user_email,
        );
        
        // Normalisation automatique (conserve camelCase)
        $normalized = NormalizerChain::get()->normalize($userData);
        
        return response()->json($normalized);
        // {
        //     "userId": 123,
        //     "userName": "John",
        //     "userEmail": "john@example.com"
        // }
    }
}
```

### 7.2. Logging structuré

```php
final class LoggerService
{
    public function logUserAction(UserRecord $user, string $action): void
    {
        $context = NormalizerChain::get()->normalize($user);
        $context['action'] = $action;
        
        $this->logger->info('User action', $context);
    }
}
```

### 7.3. Cache

```php
final class CacheService
{
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $normalized = NormalizerChain::get()->normalize($value);
        $serialized = serialize($normalized);
        
        $this->cache->set($key, $serialized, $ttl);
    }
}
```

---

## 8. Exemples concrets

### 8.1. Structure complexe

```php
// Structure objet complexe
$order = new OrderRecord(
    order_id: 12345,
    order_status: OrderStatus::PAID,
    customer: new CustomerRecord(
        customer_id: 789,
        customer_email: EmailAddress::from('customer@example.com'),
        customer_tags: new StringTypedCollection(['vip', 'premium'])
    ),
    items: new TypedCollection(OrderItemRecord::class)
);

$order->items->add(
    new OrderItemRecord(product_id: 1, quantity: 2, price: 49.99),
    new OrderItemRecord(product_id: 2, quantity: 1, price: 99.99)
);

// Normalisation
$normalized = NormalizerChain::get()->normalize($order);

// Résultat (snake_case) :
// [
//     'order_id' => 12345,
//     'order_status' => 'paid',
//     'customer' => [
//         'customer_id' => 789,
//         'customer_email' => 'customer@example.com',
//         'customer_tags' => ['vip', 'premium']
//     ],
//     'items' => [
//         ['product_id' => 1, 'quantity' => 2, 'price' => 49.99],
//         ['product_id' => 2, 'quantity' => 1, 'price' => 99.99]
//     ]
// ]
```

### 8.2. Data avec camelCase pour API

```php
final class OrderData extends AbstractData
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $orderStatus,
        public readonly CustomerData $customer,
        public readonly array $items,
    ) {}
}

$orderData = new OrderData(
    orderId: 12345,
    orderStatus: 'paid',
    customer: new CustomerData(customerId: 789, customerName: 'John'),
    items: [
        ['productId' => 1, 'quantity' => 2, 'price' => 49.99],
        ['productId' => 2, 'quantity' => 1, 'price' => 99.99]
    ]
);

$normalized = NormalizerChain::get()->normalize($orderData);

// Résultat (camelCase conservé) :
// [
//     'orderId' => 12345,
//     'orderStatus' => 'paid',
//     'customer' => ['customerId' => 789, 'customerName' => 'John'],
//     'items' => [
//         ['productId' => 1, 'quantity' => 2, 'price' => 49.99],
//         ['productId' => 2, 'quantity' => 1, 'price' => 99.99]
//     ]
// ]
```

---

## 9. Bonnes pratiques

### 9.1. Utiliser NormalizerChain (singleton)

```php
// ✅ RECOMMANDÉ - Point d'entrée unique
$normalizer = NormalizerChain::get();

// ❌ À ÉVITER - Créer directement RootNormalizer
$normalizer = new RootNormalizer();
```

### 9.2. Ne pas normaliser deux fois

```php
// ✅ BON - Une seule normalisation
$normalized = NormalizerChain::get()->normalize($user);
$json = json_encode($normalized);

// ❌ MAUVAIS - Double normalisation (inutile)
$normalized1 = NormalizerChain::get()->normalize($user);
$normalized2 = NormalizerChain::get()->normalize($normalized1);
```

### 9.3. Respecter les conventions de casse

```php
// ✅ BON - Record en snake_case
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
}

// ✅ BON - Data en camelCase
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
    ) {}
}
```

---

## 10. Récapitulatif des contraintes

| Type | Convention | Normalisation | Usage |
|------|------------|---------------|-------|
| **AbstractRecord** | `snake_case` | Clés conservées | Communication interne |
| **AbstractData** | `camelCase` | Clés conservées | Réponses API |
| **AbstractValueObject** | `camelCase` | Valeur brute | Concepts métier |
| **Enum** | `SCREAMING_SNAKE_CASE` | Valeur scalaire | Énumérations |
| **TypedCollection** | - | Tableau | Collections typées |
| **StrictDataObject** | - | Tableau associatif | Sources externes |

---

## 11. Règle d'or

> **Le système de normalisation convertit récursivement les objets complexes en structures simples. Les Records (snake_case) sont normalisés tels quels. Les Data (camelCase) sont normalisés tels quels. Les ValueObjects extraient leur valeur brute.**
>
> **⚠️ Utilisez toujours `NormalizerChain::get()` comme point d'entrée unique.**
> **⚠️ Respectez les conventions de casse : `snake_case` pour les Records, `camelCase` pour les Data.**
> **⚠️ La normalisation est récursive : les structures imbriquées sont traitées automatiquement.**

```php
// Record (snake_case) - interne
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly EmailAddress $user_email,
    ) {}
}

// Data (camelCase) - API
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
    ) {}
}

// Normalisation des Records
$userRecord = new UserRecord(user_id: 123, user_name: 'John', user_email: EmailAddress::from('john@example.com'));
$normalizedRecord = NormalizerChain::get()->normalize($userRecord);
// ['user_id' => 123, 'user_name' => 'John', 'user_email' => 'john@example.com']

// Normalisation des Data
$userData = new UserData(userId: 123, userName: 'John', userEmail: 'john@example.com');
$normalizedData = NormalizerChain::get()->normalize($userData);
// ['userId' => 123, 'userName' => 'John', 'userEmail' => 'john@example.com']

// Réponse API
return response()->json($normalizedData);
// {
//     "userId": 123,
//     "userName": "John",
//     "userEmail": "john@example.com"
// }
```
---