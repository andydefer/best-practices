# Principe d'usage des Models (Version finale)


## 1. Définition

Un **Model** est une classe qui représente une table de la base de données et encapsule les relations, les casts, et les attributs. Il ne contient **aucune logique métier**, seulement des déclarations de structure.

```
Model → Représentation d'une table → Relations + Casts + Attributs → Pas de logique métier
```

```php
final class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'role' => UserRole::class,
        'metadata' => JsonCast::class,
        'wallet' => MoneyCast::class,
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

---

## 2. Problématique à laquelle les Models répondent

| Problème | Solution |
|----------|----------|
| **Logique métier dans les Models** | Les Models ne doivent contenir que des déclarations de structure |
| **Accès direct aux Models** | Toute interaction avec les Models passe par les Repositories |
| **Duplication de la logique de cast** | Les Casts centralisent la transformation des données |
| **Erreurs de précision monétaire** | `MoneyCast` stocke en centimes (entier) |
| **Données JSON corrompues** | `JsonCast` gère les erreurs et retourne `null` |

### 2.1 Règle fondamentale (⚠️ IMMUABLE)

> **Un Model ne contient AUCUNE logique métier. Il ne contient que des déclarations : table, fillable, casts, relations, attributs de formatage.**

```php
// ✅ BON - Model avec déclarations uniquement
final class User extends Model
{
    protected $fillable = ['name', 'email'];
    protected $casts = ['metadata' => JsonCast::class];
    
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => $attrs['first_name'] . ' ' . $attrs['last_name'],
        );
    }
    
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

// ❌ MAUVAIS - Model avec logique métier
final class User extends Model
{
    public function isAdmin(): bool  // ❌ Logique métier
    {
        return $this->role === 'admin';
    }
    
    public function calculateTotal(): float  // ❌ Logique métier
    {
        return $this->items->sum('price');
    }
}
```

---

## 3. Les Casts fournis par le package

Le package `best-practices` fournit deux casts essentiels pour sécuriser et standardiser les données.

### 3.1 MoneyCast - Gestion des montants monétaires

#### 3.1.1 Le problème des nombres flottants

En PHP (et dans la plupart des langages informatiques), les nombres flottants souffrent d'une imprécision fondamentale due à la représentation binaire.

```php
// ❌ Ce qui semble vrai mathématiquement est faux en informatique
0.1 + 0.2 == 0.3 // false

// Exemple concret
$total = 0.1 + 0.2;
echo $total; // 0.30000000000000004

// Conséquence sur les calculs monétaires
$price = 10.99;
$quantity = 3;
$taxRate = 0.10;

$subtotal = $price * $quantity;        // 32.969999999999999
$tax = $subtotal * $taxRate;            // 3.2969999999999997
$total = $subtotal + $tax;              // 36.266999999999996
```

#### 3.1.2 La solution : Stocker en centimes (plus petite unité monétaire)

La solution consiste à stocker les montants monétaires en **centimes** (ou plus petite unité) dans la base de données, c'est-à-dire sous forme d'**entier**.

| Montant réel | Stockage en centimes |
|--------------|---------------------|
| 12.34 € | 1234 |
| 9.99 € | 999 |
| 0.50 € | 50 |
| 100.00 € | 10000 |

**Avantages :**
- ✅ Calculs parfaitement précis (entiers)
- ✅ Pas d'erreurs d'arrondi inattendues
- ✅ Performance optimale (entier vs flottant)

#### 3.1.3 Code du MoneyCast

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles casting between monetary values stored as integers (smallest currency unit)
 * in database and floats (standard currency unit) in application.
 *
 * This cast ensures precise monetary value storage by storing amounts as integers
 * (cents, pence, etc.) in the database, preventing floating-point precision errors.
 * Values are presented as floats to the application with proper rounding to 2 decimals.
 *
 * @package AndyDefer\BestPractices\Casts
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * Number of decimal places for monetary values (standard currency precision).
     *
     * @var int
     */
    private const DECIMAL_PLACES = 2;

    /**
     * Conversion multiplier between standard currency unit and smallest unit.
     * Calculated as 10^DECIMAL_PLACES (100 for 2 decimal places).
     *
     * @var int
     */
    private const UNIT_MULTIPLIER = 100;

    /**
     * Converts from smallest currency unit (database) to standard unit (application).
     *
     * Handles nullable values gracefully and ensures proper rounding to 2 decimal places.
     * Example: 1234 cents → 12.34 dollars/euros
     *
     * @param Model $model The Eloquent model being cast
     * @param string $key The attribute name being cast
     * @param int|null $value The value in smallest currency unit from database
     * @param array<string, mixed> $attributes All model attributes
     * @return float|null The value in standard currency unit for application
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }

        return round(
            num: (int) $value / self::UNIT_MULTIPLIER,
            precision: self::DECIMAL_PLACES
        );
    }

    /**
     * Converts from standard currency unit (application) to smallest unit (database).
     *
     * Handles nullable values gracefully and ensures proper rounding before conversion.
     * Example: 12.34 dollars/euros → 1234 cents
     *
     * @param Model $model The Eloquent model being cast
     * @param string $key The attribute name being cast
     * @param float|int|null $value The value in standard currency unit from application
     * @param array<string, mixed> $attributes All model attributes
     * @return int|null The value in smallest currency unit for database storage
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) round(
            num: (float) $value * self::UNIT_MULTIPLIER,
            precision: 0
        );
    }
}
```

