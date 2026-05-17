# Principe d'usage des Records (Version finale)

## 1. Définition

Un **Record** est une structure de données typée, utilisée pour la communication **interne** entre les couches de l'application (Services, Repositories, Factories).

```
Record → Remplace les tableaux bruts par des structures typées
```

### 1.1 Pourquoi les Records ?

| Problème | Solution |
|----------|----------|
| `array $credentials` → On ne sait pas ce qu'il contient | `UserCredentialsRecord $record` → Structure claire et typée |
| Pas de typage sur les clés | Propriétés typées |
| Documentation implicite | Documentation explicite dans le constructeur |
| Refactoring dangereux | Le compilateur guide les modifications |

```php
// ❌ MAUVAIS - On ne sait pas ce qu'il y a dans le tableau
function updateField(array $credentials): void { ... }

// ✅ BON - On sait exactement ce qu'on reçoit
function updateField(UserCredentialsRecord $credentials): void { ... }
```

---
## 2. Séparation des responsabilités (⚠️ IMPORTANT)

> **Un Record est STRICTEMENT réservé à l'usage interne. Il ne peut en aucun cas être retourné comme réponse HTTP.**

| Composant | Usage | Réponse API |
|-----------|-------|-------------|
| **Record** | Communication interne (Services, Repositories) | ❌ **INTERDIT** |
| **Data** | Réponse API (Actions) | ✅ **OBLIGATOIRE** |

```php
// ❌ MAUVAIS - Un Record ne répond pas à une API
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $record = $this->userService->getUser($userId);
        return $this->json($record);  // ← INTERDIT !
    }
}

// ✅ BON - L'Action transforme le Record en Data
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $record = $this->userService->getUser($userId);  // ← Record (interne)
        $data = UserData::fromRecord($record);           // ← Data (API)
        return $this->json($data);                       // ✅ Autorisé
    }
}
```

**Règle d'or :** Si tu es dans une Action et que tu veux retourner une réponse HTTP, tu utilises une **Data**, jamais un Record. Le Record s'arrête à la porte de l'Action.

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Description}Record
```

| Record | Utilisation |
|--------|-------------|
| `UserContextRecord` | Contexte utilisateur pour les factories |
| `PaymentResultRecord` | Résultat de traitement de paiement |
| `DashboardFilterRecord` | Filtres pour un tableau de bord |

### 3.2 Localisation

```
app/Records/{Description}Record.php
```

```
app/Records/
├── UserContextRecord.php
├── PaymentResultRecord.php
├── DashboardFilterRecord.php
└── AppointmentFilterRecord.php
```

### 3.3 Héritage

> **Tous les Records doivent étendre `AbstractRecord`**

```php
final class UserContextRecord extends AbstractRecord
{
    // ...
}
```

---

## 4. Structure d'un Record

### 4.1 Types de propriétés autorisés

- ✅ **Types autorisés** : `scalaire` (int, float, string, bool), `Enum`, `Record`, `array<Record>`, `array<scalaire>`, `array<Enum>`
- ❌ **Types interdits** : `array` brut (non typé), `Model`, `Data`, `Collection`, `Carbon`, `DateTime`

```php
final class UserContextRecord extends AbstractRecord
{
    public function __construct(
        public string $userId,                           // ✅ scalaire
        public UserRole $role,                           // ✅ Enum
        public bool $includePermissions,                 // ✅ scalaire
        public DashboardFilterRecord $filters,           // ✅ autre Record
        /** @var array<int, UserRecord> */
        public array $users,                             // ✅ array<Record>
        /** @var array<int, string> */
        public array $tags,                              // ✅ array<scalaire>
        /** @var array<int, UserRole> */
        public array $allowedRoles,                      // ✅ array<Enum>
    ) {}
}
```

**Règle :** Un tableau (`array`) est autorisé UNIQUEMENT s'il est typé avec `array<Record>`, `array<scalaire>` ou `array<Enum>`. Les tableaux bruts non typés (`array $data`) sont interdits.

> **⚠️ Important : un Record ne peut jamais être initialisé avec un tableau. Toutes les propriétés doivent être passées explicitement par nom.**

```php
// ✅ BON - Initialisation explicite
$record = new ListUsersRecord(
    search: $request->input('search'),
    page: $request->integer('page', 1),
);

