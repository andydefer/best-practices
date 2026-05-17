# Principe d'usage des Data DTO (Version finale)

## 1. Définition

Une **Data DTO** est une classe **pure** et **immutable** qui représente une structure de données **uniquement pour les réponses HTTP**.

> ⚠️ **Les Data sont exclusivement pour les réponses API. Pour la communication interne (Services, Repositories, méthodes), utilisez les Records.**

> 📌 **Une Data DTO ne contient qu'une seule méthode de création : `fromRecord()`.**

---

## 2. Problématique à laquelle les Data répondent

### 2.1. Le problème de la sérialisation

Dans une application moderne, l'API peut être consommée par différents clients :

| Client | Langage / Framework |
|--------|---------------------|
| Application mobile | Kotlin (Android), Swift (iOS) |
| Application desktop | Rust, C#, Python |
| Frontend web | TypeScript, JavaScript |
| Microservices | Go, Java, Rust |

**Sans une structure de données standardisée, chaque client doit deviner :**
- La structure exacte de la réponse
- Les types des champs (string, int, bool, enum)
- Les champs optionnels vs obligatoires
- Les conventions de nommage (camelCase, snake_case)

### 2.2. La solution : Les Data DTO

> **Les Data DTO fournissent un contrat explicite entre le serveur et tous ses clients, quel que soit le langage.**

```php
// PHP (serveur)
final class UserData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly UserRole $role,
        public readonly array $recentPosts,
        public readonly string $createdAt,
    ) {}
}

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
}
```

**Réponse JSON générée :**
```json
{
    "id": "123",
    "name": "John Doe",
    "email": "john@example.com",
    "role": "admin",
    "recentPosts": [...],
    "createdAt": "2024-01-15T10:30:00Z"
}
```

### 2.3. Avantage : Structure miroir dans n'importe quel langage

Grâce à la standardisation des Data DTO, les clients peuvent créer des **structures miroir** parfaitement alignées.

---

## 3. Exemples d'intégration multi-langages

### 3.1. Client Rust

```rust
use serde::{Deserialize, Serialize};
use chrono::{DateTime, Utc};

#[derive(Debug, Serialize, Deserialize)]
struct UserData {
    id: String,
    name: String,
   email: String,
    role: UserRole,
    recent_posts: Vec<PostData>,
    created_at: DateTime<Utc>,
}

#[derive(Debug, Serialize, Deserialize)]
#[serde(rename_all = "snake_case")]
enum UserRole {
    Admin,
    User,
}

// Appel à l'API
async fn fetch_user(client: &reqwest::Client, user_id: &str) -> Result<UserData, reqwest::Error> {
    let response = client
        .get(&format!("https://api.example.com/users/{}", user_id))
        .send()
        .await?;
    
    let user_data: UserData = response.json().await?;
    
    println!("User: {} ({})", user_data.name, user_data.email);
    match user_data.role {
        UserRole::Admin => println!("Has admin privileges"),
        UserRole::User => println!("Regular user"),
    }
    
    Ok(user_data)
}
```

### 3.2. Client Kotlin (Android)

```kotlin
import kotlinx.serialization.*
import kotlinx.serialization.json.*
import java.time.Instant

@Serializable
data class UserData(
    val id: String,
    val name: String,
    val email: String,
    val role: UserRole,
    val recentPosts: List<PostData>,
    val createdAt: Instant,
)

@Serializable
enum class UserRole {
    @SerialName("admin")
    ADMIN,
    
    @SerialName("user")
    USER;
}

// Appel à l'API avec Retrofit
interface ApiService {
    @GET("users/{userId}")
    suspend fun getUser(@Path("userId") userId: String): UserData
}

// Utilisation
class UserRepository(private val api: ApiService) {
    suspend fun fetchUser(userId: String): UserData {
        val user = api.getUser(userId)
        println("User: ${user.name} (${user.email})")
        when (user.role) {
            UserRole.ADMIN -> println("Has admin privileges")
            UserRole.USER -> println("Regular user")
        }
        return user
    }
}
```

