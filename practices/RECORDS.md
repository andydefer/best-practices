Voici votre documentation des Records mise à jour avec les bonnes pratiques :

# Principe d'usage des Records

## 1. Définition

Un **Record** est une structure de données **immuable**, **typée** et **auto-hydratable** qui sert de transporteur d'informations entre les couches de l'application (Controllers → Services → Repositories). Il ne contient **aucune logique métier**, uniquement des données.

```
Record → Transporteur de données → Immutable → Typé → Auto-hydratable → Pas de méthodes
```

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly UserRole $user_role,
    ) {}
}

// Hydratation automatique (Hydratable est déjà dans AbstractRecord)
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com',
    'user_role' => 'admin'
]);
```

---

## 2. Record vs Value Object vs Data

| Aspect | Record | Value Object | Data |
|--------|--------|--------------|------|
| **Usage principal** | Communication interne | Concepts métier | Réponses API |
| **Logique métier** | ❌ Aucune | ✅ Peut contenir | ❌ Transformation uniquement |
| **Méthodes** | ❌ Aucune (sauf constructeur) | ✅ Validation, calculs | ✅ Transformation Record → API |
| **Validation** | ❌ Optionnelle | ✅ OBLIGATOIRE | ❌ Optionnelle |
| **Hydratation** | ✅ Automatique (héritée) | ✅ Automatique (`from()`) | ❌ Manuelle |
| **Propriétés** | ✅ `public readonly` | ✅ `public readonly` | ✅ `public readonly` |
| **Convention nommage** | `snake_case` | `camelCase` | `camelCase` |
| **Nommage classe** | `UserRecord` | `EmailAddress`, `Money` | `UserData` |

---

## 3. Règle fondamentale : Convention de nommage (⚠️ STRICTE)

> **⚠️ Les propriétés d'un Record DOIVENT être en `snake_case`.**
>
> Cette convention garantit la cohérence avec les sources de données (API, base de données, fichiers) qui utilisent majoritairement cette convention.

```php
// ✅ BON - Propriétés en snake_case
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly UserRole $user_role,
        public readonly ?string $phone_number,
    ) {}
}

// ❌ MAUVAIS - Propriétés en camelCase
final class BadRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $userId,      // ❌ camelCase
        public readonly string $userName,  // ❌ camelCase
    ) {}
}
```

### 3.1. Pourquoi `snake_case` ?

| Raison | Explication |
|--------|-------------|
| **Sources de données** | APIs, JSON, base de données utilisent `snake_case` |
| **Cohérence** | Pas de transformation entre source et Record |
| **Hydratation directe** | `from()` fonctionne sans mapping |
| **`StrictDataObject`** | Préserve la casse, attend `snake_case` |

---

## 4. Hydratation avec `StrictDataObject`

> **⚠️ `AbstractRecord` utilise `StrictDataObject` pour l'hydratation. Le trait `Hydratable` est déjà intégré, pas besoin de l'ajouter.**

```php
// ✅ BON - Hydratation automatique (Hydratable est déjà dans AbstractRecord)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// Les clés doivent correspondre exactement aux propriétés
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com'
]);

// Depuis JSON
$user = UserRecord::fromJson('{"user_id":1,"user_name":"John Doe","user_email":"john@example.com"}');
```

### 4.1. Caractéristiques de `StrictDataObject`

| Caractéristique | Description |
|-----------------|-------------|
| **Préserve la casse** | Les clés restent exactement comme fournies |
| **Conversion récursive** | Les tableaux imbriqués deviennent `StrictDataObject` |
| **Accès direct** | `$data->user_id` (pas de normalisation camelCase) |
| **Immuable** | Les assignations directes sont bloquées |

---

## 5. Types supportés par l'hydratation

| Type | Support | Exemple |
|------|---------|---------|
| `int` / `integer` | ✅ | `public readonly int $user_id` |
| `float` / `double` | ✅ | `public readonly float $price_amount` |
| `string` | ✅ | `public readonly string $user_name` |
| `bool` / `boolean` | ✅ | `public readonly bool $is_active` |
| `null` | ✅ | `public readonly ?string $phone_number` |
| `UnitEnum` (Backed) | ✅ | `public readonly UserRole $user_role` |
| `AbstractValueObject` | ✅ | `public readonly EmailAddress $email_address` |
| `AbstractRecord` | ✅ | `public readonly AddressRecord $address` |
| `AbstractTypedCollection` | ✅ | `public readonly UserCollection $users` |
| `Transformable` (interface) | ✅ | Tout objet implémentant `Transformable` |

### 5.1. Types INTERDITS

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| `array` brut | Non typé, contenu inconnu | `TypedCollection` |
| `mixed` | Pas de type | Type explicite |
| `object` | Non typé | VO ou Record |
| `Model` Eloquent | Contient logique | VO ou Record |

---

## 6. Ce qu'un Record NE peut PAS faire

| Interdiction | Pourquoi |
|--------------|----------|
| **Avoir des méthodes** (sauf constructeur) | Violation du principe de transporteur |
| **Méthode `toUserRecord()`** | Un Record ne se transforme pas lui-même |
| **Méthode `fromModel()`** | La transformation se fait dans le Service/Repository |
| **Logique métier** | Violation SRP |
| **Validation** | À faire dans les Value Objects |
| **Calculs** | À faire dans les Services |
| **Propriétés en `camelCase`** | Non conforme à la convention |

### 6.1. Exemple de CE QU'IL NE FAUT PAS FAIRE

```php
// ❌ MAUVAIS - Record avec méthodes
final class BadRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
    
    // ❌ Méthode interdite
    public function toUserRecord(): UserRecord
    {
        return new UserRecord(...);
    }
    
    // ❌ Logique métier interdite
    public function isValid(): bool
    {
        return $this->user_id > 0;
    }
    
    // ❌ Transformation depuis Model interdite
    public static function fromModel(User $user): self
    {
        return new self(...);
    }
}

