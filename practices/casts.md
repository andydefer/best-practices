# Principe d'usage des Casts (Version finale)

## 1. Définition

Un **Cast** est un composant qui transforme les données entre leur représentation en base de données et leur représentation dans l'application. Il garantit la cohérence des types et l'intégrité des données à la lecture et à l'écriture.

```
Cast → Transformation (DB ↔ Application) → Cohérence des types → Intégrité des données
```

```php
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): float
    {
        return round((int) $value / 100, 2);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): int
    {
        return (int) round($value * 100);
    }
}
```

---

## 2. Problématique à laquelle les Casts répondent

| Problème | Solution |
|----------|----------|
| **Données mal typées** | Les casts garantissent le type retourné |
| **Incohérence de format** | Les casts centralisent la transformation |
| **Logique métier dans les contrôleurs** | Les casts encapsulent la transformation |
| **Erreurs d'arrondi monétaire** | Les casts standardisent le traitement |

---

## 3. Types de Casts

### 3.1 Casts de valeurs primitives

| Cast | DB | Application | Cas d'usage |
|------|----|--------------|--------------|
| `MoneyCast` | `int` (cents) | `float` (euros) | Prix, montants |
| `PercentageCast` | `int` (base 10000) | `float` (pourcentage) | Taux, commissions |
| `BooleanCast` | `int` (0/1) | `bool` | Flags, statuts |

### 3.2 Casts de structures complexes

| Cast | DB | Application | Cas d'usage |
|------|----|--------------|--------------|
| `JsonCast` | `string` (JSON) | `array` | Métadonnées, configurations |
| `DataCast` | `string` (JSON) | `object` (DTO) | Données structurées |
| `CollectionCast` | `string` (JSON) | `Collection` | Listes typées |

### 3.3 Casts de valeurs métier

| Cast | DB | Application | Cas d'usage |
|------|----|--------------|--------------|
| `UuidCast` | `string` (UUID) | `Uuid` (objet) | Identifiants |
| `DateCast` | `string` (Y-m-d) | `Carbon` | Dates sans heure |
| `PhoneCast` | `string` | `PhoneNumber` | Numéros formatés |

### 3.4 Localisation des Casts

```
src/Casts/
├── MoneyCast.php
├── JsonCast.php
├── PercentageCast.php
├── UuidCast.php
├── PhoneCast.php
├── Data/
│   ├── MetadataCast.php
│   └── SettingsCast.php
└── Contracts/
    ├── CastableInterface.php
    └── HasValueObjectCast.php
```

---

## 4. Convention de nommage (⚠️ STRICT)

### 4.1 Nom du fichier

> **Le fichier DOIT se terminer par `Cast.php`.**

```php
// ✅ BON
MoneyCast.php
JsonCast.php
PercentageCast.php

// ❌ MAUVAIS
Money.php
MoneyCaster.php
MoneyTransformer.php
```

### 4.2 Nom de la classe

> **La classe DOIT avoir le même nom que le fichier et être `final`.**

```php
// ✅ BON
final class MoneyCast implements CastsAttributes { ... }

// ❌ MAUVAIS
class Money extends Cast { ... }           // ❌ Mauvais nom
class MoneyCaster { ... }                  // ❌ Pas final, mauvais nom
```

---

## 5. Implémentation (⚠️ RÈGLES IMPORTANTES)

### 5.1 Toujours implémenter `CastsAttributes`

```php
// ✅ BON
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

final class MoneyCast implements CastsAttributes { ... }

// ❌ MAUVAIS
final class MoneyCast { ... }  // ❌ N'implémente pas l'interface
```

### 5.2 Méthode `get` : de la DB vers l'application

```php
public function get(Model $model, string $key, mixed $value, array $attributes): mixed
{
    // ⚠️ $value est la valeur stockée en DB (string, int, null, etc.)
    // ⚠️ Retourner la valeur transformée pour l'application
    
    // ✅ BON - Transformation explicite
    if ($value === null) {
        return null;
    }
    return round((int) $value / 100, 2);
    
    // ❌ MAUVAIS - Pas de gestion des nulls
    return round((int) $value / 100, 2);  // Erreur si $value === null
}
```

### 5.3 Méthode `set` : de l'application vers la DB

```php
public function set(Model $model, string $key, mixed $value, array $attributes): mixed
{
    // ⚠️ $value est la valeur depuis l'application
    // ⚠️ Retourner la valeur à stocker en DB
    
    // ✅ BON - Transformation explicite
    if ($value === null) {
        return null;
    }
    return (int) round($value * 100);
    
    // ❌ MAUVAIS - Pas de validation
    return (int) round($value * 100);  // Exception si $value n'est pas numérique
}
```

### 5.4 Gérer les valeurs `null`