#### 3.1.4 Utilisation du MoneyCast

```php
// Dans le Model
final class Product extends Model
{
    protected $casts = [
        'price' => MoneyCast::class,      // Stocké en centimes en base
        'discount' => MoneyCast::class,
        'tax' => MoneyCast::class,
    ];
}

// En base de données, `price` est stocké comme INTEGER (ex: 1999 pour 19.99€)
// En application, vous manipulez des float
$product->price = 19.99;  // ← set() convertit en 1999
$price = $product->price;  // ← get() convertit en 19.99 (float)

// Calculs précis
$total = $product->price * 3;  // 59.97 (précis)
```

### 3.2 JsonCast - Gestion des données JSON

#### 3.2.1 Problématique

Les données JSON stockées en base peuvent être :
- Corrompues (encodage incorrect)
- Partiellement écrites (crash en écriture)
- Mal formatées
- Non présentes (null)

Un cast classique `array` de Laravel lève une exception si le JSON est invalide, ce qui peut faire planter l'application.

#### 3.2.2 La solution : JsonCast avec fallback

Le `JsonCast` fourni par le package gère les erreurs de décodage JSON et retourne `null` plutôt que de planter, assurant la stabilité de l'application.

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * Handles casting between JSON database storage and PHP arrays.
 *
 * This cast ensures safe conversion between JSON strings stored in the database
 * and PHP arrays used in the application. It gracefully handles invalid JSON
 * by returning null, preventing application crashes from corrupted data.
 *
 * @package AndyDefer\BestPractices\Casts
 */
final class JsonCast implements CastsAttributes
{
    /**
     * Maximum JSON nesting depth for decoding operations.
     *
     * @var int
     */
    private const MAX_JSON_DEPTH = 512;

