# Principe d'usage des Data DTO (Version finale)

## 1. Définition
Une **Data DTO** est une classe **pure** et **immutable** qui représente une structure de données **uniquement pour les réponses HTTP**.

> ⚠️ **Les Data sont exclusivement pour les réponses API. Pour la communication interne (Services, Repositories, méthodes), utilisez les Records.**

> 📌 **Une Data DTO doit étendre `AbstractData`** pour bénéficier des méthodes `toArray()` et `collect()`.

---

## 2. Règles strictes

### 2.1. Héritage obligatoire

**Toute Data DTO DOIT étendre `AbstractData`.**

```php
// ✅ Bon - Data DTO avec héritage correct
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}

// ❌ Mauvais - N'étend pas AbstractData
final class UserData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
```

### 2.2. Pureté et immutabilité 🧊

**Une Data DTO doit être PURE :**
- ✅ **Immutable** - Les propriétés ne peuvent pas être modifiées après instanciation
- ✅ **Sans comportement** - Pas de méthodes métier, pas de logique
- ✅ **Sans effets de bord** - La conversion en array ne modifie pas l'état
- ✅ **Transparente** - Elle ne fait que transporter des données

```php
// ✅ Bon - Data DTO pure et immutable
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
    // Pas d'autres méthodes !
}

// ❌ Mauvais - Data DTO impure avec comportement
final class UserData extends AbstractData
{
    public function __construct(
        public string $id,        // ❌ Pas de readonly = mutable
        public string $name,
        public string $email,
    ) {}
    
    public function isAdmin(): bool  // ❌ Comportement interdit
    {
        return $this->role === 'admin';
    }
}
```

### 2.3. Nommage des propriétés
- Les propriétés doivent être en **camelCase**
- ⚠️ Pas de `snake_case` dans les DTOs
- ⚠️ Toutes les propriétés doivent être `public readonly`

```php
// ✅ Bon
public readonly string $doctorId;
public readonly ?AddressData $primaryAddress;

// ❌ Mauvais
public string $doctor_id;           // ❌ snake_case + pas readonly
protected string $doctorId;         // ❌ protected
private string $doctorId;           // ❌ private
public string $doctorId;            // ❌ pas readonly
```

### 2.4. Types de propriétés autorisés

| Type | Exemple |
|------|---------|
| `int` | `public readonly int $count;` |
| `float` | `public readonly float $amount;` |
| `bool` | `public readonly bool $isActive;` |
| `string` | `public readonly string $name;` |
| `array` | `public readonly array $items;` |
| `?type` (nullable) | `public readonly ?string $message;` |
| `array<Data>` | `public readonly array $ratings;` |
| `?array<Data>` | `public readonly ?array $metadata = [];` |
| Autre Data | `public readonly AddressData $address;` |
| `Enum` | `public readonly UserRole $role;` |
| `?Enum` (nullable) | `public readonly ?UserRole $role = null;` |
| `array<Enum>` | `public readonly array $roles;` |
| `?array<Enum>` (nullable) | `public readonly ?array $roles = [];` |

```php
// ✅ Bon
public readonly string $doctorId;
public readonly int $appointmentsCount;
public readonly array $recentRatings; // array de RatingData

// ❌ Mauvais
public readonly Carbon $createdAt;     // ❌ Carbon interdit
public readonly User $user;            // ❌ Model interdit
public readonly Collection $items;     // ❌ Collection interdite
```

### 2.5. Règle des valeurs par défaut pour les nullables (Éviter les undefined côté Frontend)

> **Pour éviter les `undefined` côté Frontend, toute propriété nullable doit avoir une valeur par défaut.**

