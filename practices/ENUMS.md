# Principe d'usage des Enums (Version finale)

## 1. Définition

Un **Enum** est une structure de données qui définit un ensemble fixe de valeurs possibles. Il remplace les constantes de classe et les tableaux de valeurs.

```
Enum → Ensemble fixe de valeurs → Backed (string|int) → Nom en PascalCase
```

```php
enum UserRole: string
{
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

## 3. Composition avec EnumService

> **⚠️ Pour les opérations sur les enums (récupération des valeurs, validation, hydratation), nous utilisons un service dédié plutôt que des méthodes statiques dans l'Enum. Ce choix est guidé par le principe de composition.**

### 3.1 Pourquoi un service plutôt que des méthodes statiques ?

| Problème des méthodes statiques dans l'Enum | Solution avec service |
|---------------------------------------------|----------------------|
| **Couplage** | L'Enum mélange ses valeurs et les opérations associées |
| **Violation du SRP** | L'Enum a plusieurs raisons de changer (valeurs + opérations) |
| **Testabilité** | Les méthodes statiques sont difficiles à mocker |
| **Réutilisabilité** | Chaque Enum devrait réimplémenter les mêmes méthodes |
| **Composition** | La composition favorise l'injection et le découplage |

### 3.2 Le service : EnumService

```php
<?php

declare(strict_types=1);

namespace AndyDefer\DomainStructures\Services;

/**
 * Service for handling enum operations.
 * 
 * This service provides utility methods for working with enums,
 * both backed and pure enums. It follows the composition principle
 * by encapsulating enum operations in a dedicated service.
 */
class EnumService
{
    public function __construct(
        private readonly \UnitEnum $enum
    ) {}

    /**
     * Returns all scalar values from the enum.
     *
     * @return array<int, string|int>
     */
    public function values(): array
    {
        if ($this->isBackedEnum()) {
            return array_column($this->enum::cases(), 'value');
        }

        return array_column($this->enum::cases(), 'name');
    }

    /**
     * Returns all case names from the enum.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_column($this->enum::cases(), 'name');
    }

    /**
     * Returns all enum cases in their defined order.
     *
     * @return array<int, \UnitEnum>
     */
    public function typesInOrder(): array
    {
        return $this->enum::cases();
    }

    /**
     * Checks if a given value exists in the enum.
     *
     * @param  string|int  $value
     * @return bool
     */
    public function isValid(string|int $value): bool
    {
        if ($this->isBackedEnum()) {
            return in_array($value, $this->values(), true);
        }

        return in_array($value, $this->names(), true);
    }

