Voici votre documentation des Models corrigée avec les bonnes pratiques des Value Objects :

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
        'metadata' => 'array',
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
| **Duplication des requêtes** | Les Repositories centralisent les requêtes complexes |
| **Données complexes** | Les Services et Value Objects gèrent la logique |

### 2.1 Règle fondamentale (⚠️ IMMUABLE)

> **Un Model ne contient AUCUNE logique métier. Il ne contient que des déclarations : table, fillable, casts, relations, attributs de formatage.**

```php
// ✅ BON - Model avec déclarations uniquement
final class User extends Model
{
    protected $fillable = ['first_name', 'last_name', 'email'];
    protected $casts = ['metadata' => 'array'];
    
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => $attrs['first_name'] . ' ' . $attrs['last_name'],
        );
    }
    
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => strtolower($value),
            set: fn (string $value) => strtolower($value),
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

## 3. Gestion des données complexes via les Services et Value Objects

> **Le package ne fournit plus de casts (MoneyCast, JsonCast). La gestion des données complexes est désormais assurée par des Services (pour les transformations) et des Value Objects (pour les concepts métier).**

### 3.1 Service avec Config injectée (pas d'état interne)

```php
// ✅ BON - Service avec Config injectée (pas d'état interne)
final class MoneyService
{
    public function __construct(
        private readonly MoneyConfig $config,  // ✅ Config injectée
    ) {}
    
    public function fromCents(?int $cents): ?float
    {
        if ($cents === null) {
            return null;
        }
        
        return round($cents / $this->config->getUnitMultiplier(), $this->config->getDecimalPlaces());
    }
    
    public function toCents(?float $euros): ?int
    {
        if ($euros === null) {
            return null;
        }
        
        return (int) round($euros * $this->config->getUnitMultiplier(), 0);
    }
}

// Config associée
final class MoneyConfig extends AbstractConfig
{
    public function getDecimalPlaces(): int
    {
        return (int) (getenv('MONEY_DECIMAL_PLACES') ?: 2);
    }
    
    public function getUnitMultiplier(): int
    {
        return (int) (getenv('MONEY_UNIT_MULTIPLIER') ?: 100);
    }
    
    public function getDefaultCurrency(): string
    {
        return getenv('DEFAULT_CURRENCY') ?: 'EUR';
    }
}
```

### 3.2 Value Objects (héritent de AbstractValueObject)

> **⚠️ Les Value Objects utilisent la méthode `from()` héritée de `AbstractValueObject`, pas des méthodes statiques personnalisées.**

```php
// Value Object pour Email (avec validation et comportement)
final class EmailAddress extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email: {$this->value}");
        }
    }
    
    public function getDomain(): string
    {
        return substr(strrchr($this->value, "@"), 1);
    }
    
    public function getLocalPart(): string
    {
        return explode('@', $this->value)[0];
    }
    
    public function isGmail(): bool
    {
        return $this->getDomain() === 'gmail.com';
    }
    
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}

// Value Object pour Money (avec opérations métier)
final class Money extends AbstractValueObject
{
    public function __construct(
        public readonly int $cents,
        public readonly Currency $currency,
    ) {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }
    
    public function add(self $other): self
    {
        if (!$this->currency->equals($other->currency)) {
            throw new \InvalidArgumentException('Cannot add different currencies');
        }
        
        return new self($this->cents + $other->cents, $this->currency);
    }
    
    public function getAmount(): float
    {
        return $this->cents / 100;
    }
    
    public function format(): string
    {
        return $this->currency->getSymbol() . number_format($this->getAmount(), 2);
    }
    
    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency->equals($other->currency);
    }
    
    public function getValue(): array
    {
        return [
            'cents' => $this->cents,
            'currency' => $this->currency->getValue(),
        ];
    }
}

// Utilisation des Value Objects (via la méthode from() héritée)
$email = EmailAddress::from('john@example.com');  // ✅ from() et non fromString()
echo $email->getValue();    // 'john@example.com'
echo $email->getDomain();   // 'example.com'

$money = Money::from(['cents' => 1999, 'currency' => Currency::EUR]);
echo $money->getAmount();   // 19.99
echo $money->format();      // '€19.99'
```

---

## 4. Attributs de formatage avec `Attribute::make` (⚠️ RÈGLE STRICTE)

> **⚠️ Les Attributs sont réservés au formatage, à la concaténation, ET à la transformation en Value Objects. Ils NE DOIVENT PAS contenir de logique métier, de validation, ou de conditions complexes.**

### 4.1 Formatage simple (get uniquement)

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

// Formatage de date
protected function formattedDate(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => Carbon::parse($attributes['created_at'])->format('d/m/Y'),
    );
}
```

### 4.2 Normalisation (get et set)

