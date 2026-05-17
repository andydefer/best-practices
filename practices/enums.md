# Principe d'usage des Enums (Version finale)

## 1. Définition

Un **Enum** est une structure de données qui définit un ensemble fixe de valeurs possibles. Il remplace les constantes de classe et les tableaux de valeurs.

```
Enum → Ensemble fixe de valeurs → Backed (string|int) → Nom en PascalCase
```

```php
enum UserRole: string
{
    use Enumerable;

    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::ADMIN => $language === Language::EN ? 'Administrator' : 'Administrateur',
            self::USER => $language === Language::EN ? 'User' : 'Utilisateur',
            self::DOCTOR => $language === Language::EN ? 'Doctor' : 'Médecin',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::ADMIN => 'shield',
            self::USER => 'user',
            self::DOCTOR => 'stethoscope',
        };
    }
    
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

---

## 2. Règles fondamentales

### 2.1 Backed Enum (⚠️ OBLIGATOIRE)

> **Tout Enum DOIT être un backed enum (valeurs associées de type `string` ou `int`).**

```php
// ✅ BON - Backed enum avec string
enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
}

// ✅ BON - Backed enum avec int
enum HttpStatusCode: int
{
    case OK = 200;
    case NOT_FOUND = 404;
}

// ❌ MAUVAIS - Pure enum (non backed)
enum UserRole
{
    case ADMIN;  // ❌ Interdit
    case USER;
}
```

### 2.2 Nommage (⚠️ STRICT)

> **Le nom de l'Enum DOIT être en `PascalCase`. S'il est lié à un Model, le nom est la concaténation du nom du Model et du nom du champ.**

| Contexte | Exemple | Nom de l'Enum |
|----------|---------|---------------|
| Lié à un Model (`user->status`) | `App\Models\User` | `UserStatus` |
| Lié à un Model (`order->state`) | `App\Models\Order` | `OrderState` |
| Indépendant (non lié à un Model) | Code HTTP | `HttpStatusCode` |
| Indépendant (non lié à un Model) | Niveaux de priorité | `PriorityLevel` |

```php
// ✅ BON - Lié à un Model
namespace App\Enums;
enum UserStatus: string { ... }    // Pour user->status
enum OrderState: string { ... }    // Pour order->state

// ✅ BON - Indépendant (non lié à un Model)
enum HttpStatusCode: int { ... }
enum PriorityLevel: string { ... }

// ❌ MAUVAIS - Nom trop générique
enum Status: string { ... }
enum Role: string { ... }
```

### 2.3 Convention des clés et valeurs

> **Les clés (cases) sont en `SCREAMING_SNAKE_CASE`. Les valeurs sont en `snake_case`.**

```php
// ✅ BON
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING_APPROVAL = 'pending_approval';
    case EMAIL_VERIFICATION_PENDING = 'email_verification_pending';
}

// ❌ MAUVAIS
enum UserStatus: string
{
    case Active = 'active';          // ❌ PascalCase
    case Inactive = 'inactive';      // ❌ PascalCase
    case pending = 'pending';        // ❌ lowercase
    case PendingApproval = 'pending_approval';  // ❌ PascalCase
}
```

---

## 3. Trait `Enumerable`

> **Tout Enum DOIT utiliser le trait `Enumerable` qui fournit les méthodes utilitaires.**

### 3.1 Code du trait `Enumerable`

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Traits\Enum;

/**
 * Provides common utility methods for PHP 8.1+ Enums.
 *
 * This trait adds convenient methods to enums for value validation, listing,
 * and case retrieval. It works with both backed enums (with scalar values)
 * and pure enums (without values).
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Traits\Enum
 */
trait Enumerable
{
    /**
     * Returns all scalar values from the enum.
     *
     * For backed enums (string|int), returns the backing values.
     * For pure enums (without values), returns the case names.
     *
     * @return array<int, string|int> Array of enum values or case names
     */
    public static function values(): array
    {
        if (self::isBackedEnum()) {
            return array_column(self::cases(), 'value');
        }

        return array_column(self::cases(), 'name');
    }

    /**
     * Returns all case names from the enum.
     *
     * @return array<int, string> Array of enum case names (UPPER_CASE format)
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Returns all enum cases in their defined order.
     *
     * This is an alias for the native cases() method that provides a more
     * semantic name when the intent is to respect the definition order.
     *
     * @return array<int, self> Array of all enum cases
     */
    public static function typesInOrder(): array
    {
        return self::cases();
    }

    /**
     * Checks if a given value exists in the enum.
     *
     * For backed enums, checks against backing values.
     * For pure enums, checks against case names.
     *
     * @param string|int $value The value to validate
     * @return bool True if the value exists in the enum, false otherwise
     */
    public static function isValid(string|int $value): bool
    {
        if (self::isBackedEnum()) {
            return in_array($value, self::values(), true);
        }

        return in_array($value, self::names(), true);
    }

    /**
     * Retrieves the enum case corresponding to a value.
     *
     * For backed enums, returns the case with the matching backing value.
     * For pure enums, attempts to find a case by name (case-sensitive).
     *
     * @param string|int $value The value to search for
     * @return self|null The matching enum case, or null if not found
     */
    public static function fromValue(string|int $value): ?self
    {
        if (self::isBackedEnum()) {
            return self::tryFrom($value);
        }

        $value = (string) $value;
        foreach (self::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Checks if the enum is a backed enum (has scalar values).
     *
     * @return bool True if the enum is backed, false if it's a pure enum
     */
    private static function isBackedEnum(): bool
    {
        return is_subclass_of(self::class, \BackedEnum::class);
    }
}
```

