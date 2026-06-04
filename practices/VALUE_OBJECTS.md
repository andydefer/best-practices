# Principe d'usage des Value Objects

## 1. Définition

Un **Value Object (VO)** est une structure de données **immuable**, **auto-validante** et **sans identité propre** qui représente un concept métier avec son propre comportement.

```
Value Object → Concept métier → Validation OBLIGATOIRE → Pas d'identité → Immutable
```

```php
use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;

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
    
    public function isGmail(): bool
    {
        return $this->getDomain() === 'gmail.com';
    }
}

// Utilisation
$email = EmailAddress::from('john@example.com');
echo $email->value;       // 'john@example.com'
echo $email->getDomain(); // 'example.com'
```

---

## 2. Problématique à laquelle les Value Objects répondent

| Problème | Solution |
|----------|----------|
| **Types primitifs ambigus** | `string $email` ? `int $age` ? | Un VO explicite (`EmailAddress`, `Age`) |
| **Validation dispersée** | La même validation répétée partout | Validation centralisée dans le VO |
| **Comportement métier éparpillé** | `if ($age >= 18)` partout | Méthode `canVote()` encapsulée |
| **Données invalides** | On peut créer un email invalide | Validation immédiate à la construction |
| **Couplage aux primitifs** | Changement de règle = modification partout | Une seule modification dans le VO |

---

## 3. Value Object vs Record vs Data

| Aspect | Value Object | Record | Data DTO |
|--------|--------------|--------|----------|
| **Usage principal** | Concepts métier | Communication interne | Réponses HTTP |
| **Logique métier** | ✅ Peut contenir | ❌ Aucune | ❌ Transformation uniquement |
| **Validation** | ✅ OBLIGATOIRE | ❌ Optionnelle | ❌ Optionnelle |
| **Constructeur** | Public avec validation | Public | Public |
| **Peut contenir des Records** | ❌ Interdit | ✅ Oui | ✅ Oui |
| **Nommage** | `EmailAddress`, `Money` | `UserRecord` | `UserData` |
| **Propriétés** | `camelCase` | `snake_case` | `camelCase` |

---

## 4. Règle fondamentale (⚠️ ABSOLUE)

> **Un Value Object DOIT être immuable, auto-validant, et sans identité propre. Il représente un concept métier, pas un transporteur de données.**

```php
// ✅ BON - Value Object parfait
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
}

// ❌ MAUVAIS - Value Object sans validation
final class BadEmail extends AbstractValueObject
{
    public function __construct(public readonly string $value) {}  // ❌ Pas de validation
}

// ❌ MAUVAIS - Value Object avec état mutable
final class BadAge extends AbstractValueObject
{
    private int $value;
    
    public function __construct(int $value)
    {
        $this->value = $value;  // ❌ Pas de readonly
    }
    
    public function increment(): void  // ❌ Méthode qui modifie l'état
    {
        $this->value++;
    }
}
```

---

## 5. Les classes fondamentales

### 5.1 AbstractValueObject

La classe abstraite que **tout Value Object doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\DomainStructures\Abstracts;

use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\DomainStructures\Normalizers\NormalizerChain;
use AndyDefer\DomainStructures\Traits\HasPropertiesAccess;
use AndyDefer\DomainStructures\Traits\Hydratable;
use InvalidArgumentException;
use UnitEnum;

/**
 * Abstract Value Object with automatic hydration via Hydratable trait.
 *
 * Children only need to:
 * 1. Define a public constructor with typed properties (validation inside constructor)
 * 2. Implement getValue()
 *
 * @example
 * final class EmailAddress extends AbstractValueObject
 * {
 *     public function __construct(public readonly string $value)
 *     {
 *         if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
 *             throw new InvalidArgumentException("Invalid email");
 *         }
 *     }
 *
 *     public function getValue(): string { return $this->value; }
 * }
 *
 * // Usage - all provided by Hydratable trait
 * $email = EmailAddress::from('user@example.com');
 * $email = EmailAddress::fromJson('"user@example.com"');
 * $collection = EmailAddress::collect(['a@b.com', 'c@d.com']);
 */
abstract class AbstractValueObject implements Transformable
{
    use HasPropertiesAccess, Hydratable;

    /**
     * Returns the raw value of the Value Object.
     * Can return scalar, enum, record, data, collection, or DataObject.
     */
    abstract public function getValue(): int|string|float|bool|null|UnitEnum|Transformable;

    /**
     * Checks if this value object is equal to another.
     */
    public function equals(self $other): bool
    {
        if (get_class($this) !== get_class($other)) {
            return false;
        }

        $thisValue = $this->getValue();
        $otherValue = $other->getValue();

        if (is_object($thisValue) && is_object($otherValue)) {
            if ($thisValue instanceof $otherValue) {
                return $thisValue == $otherValue;
            }

            return false;
        }

        return $thisValue === $otherValue;
    }

