# Principe d'usage des Data DTO

## 1. Définition

Une **Data DTO** (Data Transfer Object) est une structure **immuable**, **type-safe** et **auto-hydratable** qui représente une réponse API. Elle garantit un contrat explicite entre le serveur et ses clients (mobile, frontend, microservices).

```
Record (interne) → Data DTO (API) → Réponse JSON → Clients
Value Object (interne) → Data DTO (API) → Réponse JSON → Clients
```

```php
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use App\ValueObjects\UserId;
use App\ValueObjects\PersonName;
use App\ValueObjects\EmailAddress;
use App\Enums\UserRole;

final class UserData extends AbstractData
{
    public function __construct(
        public readonly UserId $id,
        public readonly PersonName $name,
        public readonly EmailAddress $email,
        public readonly UserRole $role,
        public readonly ProductDataCollection $purchasedProducts,
    ) {}
}

// Hydratation depuis un tableau
$user = UserData::from([
    'id' => '123e4567-e89b-12d3-a456-426614174000',
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'role' => 'admin',
    'purchasedProducts' => [...]
]);

// Réponse API
return ResponseFactory::json($user);
```

---

## 2. Problématique à laquelle les Data répondent

| Problème | Solution |
|----------|----------|
| **Types primitifs ambigus** | `int $id` ? `string $name` ? Utiliser des Value Objects explicites |
| **Collections non typées** | `array $products` ? `TypedCollection $items` ? Utiliser des collections concrètes |
| **Contrat implicite** | Le client ne sait pas à quoi s'attendre | API auto-documentée par les types |
| **Incohérence API** | snake_case ici, camelCase là | Normalisation automatique camelCase |
| **Duplication Record/Data** | Transformation manuelle | `from()` automatique via Hydratable |

### 2.1. Le problème de la sérialisation

Dans une application moderne, l'API peut être consommée par différents clients :

| Client | Langage / Framework |
|--------|---------------------|
| Application mobile | Kotlin (Android), Swift (iOS) |
| Frontend web | TypeScript, JavaScript |
| Microservices | Go, Java, Rust |

**Sans une structure de données standardisée, chaque client doit deviner :**
- La structure exacte de la réponse
- Les types des champs (string, int, bool, enum)
- Les champs optionnels vs obligatoires
- Les conventions de nommage (camelCase, snake_case)

> **Les Data DTO fournissent un contrat explicite entre le serveur et tous ses clients, quel que soit le langage.**

---

## 3. Record vs Value Object vs Data

| Aspect | Record | Value Object | Data DTO |
|--------|--------|--------------|----------|
| **Usage principal** | Communication interne | Concepts métier | Réponses HTTP |
| **Logique métier** | ❌ Aucune | ✅ Peut contenir | ❌ Transformation uniquement |
| **Validation** | Optionnelle | ✅ OBLIGATOIRE | Optionnelle |
| **Constructeur** | Public | Privé (factory) | Public |
| **Types autorisés** | VO, Enum, Record, Collection | VO, Enum, Collection | VO, Enum, Data, Collection concrète |
| **Peut contenir des Records** | ✅ Oui | ❌ Interdit | ✅ Oui (via transformation) |
| **Peut contenir des Data** | ❌ Interdit | ❌ Interdit | ✅ Oui |
| **Nommage des clés** | `snake_case` | `camelCase` | `camelCase` |
| **Hydratation** | `StrictDataObject` | `DataObject` | `DataObject` |
| **Destination** | Base de données / Services | Logique métier | Client (JSON) |

---

## 4. Les classes fondamentales : AbstractData

### 4.1. AbstractData

La classe abstraite que **toute Data DTO doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\DomainStructures\Abstracts;

use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\DomainStructures\Traits\Hydratable;

abstract class AbstractData implements Transformable
{
    use Hydratable;

    /**
     * Convertit la Data en tableau via le système de normalisation.
     * Conserve les noms de propriétés en camelCase.
     */
    public function toArray(): array
    {
        return NormalizerChain::get()->normalize($this);
    }