### 3.2 Comportement selon le type d'Enum

| Méthode | Backed Enum | Pure Enum |
|---------|-------------|-----------|
| `values()` | Retourne les valeurs (ex: `['active', 'inactive']`) | Retourne les noms des cas (ex: `['ACTIVE', 'INACTIVE']`) |
| `names()` | Retourne les noms des cas | Retourne les noms des cas |
| `typesInOrder()` | Retourne tous les cas dans l'ordre de définition | Retourne tous les cas dans l'ordre de définition |
| `isValid(string\|int $value)` | Vérifie si la valeur existe | Vérifie si le nom du cas existe |
| `fromValue(string\|int $value)` | Retourne le cas par valeur | Retourne le cas par nom (ou null si non trouvé) |

### 3.3 Utilisation du trait

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum UserRole: string
{
    use Enumerable;
    
    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
}

// Utilisation
UserRole::values();      // ['admin', 'user', 'doctor']
UserRole::names();       // ['ADMIN', 'USER', 'DOCTOR']
UserRole::typesInOrder(); // [UserRole::ADMIN, UserRole::USER, UserRole::DOCTOR]
UserRole::isValid('admin');  // true
UserRole::isValid('invalid'); // false
UserRole::fromValue('admin'); // UserRole::ADMIN
UserRole::fromValue('unknown'); // null
```

---

## 4. Méthodes autorisées dans un Enum

### 4.1 Méthodes de formatage avec préfixe `get`

> **Les méthodes de formatage comme `getLabel()`, `getIcon()`, `getColor()` sont autorisées. Elles doivent commencer par le préfixe `get`.**

```php
// ✅ BON - Méthodes de formatage avec préfixe 'get'
enum UserStatus: string
{
    use Enumerable;
    
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::ACTIVE => $language === Language::EN ? 'Active' : 'Actif',
            self::INACTIVE => $language === Language::EN ? 'Inactive' : 'Inactif',
            self::SUSPENDED => $language === Language::EN ? 'Suspended' : 'Suspendu',
            self::BANNED => $language === Language::EN ? 'Banned' : 'Banni',
        };
    }
    
    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::SUSPENDED => 'orange',
            self::BANNED => 'red',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'minus-circle',
            self::SUSPENDED => 'alert-circle',
            self::BANNED => 'x-circle',
        };
    }
}

// ❌ MAUVAIS - Méthodes de formatage sans préfixe 'get'
public function label(): string { ... }  // ❌ Interdit
public function color(): string { ... }  // ❌ Interdit
public function icon(): string { ... }   // ❌ Interdit
```

### 4.2 Méthodes utilitaires métier (⚠️ RÈGLES STRICTES)

> **Les méthodes utilitaires métier sont autorisées UNIQUEMENT sous la forme `is{Case}`. Elles retournent TOUJOURS un `bool` et ne prennent aucun paramètre.**

```php
// ✅ BON - Méthodes utilitaires (is + nom du case)
enum UserStatus: string
{
    use Enumerable;
    
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }
    
    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
    
    public function isBanned(): bool
    {
        return $this === self::BANNED;
    }
}