    /**
     * Converts JSON from database storage to a PHP array.
     *
     * Handles various input types and gracefully degrades to null for invalid JSON.
     * This prevents application crashes when encountering corrupted database data.
     *
     * @param Model $model The Eloquent model being cast
     * @param string $key The attribute name being cast
     * @param string|array|null $value The raw value from the database
     * @param array<string, mixed> $attributes All model attributes
     * @return array|null The decoded array, or null if conversion fails
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->decodeJsonString($value);
        }

        return null;
    }

    /**
     * Converts a PHP array to JSON for database storage.
     *
     * Handles arrays, existing JSON strings, and gracefully falls back to
     * JSON encoding with strict error handling.
     *
     * @param Model $model The Eloquent model being cast
     * @param string $key The attribute name being cast
     * @param array|string|null $value The application value to store
     * @param array<string, mixed> $attributes All model attributes
     * @return string|null The JSON string for storage, or null if input is null
     * @throws JsonException When encoding fails for non-null, non-string, non-array values
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) && $this->isValidJsonString($value)) {
            return $value;
        }

        return $this->encodeToJson($value);
    }

    /**
     * Decodes a JSON string to a PHP array.
     *
     * @param string $jsonString The JSON string to decode
     * @return array|null The decoded array, or null if decoding fails
     */
    private function decodeJsonString(string $jsonString): ?array
    {
        try {
            $decoded = json_decode(
                json: $jsonString,
                associative: true,
                depth: self::MAX_JSON_DEPTH,
                flags: JSON_THROW_ON_ERROR
            );

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Encodes a value to a JSON string.
     *
     * @param mixed $value The value to encode
     * @return string The JSON encoded string
     * @throws JsonException When encoding fails
     */
    private function encodeToJson(mixed $value): string
    {
        return json_encode(
            value: $value,
            flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Validates if a string contains valid JSON.
     *
     * @param string $value The string to validate
     * @return bool True if the string contains valid JSON, false otherwise
     */
    private function isValidJsonString(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
```

#### 3.2.3 Utilisation du JsonCast

```php
// Dans le Model
final class User extends Model
{
    protected $casts = [
        'metadata' => JsonCast::class,      // JSON → array sécurisé
        'settings' => JsonCast::class,
        'preferences' => JsonCast::class,
    ];
}

// En base de données, `metadata` est stocké comme JSON TEXT
// En application, vous manipulez un array
$user->metadata = ['theme' => 'dark', 'language' => 'fr'];  // set() convertit en JSON
$theme = $user->metadata['theme'] ?? 'default';  // get() convertit en array

// Si le JSON en base est corrompu, get() retourne null sans planter
```

---

## 4. La constante BEST_PRACTICES_LIMIT et le helper

### 4.1 Problématique des limites éparpillées

Dans une application, on retrouve souvent des limites magiques éparpillées :

```php
// ❌ MAUVAIS - Magic numbers partout
$recentPosts = $user->posts()->limit(10)->get();      // Pourquoi 10 ?
$notifications = $user->notifications()->limit(20)->get(); // Pourquoi 20 ?
$comments = $post->comments()->limit(15)->get();      // Pourquoi 15 ?
```

**Problèmes :**
- ❌ Duplication de valeurs magiques
- ❌ Maintenance impossible (modifier 10 en 15 sans oublier)
- ❌ Aucune cohérence dans l'application

### 4.2 La solution : Une constante centralisée

Le package fournit une constante `BEST_PRACTICES_LIMIT` et un helper `best_practices_limit()`.

```php
<?php
// src/Constants/BestPracticesConstants.php
namespace AndyDefer\BestPractices\Constants;

final class BestPracticesConstants
{
    /**
     * Default limit for collection queries throughout the application.
     * This value should be used for any paginated or limited collection query
     * to ensure consistency and prevent memory issues.
     *
     * @var int
     */
    public const BEST_PRACTICES_LIMIT = 10;
}
```

### 4.3 Helper fonction

```php
<?php
// src/helpers.php
use AndyDefer\BestPractices\Constants\BestPracticesConstants;

if (!function_exists('best_practices_limit')) {
    /**
     * Get the default limit for collection queries.
     *
     * This helper returns the standard limit value that should be used
     * for any paginated or limited collection query throughout the application.
     * Using this helper ensures consistency and makes future adjustments easier.
     *
     * @return int The default limit value (10 by default)
     */
    function best_practices_limit(): int
    {
        return BestPracticesConstants::BEST_PRACTICES_LIMIT;
    }
}
```

### 4.4 Utilisation

```php
// ✅ BON - Utilisation de la constante via le helper
use function best_practices_limit;

protected function recentPosts(): Attribute
{
    return Attribute::make(
        get: function (): Collection {
            return $this->posts()
                ->orderBy('created_at', 'desc')
                ->limit(best_practices_limit())  // 10 par défaut
                ->get();
        },
    );
}

// ✅ BON - Avec une limite personnalisée si vraiment nécessaire
protected function featuredPosts(): Attribute
{
    return Attribute::make(
        get: function (): Collection {
            return $this->posts()
                ->where('is_featured', true)
                ->limit(5)  // Limite explicite pour un cas spécifique
                ->get();
        },
    );
}
```

### 4.5 Pourquoi utiliser le helper plutôt que la constante directement ?

| Approche | Pourquoi |
|----------|----------|
| `best_practices_limit()` | Plus flexible, permet d'ajouter de la logique future (config, contexte) |
| `BestPracticesConstants::BEST_PRACTICES_LIMIT` | Accessible directement mais moins flexible |

**Recommandation :** Utilisez TOUJOURS le helper `best_practices_limit()` pour permettre une éventuelle évolution (ex: limite différente par environnement, par utilisateur, etc.).

---

## 5. Déclarations autorisées

### 5.1 Propriétés de configuration

```php
final class User extends Model
{
    // Table et clé primaire
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    public $incrementing = true;
    protected $keyType = 'int';
    
    // Mass assignment
    protected $fillable = ['name', 'email', 'password'];
    protected $guarded = ['is_admin', 'role'];
    
    // Sérialisation
    protected $hidden = ['password', 'remember_token'];
    protected $visible = ['id', 'name', 'email'];
    
    // Format des dates
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $dates = ['created_at', 'updated_at'];
}
```

### 5.2 Casts

> **Tous les champs non scalaires DOIVENT avoir un cast défini.**

```php
protected $casts = [
    // Types natifs Laravel
    'email_verified_at' => 'datetime',
    'is_active' => 'boolean',
    'price' => 'decimal:2',
    'metadata' => 'array',
    'config' => 'json',
    'views' => 'integer',
    'score' => 'float',
    
    // Casts du package
    'wallet' => MoneyCast::class,      // ⚠️ Stockage en centimes
    'settings' => JsonCast::class,     // ⚠️ Gestion des erreurs JSON
    
    // Enum (recommandé)
    'role' => UserRole::class,
    'status' => UserStatus::class,
];
```

### 5.3 Bonne pratique pour les Enums (⚠️ RÈGLE STRICTE)

> **Le nom de l'Enum DOIT être en `PascalCase` et correspondre au nom du champ avec la première lettre en majuscule.**

| Champ | Nom de l'Enum |
|-------|---------------|
| `user->role` | `UserRole` |
| `user->status` | `UserStatus` |
| `order->state` | `OrderState` |
| `payment->method` | `PaymentMethod` |

```php
// ✅ BON - Nom de l'Enum correspond au champ
protected $casts = [
    'role' => UserRole::class,      // Champ 'role' → Enum 'UserRole'
    'status' => UserStatus::class,  // Champ 'status' → Enum 'UserStatus'
];

// ❌ MAUVAIS - Nom de l'Enum générique ou incorrect
protected $casts = [
    'role' => Role::class,           // ❌ Devrait être UserRole
    'status' => Status::class,       // ❌ Devrait être UserStatus
];
```

#### 5.3.1 Exemple de l'Enum associé

```php
// App\Enums\UserRole.php (nom = champ + première lettre majuscule)
enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
    
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
    
    public function isDoctor(): bool
    {
        return $this === self::DOCTOR;
    }
}
```

### 5.4 Attributs (Accesseurs / Mutateurs) (⚠️ RÈGLE STRICTE)

> **⚠️ Les Attributs sont réservés au formatage, à la concaténation, ET à la limitation des collections. Les attributs qui retournent une collection DOIVENT TOUJOURS être limités à `best_practices_limit()` ou une valeur explicite.**

#### 5.4.1 Formatage simple (get uniquement)

```php
// Concaténation
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => $attributes['first_name'] . ' ' . $attributes['last_name'],
    );
}

// Formatage numérique
protected function formattedPrice(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => number_format($attributes['price'], 2) . ' €',
    );
}
```

#### 5.4.2 Normalisation (get et set)

```php
// First name : mise en minuscule à l'écriture, mise en ucfirst à la lecture
protected function firstName(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst(strtolower($value)),
        set: fn (string $value) => strtolower($value),
    );
}

// Last name : mise en minuscule à l'écriture, mise en uppercase à la lecture
protected function lastName(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => strtoupper($value),
        set: fn (string $value) => strtolower($value),
    );
}