// ❌ MAUVAIS - Initialisation avec tableau
$record = new ListUsersRecord($request->validated());
```

#### 4.1.1 Record optionnel avec `EmptyRecord`

> **Pour les cas où un Record peut être optionnel (ex: filtres de recherche), utilisez `EmptyRecord` plutôt que `null`.**

```php
use AndyDefer\BestPractices\Records\EmptyRecord;
use AndyDefer\BestPractices\Records\AbstractRecord;

final class FindByRecord extends AbstractRecord
{
    public function __construct(
        public readonly Recordable $filters = new EmptyRecord(),
        public readonly ?int $limit = 100,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'asc',
    ) {}
}
```

**Code du `EmptyRecord` :**

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

/**
 * Empty record for optional filter parameters.
 *
 * This record is used when a Service or Repository needs to accept a Record
 * parameter but no actual data is required. It provides a type-safe way to
 * handle optional filtering without using null or empty arrays.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Records
 */
final class EmptyRecord extends AbstractRecord
{
    public function __construct() {}
}
```

**Pourquoi `EmptyRecord` plutôt que `null` ?**

| Avec `null` | Avec `EmptyRecord` |
|-------------|---------------------|
| `$filters?->toArray() ?? []` | `$filters->toArray()` |
| Condition ternaire partout | Pas de condition |
| Risque d'oubli de `?` | Type-safe garanti |
| `?Recordable` est ambigu | Le Record est toujours présent |

```php
// ✅ Avec EmptyRecord - Pas de condition spéciale
$filtersArray = $record->filters->toArray(); // Retourne [] si EmptyRecord

// ❌ Avec null - Il faut gérer le cas null
$filtersArray = $record->filters?->toArray() ?? [];
```

`EmptyRecord` est une implémentation concrète d'`AbstractRecord` qui ne contient aucune propriété. Elle garantit que l'appel à `toArray()` retourne toujours un tableau vide `[]`.

### 4.2 À ne PAS mettre dans un Record

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| `array` brut (non typé) | On ne sait pas ce qu'il contient | `array<Record>` |
| `Model` (Eloquent) | Contient de la logique et des relations | `UserRecord`, `DoctorRecord` |
| `Data` (DTO API) | Destiné à la couche API uniquement | `UserRecord` |
| `Collection` | Structure non typée | `array<Record>` |
| `Carbon` / `DateTime` | Contient de la logique et des comportements | `string` ISO 8601 |

```php
// ❌ MAUVAIS
final class AppointmentRecord extends AbstractRecord
{
    public function __construct(
        public Appointment $appointment,        // ❌ Model interdit
        public array $items,                   // ❌ array brut non typé
        public Collection $users,              // ❌ Collection interdite
        public Carbon $createdAt,              // ❌ Carbon interdit
    ) {}
}

// ✅ BON
final class AppointmentRecord extends AbstractRecord
{
    public function __construct(
        public string $appointmentId,
        public string $doctorId,
        public string $startDate,
        /** @var array<int, AppointmentItemRecord> */
        public array $items,                   // ✅ array<Record>
        /** @var array<int, UserRecord> */
        public array $users,                   // ✅ array<Record>
        public string $createdAt,              // ✅ string ISO
    ) {}
}
```
### 4.2 À ne PAS mettre dans un Record

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| `array` brut (non typé) | On ne sait pas ce qu'il contient | `array<Record>` |
| `Model` (Eloquent) | Contient de la logique et des relations | `UserRecord`, `DoctorRecord` |
| `Data` (DTO API) | Destiné à la couche API uniquement | `UserRecord` |
| `Collection` | Structure non typée | `array<Record>` |
| `Carbon` / `DateTime` | Contient de la logique et des comportements | `string` ISO 8601 |

```php
// ❌ MAUVAIS
final class AppointmentRecord extends AbstractRecord
{
    public function __construct(
        public Appointment $appointment,        // ❌ Model interdit
        public array $items,                   // ❌ array brut non typé
        public Collection $users,              // ❌ Collection interdite
        public Carbon $createdAt,              // ❌ Carbon interdit
    ) {}
}

// ✅ BON
final class AppointmentRecord extends AbstractRecord
{
    public function __construct(
        public string $appointmentId,
        public string $doctorId,
        public string $startDate,
        /** @var array<int, AppointmentItemRecord> */
        public array $items,                   // ✅ array<Record>
        /** @var array<int, UserRecord> */
        public array $users,                   // ✅ array<Record>
        public string $createdAt,              // ✅ string ISO
    ) {}
}
```