```php
// Email : mise en minuscule à l'écriture et à la lecture
protected function email(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => strtolower($value),
        set: fn (string $value) => strtolower($value),
    );
}

// First name : mise en minuscule à l'écriture, ucfirst à la lecture
protected function firstName(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst(strtolower($value)),
        set: fn (string $value) => strtolower($value),
    );
}
```

### 4.3 Transformation en Value Objects (⚠️ RÈGLE IMPORTANTE)

> **Pour transformer une propriété du Model en Value Object, on utilise `Attribute::make` avec la méthode `from()` du VO.**

```php

// ✅ BON - Transformation en EmailAddress VO
protected function emailAddress(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => EmailAddress::from($attributes['email']),
        set: fn (EmailAddress $email) => ['email' => $email->getValue()],
    );
}

// ✅ BON - Transformation en Money VO
protected function wallet(): Attribute
{
    return Attribute::make(
        get: fn (mixed $value, array $attributes) => Money::from([
            'cents' => $attributes['wallet_cents'],
            'currency' => Currency::from($attributes['currency']),
        ]),
        set: fn (Money $money) => [
            'wallet_cents' => $money->cents,
            'currency' => $money->currency->getValue(),
        ],
    );
}

// ✅ BON - Attribut de collection avec transformation en VO
protected function recentPosts(): Attribute
{
    return Attribute::make(
        get: function (mixed $value, array $attributes): Collection {
            return $this->posts()
                ->orderBy('created_at', 'desc')
                ->limit($$this->config->limit)
                ->get()
                ->map(fn(Post $post) => PostSummaryVO::from($post->toArray()));
        },
    );
}
```

### 4.4 Ce qui est AUTORISÉ dans `Attribute::make`

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Concaténation de chaînes** | ✅ | `$first . ' ' . $last` |
| **Formatage numérique** | ✅ | `number_format($price, 2)` |
| **Mise en minuscule/uppercase** | ✅ | `strtolower()`, `ucfirst()` |
| **Transformation en Value Object** | ✅ | `EmailAddress::from($value)` |
| **Tri simple** | ✅ | `orderBy('created_at', 'desc')` |
| **Validation** | ❌ | `filter_var($email, FILTER_VALIDATE_EMAIL)` |
| **Logique métier** | ❌ | `if ($this->isAdmin())` |
| **Calculs complexes** | ❌ | `$this->items->sum('price')` |
| **Accès à d'autres Models** | ❌ | `$this->otherModel->something` |
| **Appels à des Services** | ❌ | `$this->service->calculate()` |
| **Conditions complexes** | ❌ | `match`, `switch`, `if/else` imbriqués |

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

### 5.2 Casts (types Laravel natifs)

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

// ❌ MAUVAIS - Nom de l'Enum générique
protected $casts = [
    'role' => Role::class,           // ❌ Devrait être UserRole
    'status' => Status::class,       // ❌ Devrait être UserStatus
];
```

### 5.4 Relations

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

### 5.5 Scopes

> **Les scopes sont autorisés pour factoriser des requêtes réutilisables.**

```php
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}

public function scopeByRole(Builder $query, UserRole $role): Builder
{
    return $query->where('role', $role);
}
```

---

## 6. Ce qui est INTERDIT dans un Model (⚠️ STRICTEMENT)

### 6.1 Méthodes `isXxx()` (logique métier)

```php
// ❌ STRICTEMENT INTERDIT
public function isAdmin(): bool
{
    return $this->role === 'admin';
}

// ✅ BON - Déplacer dans l'Enum
enum UserRole: string
{
    case ADMIN = 'admin';
    
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }
}

// Utilisation
if ($user->role->isAdmin()) { ... }
```

### 6.2 Constantes

```php
// ❌ STRICTEMENT INTERDIT
const STATUS_ACTIVE = 'active';
const ROLE_ADMIN = 'admin';

// ✅ BON - Utiliser des Enums
enum UserStatus: string
{
    case ACTIVE = 'active';
}

enum UserRole: string
{
    case ADMIN = 'admin';
}
```

### 6.3 Logique métier complexe

```php
// ❌ STRICTEMENT INTERDIT
public function calculateTotal(): float
{
    return $this->items->sum(fn($item) => $item->price * $item->quantity);
}

// ✅ BON - Déplacer dans un Service
final class OrderService
{
    public function calculateTotal(OrderRecord $record): Money
    {
        // Logique ici
    }
}
```

### 6.4 Accès direct à d'autres Models

```php
// ❌ STRICTEMENT INTERDIT
public function getDoctorAvailability(): Collection
{
    return Availability::where('doctor_id', $this->id)->get();
}