    /**
     * Retrieves the enum case corresponding to a value.
     *
     * @param  string|int  $value
     * @return \UnitEnum|null
     */
    public function fromValue(string|int $value): ?\UnitEnum
    {
        if ($this->isBackedEnum()) {
            if (is_string($value) && $value === '') {
                return null;
            }

            /** @var \BackedEnum $enumClass */
            $enumClass = $this->enum::class;

            if (is_string($value) && is_numeric($value)) {
                $value = (int) $value;
            }

            return $enumClass::tryFrom($value);
        }

        $value = (string) $value;
        foreach ($this->enum::cases() as $case) {
            if ($case->name === $value) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Creates an enum instance from any source for hydration.
     *
     * @param  mixed  $source
     * @return \UnitEnum
     * @throws \InvalidArgumentException
     */
    public function from(mixed $source): \UnitEnum
    {
        $enumClass = $this->enum::class;

        if ($source instanceof $enumClass) {
            return $source;
        }

        if ($this->isBackedEnum()) {
            if (is_string($source) && $source === '') {
                throw new \InvalidArgumentException(sprintf(
                    'Empty string is not a valid backing value for enum %s',
                    $enumClass
                ));
            }

            if (is_string($source) || is_int($source)) {
                if (is_string($source) && is_numeric($source)) {
                    $source = (int) $source;
                }
                
                /** @var \BackedEnum $enumClass */
                $enum = $enumClass::tryFrom($source);
                if ($enum !== null) {
                    return $enum;
                }
            }

            if (is_object($source) && property_exists($source, 'value')) {
                return $this->from($source->value);
            }

            if (is_array($source) && isset($source['value'])) {
                return $this->from($source['value']);
            }

            throw new \InvalidArgumentException(sprintf(
                'Cannot convert value to enum %s: expected string|int, got %s',
                $enumClass,
                is_object($source) ? $source::class : gettype($source)
            ));
        }

        if (is_string($source)) {
            foreach ($enumClass::cases() as $case) {
                if ($case->name === $source) {
                    return $case;
                }
            }
        }

        if (is_object($source) && property_exists($source, 'name')) {
            return $this->from($source->name);
        }

        throw new \InvalidArgumentException(sprintf(
            'Cannot convert value to pure enum %s: expected string, got %s',
            $enumClass,
            is_object($source) ? $source::class : gettype($source)
        ));
    }

    /**
     * Checks if the enum is a backed enum (has scalar values).
     *
     * @return bool
     */
    private function isBackedEnum(): bool
    {
        return is_subclass_of($this->enum, \BackedEnum::class);
    }

    /**
     * Gets the original enum instance.
     *
     * @return \UnitEnum
     */
    public function getEnum(): \UnitEnum
    {
        return $this->enum;
    }
}
```

### 3.3 Avantages de la composition

| Avantage | Explication |
|----------|-------------|
| **Séparation des responsabilités** | L'Enum gère ses valeurs, le service gère les opérations |
| **Testabilité** | `EnumService` peut être mocké facilement |
| **Réutilisabilité** | Un seul service pour tous les enums |
| **Injection** | Le service peut être injecté où nécessaire |
| **Découplage** | L'Enum n'a pas de dépendances vers des méthodes statiques |

### 3.4 Utilisation du EnumService

```php
// Création d'un service dédié pour un enum spécifique
final class UserRoleService
{
    private EnumService $enumService;
    
    public function __construct()
    {
        $this->enumService = new EnumService(UserRole::ADMIN);
    }
    
    public function getValues(): array
    {
        return $this->enumService->values();
    }
    
    public function getNames(): array
    {
        return $this->enumService->names();
    }
    
    public function isValid(string $role): bool
    {
        return $this->enumService->isValid($role);
    }
    
    public function fromValue(string $role): ?UserRole
    {
        return $this->enumService->fromValue($role);
    }
}

// Ou injection directe dans un controller
final class RoleController
{
    public function __construct(
        private readonly EnumService $roleService,
    ) {
        $this->roleService = new EnumService(UserRole::ADMIN);
    }
    
    public function getRoles(): JsonResponse
    {
        return ResponseFactory::json([
            'values' => $this->roleService->values(),
            'names' => $this->roleService->names(),
        ]);
    }
}
```

---

## 4. Méthodes autorisées dans un Enum

### 4.1 Méthodes de formatage avec préfixe `get`

> **Les méthodes de formatage comme `getLabel()`, `getIcon()`, `getColor()` sont autorisées. Elles doivent commencer par le préfixe `get`.**

```php
// ✅ BON - Méthodes de formatage avec préfixe 'get'
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::ACTIVE => $language === Language::EN ? 'Active' : 'Actif',
            self::INACTIVE => $language === Language::EN ? 'Inactive' : 'Inactif',
        };
    }
    
    public function getColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'green',
            self::INACTIVE => 'gray',
        };
    }
    
    public function getIcon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'minus-circle',
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
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
    
    public function isInactive(): bool
    {
        return $this === self::INACTIVE;
    }
}

// ❌ MAUVAIS - Méthodes utilitaires mal nommées
public function canLogin(): bool { ... }   // ❌ Pas is{Case}
public function isBlocked(): bool { ... }  // ❌ Pas is{Case}

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

// ❌ MAUVAIS - Paramètres interdits
public function getLabel(Record $record): string { ... }  // ❌ Record
public function getLabel(Data $data): string { ... }      // ❌ Data
public function getLabel(User $user): string { ... }      // ❌ Model
```

### 4.4 Règle du `match` exhaustif

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
| **Avoir des méthodes statiques utilitaires** | Principe de composition | Utiliser `EnumService` |
| **Avoir des clés en PascalCase** | Violation de la convention | Utiliser `SCREAMING_SNAKE_CASE` |
| **Avoir des valeurs en camelCase** | Violation de la convention | Utiliser `snake_case` |
| **Utiliser `default` dans `match`** | Cache des cas manquants | Gérer tous les cas explicitement |
| **Méthodes de formatage sans `get`** | Convention non respectée | Utiliser `getLabel()`, `getIcon()` |
| **Méthodes utilitaires avec paramètres** | Violation de la règle | Ne pas utiliser de paramètres |
| **Prendre des `Record` / `Data` / `Model`** | Violation de la couche | Utiliser des scalaires ou Enums |
| **Contenir de la logique métier complexe** | Violation SRP | Déplacer dans un Service |
| **Accéder à la base de données** | Violation de responsabilité | Déplacer dans un Repository |

---

## 6. Exemples complets

### 6.1 Enum lié à un Model

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING_APPROVAL = 'pending_approval';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    
    public function getLabel(Language $language = Language::FR): string
    {
        return match ($this) {
            self::ACTIVE => $language === Language::EN ? 'Active' : 'Actif',
            self::INACTIVE => $language === Language::EN ? 'Inactive' : 'Inactif',
            self::PENDING_APPROVAL => $language === Language::EN ? 'Pending Approval' : 'En attente d\'approbation',
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
            self::SUSPENDED => 'orange',
            self::BANNED => 'red',
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
    
    public function isSuspended(): bool
    {
        return $this === self::SUSPENDED;
    }
    
    public function isBanned(): bool
    {
        return $this === self::BANNED;
    }
}

// Service associé pour les opérations sur l'enum (composition)
final class UserStatusService
{
    private EnumService $enumService;
    
    public function __construct()
    {
        $this->enumService = new EnumService(UserStatus::ACTIVE);
    }
    
    public function getValues(): array
    {
        return $this->enumService->values();
    }
    
    public function getNames(): array
    {
        return $this->enumService->names();
    }
    
    public function isValid(string $status): bool
    {
        return $this->enumService->isValid($status);
    }
    
    public function fromValue(string $status): ?UserStatus
    {
        return $this->enumService->fromValue($status);
    }
}
```