// ❌ MAUVAIS - Méthodes utilitaires mal nommées
public function canLogin(): bool { ... }   // ❌ Pas is{Case}
public function isBlocked(): bool { ... }  // ❌ Pas is{Case} (BANNED existe)

// ❌ MAUVAIS - Méthodes utilitaires avec paramètres
public function isActive(Language $language): bool { ... }  // ❌ Pas de paramètre

// ❌ MAUVAIS - Méthodes utilitaires qui ne retournent pas bool
public function getStatus(): string { ... }  // ❌ Ne retourne pas bool
```

### 4.3 Types de paramètres autorisés

> **Une méthode d'un enum ne peut prendre que des paramètres de type `scalaire` ou `Enum`. Pas de `Record`, pas de `Data`, pas de `Model`.**

```php
// ✅ BON - Paramètres scalaires ou Enum
public function getLabel(Language $language = Language::FR): string { ... }
public function getLabelWithFallback(string $default): string { ... }
public function isCompatibleWith(PriorityLevel $level): bool { ... }

// ❌ MAUVAIS - Paramètres interdits
public function getLabel(Record $record): string { ... }  // ❌ Record
public function getLabel(Data $data): string { ... }      // ❌ Data
public function getLabel(User $user): string { ... }      // ❌ Model
public function getLabel(array $options): string { ... }  // ❌ Array
```

### 4.4 Types de retour autorisés

> **Les méthodes d'un enum ne peuvent retourner que des `scalaires` ou des `array de scalaires`. Pas de `Record`, pas de `Data`, pas de `Model`.**

```php
// ✅ BON - Retours scalaires
public function getLabel(): string { ... }
public function getValue(): string { ... }
public function getOrder(): int { ... }

// ✅ BON - Retour array de scalaires
public function getAvailableTransitions(): array
{
    return match ($this) {
        self::ACTIVE => ['inactive', 'suspended'],
        self::INACTIVE => ['active'],
        self::SUSPENDED => ['active', 'banned'],
        self::BANNED => [],
    };
}

// ❌ MAUVAIS - Retours interdits
public function getRecord(): UserRecord { ... }  // ❌ Record
public function getData(): UserData { ... }      // ❌ Data
public function getModel(): User { ... }         // ❌ Model
```

### 4.5 Règle du `match` exhaustif

> **⚠️ Toute méthode utilisant `match` DOIT gérer TOUS les cas. L'utilisation de `default` est INTERDITE.**

```php
// ✅ BON - Tous les cas sont gérés explicitement
public function getLabel(): string
{
    return match ($this) {
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Inactive',
        self::SUSPENDED => 'Suspended',
        self::BANNED => 'Banned',
    };
}

// ❌ MAUVAIS - Utilisation de default (cache des cas manquants)
public function getLabel(): string
{
    return match ($this) {
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Inactive',
        default => 'Unknown',  // ❌ Interdit
    };
}