```php
// ✅ BON - Null safe
public function get(Model $model, string $key, mixed $value, array $attributes): ?float
{
    return $value === null ? null : round((int) $value / 100, 2);
}

// ✅ BON - Avec valeur par défaut
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    return $value === null ? 0.0 : round((int) $value / 100, 2);
}

// ❌ MAUVAIS - Erreur sur null
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    return round((int) $value / 100, 2);  // TypeError si $value === null
}
```

### 5.5 Documenter le type de retour

```php
// ✅ BON - Docblock explicite
/**
 * Convertit les centimes stockés en DB en euros.
 *
 * @param Model $model
 * @param string $key
 * @param int|null $value  // Valeur en centimes depuis la DB
 * @param array $attributes
 * @return float|null      // Valeur en euros pour l'application
 */
public function get(Model $model, string $key, mixed $value, array $attributes): ?float
{
    return $value === null ? null : round($value / 100, 2);
}
```

### 5.6 Utiliser le typing PHP (⚠️ STRICT)

```php
// ✅ BON - Typage fort
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null ? null : round((int) $value / 100, 2);
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null ? null : (int) round($value * 100);
    }
}

// ❌ MAUVAIS - Pas de type de retour
public function get(Model $model, string $key, $value, array $attributes)
{
    return round($value / 100, 2);
}
```

---

## 6. Règles par type de Cast

### 6.1 MoneyCast : valeurs monétaires

```php
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }
        
        // ⚠️ TOUJOURS arrondir à 2 décimales
        return round((int) $value / 100, 2);
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }
        
        // ⚠️ TOUJOURS arrondir avant conversion
        return (int) round($value * 100);
    }
}
```