### 3.3. Client TypeScript (Frontend Web)

```typescript
// types/api.ts
interface UserData {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    recentPosts: PostData[];
    createdAt: string; // ISO 8601 string
}

enum UserRole {
    Admin = 'admin',
    User = 'user',
}

// API client
class ApiClient {
    async getUser(userId: string): Promise<UserData> {
        const response = await fetch(`https://api.example.com/users/${userId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const userData: UserData = await response.json();
        
        console.log(`User: ${userData.name} (${userData.email})`);
        switch (userData.role) {
            case UserRole.Admin:
                console.log('Has admin privileges');
                break;
            case UserRole.User:
                console.log('Regular user');
                break;
        }
        
        return userData;
    }
}

// Utilisation avec React
function UserProfile({ userId }: { userId: string }) {
    const [user, setUser] = useState<UserData | null>(null);
    
    useEffect(() => {
        apiClient.getUser(userId).then(setUser);
    }, [userId]);
    
    if (!user) return <div>Loading...</div>;
    
    return (
        <div>
            <h1>{user.name}</h1>
            <p>{user.email}</p>
            <p>Role: {user.role}</p>
            <p>Joined: {new Date(user.createdAt).toLocaleDateString()}</p>
        </div>
    );
}
```

### 3.4. Client Python

```python
from datetime import datetime
from pydantic import BaseModel
from enum import Enum
import httpx

class UserRole(str, Enum):
    ADMIN = "admin"
    USER = "user"

class UserData(BaseModel):
    id: str
    name: str
    email: str
    role: UserRole
    recent_posts: list[dict]
    created_at: datetime

class ApiClient:
    def __init__(self, base_url: str = "https://api.example.com"):
        self.base_url = base_url
    
    async def get_user(self, user_id: str) -> UserData:
        async with httpx.AsyncClient() as client:
            response = await client.get(f"{self.base_url}/users/{user_id}")
            response.raise_for_status()
            
            user_data = UserData(**response.json())
            
            print(f"User: {user_data.name} ({user_data.email})")
            if user_data.role == UserRole.ADMIN:
                print("Has admin privileges")
            else:
                print("Regular user")
            
            return user_data
```

---

## 4. Les classes fondamentales : DataInterface et AbstractData

### 4.1. DataInterface

L'interface que toutes les Data DTO doivent implémenter (via `AbstractData`) :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Data;

/**
 * Contract for Data DTO objects.
 *
 * Defines the essential methods that all Data DTOs must implement to ensure
 * consistent data transformation across the application. Data DTOs are pure,
 * immutable structures used exclusively for HTTP responses.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Data
 */
interface DataInterface
{
    /**
     * Converts the Data DTO to an associative array.
     *
     * The conversion handles:
     * - Nested Data objects (recursive conversion)
     * - Enums (converted to their scalar values or names)
     * - DateTime objects (converted to ISO 8601 format)
     * - Laravel Collections (converted to arrays)
     * - Property keys remain in camelCase
     *
     * @return array<string, mixed> Associative array representation of the DTO
     */
    public function toArray(): array;

    /**
     * Creates an array of Data DTO instances from an iterable source.
     *
     * Accepts either arrays or objects as source items. For objects, extracts
     * public properties to match the DTO constructor parameters.
     *
     * @param iterable<object|array> $items Source items to convert
     * @return array<int, static> Array of DTO instances
     *
     * @throws InvalidArgumentException When an item is neither an object nor an array
     */
    public static function collect(iterable $items): array;
}
```

### 4.2. AbstractData

La classe abstraite que **toute Data DTO doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Data;

use Illuminate\Support\Collection as LaravelCollection;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

/**
 * Abstract base class for all Data DTOs.
 *
 * Provides pure data transformation capabilities including array conversion,
 * nested object handling, enum conversion, and date formatting. Data DTOs are
 * immutable structures used exclusively for API responses.
 *
 * @author Andy Defer
 * @package AndyDefer\BestPractices\Data
 */