    /**
     * Convertit la Data en chaîne JSON.
     */
    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
```

### 4.2. Ce qu'offre AbstractData

| Méthode | Description | Exemple |
|---------|-------------|---------|
| `from(mixed $source): static` | Hydrate une Data depuis une source | `UserData::from($array)` |
| `fromJson(string $json): static` | Hydrate depuis JSON | `UserData::fromJson($json)` |
| `collect(iterable $sources, string $class = TypedCollection::class): AbstractTypedCollection` | Hydrate une collection | `UserData::collect($sources)` |
| `toArray(): array` | Normalise la Data | `$userData->toArray()` |
| `__toString(): string` | Convertit en JSON | `(string) $userData` |

### 4.3. Comportement de `toArray()`

Le système de normalisation (`NormalizerChain`) applique les règles suivantes pour `AbstractData` :

| Type | Comportement |
|------|-------------|
| **Propriétés public** | Toutes sont incluses |
| **Nommage** | ✅ **Conserve camelCase** |
| **Enums** | Convertit en leur valeur (`string`/`int`) |
| **Data imbriquées** | Convertit récursivement via `toArray()` |
| **ValueObjects** | Extrait la valeur brute (`getValue()`) |
| **Collections typées** | Convertit en tableau |
| **Null** | ✅ Préservé |

---

## 5. Règle fondamentale (⚠️ IMPORTANTE)

> **Une Data DTO est EXCLUSIVEMENT pour les réponses API. Pour la communication interne (Services, Repositories, Actions), utilisez les Records.**

```php
// ✅ BON - Data pour la réponse API
final class UserData extends AbstractData
{
    public function __construct(
        public readonly UserId $id,
        public readonly PersonName $name,
    ) {}
}

// ✅ BON - Record pour la communication interne
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
    ) {}
}
```

---

## 6. Pourquoi privilégier les Value Objects plutôt que les scalaires ?

> **⚠️ Nous conseillons d'éviter les types primitifs, mais nous ne les interdisons pas dogmatiquement. L'important est la clarté du contrat.**

### 6.1. Les limites des scalaires

```php
// ⚠️ DÉCONSEILLÉ - Utilisation de scalaires
final class BadUserData extends AbstractData
{
    public function __construct(
        public readonly string $email,      // string non typé
        public readonly string $createdAt,  // string non formaté
        public readonly string $status,     // string non typé
    ) {}
}

// Problèmes :
// - email non validé à la création
// - createdAt peut être n'importe quelle chaîne
// - status peut contenir n'importe quelle valeur
```

### 6.2. Les avantages des Value Objects

| Avantage | Explication |
|----------|-------------|
| **Validation automatique** | Le VO valide sa valeur à la construction |
| **Typage fort** | Le type parle de lui-même (`EmailAddress` vs `string`) |
| **Comportement encapsulé** | Le VO peut contenir des méthodes métier (`getDomain()`, `isAfter()`) |
| **Détection d'invalidité** | Une donnée invalide ne peut pas être créée |
| **Format standardisé** | Garantit le format de sortie (ISO 8601, etc.) |

```php
// ✅ BON - Utilisation de Value Objects (recommandé)
final class GoodUserData extends AbstractData
{
    public function __construct(
        public readonly EmailAddress $email,        // Validé à la construction
        public readonly Iso8601DateTime $createdAt, // Format garanti
        public readonly UserStatus $status,         // Enum (type sûr)
    ) {}
}

// ⚠️ ACCEPTABLE - Usage de primitifs (déconseillé mais pas interdit)
final class SimpleData extends AbstractData
{
    public function __construct(
        public readonly int $id,      // Déconseillé mais accepté
        public readonly string $name, // Déconseillé mais accepté
    ) {}
}
```

### 6.3. Règle d'utilisation

| Type de donnée | Recommandation | Exemple |
|----------------|----------------|---------|
| **Email** | `EmailAddress` VO | `public readonly EmailAddress $email` |
| **Date/heure** | `Iso8601DateTime` VO | `public readonly Iso8601DateTime $createdAt` |
| **URL** | `Url` VO | `public readonly Url $avatar` |
| **UUID** | `Uuid` VO | `public readonly Uuid $id` |
| **Statut** | Enum | `public readonly UserStatus $status` |
| **Rôle** | Enum | `public readonly UserRole $role` |
| **Tags** | `StringTypedCollection` | `public readonly StringTypedCollection $tags` |

---

## 7. Types autorisés et interdits

### 7.1. Types autorisés

| Type | Statut | Exemple |
|------|--------|---------|
| **Value Objects** | ✅ Recommandé | `public readonly EmailAddress $email` |
| **Enums** | ✅ Recommandé | `public readonly UserRole $role` |
| **Autres Data** | ✅ Recommandé | `public readonly AddressData $address` |
| **Collections typées** | ✅ Recommandé | `public readonly ProductDataCollection $products` |
| `string`, `int`, `float`, `bool` | ⚠️ Déconseillé mais accepté | `public readonly string $name` |
| `array` (simple) | ⚠️ Déconseillé mais accepté | `public readonly array $items` |

### 7.2. Types INTERDITS (⚠️ STRICTEMENT)

| Type interdit | Raison | Alternative |
|---------------|--------|-------------|
| **`array` brut non typé** | Contenu inconnu | `TypedCollection` concrète |
| **`AbstractRecord`** | Violation séparation des couches | Transformer en VO/Data |
| **Collection de Records** | Violation séparation des couches | Transformer en collection de Data |
| **`TypedCollection` générique** | Type contenu inconnu | Collection concrète (`ProductDataCollection`) |
| **`mixed`** | Pas de typage | Type explicite |

```php
// ❌ INTERDIT - array brut
public readonly array $products;  // ❌