---

## 5. La classe `AbstractRecord`

`AbstractRecord` est une classe abstraite que **tous les Records doivent étendre**. Elle fournit des méthodes utilitaires pour la sérialisation.

### 5.1 Ce que `AbstractRecord` offre (les méthodes héritées)

| Méthode | Description | Usage typique |
|---------|-------------|----------------|
| `toArray(): array` | Convertit automatiquement le Record en tableau normalisé | Insertion base de données |
| `toJson(): string` | Convertit le Record en chaîne JSON | Envoi à API externe |

**La sérialisation est automatique :**
- Toutes les propriétés **publiques** sont automatiquement incluses
- Les clés sont automatiquement converties en `snake_case`
- Aucune méthode `jsonSerialize()` à implémenter

### 5.2 Normalisation automatique effectuée par `toArray()`

| Type d'entrée | Sortie |
|---------------|--------|
| `Record` | `array` (appel récursif de `toArray()`) |
| `Traversable` (Collection, ArrayIterator, etc.) | `array` normalisé |
| `BackedEnum` | Valeur scalaire (`$enum->value`) |
| `PureEnum` | Nom de l'enum (`$enum->name`) |
| `DateTimeInterface` / `Carbon` | String ISO 8601 (`Y-m-d\TH:i:s\Z`) |
| `array` | Normalisation récursive |
| `scalaire` (int, float, string, bool) | Retour brut |

### 5.3 Exemple d'utilisation

```php
$record = new UserRecord(
    id: 1,
    name: 'Andy',
    email: 'andy@example.com',
    createdAt: '2024-01-15T14:30:00Z'
);

// ✅ toArray() pour la base de données
DB::table('users')->insert($record->toArray());
// Résultat : ['id' => 1, 'name' => 'Andy', 'email' => 'andy@example.com', 'created_at' => '2024-01-15T14:30:00Z']

// ✅ toJson() pour une API externe
$response = Http::post('https://api.external.com/users', $record->toJson());
```

### 5.4 Ce que `AbstractRecord` ne fait PAS

| Méthode | Pourquoi ce n'est PAS dans AbstractRecord |
|---------|-------------------------------------------|
| `fromArray()` | La création n'est pas la responsabilité du Record (c'est le rôle du constructeur) |
| `validate()` | Un Record ne doit contenir aucune logique de validation |
| `toData()` | La transformation en Data se fait dans l'Action via `UserData::fromRecord($record)` |
| `save()` | Un Record ne doit jamais interagir avec la base de données |
| `collect()` | La création de collections de Records n'est pas une méthode générique |

### 5.5 Code complet de `AbstractRecord`

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Records;

use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use Traversable;
use UnitEnum;

/**
 * Abstract base class for all Record DTOs.
 *
 * Provides pure data transformation capabilities including array conversion,
 * snake_case key normalization, nested record handling, enum conversion,
 * and date formatting. Records are immutable structures used exclusively for
 * internal communication between Services and Repositories.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Records
 */
abstract class AbstractRecord implements Recordable
{
    /**
     * Converts the Record to an associative array with snake_case keys.
     *
     * Recursively processes all public properties, converting nested Record objects,
     * traversable structures, enums, arrays, and date objects to their array/string
     * representations. All array keys are automatically converted from camelCase
     * to snake_case for database compatibility.
     *
     * @return array<string, mixed> Associative array representation of the Record
     */
    public function toArray(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();

        return $this->normalizeArray($properties);
    }

    /**
     * Converts the Record to an associative array for database operations.
     *
     * Only includes non-null values, making it ideal for update operations
     * where you only want to set provided fields. Keys are converted to snake_case.
     *
     * @return array<string, mixed> Associative array with only non-null values
     */
    public function toDatabase(): array
    {
        $properties = $this->extractPublicPropertiesWithSnakeKeys();
        $normalized = $this->normalizeArray($properties);

        // Remove null values recursively
        return $this->removeNullValues($normalized);
    }