abstract class AbstractData implements DataInterface
{
    /**
     * Converts the Data DTO to an associative array.
     *
     * Recursively processes all public properties, converting nested Data objects,
     * enums, collections, and date objects to their array/string representations.
     *
     * @return array<string, mixed> Associative array representation of the DTO
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $value = $property->getValue($this);
            $key = $property->getName();

            $result[$key] = $this->transformValue($value);
        }

        return $result;
    }

    /**
     * Creates an array of Data DTO instances from an iterable source.
     *
     * @param iterable<object|array> $items Source items to convert
     * @return array<int, static> Array of DTO instances
     *
     * @throws InvalidArgumentException When an item is neither an object nor an array
     */
    public static function collect(iterable $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = new static(...$item);
            } elseif (is_object($item)) {
                $result[] = new static(...self::extractPublicProperties($item));
            } else {
                throw new InvalidArgumentException(
                    sprintf('Item must be an object or array, %s given', gettype($item))
                );
            }
        }

        return $result;
    }

    /**
     * Recursively transforms a value for array representation.
     *
     * Handles:
     * - Null values (passed through)
     * - Enums (converted to scalar values or names)
     * - Arrays (recursively processed)
     * - Laravel Collections (converted to arrays recursively)
     * - Nested Data objects (converted via their toArray method)
     * - DateTime objects (formatted as ISO 8601)
     *
     * @param mixed $value The value to transform
     * @return mixed Transformed value ready for array output
     */
    private function transformValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return $this->transformEnum($value);
        }

        if (is_array($value)) {
            return array_map(fn($item) => $this->transformValue($item), $value);
        }

        if ($value instanceof LaravelCollection) {
            return $value->map(fn($item) => $this->transformValue($item))->toArray();
        }

        if ($value instanceof AbstractData) {
            return $value->toArray();
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return $value;
    }

    /**
     * Converts an Enum to its scalar representation.
     *
     * For backed enums, returns the backing value (string|int).
     * For pure enums, returns the enum case name.
     *
     * @param UnitEnum $enum The enum instance to convert
     * @return string|int Scalar representation of the enum
     */
    private function transformEnum(UnitEnum $enum): string|int
    {
        if ($enum instanceof \BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }

    /**
     * Extracts public properties from an object as an associative array.
     *
     * Used internally by the collect method to convert objects to arrays
     * before instantiating Data DTOs.
     *
     * @param object $object The source object
     * @return array<string, mixed> Associative array of public property values
     */
    private static function extractPublicProperties(object $object): array
    {
        $reflection = new ReflectionClass($object);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            $result[$property->getName()] = $property->getValue($object);
        }

        return $result;
    }
}
```

### 4.3. Ce qu'offre AbstractData

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `toArray()` | Convertit récursivement la Data en tableau (supporte Enums, nested Data, Collections, DateTime) | `$userData->toArray()` |
| `collect(iterable $items)` | Crée un tableau de Data DTOs à partir d'un itérable d'objets ou de tableaux | `UserData::collect($users)` |

**Comportement de `toArray()` :**
- ✅ Garde les noms de propriétés en **camelCase**
- ✅ Convertit les Enums en leur valeur (`string`/`int`)
- ✅ Convertit récursivement les Data imbriquées
- ✅ Convertit les tableaux de Data
- ✅ Convertit les `Collection` Laravel en `array`
- ✅ Convertit `DateTime`/`Carbon` en **ISO 8601** (`Y-m-d\TH:i:s\Z`)

---

## 5. Règles strictes

### 5.1. Héritage obligatoire

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

### 5.2. Pureté et immutabilité

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
}

// ❌ Mauvais - Data DTO impure avec comportement
final class UserData extends AbstractData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
    
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
```

### 5.3. Nommage des propriétés

- Les propriétés doivent être en **camelCase**
- ⚠️ Pas de `snake_case` dans les DTOs
- ⚠️ Toutes les propriétés doivent être `public readonly`