// Email : mise en minuscule à l'écriture et à la lecture
protected function email(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => strtolower($value),
        set: fn (string $value) => strtolower($value),
    );
}
```

#### 5.4.3 Attributs de collection (⚠️ RÈGLE STRICTE)

> **Un attribut qui retourne une collection DOIT :**
> 1. **Être TOUJOURS limité** (utiliser `best_practices_limit()` ou une constante explicite)
> 2. **Avoir un nom explicite** qui indique la nature et la limite (ex: `recentPosts`, `publicPosts`)
> 3. **Ne JAMAIS retourner une relation non limitée** (ex: `$this->posts` sans `limit()`)

```php
use function best_practices_limit;

// ✅ BON - Attribut de collection limité avec nom explicite
protected function recentPosts(): Attribute
{
    return Attribute::make(
        get: function (mixed $value, array $attributes): Collection {
            return $this->posts()
                ->orderBy('created_at', 'desc')
                ->limit(best_practices_limit())  // 10 par défaut
                ->get();
        },
    );
}

protected function publicPosts(): Attribute
{
    return Attribute::make(
        get: function (mixed $value, array $attributes): Collection {
            return $this->posts()
                ->where('is_public', true)
                ->orderBy('created_at', 'desc')
                ->limit(best_practices_limit())
                ->get();
        },
    );
}