### 6.2 Intégration dans un Model

```php
// App\Models\User.php
final class User extends Model
{
    protected $casts = [
        'role' => UserRole::class,
        'status' => UserStatus::class,
    ];
}

// Utilisation (via Service)
final class UserService
{
    public function __construct(
        private readonly UserStatusService $statusService,
    ) {}
    
    public function getUserStatuses(): array
    {
        return $this->statusService->getValues();
    }
    
    public function isValidStatus(string $status): bool
    {
        return $this->statusService->isValid($status);
    }
}

// Utilisation (via Repository)
$user = $this->userRepository->find(1);

// Méthodes de formatage
echo $user->status->getLabel(Language::FR);  // 'Actif'
echo $user->status->getColor();              // 'green'

// Méthodes utilitaires
if ($user->role->isAdmin()) { ... }
if ($user->status->isActive()) { ... }
if ($user->status->isBanned()) { ... }
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
| **Méthodes statiques utilitaires** | ❌ **INTERDITES** (principe de composition) |
| **Opérations sur enums** | ✅ Déléguées à `EnumService` |
| **Méthodes de formatage** | Avec préfixe `get` (ex: `getLabel()`, `getIcon()`) |
| **Méthodes utilitaires** | `is{Case}` (ex: `isActive()`, `isInactive()`) |
| **Retour méthodes utilitaires** | Toujours `bool` |
| **Paramètres méthodes utilitaires** | Aucun paramètre |
| **Paramètres autorisés** | Scalaire ou Enum uniquement |
| **`match`** | Exhaustif (tous les cas, pas de `default`) |
| **`Record` / `Data` / `Model`** | ❌ Interdit en paramètre |
| **Logique métier complexe** | ❌ Interdit (dans Service) |
| **Accès DB** | ❌ Interdit |

---

## 8. Règle d'or

> **Un Enum est un backed enum avec un nom en PascalCase ({Model}{Field} s'il est lié à un Model), des clés en SCREAMING_SNAKE_CASE, des valeurs en snake_case.**
>
> **⚠️ Par principe de composition, les opérations sur les enums (valeurs, noms, validation, hydratation) sont déléguées au service `EnumService`. L'Enum ne contient que ses valeurs et les méthodes qui lui sont propres (formatage, utilitaires métier).**
>
> **Les méthodes de formatage commencent par `get`. Les méthodes utilitaires commencent par `is`, retournent `bool` et n'ont pas de paramètres. Tous les `match` sont exhaustifs.**

```php
// L'Enum parfait
enum PerfectEnum: string
{
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

// Service associé pour les opérations (composition)
final class PerfectEnumService
{
    private EnumService $enumService;
    
    public function __construct()
    {
        $this->enumService = new EnumService(PerfectEnum::FIRST_VALUE);
    }
    
    public function getValues(): array
    {
        return $this->enumService->values();
    }
    
    public function getNames(): array
    {
        return $this->enumService->names();
    }
    
    public function isValid(string $value): bool
    {
        return $this->enumService->isValid($value);
    }
    
    public function fromValue(string $value): ?PerfectEnum
    {
        return $this->enumService->fromValue($value);
    }
}

// Utilisation
$service = new PerfectEnumService();
$service->getValues();   // ['first_value', 'second_value', 'third_value']
$service->getNames();    // ['FIRST_VALUE', 'SECOND_VALUE', 'THIRD_VALUE']
$service->isValid('first_value');     // true
$service->fromValue('first_value');   // PerfectEnum::FIRST_VALUE

$enum = PerfectEnum::FIRST_VALUE;
echo $enum->getLabel(Language::FR);  // 'Première valeur'
echo $enum->getIcon();               // 'icon-first'
if ($enum->isFirstValue()) { ... }
```