```php
// ✅ Bon
public readonly string $doctorId;
public readonly ?AddressData $primaryAddress;

// ❌ Mauvais
public string $doctor_id;
protected string $doctorId;
private string $doctorId;
public string $doctorId;
```

### 5.4. Types de propriétés autorisés

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
public readonly array $recentRatings;

// ❌ Mauvais
public readonly Carbon $createdAt;
public readonly User $user;
public readonly Collection $items;
```

### 5.5. Règle des valeurs par défaut pour les nullables

> **Pour éviter les `undefined` côté Frontend, toute propriété nullable doit avoir une valeur par défaut.**

```php
// ✅ Bon - Valeur par défaut pour les nullables
public function __construct(
    public readonly ?string $message = null,
    public readonly ?array $metadata = [],
    public readonly ?array $errors = [],
    public readonly ?UserRole $role = null,
    public readonly ?array $roles = [],
) {}

// ❌ Mauvais - Pas de valeur par défaut
public function __construct(
    public readonly ?string $message,
    public readonly ?array $metadata,
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

### 5.6. Règle pour les dates

**Toutes les dates doivent être représentées par des chaînes de caractères au format ISO 8601.**

| Format | Exemple |
|--------|---------|
| Date seule | `2024-01-15` |
| Date + heure | `2024-01-15T14:30:00Z` |
| Date + heure + timezone | `2024-01-15T14:30:00+01:00` |

```php
// ✅ Bon - String ISO 8601
public readonly string $createdAt;
public readonly string $scheduleDate;
public readonly ?string $emailVerifiedAt = null;

// ❌ Mauvais - Types interdits pour les dates
public readonly Carbon $createdAt;
public readonly DateTime $updatedAt;
```

### 5.7. Usage exclusif : API seulement

> **Une Data ne peut être utilisée que pour une réponse API. Elle ne peut pas être passée en paramètre d'une méthode interne.**

```php
// App\Data\UserData.php
final class UserData extends AbstractData
{
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $recentPosts,
        public readonly string $createdAt,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?UserRole $role = null,
    ) {}
    
    // UNIQUEMENT fromRecord
    public static function fromRecord(UserRecord $record): self
    {
        return new self(
            id: (string) $record->id,
            name: $record->name,
            email: $record->email,
            recentPosts: $record->recentPosts,
            createdAt: $record->createdAt,
            emailVerifiedAt: $record->emailVerifiedAt,
            role: $record->role,
        );
    }
}

// ✅ BON - Data utilisée pour une réponse API
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $userRecord = $this->userService->getUser($userId);
        
        if ($userRecord === null) {
            return $this->json(null, 404);
        }
        
        // La Data crée elle-même à partir du Record
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}

// ❌ MAUVAIS - Data utilisée en paramètre interne
final class UserService
{
    public function updateUser(UserData $userData): void
    {
        // Les Data ne sont pas pour l'interne !
    }
}

// ✅ BON - Utiliser un Record pour l'interne
final class UserService
{
    public function updateUser(UserRecord $userRecord): void
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

### 5.8. Condition d'existence

Une Data **DOIT** répondre à au moins une de ces conditions :

1. **Être liée à un Record et en faire une représentation**
2. **Ou être retournée dans une réponse HTTP**
3. **Ou être utilisée dans une autre Data**

### 5.9. Règle de cohérence des noms (⚠️ RÈGLE STRICTE)

> **Une propriété Data qui représente une collection DOIT avoir le MÊME nom que l'attribut du Model source.**

```php
// Model
final class User extends Model
{
    protected function recentPosts(): Attribute  // Attribut 'recent_posts'
    {
        return Attribute::make(
            get: fn () => $this->posts()->limit(10)->get(),
        );
    }
}

// Data (camelCase du nom de l'attribut)
final class UserData extends AbstractData
{
    public readonly array $recentPosts;  // Même nom que l'attribut (camelCase)
}

// ❌ MAUVAIS - Noms différents
final class UserData extends AbstractData
{
    public readonly array $posts;        // ❌ Devrait être $recentPosts
    public readonly array $userPosts;    // ❌ Devrait être $recentPosts
}
```

### 5.10. Règle des collections limitées (⚠️ RÈGLE STRICTE)

> **Une Data ne peut pas contenir de collection non limitée. Toute propriété de type `array` représentant une collection DOIT provenir d'un attribut de Model déjà limité.**

```php
// ✅ BON - Collection limitée via l'attribut Model
final class UserData extends AbstractData
{
    public readonly array $recentPosts;   // Limitée à 10 dans le Model
    public readonly array $publicPosts;   // Limitée à 10 dans le Model
}

// ❌ MAUVAIS - Collection non limitée
final class UserData extends AbstractData
{
    public readonly array $posts;  // ❌ Tous les posts (1000+)
}
```

### 5.11. Interdictions strictes

| Interdit | Alternative |
|----------|-------------|
| Méthodes métier | Déplacer dans un Service |
| Propriétés non-readonly | `public readonly` |
| `Carbon` / `DateTime` | `string` ISO 8601 |
| `Model` (Eloquent) | Utiliser `Record` |
| `Collection` | Utiliser `array` |
| Utilisation en paramètre interne | Utiliser `Record` |
| Logique de transformation | Dans `fromRecord()` uniquement |
| Collection non limitée | Utiliser attribut Model limité |
| Nom incohérent avec attribut Model | Respecter le nom de l'attribut |

---

## 6. Qui crée les Data DTO ?

### 6.1. Règle fondamentale

> **Une Data DTO ne doit JAMAIS être instanciée directement avec `new` sauf pour les cas simples (≤ 3 propriétés).**

### 6.2. Arbre de décision

```
┌─────────────────────────────────────────────────────────────────┐
│  Comment la Data doit-elle être créée ?                         │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  La Data a-t-elle plus de 3 propriétés ?                        │
└─────────────────────────────────────────────────────────────────┘
                              │
            ┌─────────────────┴─────────────────┐
            │                                   │
            ▼                                   ▼
           OUI                                  NON
            │                                   │
            ▼                                   ▼
┌─────────────────────────────────┐   ┌─────────────────────────────┐
│ La Data a besoin d'une source   │   │ Constructeur direct         │
│ unique : le Record              │   │ new PingData()              │
└─────────────────────────────────┘   └─────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│  Data::fromRecord($record)                                      │
└─────────────────────────────────────────────────────────────────┘
```

### 6.3. Règle d'unicité de la méthode de création (⚠️ RÈGLE STRICTE)

> **Une Data ne peut avoir qu'UNE SEULE méthode de création : `fromRecord()`. C'est sa méthode unique et obligatoire.**

```php
// ✅ BON - Data liée à un Record (seulement fromRecord)
final class UserData extends AbstractData
{
    private function __construct(...) {}
    
    public static function fromRecord(UserRecord $record): self
    {
        return new self(
            id: (string) $record->id,
            name: $record->name,
            email: $record->email,
            recentPosts: $record->recentPosts,
            createdAt: $record->createdAt,
        );
    }
}

// ❌ MAUVAIS - Plusieurs méthodes
final class UserData extends AbstractData
{
    public static function fromRecord(UserRecord $record): self { ... }
    public static function fromArray(array $data): self { ... }      // ❌ Interdit
    public static function make(array $data): self { ... }           // ❌ Interdit
}
```

### 6.4. Ce que la méthode `fromRecord()` doit faire

```php
// ✅ BON - Uniquement transformation de types (cast, toArray, toIso8601String)
public static function fromRecord(UserRecord $record): self
{
    return new self(
        id: (string) $record->id,                           // cast type
        name: $record->name,                                 // simple passage
        email: $record->email,                               // simple passage
        recentPosts: $record->recentPosts,                  // attribut déjà limité
        createdAt: $record->createdAt,                      // déjà string ISO 8601
        emailVerifiedAt: $record->emailVerifiedAt,          // nullable
        role: $record->role,                                // Enum
    );
}

// ❌ INTERDIT - Logique conditionnelle
public static function fromRecord(UserRecord $record): self
{
    if ($record->role->isAdmin()) {
        return new self(...);
    }
    return new self(...);
}

// ❌ INTERDIT - Calcul de valeurs
public static function fromRecord(UserRecord $record): self
{
    return new self(
        canEdit: $record->context->currentUserId === $record->user->id, // ❌
    );
}
```

### 6.5. Cas particuliers : Sources multiples avec valeurs calculées

> **Si la Data a besoin de plusieurs sources (ex: User + Contexte) ou de valeurs calculées, on crée d'abord un Record qui contient toutes les sources ET les valeurs calculées, puis la Data utilise `fromRecord()`.**

```php
// Record qui contient toutes les sources ET les valeurs calculées
final class UserWithContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly UserRecord $user,
        public readonly UserContextRecord $context,
        public readonly bool $canEdit,  // ← Valeur calculée DANS le Record
    ) {}
}