```php
// ✅ BON - Valeur par défaut pour les nullables
public function __construct(
    public readonly ?string $message = null,
    public readonly ?array $metadata = [],        // ← [] au lieu de null
    public readonly ?array $errors = [],          // ← [] au lieu de null
    public readonly ?UserRole $role = null,
    public readonly ?array $roles = [],
) {}

// ❌ MAUVAIS - Pas de valeur par défaut
public function __construct(
    public readonly ?string $message,     // ❌ Peut être undefined côté front
    public readonly ?array $metadata,     // ❌ Peut être undefined côté front
) {}
```

**Règles :**
| Type nullable | Valeur par défaut |
|---------------|-------------------|
| `?string` | `= null` |
| `?int` | `= null` |
| `?bool` | `= null` |
| `?array` | `= []` (⚠️ PAS `= null`) |
| `?array<Data>` | `= []` (⚠️ PAS `= null`) |
| `?array<Enum>` | `= []` (⚠️ PAS `= null`) |
| `?Enum` | `= null` |

### 2.6. Règle pour les dates ⏱️

**Toutes les dates doivent être représentées par des chaînes de caractères au format ISO 8601.**

| Format | Exemple |
|--------|---------|
| Date seule | `2024-01-15` |
| Date + heure | `2024-01-15T14:30:00Z` |
| Date + heure + timezone | `2024-01-15T14:30:00+01:00` |

```php
// ✅ Bon - String ISO 8601
public readonly string $createdAt;      // "2024-01-15T14:30:00Z"
public readonly string $scheduleDate;   // "2024-01-15"
public readonly ?string $emailVerifiedAt = null;

// ❌ Mauvais - Types interdits pour les dates
public readonly Carbon $createdAt;       // ❌ Carbon interdit
public readonly DateTime $updatedAt;     // ❌ DateTime interdit
```

**Conversion recommandée dans la Factory :**
```php
public function fromRecord(UserRecord $record): UserData
{
    return new UserData(
        createdAt: $record->createdAt, // Déjà une string ISO dans le Record
    );
}
```

### 2.7. Usage exclusif : API seulement 🚫

> **Une Data ne peut être utilisée que pour une réponse API dans un Controller. Elle ne peut pas être passée en paramètre d'une méthode interne.**

```php
// ✅ BON - Data utilisée pour une réponse API
final class UserController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $userData = $this->userDataFactory->fromModel($user);
        return response()->json($userData);  // ✅ Uniquement pour la réponse
    }
}

// ❌ MAUVAIS - Data utilisée en paramètre interne
final class UserService
{
    public function updateUser(UserData $userData): void  // ❌ INTERDIT
    {
        // Les Data ne sont pas pour l'interne !
    }
}

// ✅ BON - Utiliser un Record pour l'interne
final class UserService
{
    public function updateUser(UserRecord $userRecord): void  // ✅ OK
    {
        // Les Records sont pour la communication interne
    }
}
```

**Récapitulatif :**
| Usage | Type à utiliser |
|-------|-----------------|
| Réponse API | `Data` |
| Paramètre d'une méthode interne | `Record` |
| Retour d'une méthode interne | `Record` |
| Communication entre Services | `Record` |
| Communication entre Service et Repository | `Record` |

### 2.8. Condition d'existence
Une Data **DOIT** répondre à au moins une de ces conditions :