// ❌ MAUVAIS - Cas manquants
public function getLabel(): string
{
    return match ($this) {
        self::ACTIVE => 'Active',
        self::INACTIVE => 'Inactive',
        // ❌ SUSPENDED et BANNED non gérés
    };
}
```

---

## 5. Ce qu'un Enum ne peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Être un pure enum (non backed)** | Pas de valeur associée | Ajouter un type `string` ou `int` |
| **Avoir des clés en PascalCase** | Violation de la convention | Utiliser `SCREAMING_SNAKE_CASE` |
| **Avoir des valeurs en camelCase** | Violation de la convention | Utiliser `snake_case` |
| **Utiliser `default` dans `match`** | Cache des cas manquants | Gérer tous les cas explicitement |
| **Méthodes de formatage sans `get`** | Convention non respectée | Utiliser `getLabel()`, `getIcon()` |
| **Méthodes utilitaires sans `is`** | Convention non respectée | Utiliser `isActive()`, `isInactive()` |
| **Méthodes utilitaires avec paramètres** | Violation de la règle | Ne pas utiliser de paramètres |
| **Méthodes utilitaires retournant autre chose que `bool`** | Violation de la règle | Retourner uniquement `bool` |
| **Prendre des `Record` / `Data` / `Model`** | Violation de la couche | Utiliser des scalaires ou Enums |
| **Retourner des `Record` / `Data` / `Model`** | Violation de la couche | Retourner des scalaires ou array de scalaires |
| **Contenir de la logique métier complexe** | Violation SRP | Déplacer dans un Service |
| **Accéder à la base de données** | Violation de responsabilité | Déplacer dans un Repository |

---

## 6. Exemples complets

### 6.1 Enum lié à un Model

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum UserStatus: string
{
    use Enumerable;
    
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING_APPROVAL = 'pending_approval';
    case EMAIL_VERIFICATION_PENDING = 'email_verification_pending';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::ACTIVE => $language === Language::EN ? 'Active' : 'Actif',
            self::INACTIVE => $language === Language::EN ? 'Inactive' : 'Inactif',
            self::PENDING_APPROVAL => $language === Language::EN ? 'Pending Approval' : 'En attente d\'approbation',
            self::EMAIL_VERIFICATION_PENDING => $language === Language::EN ? 'Email Verification Pending' : 'En attente de vérification email',
            self::SUSPENDED => $language === Language::EN ? 'Suspended' : 'Suspendu',
            self::BANNED => $language === Language::EN ? 'Banned' : 'Banni',
        };
    }
    
    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
            self::PENDING_APPROVAL => 'yellow',
            self::EMAIL_VERIFICATION_PENDING => 'orange',
            self::SUSPENDED => 'orange',
            self::BANNED => 'red',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'minus-circle',
            self::PENDING_APPROVAL => 'clock',
            self::EMAIL_VERIFICATION_PENDING => 'mail',
            self::SUSPENDED => 'alert-circle',
            self::BANNED => 'x-circle',
        };
    }
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }
    
    public function isPendingApproval(): bool
    {
        return $this === self::PENDING_APPROVAL;
    }
    
    public function isEmailVerificationPending(): bool
    {
        return $this === self::EMAIL_VERIFICATION_PENDING;
    }
    
    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
    
    public function isBanned(): bool
    {
        return $this === self::BANNED;
    }
}
```

### 6.2 Enum indépendant (non lié à un Model)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

use AndyDefer\BestPractices\Traits\Enum\Enumerable;

enum HttpStatusCode: int
{
    use Enumerable;
    
    case OK = 200;
    case CREATED = 201;
    case ACCEPTED = 202;
    case BAD_REQUEST = 400;
    case UNAUTHORIZED = 401;
    case FORBIDDEN = 403;
    case NOT_FOUND = 404;
    case INTERNAL_SERVER_ERROR = 500;
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::OK => $language === Language::EN ? 'OK' : 'OK',
            self::CREATED => $language === Language::EN ? 'Created' : 'Créé',
            self::ACCEPTED => $language === Language::EN ? 'Accepted' : 'Accepté',
            self::BAD_REQUEST => $language === Language::EN ? 'Bad Request' : 'Mauvaise requête',
            self::UNAUTHORIZED => $language === Language::EN ? 'Unauthorized' : 'Non autorisé',
            self::FORBIDDEN => $language === Language::EN ? 'Forbidden' : 'Interdit',
            self::NOT_FOUND => $language === Language::EN ? 'Not Found' : 'Non trouvé',
            self::INTERNAL_SERVER_ERROR => $language === Language::EN ? 'Internal Server Error' : 'Erreur interne du serveur',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::OK, self::CREATED, self::ACCEPTED => 'check-circle',
            self::BAD_REQUEST, self::UNAUTHORIZED, self::FORBIDDEN, self::NOT_FOUND => 'alert-circle',
            self::INTERNAL_SERVER_ERROR => 'x-circle',
        };
    }
    
    public function isOk(): bool
    {
        return $this === self::OK;
    }
    
    public function isCreated(): bool
    {
        return $this === self::CREATED;
    }
    
    public function isBadRequest(): bool
    {
        return $this === self::BAD_REQUEST;
    }
    
    public function isNotFound(): bool
    {
        return $this === self::NOT_FOUND;
    }
    
    public function isServerError(): bool
    {
        return $this === self::INTERNAL_SERVER_ERROR;
    }
}
```

### 6.3 Intégration dans un Model

```php
// App\Models\User.php
final class User extends Model
{
    protected $casts = [
        'role' => UserRole::class,
        'status' => UserStatus::class,
    ];
}