// ✅ BON - Passer par un Service ou Repository
final class DoctorAvailabilityService
{
    public function getAvailability(int $doctorId): Collection
    {
        return $this->availabilityRepository->findByDoctorId($doctorId);
    }
}
```

### 6.5 Méthodes dépréciées

```php
// ❌ STRICTEMENT INTERDIT - Anciennes méthodes d'attributs
public function getFirstNameAttribute(string $value): string
{
    return ucfirst($value);
}

// ✅ BON - Utiliser Attribute
protected function firstName(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst($value),
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

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\ValueObjects\EmailAddress;
use App\ValueObjects\Money;
use App\ValueObjects\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;

final class User extends Model
{
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
        'wallet_cents',
        'currency',
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
        'metadata' => 'array',
    ];
    
    // ========== Attributs de formatage ==========
    
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => $attrs['first_name'] . ' ' . $attrs['last_name'],
        );
    }
    
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => ucfirst(strtolower($value)),
            set: fn (string $value) => strtolower($value),
        );
    }
    
    // ========== Transformation en Value Object ==========
    
    protected function emailAddress(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => EmailAddress::from($attrs['email']),
            set: fn (EmailAddress $email) => ['email' => $email->getValue()],
        );
    }
    
    protected function wallet(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attrs) => Money::from([
                'cents' => $attrs['wallet_cents'],
                'currency' => Currency::from($attrs['currency']),
            ]),
            set: fn (Money $money) => [
                'wallet_cents' => $money->cents,
                'currency' => $money->currency->getValue(),
            ],
        );
    }
    
    // ========== Attributs de collection limités ==========
    
    protected function recentPosts(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes): Collection {
                return $this->posts()
                    ->orderBy('created_at', 'desc')
                    ->limit($this->config->limit)
                    ->get();
            },
        );
    }
    
    // ========== Relations ==========
    
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}

// Utilisation
$user = User::find(1);

// Accès via l'attribut qui transforme en Value Object
$email = $user->emailAddress;  // EmailAddress VO
echo $email->getValue();       // 'john@example.com'
echo $email->getDomain();      // 'example.com'

// Accès via l'attribut wallet
$wallet = $user->wallet;       // Money VO
echo $wallet->format();        // '€19.99'

// Collection limitée
foreach ($user->recentPosts as $post) {
    echo $post->title;
}
```

---

## 9. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Logique métier** | ❌ Interdit (déplacer dans Service ou Value Object) |
| **Méthodes `isXxx()`** | ❌ Interdit (déplacer dans Enum) |
| **Constantes** | ❌ Interdit (utiliser Enum) |
| **`getXxxAttribute` / `setXxxAttribute`** | ❌ Interdit (utiliser `Attribute::make`) |
| **Attribut pour condition logique** | ❌ Interdit (réserver au formatage) |
| **Attribut de collection sans limit** | ❌ Interdit (TOUJOURS limité) |
| **Validation dans Attribute** | ❌ Interdit (faire dans le Value Object) |
| **Accès direct à autre Model** | ❌ Interdit (passer par Service/Repository) |
| **Accès direct à Model** | ❌ Interdit (passer par Repository) |
| **Nommage Enum** | ✅ `PascalCase` correspondant au champ |
| **Casts** | ✅ Types natifs Laravel uniquement |
| **Relations** | ✅ Oui |
| **Scopes** | ✅ Oui |
| **Attributs `Attribute::make`** | ✅ Pour formatage, concaténation, transformation en VO |
| **Value Objects** | ✅ Héritent de `AbstractValueObject`, utilisent `from()` |

---

## 10. Règle d'or

> **Un Model ne fait que déclarer sa structure : table, casts, relations, scopes, attributs de formatage. Pas de logique métier. Pas de méthodes isXxx. Pas de constantes.**
>
> **⚠️ Les Attributs (`Attribute::make`) sont réservés au formatage, à la concaténation, et à la transformation en Value Objects via la méthode `from()`. Ils NE DOIVENT PAS contenir de validation, de logique métier, ou de conditions complexes.**
>
> **Les Value Objects héritent de `AbstractValueObject` et utilisent la méthode `from()` pour l'hydratation. Ils implémentent obligatoirement `getValue()` pour exposer leur valeur brute.**
>
> **Les Services orchestrent et transforment les données sans état interne (les Configs sont injectées).**
>
> **Toute interaction avec un Model passe par un Repository.**

```php
// Le Value Object parfait (hérite de AbstractValueObject)
final class EmailAddress extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: {$value}");
        }
    }
    
    public function getDomain(): string
    {
        return substr(strrchr($this->value, '@'), 1);
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
    
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

// Utilisation dans le Model
protected function email(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => EmailAddress::from($value),
        set: fn (EmailAddress $email) => ['email' => $email->getValue()],
    );
}

// Utilisation
$user = User::find(1);
$email = $user->email;  // EmailAddress VO
echo $email->getValue();    // 'john@example.com'
echo $email->getDomain();   // 'example.com'
```
---