protected function featuredPosts(): Attribute
{
    return Attribute::make(
        get: function (mixed $value, array $attributes): Collection {
            return $this->posts()
                ->where('is_featured', true)
                ->limit(5)  // Limite explicite pour un cas spécifique
                ->get();
        },
    );
}

// ❌ MAUVAIS - Attribut de collection non limité
protected function posts(): Attribute  // ❌ Nom trop générique + pas de limit
{
    return Attribute::make(
        get: fn () => $this->posts()->get(),  // ❌ Tous les posts (1000+)
    );
}

// ❌ MAUVAIS - Accès direct à la relation
protected function allPosts(): Attribute  // ❌ Pas de limit
{
    return Attribute::make(
        get: fn () => $this->posts,  // ❌ Toute la relation
    );
}
```

#### 5.4.4 Ce qui est INTERDIT dans les Attributs

```php
// ❌ INTERDIT - Condition logique (dépend de l'Enum)
protected function isDoctor(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => $attributes['role'] === UserRole::DOCTOR->value,
    );
}

// ✅ BON - Utiliser l'Enum directement
if ($user->role->isDoctor()) {
    // Logique ici
}

// ❌ INTERDIT - Logique métier dans l'attribut
protected function canEdit(): Attribute
{
    return Attribute::make(
        get: fn () => $this->role->isAdmin() || $this->is_editor,
    );
}

// Utilisation dans une Action
final class UpdateUserAction extends AbstractAction
{
    public function __construct(
        private readonly ValidateUserEditTask $validateUserEditTask,
        private readonly UserService $userService,
    ) {}
    
    public function run(int $userId, UpdateUserRequest $request): JsonResponse
    {
        // La Task vérifie les droits et appelle abort() si nécessaire
        $this->validateUserEditTask->execute(new ValidateUserEditRecord(
            targetUserId: $userId,
            currentUserId: auth()->id(),
            currentUserRole: auth()->user()->role,
        ));
        
        // Suite de la logique...
        $record = new UpdateUserRecord(
            id: $userId,
            name: $request->input('name'),
            email: $request->input('email'),
        );
        
        $userRecord = $this->userService->updateUser($record);
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}

// Dans la Task, on gère l'abort en cas de non droit
final class ValidateUserEditTask extends AbstractTask
{
    public function execute(ValidateUserEditRecord $record): bool
    {
        // Admin peut tout modifier
        if ($record->currentUserRole->isAdmin()) {
            return true;
        }
        
        // Un utilisateur ne peut modifier que son propre profil
        if ($record->currentUserId === $record->targetUserId) {
            return true;
        }
        
        abort(403, 'Unauthorized to edit this user');
    }
}
```

### 5.5 Relations

```php
// Relations simples
public function posts(): HasMany
{
    return $this->hasMany(Post::class);
}

public function profile(): HasOne
{
    return $this->hasOne(Profile::class);
}

public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class);
}