// Service qui orchestre et calcule la valeur
final class UserService
{
    public function getUserWithContext(int $userId, int $currentUserId): ?UserWithContextRecord
    {
        $user = $this->userRepository->find($userId);
        
        if ($user === null) {
            return null;
        }
        
        $userRecord = new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            recentPosts: $user->recent_posts->toArray(),
            createdAt: $user->created_at->toIso8601String(),
        );
        
        $context = new UserContextRecord(
            currentUserId: $currentUserId,
            timezone: config('app.timezone'),
        );
        
        // Calcul DANS le Service, PAS dans la Data
        $canEdit = $currentUserId === $user->id;
        
        return new UserWithContextRecord(
            user: $userRecord,
            context: $context,
            canEdit: $canEdit,
        );
    }
}

// Data avec fromRecord() seulement (PAS de calcul)
final class UserData extends AbstractData
{
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $recentPosts,
        public readonly string $createdAt,
        public readonly ?string $timezone = null,
        public readonly ?bool $canEdit = null,
    ) {}
    
    public static function fromRecord(UserWithContextRecord $record): self
    {
        return new self(
            id: (string) $record->user->id,
            name: $record->user->name,
            email: $record->user->email,
            recentPosts: $record->user->recentPosts,
            createdAt: $record->user->createdAt,
            timezone: $record->context->timezone,
            canEdit: $record->canEdit,  // ← Simple passage, pas de calcul
        );
    }
}
```

### 6.6. Règle : Où faire les calculs ?

| Où faire le calcul ? | ✅ / ❌ |
|---------------------|--------|
| Dans le Service | ✅ |
| Dans le constructeur du Record | ✅ |
| Dans la Data (`fromRecord()`) | ❌ |
| Dans l'Action | ❌ (à déplacer dans Service) |

### 6.7. Récapitulatif des responsabilités

| Composant | Responsabilité |
|-----------|----------------|
| **Data** (elle-même) | Définir la structure + `fromRecord()` (transformation de types uniquement) |
| **Record** | Contenir les données brutes (scalaires, Enums, autres Records) + valeurs calculées |
| **Service** | Orchestrer la récupération des données, calculer les valeurs, créer les Records |

### 6.8. Exception pour les DTO simples

> **Pour les DTO très simples (moins de 3 propriétés, pas de logique de transformation), on peut utiliser le constructeur directement.**

```php
// ✅ Bon - DTO simple, constructeur direct
final class PingData extends AbstractData
{
    public function __construct(
        public readonly string $status = 'ok',
        public readonly string $timestamp = '2024-01-15T10:00:00Z',
    ) {}
}