    /**
     * Recursively removes null values from an array.
     *
     * @param array<string, mixed> $array Array to clean
     * @return array<string, mixed> Array without null values
     */
    private function removeNullValues(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->removeNullValues($value);
                if (empty($value)) {
                    continue;
                }
                $result[$key] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Converts the Record to a JSON string.
     *
     * @return string JSON representation of the Record
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Extracts all public properties with keys converted to snake_case.
     *
     * Uses reflection to access all public properties of the record,
     * converts property names from camelCase to snake_case, and preserves
     * the original values for further normalization.
     *
     * @return array<string, mixed> Associative array with snake_case keys
     */
    private function extractPublicPropertiesWithSnakeKeys(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);
            $key = $this->convertCamelToSnake($property->getName());
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Converts a camelCase string to snake_case.
     *
     * @param string $input CamelCase string to convert
     * @return string snake_case representation
     */
    private function convertCamelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Recursively normalizes array values for serialization.
     *
     * @param array<string, mixed> $array Array to normalize
     * @return array<string, mixed> Normalized array
     */
    private function normalizeArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Recursively normalizes a single value for serialization.
     *
     * Handles:
     * - Nested Record objects (converted via their toArray method)
     * - Traversable objects (converted to arrays recursively)
     * - Enums (converted to scalar values or names)
     * - DateTime objects (formatted as ISO 8601 UTC)
     * - Arrays (recursively processed)
     * - Null values (passed through)
     * - Other scalars (passed through unchanged)
     *
     * @param mixed $value The value to normalize
     * @return mixed Normalized value ready for array/JSON output
     */
    private function normalizeValue(mixed $value): mixed
    {
        // Record object → recursively convert to array
        if ($value instanceof self) {
            return $value->toArray();
        }

        // Traversable (Collection, ArrayIterator, etc.) → convert to array recursively
        if ($value instanceof Traversable) {
            return $this->normalizeTraversable($value);
        }

        // Enum → convert to scalar value (backed) or case name (pure)
        if ($value instanceof UnitEnum) {
            return $this->normalizeEnum($value);
        }

        // DateTimeInterface → convert to UTC ISO 8601 string
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        // Array → recursively normalize each element
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        // Null or scalar → return as-is
        return $value;
    }

    /**
     * Converts a Traversable object to a normalized array.
     *
     * Recursively processes each element of the traversable structure,
     * applying the same normalization rules to nested values.
     *
     * @param Traversable $traversable The traversable object to convert
     * @return array<int|string, mixed> Normalized array representation
     */
    private function normalizeTraversable(Traversable $traversable): array
    {
        $result = [];

        foreach ($traversable as $key => $value) {
            $result[$key] = $this->normalizeValue($value);
        }

        return $result;
    }

    /**
     * Converts an Enum to its serializable representation.
     *
     * For backed enums (string/int backed), returns the backing value.
     * For pure enums (non-backed), returns the enum case name.
     *
     * @param UnitEnum $enum The enum instance to convert
     * @return string|int Scalar representation of the enum
     */
    private function normalizeEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }
}
```

### 5.6 Récapitulatif

| Ce que ça offre |
|-----------------|
| `toArray(): array` (sérialisation automatique + normalisation) |
| `toDatabase(): array` (exclut les valeurs null) |
| `toJson(): string` |
| Conversion camelCase → snake_case automatique |
| Normalisation récursive des types complexes |
---

## 6. Appendice : Bonnes pratiques recommandées

> **Bien qu'`AbstractRecord` normalise automatiquement les types, nous recommandons vivement de suivre ces bonnes pratiques.**

### 6.1 Pourquoi suivre les bonnes pratiques ?

Même si la normalisation automatique convertit les types interdits (comme `Carbon` en `string`), suivre les règles rend le code :

- **Plus explicite** : on voit immédiatement le type attendu
- **Plus performant** : on évite la réflexion et les conversions inutiles
- **Plus prévisible** : pas de surprise sur le format des données
- **Plus facile à tester** : les valeurs sont directement dans le bon format

### 6.2 Recommandations

| Au lieu de... | Faire... | Pourquoi ? |
|---------------|----------|-------------|
| `public Carbon $createdAt` | `public string $createdAt` | Le type est explicite, pas de conversion cachée |
| `public DateTime $updatedAt` | `public string $updatedAt` | ISO 8601 est le standard d'échange |
| `public Collection $items` | `public array $items` + PHPDoc | Le type du tableau est explicite |
| `public ?array $metadata` | `public array $metadata = []` | Évite les nulls inutiles |
| `public array $data` (brut) | `/** @var array<int, UserRecord> */ public array $users` | Le contenu est typé |

### 6.3 Exemple : Bonnes pratiques vs. Dépendance à la normalisation

```php
// ⚠️ ACCEPTABLE (normalisation fonctionne, mais moins explicite)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public Carbon $createdAt,      // Sera converti en string
        public Collection $tags,       // Sera converti en array
    ) {}
}

