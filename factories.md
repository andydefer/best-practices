# Principe d'usage des Factories (Version finale)

## 1. Définition

Une **Factory** est un composant dont la responsabilité unique est de créer des **Data Objects** (DTO) à partir de sources diverses (Models, Records).

```
Factory → crée uniquement → Data
```

---

## 2. Règles fondamentales

### 2.1 Nommage

```
{DataClassName}Factory
```

| Data | Factory |
|------|---------|
| `AppStatisticData` | `AppStatisticDataFactory` |
| `UserProfileData` | `UserProfileDataFactory` |
| `PaymentResponseData` | `PaymentResponseDataFactory` |

### 2.2 SRP strict

> **Une Factory ne crée qu'un seul type de Data.**

```php
// ✅ BON
final class UserDataFactory
{
    public function fromModel(User $user): UserData { ... }
    public function fromModels(iterable $users): array { ... }
    public function fromRecord(UserRecord $record): UserData { ... }
    public function fromRecords(iterable $records): array { ... }
}

// ❌ MAUVAIS - Violation du SRP
final class UserDataFactory
{
    public function fromModelToDoctorData(User $user): DoctorData { ... }
    public function fromModelToPatientData(User $user): PatientData { ... }
}
```

### 2.3 Localisation

```
app/Factories/{DataClassName}Factory.php
```

```
app/Factories/
├── UserDataFactory.php
├── AppStatisticDataFactory.php
├── PaymentResponseDataFactory.php
└── PlatformStatisticDataFactory.php
```

---

## 3. Quand créer une Factory ? 🤔

> **Règle :** Une Factory n'est pas systématique. Créez une Factory UNIQUEMENT si la Data remplit au moins un de ces critères :

| Critère | Exemple |
|---------|---------|
| **Plus de 3 propriétés** | `UserProfileData` (id, name, email, permissions, metadata...) |
| **Nécessite une transformation** | `Carbon` → `string` ISO, `Model` → `array`, calculs... |
| **Charge des relations Eloquent** | `$user->load('posts.comments')` |
| **Nécessite un contexte externe** | Auth user, timezone, permissions, configuration... |

### 3.1 Cas simple : Pas de Factory nécessaire

> **Pour les Data simples (≤ 3 propriétés, pas de logique, pas de transformation), utilisez directement le constructeur de la Data.**

```php
// ✅ BON - Data simple, constructeur direct
final class PingData extends AbstractData
{
    public function __construct(
        public readonly string $status = 'ok',
        public readonly string $version = '1.0',
    ) {}
}

// Dans le controller
return response()->json(new PingData());

// ✅ BON - Data simple avec 2 propriétés
final class DeleteResponseData extends AbstractData
{
    public function __construct(
        public readonly bool $success = true,
        public readonly string $message = 'Resource deleted successfully',
    ) {}
}

// Dans le controller
return response()->json(new DeleteResponseData(success: false, message: 'Not found'));
```

### 3.2 Cas complexe : Factory obligatoire

```php
// ❌ MAUVAIS - Data complexe sans Factory
final class UserProfileData extends AbstractData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $email,
        public readonly array $permissions,      // 4e propriété
        public readonly ?array $metadata,        // 5e propriété
        public readonly string $createdAt,       // 6e propriété
        public readonly ?string $updatedAt,      // 7e propriété
    ) {}
}

// ✅ BON - Utiliser une Factory
final class UserProfileDataFactory
{
    public function fromRecord(UserProfileRecord $record): UserProfileData
    {
        return new UserProfileData(
            id: (string) $record->user->id,
            name: $record->user->name,
            email: $record->user->email,
            permissions: $this->buildPermissions($record->user),
            metadata: $record->context->metadata,
            createdAt: $record->user->createdAt->toISOString(),
            updatedAt: $record->user->updatedAt?->toISOString(),
        );
    }
}
```

### 3.3 Récapitulatif

| Type de Data | Factory nécessaire ? |
|--------------|---------------------|
| `PingData` (2 propriétés, valeurs par défaut) | ❌ Non |
| `DeleteResponseData` (2 propriétés) | ❌ Non |
| `UserData` (3 propriétés, sans transformation) | ❌ Non (mais toléré) |
| `UserProfileData` (7 propriétés) | ✅ Oui |
| `DashboardStatisticData` (avec calculs) | ✅ Oui |
| `UserWithPermissionsData` (charge relations) | ✅ Oui |

> 💡 **Bon sens :** Si créer une Factory est plus lourd que la Data elle-même, passez votre chemin.

---

## 4. Les 4 méthodes conventionnelles

Une Factory expose **exactement 4 méthodes** :

| # | Méthode | Signature | Retour |
|---|---------|-----------|--------|
| 1 | `fromModel` | `fromModel(Model $model): Data` | `Data` |
| 2 | `fromModels` | `fromModels(iterable $models): array` | `array<Data>` |
| 3 | `fromRecord` | `fromRecord(Record $record): Data` | `Data` |
| 4 | `fromRecords` | `fromRecords(iterable $records): array` | `array<Data>` |

### 4.1 Règle absolue

> **Pas d'autres noms de méthodes. Ces 4 méthodes sont les SEULES autorisées.**

### 4.2 Pourquoi pas `fromModelWith` et `fromModelsWith` ?

> **Si tu as besoin d'un contexte supplémentaire pour un Model, tu crées d'abord un Record qui contient le Model ET le contexte.**