// ✅ BON - Collection typée concrète
public readonly ProductDataCollection $products;  // ✅

// ❌ INTERDIT - Record dans Data
public readonly UserRecord $user;  // ❌

// ✅ BON - Transformation en VO
public readonly UserId $userId;  // ✅
```

---

## 8. Collections typées pour les Data

> **⚠️ Pour les collections, utilisez des classes concrètes plutôt que `TypedCollection` directement.**

### 8.1. Créer une collection spécialisée

```php
<?php

declare(strict_types=1);

namespace App\Collections;

use AndyDefer\DomainStructures\Collections\Core\DataCollection;
use App\Data\ProductData;

/**
 * Collection qui ne peut contenir QUE des ProductData.
 *
 * @extends DataCollection<ProductData>
 */
final class ProductDataCollection extends DataCollection
{
    public function __construct()
    {
        parent::__construct(ProductData::class);
    }
    
    // Méthodes utilitaires spécifiques
    public function getFeatured(): self
    {
        return $this->filter(fn(ProductData $product) => $product->isFeatured === true);
    }
}
```

### 8.2. Utilisation

```php
final class UserData extends AbstractData
{
    public function __construct(
        public readonly ProductDataCollection $purchasedProducts,
    ) {}
}

// Création
$collection = new ProductDataCollection();
$collection->add(ProductData::from(['id' => 1, 'name' => 'Laptop']));

$user = new UserData(purchasedProducts: $collection);
```

---

## 9. Transformation Record → Data

> **La transformation d'un Record (snake_case) en Data (camelCase) se fait dans le Service ou l'Action.**

```php
final class UserService
{
    public function toUserData(UserRecord $record): UserData
    {
        // Transformation explicite snake_case → camelCase
        return new UserData(
            userId: $record->user_id,
            userName: $record->user_name,
            userEmail: $record->user_email,
            userRole: $record->user_role,
        );
    }
    
    public function toUserDataCollection(UserCollection $records): UserDataCollection
    {
        $collection = new UserDataCollection();
        
        foreach ($records as $record) {
            $collection->add($this->toUserData($record));
        }
        
        return $collection;
    }
}

// Utilisation dans une Action
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $userRecord = $this->userRepository->find($request->user_id);
        $userData = $this->userService->toUserData($userRecord);
        
        return ResponseFactory::json($userData);
    }
}
```

---

## 10. Hydratation : `from()` et `fromJson()`

### 10.1. Hydratation depuis un tableau

```php
// Les clés peuvent être en snake_case (normalisées automatiquement en camelCase)
$userData = UserData::from([
    'user_id' => '123e4567-e89b-12d3-a456-426614174000',  // sera normalisé en 'userId'
    'user_name' => 'John Doe',                             // sera normalisé en 'userName'
    'user_email' => 'john@example.com',                    // sera normalisé en 'userEmail'
    'user_role' => 'admin',                                // sera normalisé en 'userRole'
    'purchased_products' => [...]
]);

// Accès en camelCase
echo $userData->userId;    // '123e4567...'
echo $userData->userName;  // 'John Doe'
```

### 10.2. Hydratation depuis JSON

```php
$json = '{
    "user_id": "123e4567-e89b-12d3-a456-426614174000",
    "user_name": "John Doe",
    "user_email": "john@example.com",
    "user_role": "admin"
}';