// ✅ RECOMMANDÉ (explicite, pas de conversion cachée)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public int $id,
        public string $name,
        public string $createdAt,      // ✅ Déjà en string ISO
        /** @var array<int, string> */
        public array $tags,            // ✅ Déjà un tableau typé
    ) {}
}
```

### 6.4 Où faire la conversion ?

La conversion des types complexes (Model → Record, Carbon → string, Collection → array) doit être faite **avant** la construction du Record :

```php
// ✅ BON - Conversion avant la création du Record
final class UserService
{
    public function getUser(int $id): UserRecord
    {
        $user = User::find($id);
        
        return new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->created_at->toISOString(),  // Carbon → string
        );
    }
}
```

### 6.5 Récapitulatif des bonnes pratiques

| Pratique | Niveau |
|----------|--------|
| Typer les tableaux avec PHPDoc | ✅ Recommandé |
| Utiliser `string` pour les dates (ISO 8601) | ✅ Recommandé |
| Éviter `Carbon`, `DateTime` dans les Records | ✅ Recommandé |
| Éviter `Collection` dans les Records | ✅ Recommandé |
| Éviter les `?array` (préférer `array = []`) | ✅ Recommandé |
| `readonly` est facultatif (la normalisation fonctionne sans) | ℹ️ Optionnel |

---
## 7. Utilisation de `toArray()`, `toDatabase()` et `toJson()`

| Méthode | Usage | Exemple |
|---------|-------|---------|
| `toArray()` | Insertion/Export (avec valeurs null conservées) | `DB::table('users')->insert($record->toArray())` |
| `toDatabase()` | Update (retire les champs null) | `DB::table('users')->where('id', 1)->update($record->toDatabase())` |
| `toJson()` | Envoi via HTTP Client (API externe) | `Http::post('https://api.external.com', $record->toJson())` |

```php
// ✅ Insertion en base de données (toArray conserve les null)
$record = new UserRecord(...);
DB::table('users')->insert($record->toArray());

// ✅ Update many rows en base de données (toDatabase retire les null)
$record = new UserRecord(name: 'New Name'); // seul name est modifié
DB::table('users')->where('role', 'admin')->update($record->toDatabase());

// ✅ Envoi à une API externe
$record = new PaymentRecord(...);
$response = Http::post('https://api.external.com/payment', $record->toJson());
```

### 7.1 Ce que les Records NE font PAS

```php
// ❌ JAMAIS - Un Record ne répond pas à une API
return response()->json($record);  // ← C'est le travail des Data class !

// ✅ BON - Les Data class sont pour les réponses API
return response()->json($userData);  // UserData, pas UserRecord
```

---

## 8. Exemples complets

### 8.1 Record simple avec scalaires

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class UserCredentialsRecord extends AbstractRecord
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $rememberMe,
    ) {}
}
```

### 8.2 Record avec Enum et liste

```php
<?php

declare(strict_types=1);

namespace App\Records;

use App\Enums\UserRole;

final class UserListFilterRecord extends AbstractRecord
{
    public function __construct(
        public ?UserRole $role,
        public ?bool $isActive,
        public ?string $search,
        /** @var array<int, string> */
        public array $excludedIds,
    ) {}
}
```

### 8.3 Record avec d'autres Records

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class DashboardContextRecord extends AbstractRecord
{
    public function __construct(
        public UserContextRecord $user,
        public DashboardFilterRecord $filters,
        public string $timezone,
    ) {}
}
```

### 8.4 Record avec liste de Records

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class BatchProcessRecord extends AbstractRecord
{
    public function __construct(
        public string $batchId,
        /** @var array<int, UserRecord> */
        public array $users,
        public int $chunkSize,
    ) {}
}
```

### 8.5 Record pour les données brutes (API externe)

```php
<?php

declare(strict_types=1);

namespace App\Records;

final class ExternalApiResponseRecord extends AbstractRecord
{
    public function __construct(
        public string $transactionId,
        public string $status,
        /** @var array<int, ErrorRecord> */
        public array $errors,
        public ?string $rawPayload,
    ) {}
}
```