// Dans l'Action
return $this->json(new PingData());
```

### 6.9. Exemple : Data simple (élément unique)

```php
// ✅ BON - Data utilisée pour une réponse API (élément unique)
final class ShowUserAction extends AbstractAction
{
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $userRecord = $this->userService->getUser($userId);
        
        if ($userRecord === null) {
            return $this->json(null, 404);
        }
        
        // La Data se crée elle-même à partir du Record
        $userData = UserData::fromRecord($userRecord);
        
        return $this->json($userData);
    }
}
```

### 6.10. Exemple : Data paginée (liste)

```php
// ✅ BON - Data utilisée pour une réponse API (liste paginée)
final class ListUsersAction extends AbstractAction
{
    public function run(ListUsersRequest $request): JsonResponse
    {
        $usersRecord = $this->userService->getUsers(
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        );
        
        // Transformation des items UsersRecord → UserData
        $usersData = UserData::collect($usersRecord->items);
        
        // Création de la Data paginée
        $paginatedData = PaginatedData::fromRecord(new PaginatedRecord(
            items: $usersData,
            currentPage: $usersRecord->currentPage,
            perPage: $usersRecord->perPage,
            total: $usersRecord->total,
            lastPage: $usersRecord->lastPage,
        ));
        
        return $this->json($paginatedData);
    }
}