$userData = UserData::fromJson($json);
```

### 10.3. Règle pour l'hydratation

| Source | Méthode | Normalisation |
|--------|---------|---------------|
| Tableau associatif | `Data::from($array)` | `snake_case` → `camelCase` |
| Objet | `Data::from($object)` | `snake_case` → `camelCase` |
| JSON | `Data::fromJson($json)` | `snake_case` → `camelCase` |
| Record | Transformation manuelle | Explicite |

---

## 11. Normalisation : camelCase pour l'API

> **⚠️ Les Data sont automatiquement normalisées en `camelCase` pour les réponses JSON.**

```php
$userData = new UserData(
    userId: UserId::from('123...'),
    userName: PersonName::from('John Doe'),
    userEmail: EmailAddress::from('john@example.com'),
    userRole: UserRole::ADMIN,
    createdAt: Iso8601DateTime::from('2024-01-01T12:00:00+00:00'),
);

$json = json_encode(NormalizerChain::get()->normalize($userData));
// Résultat :
// {
//     "userId": "123...",
//     "userName": "John Doe",
//     "userEmail": "john@example.com",
//     "userRole": "admin",
//     "createdAt": "2024-01-01T12:00:00+00:00"
// }
```

---

## 12. Avantages pour les consommateurs d'API

### 12.1. Contrat explicite

Les Data DTO fournissent un **contrat explicite** que les consommateurs peuvent utiliser pour générer des clients API typés.

### 12.2. Génération de clients API typés

#### TypeScript (Frontend)

```typescript
interface UserData {
    id: string;                    // Uuid → string
    name: string;
    email: string;
    status: 'active' | 'inactive' | 'pending';
    role: 'admin' | 'user' | 'guest';
    tags: string[];
    createdAt: string;              // Format ISO 8601 garanti
}

function displayUser(user: UserData): void {
    console.log(`${user.name} (${user.email}) - ${user.status}`);
}
```

#### Kotlin (Android)

```kotlin
data class UserData(
    val id: String,
    val name: String,
    val email: String,
    val status: UserStatus,
    val role: UserRole,
    val tags: List<String>,
    val createdAt: String
)

enum class UserStatus { active, inactive, pending }
enum class UserRole { admin, user, guest }
```

### 12.3. Validation côté serveur

Les Value Objects garantissent que les données sont valides **avant même d'atteindre le client** :

```php
// Côté serveur : la Data ne peut pas être créée avec des données invalides
$invalidData = UserData::from([
    'name' => 'John Doe',
    'email' => 'not-an-email',     // ❌ Exception : Invalid email address
    'status' => 'active',
    'role' => 'admin',
    'createdAt' => '2024-01-15',    // ❌ Exception : Invalid ISO 8601 datetime
]);

// Le client reçoit soit une réponse 500, soit une réponse avec des données valides
// Jamais de données invalides dans une réponse 200 !
```

---

## 13. Tests unitaires

> **✅ Les Data sont testables unitairement (contrairement aux Actions).**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use Tests\UnitTestCase;
use App\Data\UserData;
use App\ValueObjects\EmailAddress;
use App\ValueObjects\Iso8601DateTime;
use App\Enums\UserStatus;
use App\Enums\UserRole;
use InvalidArgumentException;

final class UserDataTest extends UnitTestCase
{
    public function test_from_creates_data_with_value_objects(): void
    {
        $data = UserData::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $this->assertSame('John Doe', $data->name);
        $this->assertInstanceOf(EmailAddress::class, $data->email);
        $this->assertSame(UserStatus::ACTIVE, $data->status);
        $this->assertSame(UserRole::ADMIN, $data->role);
    }
    
    public function test_from_throws_exception_on_invalid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);
        
        UserData::from([
            'name' => 'John Doe',
            'email' => 'not-an-email',
            'status' => 'active',
            'role' => 'admin',
        ]);
    }
    
    public function test_toArray_normalizes_value_objects(): void
    {
        $data = UserData::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'createdAt' => '2024-01-15T10:30:00+01:00',
        ]);

        $array = $data->toArray();

        $this->assertEquals([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'role' => 'admin',
            'createdAt' => '2024-01-15T10:30:00+01:00',
        ], $array);
    }
}
```

---

## 14. Organisation des dossiers

```
app/
├── Actions/
│   ├── Api/
│   │   └── Users/
│   │       ├── ListUsersAction.php
│   │       └── ShowUserAction.php
├── Data/
│   ├── UserData.php
│   ├── AddressData.php
│   └── Collections/
│       └── ProductDataCollection.php
├── Records/
│   └── UserRecord.php
├── ValueObjects/
│   ├── Uuid.php
│   ├── EmailAddress.php
│   ├── Iso8601DateTime.php
│   └── Url.php
├── Enums/
│   ├── UserStatus.php
│   └── UserRole.php
└── Services/
    └── UserService.php
```