    public function __toString(): string
    {
        return json_encode(NormalizerChain::get()->normalize($this), JSON_THROW_ON_ERROR);
    }
}

```

### 5.2 Ce qu'offre AbstractValueObject

| Méthode | Description |
|---------|-------------|
| `from(mixed $source): static` | Hydrate un VO depuis une source (array, string, objet) |
| `fromJson(string $json): static` | Hydrate depuis une chaîne JSON |
| `collect(iterable $sources, string $collectionClass): TypedCollection` | Collection typée de VO |
| `getValue(): mixed` | Retourne la valeur brute (obligatoire) |
| `equals(self $other): bool` | Compare deux VO (obligatoire) |
| `toArray(): array` | Normalise le VO en tableau |
| `__toString(): string` | Convertit le VO en JSON |

---

## 6. Convention de nommage et casse

> **⚠️ Les Value Objects ont leurs propriétés en `camelCase`.**

| Type | Convention | Exemple |
|------|------------|---------|
| **Value Object** | `camelCase` | `$emailAddress`, `$domain`, `$amount` |
| **Record** | `snake_case` | `$user_id`, `$user_name` |
| **Data** | `camelCase` | `$userId`, $userName` |

```php
// ✅ BON - Value Object en camelCase
final class EmailAddress extends AbstractValueObject
{
    public function __construct(
        public readonly string $emailAddress,  // camelCase
    ) {}
}

// ✅ BON - Record en snake_case
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,  // snake_case
    ) {}
}
```

---

## 7. Créer son premier Value Object

### 7.1 Value Object simple

```php
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
    
    public function isGmail(): bool
    {
        return $this->getDomain() === 'gmail.com';
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}
```

### 7.2 Value Object avec plusieurs propriétés

```php
final class Money extends AbstractValueObject
{
    public function __construct(
        public readonly float $amount,
        public readonly Currency $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative');
        }
    }
    
    public function add(self $other): self
    {
        if (!$this->currency->equals($other->currency)) {
            throw new InvalidArgumentException('Cannot add different currencies');
        }
        
        return new self($this->amount + $other->amount, $this->currency);
    }
    
    public function format(): string
    {
        return $this->currency->getSymbol() . number_format($this->amount, 2);
    }
    
    public function getValue(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->getValue(),
        ];
    }

}
```

---

## 8. Hydratation : `from()` et `fromJson()`

### 8.1 `from(mixed $source): static`

```php
// Depuis une chaîne
$email = EmailAddress::from('john@example.com');

// Depuis un tableau
$money = Money::from(['amount' => 99.99, 'currency' => 'EUR']);

// Depuis un autre Value Object (retourne l'original)
$email2 = EmailAddress::from($email);
```

### 8.2 `fromJson(string $json): static`

```php
$json = '"john@example.com"';
$email = EmailAddress::fromJson($json);

$json = '{"amount":99.99,"currency":"EUR"}';
$money = Money::fromJson($json);
```

### 8.3 Règle : Toujours utiliser `from()`

```php
// ✅ BON - Hydratation via from()
$email = EmailAddress::from('john@example.com');

// ❌ MAUVAIS - Contourne l'hydratation
$email = new EmailAddress('john@example.com');
```

---

## 9. Collections de Value Objects

### 9.1 `collect()` - Collection simple

```php
$emails = EmailAddress::collect([
    'john@example.com',
    'jane@example.com',
    'bob@example.com',
]);

foreach ($emails as $email) {
    echo $email->value;
}
```

### 9.2 Collection personnalisée

```php
final class EmailCollection extends TypedCollection
{
    public function __construct()
    {
        parent::__construct(EmailAddress::class);
    }
    
    public function fromDomain(string $domain): self
    {
        return $this->filter(fn(EmailAddress $email) => $email->getDomain() === $domain);
    }
}

// Utilisation
$emails = EmailAddress::collect($sources, EmailCollection::class);
$gmailEmails = $emails->fromDomain('gmail.com');
```

---

## 10. Normalisation

Les Value Objects se normalisent automatiquement via `NormalizerChain` :

```php
$email = EmailAddress::from('john@example.com');
$normalized = NormalizerChain::get()->normalize($email);
// 'john@example.com' (string)

$money = Money::from(['amount' => 99.99, 'currency' => 'EUR']);
$normalized = NormalizerChain::get()->normalize($money);
// ['amount' => 99.99, 'currency' => 'EUR'] (array)
```

---

## 11. Égalité : `equals()`

```php
$email1 = EmailAddress::from('john@example.com');
$email2 = EmailAddress::from('john@example.com');
$email3 = EmailAddress::from('jane@example.com');

$email1->equals($email2); // true
$email1->equals($email3); // false
```

**Règles de comparaison :**
1. Les objets doivent être de la même classe
2. Pour les scalaires : comparaison stricte (`===`)
3. Pour les objets : comparaison via leur méthode `equals()`