```php
// ❌ MAUVAIS - Ne pas faire
public function fromModelWith(User $user, UserContextRecord $context): UserData

// ✅ BON - Créer un Record qui contient le Record utilisateur et le contexte
final class UserWithContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly UserRecord $user,          // ✅ Record, pas Model
        public readonly UserContextRecord $context, // Le contexte
    ) {}
}

// Ensuite la factory utilise ce Record
public function fromRecord(UserWithContextRecord $record): UserData
{
    // Toutes les données sont dans le Record
    return new UserData(
        id: (string) $record->user->id,
        name: $record->user->name,
        timezone: $record->context->timezone,
        canEdit: $record->context->currentUserId === $record->user->id,
    );
}
```

### 4.3 Le principe : Tout est Record

| Besoin | Solution |
|--------|----------|
| Un Model seul | `UserRecord` |
| Un Model + contexte | `UserWithContextRecord` |
| Plusieurs Models | `UsersRecord` |
| Plusieurs Models + contexte | `UsersWithContextRecord` |

**Règle d'or :** Si tu as besoin de passer des paramètres supplémentaires, **crée un Record**.

---

## 5. Exemple complet

### 5.1 Créer les Records nécessaires

```php
// Record pour un utilisateur seul
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $lastLogin,
    ) {}
}

// Record pour le contexte
final class UserContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $currentUserId,
        public readonly string $timezone,
        public readonly bool $includePermissions,
    ) {}
}

// ✅ BON - Record qui combine UserRecord + Contexte
//    Contient des Records, pas des Models (respecte principe Records)
final class UserWithContextRecord extends AbstractRecord
{
    public function __construct(
        public readonly UserRecord $user,        // ✅ Record, pas Model
        public readonly UserContextRecord $context,
    ) {}
}
```

### 5.2 La Factory

```php
final class UserDataFactory
{
    // Cas simple : un Model
    public function fromModel(User $user): UserData
    {
        return new UserData(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
        );
    }
    
    // Cas simple : plusieurs Models
    public function fromModels(iterable $users): array
    {
        return array_map([$this, 'fromModel'], $users);
    }
    
    // Cas Record : un Record (avec ou sans contexte, tout est dans le Record)
    public function fromRecord(UserWithContextRecord $record): UserData
    {
        return new UserData(
            id: (string) $record->user->id,
            name: $record->user->name,
            email: $record->user->email,
            timezone: $record->context->timezone,
            canEdit: $record->context->currentUserId === $record->user->id,
            lastLogin: $record->user->lastLogin,
        );
    }
    
    // Cas Record : plusieurs Records
    public function fromRecords(iterable $records): array
    {
        return array_map([$this, 'fromRecord'], $records);
    }
}
```

### 5.3 Utilisation

```php
// 1. Créer le Record de contexte
$context = new UserContextRecord(
    currentUserId: auth()->id(),
    timezone: 'Europe/Paris',
    includePermissions: true,
);

// 2. Récupérer le Model ET créer un UserRecord (pas de Model dans le Record final)
$user = User::find(1);
$userRecord = new UserRecord(
    id: $user->id,
    name: $user->name,
    email: $user->email,
    lastLogin: $user->last_login?->toIsoString(),
);

// 3. Combiner UserRecord + Contexte dans un Record
$userWithContext = new UserWithContextRecord(
    user: $userRecord,      // ✅ Record, pas Model
    context: $context,
);

// 4. Factory transforme le Record en Data
$userData = $userDataFactory->fromRecord($userWithContext);

// 5. Réponse API
return response()->json($userData);
```

---

## 6. Flux complet

```
┌─────────────────────────────────────────────────────────────────┐
│                     1. CRÉER LES RECORDS                        │
│                                                                 │
│  $context = new UserContextRecord(...)                          │
│  $userRecord = new UserRecord(...)                              │
│  $userWithContext = new UserWithContextRecord(...)              │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     2. FACTORY (fromRecord)                     │
│                                                                 │
│  UserDataFactory::fromRecord(UserWithContextRecord $record)     │
│                                                                 │
│  → Toutes les données sont dans le Record                       │
│  → Pas besoin de paramètres supplémentaires                     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     3. DATA (réponse API)                       │
│                                                                 │
│  return response()->json($userData);                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 7. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{DataClassName}Factory` |
| **Responsabilité** | Un seul type de Data par Factory |
| **Méthodes** | 4 méthodes : `fromModel`, `fromModels`, `fromRecord`, `fromRecords` |
| **Pas de méthodes `With`** | Le contexte va dans un Record dédié |
| **Liberté** | Peut charger des relations, appeler des services |
| **Pas de logique métier** | La logique métier reste dans les Services |
| **Retour** | `Data` ou `array<Data>` (typage strict) |
| **Factory non systématique** | Data simple (≤3 props, sans transformation) → constructeur direct |

---

## 8. Checklist d'acceptance

- [ ] Le nom de la Factory correspond au Data qu'elle produit
- [ ] La Factory ne produit qu'un seul type de Data
- [ ] Méthodes uniquement parmi : `fromModel`, `fromModels`, `fromRecord`, `fromRecords`
- [ ] Pas de méthodes supplémentaires
- [ ] Pas de méthodes `With` (le contexte va dans les Records)
- [ ] Peut charger des relations Eloquent
- [ ] Peut appeler des services
- [ ] Pas d'effets de bord (update DB, logs, events)
- [ ] Retour typé (`Data` ou `array<Data>`)
- [ ] **Une Factory a été créée UNIQUEMENT si la Data le justifie (>3 props, transformation, relations, contexte)**