---

## 15. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | ✅ DOIT étendre `AbstractData` |
| **Usage** | ✅ Réponses API uniquement |
| **Propriétés** | `public readonly` |
| **Nommage** | `camelCase` |
| **Types recommandés** | Value Objects, Enums, Data, Collections typées |
| **Types à éviter** | `int`, `string`, `float` (déconseillés mais acceptés) |
| **`array` brut** | ❌ **STRICTEMENT INTERDIT** |
| **`TypedCollection` générique** | ❌ **STRICTEMENT INTERDIT** |
| **Records** | ❌ **STRICTEMENT INTERDIT** |
| **Logique métier** | ❌ INTERDITE |
| **Hydratation** | `from()`, `fromJson()` (automatique) |
| **Normalisation JSON** | Automatique en `camelCase` |
| **Tests unitaires** | ✅ Oui |

---

## 16. Règle d'or

> **Une Data DTO est un contrat explicite entre le serveur et ses clients. Elle utilise des concepts métier (Value Objects, Enums) plutôt que des types primitifs pour être auto-documentée et type-safe.**
>
> **⚠️ Les Data sont EXCLUSIVEMENT pour les réponses API. Pour la communication interne, utilisez les Records.**
>
> **⚠️ `array` brut, `TypedCollection` générique et `Record` sont STRICTEMENT INTERDITS dans les Data.**
>
> **⚠️ Nous conseillons d'éviter les types primitifs, mais nous ne les interdisons pas dogmatiquement. L'important est la clarté du contrat.**

```php
// ✅ La Data parfaite
final class PerfectData extends AbstractData
{
    public function __construct(
        // REQUIRED PARAMETERS FIRST
        public readonly UserId $id,                    // Value Object
        public readonly PersonName $name,              // Value Object
        public readonly EmailAddress $email,           // Value Object
        public readonly UserRole $role,                // Enum
        // OPTIONAL PARAMETERS AFTER
        public readonly ?Iso8601DateTime $createdAt = null,  // Value Object
        public readonly ProductDataCollection $products = new ProductDataCollection,  // Collection typée
    ) {}
}

// ⚠️ Acceptable mais moins explicite
final class SimpleData extends AbstractData
{
    public function __construct(
        public readonly int $id,      // Déconseillé mais accepté
        public readonly string $name, // Déconseillé mais accepté
    ) {}
}

// Utilisation dans une Action
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowUserRecord $request */
        $userRecord = $this->userRepository->find($request->user_id);
        
        if ($userRecord === null) {
            abort(404, 'User not found');
        }
        
        // Transformation Record → Data
        $userData = PerfectData::from([
            'id' => $userRecord->user_id,
            'name' => $userRecord->user_name,
            'email' => $userRecord->user_email,
            'role' => $userRecord->user_role,
            'createdAt' => $userRecord->created_at,
            'products' => $userRecord->products,
        ]);
        
        return ResponseFactory::json($userData);
    }
}

// Test unitaire
final class PerfectDataTest extends UnitTestCase
{
    public function test_from_hydrates_value_objects_automatically(): void
    {
        $data = PerfectData::from([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
            'createdAt' => '2024-01-15T10:30:00+01:00',
        ]);
        
        $this->assertInstanceOf(UserId::class, $data->id);
        $this->assertInstanceOf(PersonName::class, $data->name);
        $this->assertInstanceOf(EmailAddress::class, $data->email);
        $this->assertInstanceOf(UserRole::class, $data->role);
        $this->assertInstanceOf(Iso8601DateTime::class, $data->createdAt);
    }
    
    public function test_toArray_normalizes_to_scalars(): void
    {
        $data = PerfectData::from([
            'id' => '123e4567-e89b-12d3-a456-426614174000',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
            'createdAt' => '2024-01-15T10:30:00+01:00',
        ]);
        
        $array = $data->toArray();
        
        $this->assertIsString($array['id']);
        $this->assertIsString($array['name']);
        $this->assertIsString($array['email']);
        $this->assertIsString($array['role']);
        $this->assertIsString($array['createdAt']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}$/',
            $array['createdAt']
        );
    }
}
```