1. **Être liée à un Model** (`App\Models\User`, `App\Models\Doctor`, etc.)
2. **Ou être retournée dans une réponse HTTP**
3. **Ou être utilisée dans une autre Data** (comme propriété d'une autre Data DTO)

```php
// ✅ BON - Data liée à un Model
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}

// ✅ BON - Data retournée en réponse HTTP
final class PingData extends AbstractData
{
    public function __construct(
        public readonly string $status = 'ok',
    ) {}
}

// ✅ BON - Data utilisée dans une autre Data
final class AddressData extends AbstractData
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
    ) {}
}

final class UserProfileData extends AbstractData
{
    public function __construct(
        public readonly string $name,
        public readonly AddressData $address,  // ← AddressData utilisée ici
    ) {}
}

// ❌ MAUVAIS - Data qui ne sert à rien (aucune des 3 conditions)
// → Utiliser un Record à la place
final class InternalCalculationData extends AbstractData
{
    public function __construct(
        public readonly int $value,
    ) {}
}
```

### 2.9. Interdictions strictes

| Interdit | Pourquoi | Alternative |
|----------|----------|-------------|
| **Méthodes métier** | Violation de la pureté | Déplacer dans un Service |
| **Méthodes statiques** (`fromArray`, `fromModel`) | Violation du SRP | Utiliser une Factory |
| **Propriétés non-readonly** | Rupture de l'immutabilité | `public readonly` |
| `Carbon` / `DateTime` | Trop lourds, impurs | `string` ISO 8601 |
| `Model` (Eloquent) | Couplage fort, impur | Utiliser l'ID + Factory |
| `Collection` | Logique Laravel, impure | Utiliser `array` |
| **Utilisation en paramètre interne** | Violation du principe d'usage | Utiliser `Record` |
| **Logique de transformation** | Violation de la pureté | Dans la Factory |
| **Effets de bord** | Violation de la pureté | Interdit |

---

## 3. Méthodes héritées de AbstractData

Les Data DTO héritent de deux méthodes utiles :

| Méthode | Description | Retour |
|---------|-------------|--------|
| `toArray()` | Convertit la Data et toutes ses propriétés (Data, Enums, dates) en tableau | `array<string, mixed>` |
| `collect(iterable $items)` | Crée un tableau de Data DTOs à partir d'un itérable d'objets ou de tableaux | `array<int, static>` |

```php
// Exemple d'utilisation de toArray()
$userData = new UserData(id: '1', name: 'Andy', email: 'andy@example.com');
$array = $userData->toArray();
// Résultat : ['id' => '1', 'name' => 'Andy', 'email' => 'andy@example.com']

// Exemple d'utilisation de collect()
$users = User::all();
$usersData = UserData::collect($users);  // array<int, UserData>
```

---

## 4. Qui crée les Data DTO ?

**Les Factories sont responsables de la création des DTOs, pas les Data elles-mêmes.**

```php
// ✅ Bon - Factory dédiée
final class UserDataFactory
{
    public function fromRecord(UserRecord $record): UserData
    {
        return new UserData(
            id: (string) $record->id,
            name: $record->name,
            email: $record->email,
            createdAt: $record->createdAt, // déjà une string ISO
        );
    }
}

// ❌ Mauvais - Logique dans la Data
final class UserData extends AbstractData
{
    public static function fromModel(User $user): self  // ❌ INTERDIT
    {
        return new self(...);
    }
}
```

### 4.1. Exception pour les DTO simples

> **Pour les DTO très simples (moins de 3 propriétés, pas de logique de transformation), on peut utiliser le constructeur directement sans Factory.**

```php
// ✅ BON - DTO simple, constructeur direct
final class PingData extends AbstractData
{
    public function __construct(
        public readonly string $status = 'ok',
        public readonly string $timestamp = '2024-01-15T10:00:00Z',
    ) {}
}

// Dans le controller
return response()->json(new PingData());

// ✅ BON - DTO simple avec peu de propriétés
final class DeleteResponseData extends AbstractData
{
    public function __construct(
        public readonly bool $success = true,
        public readonly string $message = 'Resource deleted successfully',
    ) {}
}

// ❌ MAUVAIS - DTO complexe, utiliser une Factory
final class UserProfileData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $permissions,
        public readonly ?array $metadata,
        public readonly string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
// → Utiliser UserProfileDataFactory à la place
```

**Règle :** Si le DTO a plus de 3 propriétés ou nécessite une transformation (Model → Data, Record → Data), on utilise une **Factory**. Sinon, le constructeur direct est acceptable.

---

## 5. Hiérarchie des Dossiers

```
App/
├── Data/           # DTOs PURES pour les réponses API (étendent AbstractData)
│   ├── AbstractData.php
│   ├── UserData.php
│   └── ...
├── Records/        # Structures internes pour la communication
│   ├── AbstractRecord.php
│   ├── UserRecord.php
│   └── ...
├── Factories/      # Transforment Record → Data
│   ├── UserDataFactory.php
│   └── ...
```

---

## 6. Exemple complet

```php
// 1. Data DTO pure (App\Data\UserData.php)
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $createdAt,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?array $metadata = [],      // ✅ Valeur par défaut
        public readonly ?UserRole $role = null,
    ) {}
    // PAS DE MÉTHODES SUPPLÉMENTAIRES !
}

// 2. Record pour l'interne (App\Records\UserRecord.php)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $createdAt,  // ✅ string ISO, pas Carbon
    ) {}
}

// 3. Factory (App\Factories\UserDataFactory.php)
final class UserDataFactory
{
    public function fromRecord(UserRecord $record): UserData
    {
        return new UserData(
            id: (string) $record->id,
            name: $record->name,
            email: $record->email,
            createdAt: $record->createdAt, // déjà une string ISO
        );
    }
}

// 4. Controller (utilisation UNIQUEMENT pour la réponse)
final class UserController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $userRecord = $this->userService->getUser($id);  // ← Record
        $userData = $this->userDataFactory->fromRecord($userRecord);  // ← Data
        return response()->json($userData->toArray());  // ✅ Réponse API
    }
}
```

---

## 7. Récapitulatif visuel

```
┌─────────────────────────────────────────────────────────────────┐
│                     RÈGLES DES DATA DTO                         │
├─────────────────────────────────────────────────────────────────┤
│  📌 HÉRITAGE                                                    │
│    ✅ DOIT étendre AbstractData                                 │
│    ✅ Hérite de toArray() et collect()                          │
├─────────────────────────────────────────────────────────────────┤
│  🎯 USAGE EXCLUSIF                                              │
│    ✅ Réponses API uniquement                                   │
│    ❌ Paramètre de méthode interne                              │
│    ❌ Communication entre Services                              │
├─────────────────────────────────────────────────────────────────┤
│  🧊 PURETÉ & IMMUTABILITÉ                                       │
│    ✅ public readonly properties                                │
│    ✅ Pas de méthodes (sauf constructeur)                       │
│    ✅ Pas d'effets de bord                                      │
│    ✅ Pas de logique métier                                     │
├─────────────────────────────────────────────────────────────────┤
│  📝 FORMAT                                                      │
│    ✅ camelCase pour les propriétés                             │
│    ✅ Types : int, float, bool, string, array, Data, Enum       │
│    ✅ ? pour nullable                                           │
│    ✅ DATES : string ISO 8601                                   │
│    ✅ nullables : toujours une valeur par défaut                │
│    ✅ ?array = [] (pas null)                                    │
├─────────────────────────────────────────────────────────────────┤
│  📦 CONDITIONS D'EXISTENCE                                      │
│    ✅ Liée à un Model                                           │
│    ✅ Retournée en réponse HTTP                                 │
│    ✅ Utilisée dans une autre Data                              │
├─────────────────────────────────────────────────────────────────┤
│  🎯 CRÉATION                                                    │
│    ✅ DTO complexe (>3 props) → Factory                         │
│    ✅ DTO simple (≤3 props) → constructeur direct possible      │
│    ✅ Factory prend un Record → retourne une Data               │
├─────────────────────────────────────────────────────────────────┤
│  ❌ INTERDICTIONS                                               │
│    ❌ Carbon / DateTime → string ISO 8601                       │
│    ❌ Model → utiliser Record                                   │
│    ❌ Collection → array                                        │
│    ❌ Méthodes statiques (fromArray, fromModel)                 │
│    ❌ Logique métier                                            │
│    ❌ Utilisation en paramètre interne                          │
│    ❌ snake_case                                                │
└─────────────────────────────────────────────────────────────────┘