---

## 12. Règles de validation

> **⚠️ La validation DOIT être effectuée dans le constructeur.**

```php
final class Age extends AbstractValueObject
{
    public function __construct(public readonly int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('Age cannot be negative');
        }
        
        if ($value > 150) {
            throw new InvalidArgumentException('Age cannot exceed 150');
        }
    }
    
    public function canVote(): bool
    {
        return $this->value >= 18;
    }
    
    public function getValue(): int
    {
        return $this->value;
    }
}
```

---

## 13. Ce qu'un Value Object NE peut PAS faire

| Interdiction | Pourquoi | Alternative |
|--------------|----------|-------------|
| **Contenir des Records** | Violation de couche | Transformer en VO |
| **Effets de bord** | Violation pureté | Déplacer dans Service |
| **Accès base de données** | Violation responsabilité | Service ou Repository |
| **Appels HTTP** | Violation pureté | Service |
| **Logs** | Violation pureté | Service avec LoggerInterface |
| **État mutable** | Violation immutabilité | `readonly` propriétés |
| **Constructeur privé** | `from()` nécessite constructeur public | Constructeur public |

```php
// ❌ MAUVAIS - Value Object avec effets de bord
final class BadEmail extends AbstractValueObject
{
    public function __construct(public readonly string $value)
    {
        Log::info("Email created");  // ❌ Effet de bord
    }
}

// ✅ BON - Service pour les effets de bord
final class EmailService
{
    public function __construct(private readonly LoggerInterface $logger) {}
    
    public function createEmail(string $value): EmailAddress
    {
        $this->logger->info("Email created");
        return EmailAddress::from($value);
    }
}
```

---

## 14. Exemples complets

### 14.1 EmailAddress

```php
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
    
    public function getLocalPart(): string
    {
        return explode('@', $this->value)[0];
    }
    
    public function isGmail(): bool
    {
        return $this->getDomain() === 'gmail.com';
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}
```

### 14.2 Iso8601DateTime

```php
final class Iso8601DateTime extends AbstractValueObject
{
    private const FORMAT = 'Y-m-d\TH:i:sP';
    
    public function __construct(public readonly string $value)
    {
        $date = DateTime::createFromFormat(self::FORMAT, $value);
        
        if (!$date || $date->format(self::FORMAT) !== $value) {
            throw new InvalidArgumentException("Invalid ISO 8601 datetime: {$value}");
        }
    }
    
    public function toDateTime(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::FORMAT, $this->value);
    }
    
    public function isAfter(self $other): bool
    {
        return $this->toDateTime() > $other->toDateTime();
    }
    
    public function isBefore(self $other): bool
    {
        return $this->toDateTime() < $other->toDateTime();
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}
```

### 14.3 Intégration dans un Record

```php
// Record (snake_case)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly EmailAddress $user_email,  // Value Object
        public readonly Iso8601DateTime $created_at,  // Value Object
    ) {}
}

// Hydratation automatique
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com',  // string → EmailAddress
    'created_at' => '2024-01-01T12:00:00+00:00',  // string → Iso8601DateTime
]);
```

---

## 15. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractValueObject` |
| **Constructeur** | Public avec validation |
| **Validation** | OBLIGATOIRE dans le constructeur |
| **Propriétés** | `public readonly` |
| **Nommage** | `camelCase` |
| **Méthodes obligatoires** | `getValue(): mixed`, `equals(self): bool` |
| **Effets de bord** | ❌ INTERDITS |
| **Accès DB/HTTP/Logs** | ❌ INTERDITS |
| **Records** | ❌ Ne peut PAS contenir de Records |
| **Hydratation** | `from()`, `fromJson()` (héritées) |

---

## 16. Règle d'or

> **Un Value Object est un concept métier immuable, auto-validant, sans identité propre. Il ne contient AUCUN effet de bord. Sa validation est dans le constructeur. Il expose ses valeurs via `getValue()` et compare via `equals()`.**
>
> **⚠️ Les propriétés sont en `camelCase`.**
> **⚠️ Jamais de Records dans un Value Object.**

```php
// ✅ Le Value Object parfait
final class PerfectValueObject extends AbstractValueObject
{
    public function __construct(
        public readonly string $value,  // camelCase
    ) {
        if (empty($value)) {
            throw new InvalidArgumentException('Value cannot be empty');
        }
    }
    
    public function getValue(): string
    {
        return $this->value;
    }
}

// Utilisation
$vo = PerfectValueObject::from('test');
echo $vo->value;  // 'test'

// Dans un Record (snake_case)
final class RecordWithVO extends AbstractRecord
{
    public function __construct(
        public readonly PerfectValueObject $vo_value,  // snake_case pour la propriété
    ) {}
}

$record = RecordWithVO::from([
    'vo_value' => 'test',  // string → PerfectValueObject
]);
```