---

## 9. Flux d'utilisation

```
┌─────────────────────────────────────────────────────────────────┐
│                         CONTROLLER                              │
│                   (orchestration des appels)                    │
│                                                                 │
│   Reçoit une Request → construit des Records → appelle Service  │
│   Reçoit un Record du Service → transforme en Data → répond     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          SERVICE                                │
│                    (logique métier pure)                        │
│                                                                 │
│   Reçoit des RECORDS et retourne des RECORDS                    │
│   ❌ Ne connaît pas les Data                                    │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       REPOSITORY                                │
│                    (accès aux données)                          │
│                                                                 │
│   Reçoit des RECORDS et retourne des RECORDS ou des MODELS      │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          FACTORY                                │
│         (transforme Record → Data pour la couche API)           │
│                                                                 │
│   Reçoit des RECORDS et retourne des DATA                       │
│   ❌ Appelée UNIQUEMENT dans les Controllers                    │
└─────────────────────────────────────────────────────────────────┘
```

**⚠️ Rappel :** Les Records ne sont **JAMAIS** utilisés pour les réponses API. C'est le rôle des **Data class**. La transformation Record → Data se fait directement dans l'Action via `UserData::fromRecord($record)` et n'est appelée que dans l'Action.

---

## 10. Utilisation concrète

### 10.1 Service qui reçoit un Record

```php
final class UserService
{
    public function updateUserField(UserCredentialsRecord $credentials): UserUpdateResultRecord
    {
        // On sait exactement ce qu'on reçoit
        $user = User::where('email', $credentials->email)->first();
        
        // Traitement...
        
        return new UserUpdateResultRecord(
            success: true,
            userId: $user->id,
        );
    }
}
```
### 10.2 Transformation d'un Record en Data

```php
// ✅ BON - Transformation directe dans l'Action
final class ShowUserProfileAction extends AbstractAction
{
    public function run(int $userId, ShowUserProfileRequest $request): JsonResponse
    {
        $record = $this->userService->getUserWithContext(
            userId: $userId,
            currentUserId: auth()->id(),
            timezone: $request->input('timezone', 'UTC'),
        );
        
        if ($record === null) {
            return $this->json(null, 404);
        }
        
        // Transformation directe Record → Data
        $userData = UserProfileData::fromRecord($record);
        
        return $this->json($userData);
    }
}

// La Data avec sa propre méthode fromRecord()
final class UserProfileData extends AbstractData
{
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $timezone = null,
        public readonly ?bool $canEdit = null,
    ) {}
    
    public static function fromRecord(UserWithContextRecord $record): self
    {
        return new self(
            id: (string) $record->user->id,
            name: $record->user->name,
            timezone: $record->context->timezone,
            canEdit: $record->canEdit,
        );
    }
}
```

### 10.3 Contrôleur qui orchestre tout

```php
final class UserController extends Controller
{
    public function update(UpdateUserRequest $request): JsonResponse
    {
        // 1. Construire un Record à partir de la Request
        $credentials = new UserCredentialsRecord(
            email: $request->input('email'),
            password: $request->input('password'),
            rememberMe: $request->input('remember_me', false),
        );
        
        // 2. Appeler le Service avec le Record (interne)
        $result = $this->userService->updateUserField($credentials);
        
        // 3. Transformer le Record en Data via la méthode statique de la Data
        $responseData = UserUpdateData::fromRecord($result);
        
        // 4. Réponse avec une Data (jamais un Record)
        return response()->json($responseData, JsonResponse::HTTP_OK);
    }
}
```

### 10.4 Insertion en base de données

```php
final class UserRepository
{
    public function create(UserRecord $record): User
    {
        // ✅ Utilisation de toArray() pour l'insertion
        $id = DB::table('users')->insertGetId($record->toArray());
        
        return User::find($id);
    }
}
```

### 10.5 Appel à une API externe

```php
final class PaymentGatewayService
{
    public function createPayment(PaymentRequestRecord $request): PaymentResponseRecord
    {
        // ✅ Utilisation de toJson() pour l'envoi HTTP
        $response = Http::post(
            'https://api.payment.com/v1/payments',
            $request->toJson()
        );
        
        return new PaymentResponseRecord(
            transactionId: $response->json('transaction_id'),
            status: $response->json('status'),
        );
    }
}
```

