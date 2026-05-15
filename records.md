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
| **Data** | Réponse API (Controllers) | ✅ **OBLIGATOIRE** |

```php
// ❌ MAUVAIS - Un Record ne répond pas à une API
final class UserController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $record = $this->userService->getUser($id);
        return response()->json($record);  // ← INTERDIT !
    }
}

// ✅ BON - Le Controller transforme le Record en Data via une Factory
final class UserController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $record = $this->userService->getUser($id);      // ← Record (interne)
        $data = $this->userDataFactory->fromRecord($record); // ← Data (API)
        return response()->json($data);                  // ✅ Autorisé
    }
}
```

**Règle d'or :** Si tu es dans un Controller et que tu veux retourner une réponse HTTP, tu utilises une **Data**, jamais un Record. Le Record s'arrête à la porte du Controller.

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
| `toData()` | La transformation en Data est le rôle des Factory, pas des Records |
| `save()` | Un Record ne doit jamais interagir avec la base de données |
| `collect()` | La création de collections de Records n'est pas une méthode générique |

### 5.5 Code complet de `AbstractRecord`

```php
<?php

declare(strict_types=1);

namespace App\Records;

use DateTimeInterface;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

/**
 * Abstract base class for all Records.
 *
 * PURE RECORD - No logic, just data structure.
 * 
 * Les Records sont utilisés UNIQUEMENT pour la communication interne
 * (Services, Repositories). Ils ne sont JAMAIS retournés comme réponses API.
 */
abstract class AbstractRecord
{
    /**
     * Convert the record to array (for database insertion).
     * 
     * La sérialisation est automatique à partir de toutes les propriétés publiques.
     * Les clés sont automatiquement converties en snake_case.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->normalizeArray($this->getProperties());
    }

    /**
     * Convert the record to JSON string (for HTTP client).
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Get all public properties of the record.
     *
     * @return array<string, mixed>
     */
    private function getProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = [];
        
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($this);
            
            // Convert camelCase → snake_case pour les clés
            $key = $this->camelToSnake($name);
            $properties[$key] = $value;
        }
        
        return $properties;
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Normalize an array recursively.
     *
     * @param array<string, mixed> $array
     * @return array<string, mixed>
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
     * Normalize a value to a serializable format.
     */
    private function normalizeValue(mixed $value): mixed
    {
        // Record → array (récursif)
        if ($value instanceof self) {
            return $value->toArray();
        }

        // Traversable (Collection, ArrayIterator, etc.) → array (récursif)
        if ($value instanceof \Traversable) {
            $result = [];
            foreach ($value as $k => $v) {
                $result[$k] = $this->normalizeValue($v);
            }
            return $result;
        }

        // Enum → valeur scalaire
        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        // DateTimeInterface → ISO 8601 (sans microsecondes)
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        // Tableau → récursif
        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        // Scalaire ou autre → retour brut
        return $value;
    }
}
```

### 5.6 Récapitulatif

| Ce que ça offre |
|-----------------|
| `toArray(): array` (sérialisation automatique + normalisation) |
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

## 7. Utilisation de `toArray()` et `toJson()`

| Méthode | Usage | Exemple |
|---------|-------|---------|
| `toArray()` | Insertion en base de données | `DB::table('users')->insert($record->toArray())` |
| `toJson()` | Envoi via HTTP Client (API externe) | `Http::post('https://api.external.com', $record->toJson())` |

```php
// ✅ Insertion en base de données
$record = new UserRecord(...);
DB::table('users')->insert($record->toArray());

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
│   ❌ Ne connaît pas les Data                                     │
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
│   ❌ Appelée UNIQUEMENT dans les Controllers                     │
└─────────────────────────────────────────────────────────────────┘
```

**⚠️ Rappel :** Les Records ne sont **JAMAIS** utilisés pour les réponses API. C'est le rôle des **Data class**. La Factory est le seul composant autorisé à transformer un Record en Data, et elle n'est appelée que dans les Controllers.

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

### 10.2 Factory qui transforme un Record en Data

```php
final class UserProfileDataFactory
{
    // ✅ BON - Factory respecte les 4 méthodes autorisées
    public function fromRecord(UserWithContextRecord $record): UserProfileData
    {
        return new UserProfileData(
            id: (string) $record->user->id,
            name: $record->user->name,
            timezone: $record->context->timezone,
            canEdit: $record->context->currentUserId === $record->user->id,
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
        
        // 3. Transformer le Record en Data via la Factory
        $responseData = $this->userUpdateDataFactory->fromRecord($result);
        
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

// ❌ JAMAIS - Pas de transformation en Data (c'est le rôle des Factory)
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
public function toData(): UserData { ... }  // c'est le rôle des Factory
```

---

## 15. Rappel fondamental

> **Un Record est un sac de données typé, sans aucune logique. Il remplace les tableaux bruts pour rendre le code plus sûr et plus lisible. La sérialisation est entièrement automatique.**

### 15.1 Séparation des responsabilités

| Composant | Rôle | Logique | Réponse API | Sérialisation |
|-----------|------|---------|-------------|---------------|
| **Record** | Communication interne (Services, Repositories) | ❌ Aucune | ❌ **INTERDIT** | Automatique |
| **Data** | Réponse API (Controllers) | ❌ Aucune | ✅ **OBLIGATOIRE** | Manuel (via Factory) |
| **Factory** | Transformation Record → Data | ✅ Logique de transformation | ❌ Non | N/A |
| **Service** | Logique métier | ✅ Oui | ❌ Non | N/A |

### 15.2 Schéma récapitulatif

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  REQUEST     │     │   RECORD     │     │   RECORD     │     │    DATA      │
│  (HTTP)      │ ──► │  (interne)   │ ──► │  (interne)   │ ──► │  (réponse)   │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
       │                    │                    │                    │
       ▼                    ▼                    ▼                    ▼
   Controller          Service             Repository            Controller
   (construit)         (logique)            (accès)            (transforme via
                                                               Factory → Data)
```

```
Record → Communication interne (Services, Repositories)
Data   → Réponse API (Controllers)
Factory → Transformation Record → Data (UNIQUEMENT dans les Controllers)
Service → Logique métier (ne connaît pas les Data)