// Relations avec conditions (mais sans logique métier)
public function activePosts(): HasMany
{
    return $this->hasMany(Post::class)->where('is_active', true);
}
```

### 5.6 Scopes

> **Les scopes sont autorisés pour factoriser des requêtes réutilisables.**

```php
// ✅ BON - Scope local
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

public function scopeByRole(Builder $query, UserRole $role): Builder
{
    return $query->where('role', $role);
}

// Utilisation (via Repository)
$activeUsers = $this->userRepository->active()->get();
```

---

## 6. Ce qui est INTERDIT dans un Model (⚠️ STRICTEMENT)

### 6.1 Méthodes utilitaires simples

```php
// ❌ STRICTEMENT INTERDIT
public function isAdmin(): bool
{
    return $this->role === 'admin';
}

public function isActive(): bool
{
    return $this->is_active === true;
}

public function canEdit(): bool
{
    return $this->role === 'admin' || $this->is_editor;
}
```

#### 6.1.1 Alternative : Déplacer dans l'Enum ou dans une Task

```php
// App\Enums\UserRole.php (nom correspond au champ 'role')
enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
    
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
    
    public function isDoctor(): bool
    {
        return $this === self::DOCTOR;
    }
}

// Task pour la validation métier complexe
final class ValidateUserEditTask extends AbstractTask
{
    public function execute(ValidateUserEditRecord $record): bool
    {
        if ($record->currentUserRole->isAdmin()) {
            return true;
        }
        
        return $record->currentUserId === $record->targetUserId;
    }
}

// Utilisation (via Repository)
$user = $this->userRepository->find(1);
if ($user->role->isAdmin()) { ... }  // ✅
```

### 6.2 Constantes

```php
// ❌ STRICTEMENT INTERDIT
const STATUS_ACTIVE = 'active';
const STATUS_INACTIVE = 'inactive';
const ROLE_ADMIN = 'admin';
const ROLE_USER = 'user';

// ✅ BON - Utiliser des Enums avec le bon nommage
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
}
```

### 6.3 Logique métier

```php
// ❌ STRICTEMENT INTERDIT - Logique métier dans le Model
public function calculateTotal(): float
{
    return $this->items->sum(fn($item) => $item->price * $item->quantity);
}

public function hasAvailableSlots(DateTimeInterface $date): bool
{
    return $this->slots()->where('date', $date)->exists();
}

// ✅ BON - Déplacer dans un Service
final class OrderService
{
    public function calculateTotal(OrderRecord $record): float { ... }
}

final class DoctorAvailabilityService
{
    public function hasAvailableSlots(int $doctorId, DateTimeInterface $date): bool { ... }
}
```

### 6.4 Accès direct à d'autres Models

```php
// ❌ STRICTEMENT INTERDIT - Accès direct à un autre Model
public function getDoctorAvailability(): Collection
{
    return Availability::where('doctor_id', $this->id)->get();
}

// ✅ BON - Passer par un Service
final class DoctorAvailabilityService
{
    public function getAvailability(int $doctorId): Collection { ... }
}
```
### 6.5 Transformation Model → Record

```php
// ❌ STRICTEMENT INTERDIT - Méthode fromModel dans le Record
final class UserRecord extends AbstractRecord
{
    public static function fromModel(User $user): self  // ❌ N'EXISTE PAS
    {
        return new self(...);
    }
}

// ✅ BON - Transformation explicite dans le Service
final class UserService
{
    public function getUser(int $id): UserRecord
    {
        $user = $this->userRepository->find($id);
        
        return new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
}
```

### 6.6 Méthodes dépréciées

```php
// ❌ STRICTEMENT INTERDIT - Anciennes méthodes d'attributs
public function getFirstNameAttribute(string $value): string
{
    return ucfirst($value);
}

public function setPasswordAttribute(string $value): void
{
    $this->attributes['password'] = bcrypt($value);
}

// ✅ BON - Utiliser Attribute
protected function firstName(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst($value),
    );
}