// ✅ BON - Record pur (seulement des données)
final class GoodRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
}

// La transformation se fait dans le Service/Repository
final class UserService
{
    public function toUserRecord(array $apiData): UserRecord
    {
        return UserRecord::from($apiData);
    }
    
    public function fromModel(User $user): UserRecord
    {
        return new UserRecord(
            user_id: $user->id,
            user_name: $user->name,
        );
    }
}
```

---

## 7. Exemples complets

### 7.1. Record simple (snake_case)

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly UserRole $user_role,
        public readonly ?string $phone_number = null,
    ) {}
}

// Hydratation
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com',
    'user_role' => 'admin'
]);
```

### 7.2. Record avec Value Object

```php
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly EmailAddress $email_address,  // Value Object
        public readonly Money $wallet_amount,         // Value Object
    ) {}
}

// Hydratation automatique
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John',
    'email_address' => 'john@example.com',  // sera converti en EmailAddress
    'wallet_amount' => ['cents' => 1999, 'currency' => 'EUR']  // sera converti en Money
]);
```

### 7.3. Record avec Record imbriqué

```php
final class AddressRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $street_name,
        public readonly string $city_name,
        public readonly string $country_code,
    ) {}
}

final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly AddressRecord $user_address,
    ) {}
}

// Hydratation
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John',
    'user_address' => [
        'street_name' => '123 Main St',
        'city_name' => 'Paris',
        'country_code' => 'FR'
    ]
]);
```

### 7.4. Record avec TypedCollection

```php
final class UserCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(UserRecord::class);
    }
}

final class TeamRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $team_id,
        public readonly string $team_name,
        public readonly UserCollection $team_members,
    ) {}
}

// Hydratation
$team = TeamRecord::from([
    'team_id' => 1,
    'team_name' => 'Developers',
    'team_members' => [
        ['user_id' => 1, 'user_name' => 'John'],
        ['user_id' => 2, 'user_name' => 'Jane']
    ]
]);
```

---

## 8. Transformation des données (dans les Services)

> **⚠️ La transformation Record ↔ Model se fait exclusivement dans les Services ou Repositories, jamais dans le Record lui-même.**

```php
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    // API → Record
    public function fromApiData(array $apiData): UserRecord
    {
        // Transformation api_data → user_id si nécessaire
        $normalized = [
            'user_id' => $apiData['api_id'],
            'user_name' => $apiData['api_name'],
            'user_email' => $apiData['api_email'],
        ];
        
        return UserRecord::from($normalized);
    }
    
    // Model → Record
    public function fromModel(User $user): UserRecord
    {
        return new UserRecord(
            user_id: $user->id,
            user_name: $user->name,
            user_email: $user->email,
            user_role: $user->role,
        );
    }
    
    // Record → Model
    public function toModel(UserRecord $record): User
    {
        $user = $this->userRepository->find($record->user_id);
        $user->name = $record->user_name;
        $user->email = $record->user_email;
        
        return $user;
    }
}
```

---

## 9. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractRecord` |
| **Trait Hydratable** | ✅ Déjà dans `AbstractRecord` (ne pas ajouter) |
| **Propriétés** | `public readonly` obligatoire |
| **Convention nommage propriétés** | ✅ `snake_case` (OBLIGATOIRE) |
| **Méthodes** | ❌ Aucune (sauf constructeur) |
| **Logique métier** | ❌ Interdit |
| **Hydratation JSON** | Utiliser `fromJson()` |
| **Normalisation** | `StrictDataObject` (préserve la casse) |
| **Types autorisés** | scalaires, Enum, VO, Record, Transformable, TypedCollection |
| `array` brut | ❌ Interdit (utiliser `TypedCollection`) |
| **Transformation Model → Record** | Dans le Service, pas dans le Record |

---

## 10. Règle d'or

> **Un Record est un SAC DE DONNÉES immuable, typé, sans méthodes. Il ne contient QUE des propriétés `public readonly` en `snake_case`.**
>
> **⚠️ `AbstractRecord` intègre déjà `Hydratable`. Pas besoin de l'ajouter manuellement.**
>
> **Les propriétés DOIVENT être en `snake_case` pour correspondre aux sources de données.**
>
> **Un Record n'a PAS de méthodes (pas de `toUserRecord()`, pas de `fromModel()`). La transformation appartient aux Services.**
>
> **Les types autorisés : scalaires, Enum, Value Object, Record, Transformable, TypedCollection. JAMAIS de `array` brut.**

```php
// ✅ Le Record parfait
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $record_id,
        public readonly string $record_name,
        public readonly EmailAddress $email_address,  // Value Object
        public readonly UserRole $user_role,          // Enum
        public readonly ?string $optional_field,
        public readonly UserCollection $users,        // TypedCollection
    ) {}
}

// Hydratation (snake_case obligatoire)
$record = PerfectRecord::from([
    'record_id' => 1,
    'record_name' => 'Perfect',
    'email_address' => 'test@example.com',
    'user_role' => 'admin',
    'users' => [
        ['user_id' => 1, 'user_name' => 'John'],
        ['user_id' => 2, 'user_name' => 'Jane']
    ]
]);

// Depuis JSON
$record = PerfectRecord::fromJson('{"record_id":1,"record_name":"Perfect"}');

// Transformation dans le Service (pas dans le Record)
final class PerfectService
{
    public function fromModel(PerfectModel $model): PerfectRecord
    {
        return new PerfectRecord(
            record_id: $model->id,
            record_name: $model->name,
            email_address: EmailAddress::from($model->email),
            user_role: $model->role,
        );
    }
}
```
---