---

## 11. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Description}Record` |
| **Héritage** | Étend `AbstractRecord` |
| **Propriétés** | `public` (l'important est d'être public, `readonly` est optionnel) |
| **Types autorisés** | `scalaire`, `Enum`, `Record`, `array<Record>`, `array<scalaire>`, `array<Enum>` |
| **Types interdits** | `array` brut non typé, `Model`, `Data`, `Collection`, `Carbon`, `DateTime` |
| **Sérialisation** | Automatique via `toArray()` et `toJson()` |
| **Convention** | Les clés sont automatiquement converties en `snake_case` |
| **Logique** | ❌ AUCUNE méthode métier |
| **Utilisation** | Communication interne UNIQUEMENT (pas de réponse API) |

---
## 12. Ce que les Records NE peuvent PAS faire

```php
// ❌ JAMAIS - Pas de logique métier
public function isValid(): bool { ... }

// ❌ JAMAIS - Pas de méthodes de validation
public function isActive(): bool { ... }

// ❌ JAMAIS - Pas de calculs
public function getTotal(): float { ... }

// ❌ JAMAIS - Pas de réponse API
return response()->json($record);

// ❌ JAMAIS - Pas de transformation en Data (c'est le rôle de la Data dans l'Action)
public function toData(): UserData { ... }
```
---

## 13. Checklist d'acceptance

- [ ] La classe étend `AbstractRecord`
- [ ] Le nom se termine par `Record`
- [ ] Les propriétés sont `public` (accessibles pour la sérialisation automatique)
- [ ] Les propriétés sont en `camelCase` (seront converties en `snake_case`)
- [ ] Pas de propriété de type `Model`, `Data`, `Collection`, `Carbon` ou `DateTime`
- [ ] Si `array`, alors typé avec PHPDoc (`array<Record>`, `array<scalaire>` ou `array<Enum>`)
- [ ] **AUCUNE méthode métier** (ni `isValid()`, ni `isActive()`, etc.)
- [ ] **JAMAIS utilisé pour les réponses API** (c'est le rôle des Data)
- [ ] Utilisé uniquement pour la communication interne

---

## 14. Anti-patterns à éviter

```php
// ❌ N'étend pas AbstractRecord
final class UserRecord { ... }

// ❌ Contient un array brut non typé
public array $items;  // interdit ! Utiliser /** @var array<int, ItemRecord> */ public array $items

// ❌ Contient un Model
public User $user;  // interdit !

// ❌ Contient un Data
public UserData $userData;  // interdit !

// ❌ Contient une Collection
public Collection $users;  // interdit ! Utiliser /** @var array<int, UserRecord> */ public array $users

// ❌ Contient Carbon
public Carbon $createdAt;  // interdit ! Utiliser string $createdAt

// ❌ Méthode métier
public function isActive(): bool { ... }  // pas de logique dans les records

// ❌ Utilisation en réponse API (INTERDICTION ABSOLUE)
return response()->json($userRecord);  // utiliser UserData à la place

// ❌ Logique de validation
public function isValid(): bool { ... }  // à mettre dans un Validator ou Service

// ❌ Transformation en Data dans le Record
public function toData(): UserData { ... }  // c'est le rôle de la Data dans l'Action
```

---

## 15. Rappel fondamental

> **Un Record est un sac de données typé, sans aucune logique. Il remplace les tableaux bruts pour rendre le code plus sûr et plus lisible. La sérialisation est entièrement automatique.**

### 15.1 Séparation des responsabilités

| Composant | Rôle | Logique | Réponse API | Sérialisation |
|-----------|------|---------|-------------|---------------|
| **Record** | Communication interne (Services, Repositories) | ❌ Aucune | ❌ **INTERDIT** | Automatique |
| **Data** | Réponse API (Actions) | ❌ Aucune | ✅ **OBLIGATOIRE** | Via `toArray()` |
| **Service** | Logique métier | ✅ Oui | ❌ Non | N/A |

### 15.2 Schéma récapitulatif

```
Record → Communication interne (Services, Repositories)
Data   → Réponse API (Actions)
Service → Logique métier (ne connaît ni Record ni Data)
```

> **Règle :** La transformation Record → Data se fait directement dans l'Action via `UserData::fromRecord($record)`.