protected function password(): Attribute
{
    return Attribute::make(
        set: fn (string $value) => bcrypt($value),
    );
}
```

---

## 7. Accès aux Models (⚠️ RÈGLE D'OR)

> **⚠️ Toute interaction avec un Model DOIT passer par un Repository. Pas de `User::find($id)` dans les Services ou Actions.**

```php
// ❌ MAUVAIS - Appel direct au Model
final class UserService
{
    public function getUser(int $id): ?User
    {
        return User::find($id);  // ❌
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
        return $this->userRepository->find($id);  // ✅
    }
}
```

---

## 8. Exemple complet

### 8.1 Le Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use AndyDefer\BestPractices\Casts\JsonCast;
use AndyDefer\BestPractices\Casts\MoneyCast;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;
use function best_practices_limit;

final class User extends Model
{
    use HasFactory;
    
    protected $table = 'users';
    protected $primaryKey = 'id';
    public $timestamps = true;
    
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'role',
        'status',
        'metadata',
        'wallet',
    ];
    
    protected $hidden = [
        'password',
        'remember_token',
    ];
    
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'role' => UserRole::class,
        'status' => UserStatus::class,
        'metadata' => JsonCast::class,
        'wallet' => MoneyCast::class,
    ];
    
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    // ========== Attributs (formatage/concaténation uniquement) ==========
    
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => $attributes['first_name'] . ' ' . $attributes['last_name'],
        );
    }
    
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst(strtolower($value)),
            set: fn (string $value) => strtolower($value),
        );
    }
    
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => strtoupper($value),
            set: fn (string $value) => strtolower($value),
        );
    }
    
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => strtolower($value),
            set: fn (string $value) => strtolower($value),
        );
    }
    
    // ========== Attributs de collection (LIMITÉS et explicites) ==========
    
    protected function recentPosts(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): Collection {
                return $this->posts()
                    ->orderBy('created_at', 'desc')
                    ->limit(best_practices_limit())
                    ->get();
            },
        );
    }
    
    protected function publicPosts(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): Collection {
                return $this->posts()
                    ->where('is_public', true)
                    ->orderBy('created_at', 'desc')
                    ->limit(best_practices_limit())
                    ->get();
            },
        );
    }
    
    // ========== Relations ==========
    
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
    
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }
    
    // ========== Scopes ==========
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
    
    public function scopeByRole(Builder $query, UserRole $role): Builder
    {
        return $query->where('role', $role);
    }
}
```

### 8.2 Les Enums associés

```php
<?php

declare(strict_types=1);

namespace App\Enums;

// Nom = champ 'role' → UserRole
enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
    
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
    
    public function isDoctor(): bool
    {
        return $this === self::DOCTOR;
    }
    
    public function getLabel(): string
    {
        return match($this) {
            self::ADMIN => 'Administrator',
            self::USER => 'Standard User',
            self::DOCTOR => 'Medical Doctor',
        };
    }
}

// Nom = champ 'status' → UserStatus
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
```

### 8.3 La Task pour la validation métier

```php
<?php

declare(strict_types=1);

namespace App\Tasks\User;

use App\Records\ValidateUserEditRecord;
use App\Tasks\AbstractTask;

final class ValidateUserEditTask extends AbstractTask
{
    public function execute(ValidateUserEditRecord $record): bool
    {
        // Admin peut tout modifier
        if ($record->currentUserRole->isAdmin()) {
            return true;
        }
        
        // Un utilisateur ne peut modifier que son propre profil
        if ($record->currentUserId === $record->targetUserId) {
            return true;
        }
        
        return false;
    }
}
```