// Utilisation (via Repository)
$user = $this->userRepository->find(1);

// Méthodes du trait Enumerable
$values = UserRole::values();      // ['admin', 'user', 'doctor']
$names = UserRole::names();        // ['ADMIN', 'USER', 'DOCTOR']
$isValid = UserRole::isValid('admin');  // true

// Méthodes de formatage (getLabel, getIcon, getColor)
echo $user->status->getLabel(Language::FR);  // 'Actif'
echo $user->status->getColor();              // 'green'
echo $user->status->getIcon();               // 'check-circle'

// Méthodes utilitaires (is + nom du case)
if ($user->role->isAdmin()) {
    // ...
}

if ($user->status->isActive()) {
    // ...
}

if ($user->status->isBanned()) {
    // ...
}
```

---

## 7. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Type** | Backed enum (`string` ou `int`) |
| **Nom de l'Enum (lié à Model)** | `{Model}{Field}` (ex: `UserStatus`) |
| **Nom de l'Enum (indépendant)** | `PascalCase` (ex: `HttpStatusCode`) |
| **Clés (cases)** | `SCREAMING_SNAKE_CASE` |
| **Valeurs** | `snake_case` |
| **Trait** | Doit utiliser `Enumerable` |
| **Méthodes de formatage** | Avec préfixe `get` (ex: `getLabel()`, `getIcon()`) |
| **Méthodes utilitaires** | `is{Case}` (ex: `isActive()`, `isInactive()`) |
| **Retour méthodes utilitaires** | Toujours `bool` |
| **Paramètres méthodes utilitaires** | Aucun paramètre |
| **Paramètres autorisés** | Scalaire ou Enum uniquement |
| **Retours autorisés** | Scalaire ou array de scalaires uniquement |
| **`match`** | Exhaustif (tous les cas, pas de `default`) |
| **`Record` / `Data` / `Model`** | ❌ Interdit en paramètre et retour |
| **Logique métier complexe** | ❌ Interdit |
| **Accès DB** | ❌ Interdit |

---

## 8. Règle d'or

> **Un Enum est un backed enum avec un nom en PascalCase ({Model}{Field} s'il est lié à un Model), des clés en SCREAMING_SNAKE_CASE, des valeurs en snake_case. Il utilise le trait Enumerable. Les méthodes de formatage commencent par `get`. Les méthodes utilitaires commencent par `is`, retournent `bool` et n'ont pas de paramètres. Tous les match sont exhaustifs.**

```php
// L'Enum parfait (lié à un Model)
enum PerfectEnum: string
{
    use Enumerable;
    
    case FIRST_VALUE = 'first_value';
    case SECOND_VALUE = 'second_value';
    case THIRD_VALUE = 'third_value';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::FIRST_VALUE => $language === Language::EN ? 'First Value' : 'Première valeur',
            self::SECOND_VALUE => $language === Language::EN ? 'Second Value' : 'Deuxième valeur',
            self::THIRD_VALUE => $language === Language::EN ? 'Third Value' : 'Troisième valeur',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::FIRST_VALUE => 'icon-first',
            self::SECOND_VALUE => 'icon-second',
            self::THIRD_VALUE => 'icon-third',
        };
    }
    
    public function isFirstValue(): bool
    {
        return $this === self::FIRST_VALUE;
    }
    
    public function isSecondValue(): bool
    {
        return $this === self::SECOND_VALUE;
    }
    
    public function isThirdValue(): bool
    {
        return $this === self::THIRD_VALUE;
    }
}

// Utilisation
PerfectEnum::values();   // ['first_value', 'second_value', 'third_value']
PerfectEnum::names();    // ['FIRST_VALUE', 'SECOND_VALUE', 'THIRD_VALUE']
PerfectEnum::isValid('first_value');     // true
PerfectEnum::fromValue('first_value');   // PerfectEnum::FIRST_VALUE

$enum = PerfectEnum::FIRST_VALUE;
echo $enum->getLabel(Language::FR);  // 'Première valeur'
echo $enum->getIcon();               // 'icon-first'
if ($enum->isFirstValue()) { ... }
```