**Règles spécifiques :**
- Stockage : `int` (centimes)
- Application : `float` (euros) ou `?float` si nullable
- TOUJOURS arrondir à 2 décimales
- Ne JAMAIS stocker de `float` en DB (risque d'arrondi)

### 6.2 JsonCast : structures JSON

```php
final class JsonCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                return is_array($decoded) ? $decoded : null;
            } catch (JsonException $e) {
                // ⚠️ Log l'erreur mais ne plante pas
                logger()->error('Failed to decode JSON', ['error' => $e->getMessage()]);
                return null;
            }
        }
        
        return null;
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_string($value) && $this->isValidJson($value)) {
            return $value;
        }
        
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
    
    private function isValidJson(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
```

**Règles spécifiques :**
- Stockage : `string` (JSON) ou `null`
- Application : `array` ou `?array` si nullable
- TOUJOURS gérer les erreurs de décodage
- NE JAMAIS retourner `null` pour un JSON valide vide (retourner `[]`)

### 6.3 PercentageCast : pourcentages

```php
final class PercentageCast implements CastsAttributes
{
    private const PRECISION = 10000; // 4 décimales
    
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        if ($value === null) {
            return null;
        }
        
        return round((int) $value / self::PRECISION, 4);
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }
        
        return (int) round($value * self::PRECISION);
    }
}
```

**Règles spécifiques :**
- Stockage : `int` (base 10000 pour 4 décimales)
- Application : `float` (pourcentage 0-100)
- PRÉCISION : 4 décimales minimum
- VALIDER : valeur entre 0 et 100

### 6.4 EnumCast : énumérations

```php
final class UserRoleCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?UserRole
    {
        if ($value === null) {
            return null;
        }
        
        return UserRole::tryFrom($value) ?? UserRole::DEFAULT;
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if ($value instanceof UserRole) {
            return $value->value;
        }
        
        return $value;
    }
}
```

**Règles spécifiques :**
- Stockage : `string` (enum value)
- Application : `Enum` (BackedEnum)
- TOUJOURS utiliser `tryFrom()` pour éviter les erreurs
- FOURNIR une valeur par défaut

---

## 7. Déclaration dans les Models (⚠️ STRICT)

### 7.1 Déclaration simple

```php
final class Product extends Model
{
    // ✅ BON - Cast explicite
    protected $casts = [
        'price' => MoneyCast::class,
        'metadata' => JsonCast::class,
        'discount_rate' => PercentageCast::class,
        'is_active' => 'boolean',
        'role' => UserRoleCast::class,
    ];
    
    // ❌ MAUVAIS - Cast string (pas de classe)
    protected $casts = [
        'metadata' => 'array',  // Préférer JsonCast::class
        'price' => 'float',     // Préférer MoneyCast::class
    ];
}
```

### 7.2 Déclaration avec valeurs par défaut

```php
final class Product extends Model
{
    protected $casts = [
        'price' => MoneyCast::class,
        'metadata' => JsonCast::class,
    ];
    
    // ✅ BON - Valeur par défaut pour les casts nullables
    protected $attributes = [
        'metadata' => '{}',  // JSON vide au lieu de null
        'price' => 0,        // 0 centime au lieu de null
    ];
}
```

### 7.3 Casts conditionnels

```php
final class Order extends Model
{
    protected function casts(): array
    {
        return [
            'total' => MoneyCast::class,
            'items' => JsonCast::class,
            'status' => OrderStatusCast::class,
        ];
    }
    
    // ✅ BON - Cast conditionnel selon le type
    public function getMetadataAttribute(): ?array
    {
        if ($this->type === 'simple') {
            return null;
        }
        
        return $this->cast->get($this, 'metadata', $this->metadata, []);
    }
}
```

---

## 8. Tests des Casts (⚠️ RÈGLES IMPORTANTES)

> **Les casts se testent en UNIT test (sans base de données) car ce sont des transformations pures.**

### 8.1 Structure du test

```php
final class MoneyCastTest extends TestCase
{
    private MoneyCast $cast;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new MoneyCast();
    }
    
    public function test_get_converts_cents_to_euros_with_two_decimals(): void
    {
        // Arrange
        $model = $this->createMock(Model::class);
        
        // Act
        $result = $this->cast->get($model, 'amount', 1234, []);
        
        // Assert
        $this->assertSame(12.34, $result);
    }
}
```

### 8.2 Cas à tester

| Méthode | Cas à tester |
|---------|---------------|
| **get** | Valeur normale, null, valeur limite, valeur négative, erreur de conversion |
| **set** | Valeur normale, null, arrondi, valeur limite, valeur négative |

```php
// ✅ BON - Tests complets
public function test_get_returns_null_when_value_is_null(): void { ... }
public function test_get_converts_cents_to_euros(): void { ... }
public function test_get_handles_negative_amounts(): void { ... }
public function test_get_handles_zero(): void { ... }
public function test_set_converts_euros_to_cents(): void { ... }
public function test_set_rounds_cents_correctly(): void { ... }
```

---

## 9. Pièges à éviter (⚠️ CRITIQUE)

### 9.1 Mutation de données

```php
// ❌ MAUVAIS - Modifie les données
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    $model->updated_at = now();  // ❌ Ne JAMAIS modifier le modèle
    return round($value / 100, 2);
}
```

### 9.2 Dépendances externes

```php
// ❌ MAUVAIS - Dépendance externe
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    $config = app('config')->get('money.decimals');  // ❌ Éviter les services
    return round($value / 100, $config);
}

// ✅ BON - Constante locale
private const DECIMALS = 2;

public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    return round($value / 100, self::DECIMALS);
}
```

### 9.3 Logique métier dans le cast

```php
// ❌ MAUVAIS - Logique métier
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    if ($model->user->isVip()) {  // ❌ Logique métier
        return round($value / 100, 2) * 0.9;
    }
    return round($value / 100, 2);
}

// ✅ BON - Transformation pure
public function get(Model $model, string $key, mixed $value, array $attributes): float
{
    return round($value / 100, 2);  // Transformation uniquement
}
```

### 9.4 Perte de précision

```php
// ❌ MAUVAIS - Perte de précision
public function set(Model $model, string $key, mixed $value, array $attributes): int
{
    return $value * 100;  // Problème avec les floats
}

// ✅ BON - Arrondi explicite
public function set(Model $model, string $key, mixed $value, array $attributes): int
{
    return (int) round($value * 100);
}
```

---

## 10. Bonnes pratiques

| Pratique | Pourquoi |
|----------|----------|
| **Cast final** | Empêche l'héritage non désiré |
| **Gérer les nulls** | Évite les erreurs en base |
| **Documenter** | Facilite la compréhension |
| **Tester** | Garantit le comportement |
| **Transformation pure** | Pas de logique métier |
| **Type retour explicite** | PHP 7.4+ le permet |

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom fichier** | `{Type}Cast.php` |
| **Nom classe** | `{Type}Cast` + `final` |
| **Interface** | `CastsAttributes` |
| **Gestion null** | ✅ Obligatoire |
| **Type retour** | ✅ Explicite |
| **Logique métier** | ❌ Interdite |
| **Dépendances** | ❌ Éviter |
| **Tests** | Unit (sans DB) |
| **Documentation** | ✅ Docblock |

---

## 12. Règle d'or

> **Un cast transforme, il ne calcule pas. Il est pur, testable et sans dépendances. Si tu as besoin de logique métier ou de services, utilise un Accessor ou un Service dédié.**

```php
// Le Cast parfait
final class MoneyCast implements CastsAttributes
{
    private const DECIMALS = 2;
    private const MULTIPLIER = 100;
    
    /**
     * Convertit les centimes stockés en DB en euros.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?float
    {
        return $value === null 
            ? null 
            : round((int) $value / self::MULTIPLIER, self::DECIMALS);
    }
    
    /**
     * Convertit les euros en centimes pour le stockage en DB.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        return $value === null 
            ? null 
            : (int) round($value * self::MULTIPLIER);
    }
}
```