// Data PaginatedData
final class PaginatedData extends AbstractData
{
    private function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
    ) {}
    
    public static function fromRecord(PaginatedRecord $record): self
    {
        return new self(
            items: $record->items,
            currentPage: $record->currentPage,
            perPage: $record->perPage,
            total: $record->total,
            lastPage: $record->lastPage,
        );
    }
}
```

### 6.11. Exemple de réponse JSON générée

```json
{
    "items": [
        {
            "id": "1",
            "name": "John Doe",
            "email": "john@example.com",
            "recentPosts": [...],
            "createdAt": "2024-01-15T10:30:00Z"
        }
    ],
    "currentPage": 1,
    "perPage": 15,
    "total": 42,
    "lastPage": 3
}
```

---

## 7. Hiérarchie des Dossiers

```
App/
├── Data/           # DTOs PURES pour les réponses API
│   ├── UserData.php
│   ├── PaginatedData.php
│   └── ...
├── Records/        # Structures internes pour la communication
│   ├── UserRecord.php
│   ├── UserWithContextRecord.php
│   ├── PaginatedRecord.php
│   └── ...
├── Actions/        # Actions utilisant les Data DTOs
│   ├── Api/
│   │   └── Users/
│   │       ├── ListUsersAction.php
│   │       └── ShowUserAction.php
│   └── Web/
│       └── Dashboard/
│           └── ShowDashboardAction.php
├── Services/       # Logique métier (retournent des Records)
│   └── UserService.php
├── Tasks/          # Actions unitaires de même nature
│   └── ValidateUserAccessTask.php
├── Workers/        # Orchestration de Tasks
│   └── HandleDashboardAccessWorker.php
└── 
```

---

## 8. Exemple complet avec Action

```php
// 1. Record simple (App\Records\UserRecord.php)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $recentPosts,
        public readonly string $createdAt,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?UserRole $role = null,
    ) {}
}

// 2. Data DTO pure (App\Data\UserData.php)
final class UserData extends AbstractData
{
    private function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $recentPosts,
        public readonly string $createdAt,
        public readonly ?string $emailVerifiedAt = null,
        public readonly ?UserRole $role = null,
    ) {}
    
    // Source unique : Record
    public static function fromRecord(UserRecord $record): self
    {
        return new self(
            id: (string) $record->id,
            name: $record->name,
            email: $record->email,
            recentPosts: $record->recentPosts,
            createdAt: $record->createdAt,
            emailVerifiedAt: $record->emailVerifiedAt,
            role: $record->role,
        );
    }
}

// 3. Record pour contexte supplémentaire (App\Records\UserContextRecord.php)
final class UserContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $currentUserId,
        public readonly string $timezone,
    ) {}
}

// 4. Record combiné (App\Records\UserWithContextRecord.php)
final class UserWithContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly UserRecord $user,
        public readonly UserContextRecord $context,
        public readonly bool $canEdit,  // Valeur calculée
    ) {}
}