### 8.4 Le Repository

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use App\Records\UserCreateRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class UserRepository
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }
    
    public function create(UserCreateRecord $record): User
    {
        return DB::transaction(function () use ($record) {
            return User::create([
                'first_name' => $record->firstName,
                'last_name' => $record->lastName,
                'email' => $record->email,
                'password' => bcrypt($record->password),
                'role' => $record->role,
            ]);
        });
    }
    
    public function findActiveByRole(UserRole $role): Collection
    {
        return User::active()->byRole($role)->get();
    }
}
```

### 8.5 Le Service (transformation explicite)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Records\UserRecord;
use App\Repositories\UserRepository;

final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function getUser(int $id): ?UserRecord
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return null;
        }
        
        // Transformation explicite Model → Record
        return new UserRecord(
            id: $user->id,
            fullName: $user->full_name,
            email: $user->email,
            role: $user->role,
            recentPosts: $user->recent_posts->toArray(),
            createdAt: $user->created_at->toIso8601String(),
        );
    }
}
```

---
## 9. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Logique métier** | ❌ Interdit (déplacer dans Service ou Task) |
| **Méthodes `isXxx()`** | ❌ Interdit (déplacer dans Enum) |
| **Constantes** | ❌ Interdit (utiliser Enum) |
| **`getXxxAttribute` / `setXxxAttribute`** | ❌ Interdit (utiliser `Attribute`) |
| **Attribut pour condition logique** | ❌ Interdit (réserver au formatage) |
| **Attribut de collection sans limit** | ❌ Interdit (TOUJOURS limité) |
| **Nom d'attribut de collection générique** | ❌ Interdit (doit être explicite) |
| **Transformation Model → Record** | ❌ Interdit (faire dans le Service) |
| **Accès direct à autre Model** | ❌ Interdit (passer par Service) |
| **Accès direct à Model** | ❌ Interdit (passer par Repository) |
| **Nommage Enum** | ✅ `PascalCase` correspondant au champ (`UserRole` pour `role`) |
| **Casts** | ✅ Oui (obligatoire pour types non scalaires) |
| **Relations** | ✅ Oui |
| **Scopes** | ✅ Oui |
| **Attributs (`Attribute` pour formatage)** | ✅ Oui |
| **Fillable / Guarded** | ✅ Oui |
| **Hidden / Visible** | ✅ Oui |
---

## 10. Règle d'or

> **Un Model ne fait que déclarer sa structure : table, casts, relations, scopes, attributs de formatage. Pas de logique métier. Pas de méthodes isXxx. Pas de constantes. Les Enums remplacent les constantes, portent le nom du champ (`UserRole` pour `role`), et contiennent les méthodes isXxx. Les attributs de collection sont TOUJOURS limités et nommés explicitement. Toute interaction passe par un Repository. La transformation Model → Record est explicite dans le Service. La logique métier complexe (comme `canEdit()`) est déplacée dans une Task qui retourne un booléen.**

```php
// Le Model parfait
final class PerfectModel extends Model
{
    protected $table = 'perfect_models';
    protected $fillable = ['field_one', 'field_two'];
    
    protected $casts = [
        'field_one' => PerfectModelFieldOneEnum::class,
        'field_two' => JsonCast::class,
    ];
    
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => $attrs['first_name'] . ' ' . $attrs['last_name'],
        );
    }
    
    protected function recentItems(): Attribute
    {
        return Attribute::make(
            get: function (): Collection {
                return $this->items()
                    ->orderBy('created_at', 'desc')
                    ->limit(best_practices_limit())
                    ->get();
            },
        );
    }
    
    public function children(): HasMany
    {
        return $this->hasMany(Child::class);
    }
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

// L'Enum associé
enum PerfectModelFieldOneEnum: string
{
    case VALUE_A = 'a';
    case VALUE_B = 'b';
    
    public function isValueA(): bool
    {
        return $this === self::VALUE_A;
    }
}

// La Task pour la logique métier
final class ValidateActionTask extends AbstractTask
{
    public function execute(ValidateActionRecord $record): bool
    {
        if ($record->currentUserRole->isAdmin()) {
            return true;
        }
        
        return $record->currentUserId === $record->targetUserId;
    }
}

// Utilisation
$user = $this->userRepository->find(1);
if ($user->field_one->isValueA()) { ... }

$canEdit = $this->validateActionTask->execute(new ValidateActionRecord(...));
```