// 5. Service (App\Services\UserService.php)
final class UserService
{
    public function getUserWithContext(int $userId, int $currentUserId): ?UserWithContextRecord
    {
        $user = $this->userRepository->find($userId);
        
        if ($user === null) {
            return null;
        }
        
        $userRecord = new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            recentPosts: $user->recent_posts->toArray(),
            createdAt: $user->created_at->toIso8601String(),
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            role: $user->role,
        );
        
        $context = new UserContextRecord(
            currentUserId: $currentUserId,
            timezone: config('app.timezone'),
        );
        
        $canEdit = $currentUserId === $user->id;
        
        return new UserWithContextRecord(
            user: $userRecord,
            context: $context,
            canEdit: $canEdit,
        );
    }
}

// 6. Action API (App\Actions\Api\Users\ShowUserAction.php)
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    public function run(int $userId, ShowUserRequest $request): JsonResponse
    {
        $currentUserId = auth()->id();
        
        $userWithContext = $this->userService->getUserWithContext($userId, $currentUserId);
        
        if ($userWithContext === null) {
            return $this->json(null, 404);
        }
        
        $userData = UserData::fromRecord($userWithContext);
        
        return $this->json($userData);
    }
}
```

---

## 9. Récapitulatif visuel

```
┌─────────────────────────────────────────────────────────────────┐
│                     RÈGLES DES DATA DTO                         │
├─────────────────────────────────────────────────────────────────┤
│  📌 HÉRITAGE                                                    │
│    ✅ DOIT étendre AbstractData                                 │
│    ✅ DOIT implémenter DataInterface                            │
│    ✅ Hérite de toArray() et collect()                          │
├─────────────────────────────────────────────────────────────────┤
│  🎯 USAGE EXCLUSIF                                              │
│    ✅ Réponses API uniquement                                   │
│    ❌ Paramètre de méthode interne                              │
├─────────────────────────────────────────────────────────────────┤
│  🌍 MULTI-LANGAGES (AVANTAGE)                                   │
│    ✅ Rust → struct UserData avec serde                         │
│    ✅ Kotlin → data class UserData avec kotlinx.serialization   │
│    ✅ TypeScript → interface UserData                           │
│    ✅ Python → class UserData avec Pydantic                     │
├─────────────────────────────────────────────────────────────────┤
│  🧊 PURETÉ & IMMUTABILITÉ                                       │
│    ✅ public readonly properties                                │
│    ✅ Pas de méthodes (sauf constructeur et fromRecord)         │
│    ✅ Pas de logique métier                                     │
├─────────────────────────────────────────────────────────────────┤
│  📝 FORMAT                                                      │
│    ✅ camelCase pour les propriétés                             │
│    ✅ Types : int, float, bool, string, array, Data, Enum       │
│    ✅ DATES : string ISO 8601                                   │
│    ✅ nullables : toujours une valeur par défaut                │
│    ✅ ?array = [] (pas null)                                    │
├─────────────────────────────────────────────────────────────────┤
│  🔗 COLLECTIONS (RÈGLES STRICTES)                               │
│    ✅ Propriété array = nom identique à l'attribut Model        │
│    ✅ Collection TOUJOURS limitée (via attribut Model)          │
│    ❌ Pas de collection non limitée                             │
├─────────────────────────────────────────────────────────────────┤
│  🏭 CRÉATION (RÈGLES STRICTES)                                  │
│    ✅ Data simple (≤3 props) → constructeur direct              │
│    ✅ Source unique (Record) → fromRecord() dans la Data        │
│    ✅ Sources multiples → Record combiné + fromRecord()         │
│    ❌ Jamais d'autre méthode que fromRecord()                   │
│    ❌ Pas de logique dans fromRecord()                          │
│    ❌ Pas de calcul dans fromRecord()                           │
├─────────────────────────────────────────────────────────────────┤
│  🚫 INTERDICTIONS                                               │
│    ❌ Carbon / DateTime → string ISO 8601                       │
│    ❌ Model → utiliser Record                                   │
│    ❌ Collection → array                                        │
│    ❌ snake_case                                                │
└─────────────────────────────────────────────────────────────────┘
```