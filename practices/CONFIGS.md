Voici la version finale du document de principes d'usage des Config, intégrant toutes les conventions et bonnes pratiques :

# Principe d'usage des Config (Version finale)

## Table des matières

1. [Définition et concepts](#1-définition-et-concepts)
2. [Pourquoi une Config POO ?](#2-pourquoi-une-config-poo-)
3. [AbstractConfig - Classe de base](#3-abstractconfig---classe-de-base)
4. [Règles fondamentales](#4-règles-fondamentales)
5. [Créer sa première Config](#5-créer-sa-première-config)
6. [Types de retour autorisés](#6-types-de-retour-autorisés)
7. [Méthodes utilitaires](#7-méthodes-utilitaires)
8. [Chargement depuis l'environnement](#8-chargement-depuis-lenvironnement)
9. [Cas d'utilisation](#9-cas-dutilisation)
10. [Exemples concrets](#10-exemples-concrets)
11. [Bonnes pratiques](#11-bonnes-pratiques)
12. [Récapitulatif](#12-récapitulatif)
13. [Règle d'or](#13-règle-dor)

---

## 1. Définition et concepts

Une **Config** est une classe fermée qui expose des valeurs de configuration via des méthodes. Elle est **immuable**, **sans état interne**, **sans constructeur paramétré** et **auto-documentée**.

```
Config → Classe fermée → Aucun état interne → Aucune propriété → Auto-documentée
```

### 1.1. Principes fondamentaux

| Principe | Description |
|----------|-------------|
| **Constructeur final** | Empêche l'instanciation avec paramètres |
| **Aucune propriété** | Interdiction formelle de toute propriété (même private) |
| **Aucun état interne** | La classe ne stocke rien entre les appels |
| **Méthodes immuables** | Chaque méthode retourne une valeur fixe ou calculée |
| **Auto-documentée** | Les noms de méthodes décrivent la configuration |
| **Testable** | Peut être mockée comme toute classe |

---

## 2. Pourquoi une Config POO ?

### 2.1. Le problème des approches traditionnelles

| Approche | Problème |
|----------|----------|
| `config('app.key')` | String magique, non typé, pas d'autocomplétion |
| `$_ENV['KEY']` | Non structuré, non typé, global |
| `array $config` | Tableau brut, aucune garantie de structure |

### 2.2. Ce que Config POO résout

| Problème | Solution |
|----------|----------|
| Clés magiques | Méthodes nommées (`host()`, `port()`) |
| Non typé | Types de retour explicites (`: string`, `: int`) |
| Pas d'autocomplétion | L'IDE connaît toutes les méthodes |
| Mutabilité | Classe sans état, méthodes immuables |

---

## 3. AbstractConfig - Classe de base

### 3.1. Code source

```php
<?php

declare(strict_types=1);

namespace AndyDefer\DomainStructures\Abstracts;

/**
 * Abstract base class for configuration classes.
 *
 * Forces child classes to have no constructor parameters.
 * All configuration values must be hardcoded in methods or loaded from environment.
 * Config classes MUST have NO properties - only methods.
 */
abstract class AbstractConfig
{
    /**
     * Final constructor prevents any parameters.
     */
    final public function __construct()
    {
        // No validation, no logic - just prevents parameters
    }
}
```

### 3.2. Caractéristiques

| Caractéristique | Description |
|-----------------|-------------|
| **Constructeur final** | Empêche l'instanciation avec paramètres |
| **Aucune propriété** | Pas de stockage d'état |
| **Aucune validation** | La validation se fait dans les Services |

---

## 4. Règles fondamentales (⚠️ STRICTES)

### 4.1. Aucune propriété

> **⚠️ Une Config n'a AUCUNE propriété. Pas de `private string $host`, pas de `private array $config`. RIEN. Uniquement des méthodes.**

```php
// ❌ MAUVAIS - Une Config avec des propriétés
final class BadConfig extends AbstractConfig
{
    private string $host;  // ❌ INTERDIT
    
    public function host(): string
    {
        return $this->host;  // ❌ Dépend d'un état interne
    }
}

// ✅ BON - Une Config sans propriétés
final class GoodConfig extends AbstractConfig
{
    public function host(): string
    {
        return getenv('DB_HOST') ?: 'localhost';
    }
}
```

### 4.2. Constructeur sans paramètres

> **⚠️ Le constructeur est final et ne prend aucun paramètre. Toute configuration doit être définie directement dans les méthodes ou lue depuis l'environnement.**

```php
// ❌ MAUVAIS - Tentative d'ajout de paramètres (impossible)
final class BadConfig extends AbstractConfig
{
    public function __construct(string $prefix)  // ❌ Impossible (constructeur final)
    {
        // ...
    }
}

// ✅ BON - Configuration via méthodes
final class GoodConfig extends AbstractConfig
{
    public function prefix(): string
    {
        return getenv('CONFIG_PREFIX') ?: 'APP_';
    }
    
    public function name(): string
    {
        return getenv($this->prefix() . 'NAME') ?: 'Application';
    }
}
```

### 4.3. Pas de logique métier ni validation

> **⚠️ Une Config ne contient ni logique métier, ni validation. Elle retourne des valeurs brutes. La validation appartient aux Services.**

```php
// ❌ MAUVAIS - Validation dans la Config
final class BadConfig extends AbstractConfig
{
    public function port(): int
    {
        $port = (int) (getenv('PORT') ?: 3306);
        if ($port <= 0 || $port > 65535) {
            throw new InvalidArgumentException('Invalid port');  // ❌ Validation
        }
        return $port;
    }
}

// ✅ BON - La Config retourne la valeur brute
final class GoodConfig extends AbstractConfig
{
    public function port(): int
    {
        return (int) (getenv('PORT') ?: 3306);
    }
}

// ✅ BON - Validation dans le Service
final class DatabaseService
{
    public function __construct(private readonly DatabaseConfig $config) {}
    
    public function getConnection(): PDO
    {
        $port = $this->config->port();
        if ($port <= 0 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port: {$port}");
        }
        return new PDO(...);
    }
}
```

### 4.4. Pas de tableaux bruts

> **⚠️ Une Config ne retourne JAMAIS de tableau brut. Utilisez TypedCollection, Record ou Value Object.**

```php
// ❌ MAUVAIS - Retourne un tableau brut
final class BadConfig extends AbstractConfig
{
    public function getValues(): array  // ❌ INTERDIT
    {
        return ['host' => 'localhost', 'port' => 3306];
    }
}

// ✅ BON - Retourne un Record
final class GoodConfig extends AbstractConfig
{
    public function connectionParameters(): DatabaseConnectionRecord
    {
        return new DatabaseConnectionRecord(
            host: $this->host(),
            port: $this->port(),
        );
    }
}
```

---

## 5. Créer sa première Config

### 5.1. Config simple

```php
<?php

declare(strict_types=1);

namespace App\Configs;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;

final class DatabaseConfig extends AbstractConfig
{
    public function driver(): string
    {
        return getenv('DB_DRIVER') ?: 'mysql';
    }
    
    public function host(): string
    {
        return getenv('DB_HOST') ?: 'localhost';
    }
    
    public function port(): int
    {
        return (int) (getenv('DB_PORT') ?: 3306);
    }
    
    public function database(): string
    {
        return getenv('DB_DATABASE') ?: 'my_app';
    }
    
    public function username(): string
    {
        return getenv('DB_USERNAME') ?: 'root';
    }
    
    public function password(): string
    {
        return getenv('DB_PASSWORD') ?: '';
    }
    
    public function charset(): string
    {
        return getenv('DB_CHARSET') ?: 'utf8mb4';
    }
}
```

### 5.2. Utilisation

```php
// ✅ Correct - pas de paramètres
$config = new DatabaseConfig();

// ❌ Erreur - le constructeur ne prend pas de paramètres
$config = new DatabaseConfig('mysql', 'localhost', 3306); // Impossible

// Utilisation des valeurs
echo $config->host();      // 'localhost'
echo $config->port();      // 3306
echo $config->database();  // 'my_app'
```

### 5.3. Injection dans un Service

```php
final class DatabaseConnectionService
{
    public function __construct(
        private readonly DatabaseConfig $config,
    ) {}
    
    public function getConnection(): PDO
    {
        return new PDO(
            $this->config->dsn(),
            $this->config->username(),
            $this->config->password()
        );
    }
}
```

---

## 6. Types de retour autorisés

### 6.1. Scalaires

```php
final class AppConfig extends AbstractConfig
{
    public function name(): string { return getenv('APP_NAME') ?: 'MyApp'; }
    public function env(): string { return getenv('APP_ENV') ?: 'production'; }
    public function debug(): bool { return getenv('APP_DEBUG') === 'true'; }
    public function port(): int { return (int) (getenv('APP_PORT') ?: 8080); }
    public function timeout(): ?int { return getenv('TIMEOUT') ? (int) getenv('TIMEOUT') : null; }
}
```

### 6.2. Enums

```php
enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}

final class LoggerConfig extends AbstractConfig
{
    public function level(): LogLevel
    {
        $level = getenv('LOG_LEVEL') ?: 'info';
        
        return match ($level) {
            'debug' => LogLevel::DEBUG,
            'info' => LogLevel::INFO,
            'warning' => LogLevel::WARNING,
            'error' => LogLevel::ERROR,
            default => LogLevel::INFO,
        };
    }
}
```

### 6.3. Value Objects (camelCase)

```php
final class EmailConfig extends AbstractConfig
{
    public function host(): string { return getenv('SMTP_HOST') ?: 'smtp.example.com'; }
    public function port(): int { return (int) (getenv('SMTP_PORT') ?: 587); }
    
    public function credentials(): SmtpCredentials
    {
        return new SmtpCredentials(
            username: getenv('SMTP_USER') ?: '',
            password: getenv('SMTP_PASSWORD') ?: '',
        );
    }
    
    public function from(): EmailAddress
    {
        return EmailAddress::from(['value' => getenv('MAIL_FROM') ?: 'noreply@example.com']);
    }
}
```

### 6.4. Records (snake_case)

```php
final class DatabaseConfig extends AbstractConfig
{
    public function host(): string { return getenv('DB_HOST') ?: 'localhost'; }
    public function port(): int { return (int) (getenv('DB_PORT') ?: 3306); }
    public function database(): string { return getenv('DB_DATABASE') ?: 'my_app'; }
    
    public function connectionParameters(): DatabaseConnectionRecord
    {
        return new DatabaseConnectionRecord(
            driver: 'mysql',
            host: $this->host(),
            port: $this->port(),
            database: $this->database(),
            username: getenv('DB_USERNAME') ?: 'root',
            password: getenv('DB_PASSWORD') ?: '',
            charset: 'utf8mb4',
        );
    }
}
```

### 6.5. TypedCollection

```php
final class ApiConfig extends AbstractConfig
{
    public function baseUrl(): string { return getenv('API_BASE_URL') ?: 'https://api.example.com'; }
    public function apiKey(): string { return getenv('API_KEY') ?: ''; }
    
    public function defaultHeaders(): HeaderCollection
    {
        $headers = new HeaderCollection();
        $headers->add(new HeaderRecord('Authorization', 'Bearer ' . $this->apiKey()));
        $headers->add(new HeaderRecord('Accept', 'application/json'));
        $headers->add(new HeaderRecord('Content-Type', 'application/json'));
        
        return $headers;
    }
}
```

---

## 7. Méthodes utilitaires

> **⚠️ Une Config peut avoir des méthodes utilitaires qui ne correspondent pas directement à une valeur de configuration, mais qui facilitent l'utilisation des valeurs.**

### 7.1. Méthodes de formatage

```php
final class DatabaseConfig extends AbstractConfig
{
    public function host(): string { return getenv('DB_HOST') ?: 'localhost'; }
    public function port(): int { return (int) (getenv('DB_PORT') ?: 3306); }
    public function database(): string { return getenv('DB_DATABASE') ?: 'my_app'; }
    
    public function dsn(): DsnRecord
    {
        return new DsnRecord(
            driver: 'mysql',
            host: $this->host(),
            port: $this->port(),
            database: $this->database(),
        );
    }
}
```

### 7.2. Méthodes de question

```php
final class AppConfig extends AbstractConfig
{
    public function env(): string { return getenv('APP_ENV') ?: 'local'; }
    public function debug(): bool { return getenv('APP_DEBUG') === 'true'; }
    
    public function isProduction(): bool
    {
        return $this->env() === 'production';
    }
    
    public function isLocal(): bool
    {
        return $this->env() === 'local';
    }
    
    public function shouldCache(): bool
    {
        return !$this->isLocal() && !$this->debug();
    }
}
```

### 7.3. Méthodes de transformation

```php
final class RedisConfig extends AbstractConfig
{
    public function host(): string { return getenv('REDIS_HOST') ?: 'localhost'; }
    public function port(): int { return (int) (getenv('REDIS_PORT') ?: 6379); }
    public function password(): ?string { return getenv('REDIS_PASSWORD') ?: null; }
    
    public function dsn(): RedisDsn
    {
        if ($this->password()) {
            return new RedisDsn(
                sprintf('redis://:%s@%s:%d', $this->password(), $this->host(), $this->port())
            );
        }
        
        return new RedisDsn(sprintf('redis://%s:%d', $this->host(), $this->port()));
    }
}
```

### 7.4. Règle pour les méthodes utilitaires

| Type de méthode utilitaire | Type de retour autorisé | Exemple |
|---------------------------|----------------------|---------|
| Formatage | Record, Value Object | `dsn(): DsnRecord` |
| Transformation | Record, Value Object, TypedCollection | `connectionParameters(): ConnectionRecord` |
| Question | Scalaire (bool, string, int) | `isProduction(): bool` |
| **Tableau brut** | ❌ **INTERDIT** | ~~`toArray(): array`~~ |
| **Logique métier complexe** | ❌ **INTERDIT** | ~~`calculateTotal()`~~ |
| **Effets de bord** | ❌ **INTERDIT** | ~~`saveToFile()`~~ |

---

## 8. Chargement depuis l'environnement

### 8.1. Valeurs par défaut explicites

```php
final class DatabaseConfig extends AbstractConfig
{
    // ✅ BON - Default explicite via opérateur ?:
    public function host(): string
    {
        return getenv('DB_HOST') ?: 'localhost';
    }
    
    // ❌ MAUVAIS - Default caché
    public function badHost(): string
    {
        $host = getenv('DB_HOST');
        if ($host === false) {
            $host = 'localhost';
        }
        return $host;
    }
}
```

### 8.2. Gestion des types

```php
final class DatabaseConfig extends AbstractConfig
{
    // string
    public function host(): string
    {
        return getenv('DB_HOST') ?: 'localhost';
    }
    
    // int (avec cast explicite)
    public function port(): int
    {
        return (int) (getenv('DB_PORT') ?: 3306);
    }
    
    // bool (comparaison stricte)
    public function strict(): bool
    {
        return getenv('DB_STRICT') === 'true';
    }
    
    // nullable
    public function password(): ?string
    {
        $password = getenv('DB_PASSWORD');
        return $password !== false ? $password : null;
    }
}
```

---

## 9. Cas d'utilisation

### 9.1. Configuration base de données

```php
final class DatabaseConfig extends AbstractConfig
{
    public function host(): string { return getenv('DB_HOST') ?: 'localhost'; }
    public function port(): int { return (int) (getenv('DB_PORT') ?: 3306); }
    public function database(): string { return getenv('DB_DATABASE') ?: 'app'; }
    public function username(): string { return getenv('DB_USERNAME') ?: 'root'; }
    public function password(): string { return getenv('DB_PASSWORD') ?: ''; }
    
    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s',
            $this->host(),
            $this->port(),
            $this->database()
        );
    }
}
```

### 9.2. Configuration API externe

```php
final class ApiConfig extends AbstractConfig
{
    public function baseUrl(): string
    {
        return getenv('API_BASE_URL') ?: 'https://api.example.com';
    }
    
    public function timeout(): int
    {
        return (int) (getenv('API_TIMEOUT') ?: 30);
    }
    
    public function retryAttempts(): int
    {
        return (int) (getenv('API_RETRY_ATTEMPTS') ?: 3);
    }
    
    public function apiKey(): string
    {
        return getenv('API_KEY') ?: '';
    }
}
```

---

## 10. Exemples concrets

### 10.1. Configuration complète d'application

```php
final class AppConfig extends AbstractConfig
{
    // Application
    public function name(): string { return getenv('APP_NAME') ?: 'MyApp'; }
    public function env(): string { return getenv('APP_ENV') ?: 'local'; }
    public function debug(): bool { return getenv('APP_DEBUG') === 'true'; }
    public function url(): string { return getenv('APP_URL') ?: 'http://localhost'; }
    public function timezone(): string { return getenv('APP_TIMEZONE') ?: 'UTC'; }
    
    // Database
    public function dbHost(): string { return getenv('DB_HOST') ?: 'localhost'; }
    public function dbPort(): int { return (int) (getenv('DB_PORT') ?: 3306); }
    public function dbName(): string { return getenv('DB_NAME') ?: 'app'; }
    public function dbUser(): string { return getenv('DB_USER') ?: 'root'; }
    public function dbPassword(): string { return getenv('DB_PASSWORD') ?: ''; }
    
    // Redis
    public function redisHost(): string { return getenv('REDIS_HOST') ?: 'localhost'; }
    public function redisPort(): int { return (int) (getenv('REDIS_PORT') ?: 6379); }
    
    // Méthodes utilitaires
    public function dbDsn(): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s', $this->dbHost(), $this->dbPort(), $this->dbName());
    }
    
    public function isProduction(): bool { return $this->env() === 'production'; }
    public function isLocal(): bool { return $this->env() === 'local'; }
    public function shouldCache(): bool { return !$this->isLocal() && !$this->debug(); }
}
```

### 10.2. Utilisation dans un Service

```php
final class DatabaseService
{
    public function __construct(
        private readonly AppConfig $config,
    ) {}
    
    public function getConnection(): PDO
    {
        return new PDO(
            $this->config->dbDsn(),
            $this->config->dbUser(),
            $this->config->dbPassword()
        );
    }
}
```

---

## 11. Bonnes pratiques

### 11.1. Nommage des méthodes

```php
// ✅ BON - Noms clairs et explicites
public function host(): string { ... }
public function port(): int { ... }
public function database(): string { ... }

// ❌ MAUVAIS - Noms vagues
public function get(): string { ... }
public function val(): int { ... }
```

### 11.2. Regrouper par domaine

```php
// ✅ BON - Config séparées par domaine
$dbConfig = new DatabaseConfig();
$cacheConfig = new CacheConfig();
$mailConfig = new MailConfig();

// ❌ MAUVAIS - Une seule config pour tout
$config = new AppConfig();  // 50 méthodes mélangées
```

### 11.3. Pas de logique métier

```php
// ❌ MAUVAIS - Logique métier dans la Config
final class BadConfig extends AbstractConfig
{
    public function calculateTotal(): float  // ❌ Logique métier
    {
        return $this->price() * $this->quantity();
    }
}

// ✅ BON - Logique métier dans le Service
final class GoodService
{
    public function calculateTotal(Config $config, OrderRecord $order): float
    {
        return $order->price * $order->quantity;
    }
}
```

---

## 12. Récapitulatif

### 12.1. Caractéristiques principales

| Caractéristique | Règle |
|-----------------|-------|
| **Constructeur** | `final public function __construct()` (sans paramètres) |
| **Propriétés** | ❌ **AUCUNE** propriété (même private) |
| **État interne** | ❌ INTERDIT |
| **Méthodes** | ✅ Oui (publiques uniquement) |
| **Validation** | ❌ INTERDITE (dans les Services) |
| **Tableaux bruts** | ❌ INTERDITS |
| **Logique métier** | ❌ INTERDITE |
| **Effets de bord** | ❌ INTERDITS |

### 12.2. Types de retour autorisés

| Type | Exemple |
|------|---------|
| Scalaire | `public function host(): string` |
| Enum | `public function level(): LogLevel` |
| Value Object (camelCase) | `public function credentials(): SmtpCredentials` |
| Record (snake_case) | `public function dsn(): DsnRecord` |
| TypedCollection | `public function headers(): HeaderCollection` |
| **Tableau brut** | ❌ **INTERDIT** |

### 12.3. Récapitulatif des contraintes

| Action | Autorisé |
|--------|----------|
| Retourner des scalaires | ✅ |
| Retourner des enums | ✅ |
| Retourner des Value Objects | ✅ |
| Retourner des Records | ✅ |
| Retourner des TypedCollection | ✅ |
| Lire l'environnement | ✅ |
| Avoir des méthodes utilitaires | ✅ |
| Avoir un constructeur avec paramètres | ❌ |
| Avoir des propriétés | ❌ |
| Être mutable | ❌ |
| Avoir des effets de bord | ❌ |
| Retourner des tableaux bruts | ❌ |
| Contenir de la logique métier | ❌ |
| Contenir de la validation | ❌ |

---

## 13. Règle d'or

> **Une Config est une classe sans état, sans propriété, sans constructeur paramétré. Elle expose des valeurs de configuration via des méthodes typées et auto-documentées.**
>
> **⚠️ Une Config ne contient :**
> - ❌ PAS de propriétés
> - ❌ PAS de logique métier
> - ❌ PAS de validation
> - ❌ PAS de tableaux bruts
> - ❌ PAS d'effets de bord
>
> **✅ Une Config peut :**
> - ✅ Lire les variables d'environnement
> - ✅ Retourner des scalaires, enums, Value Objects, Records, TypedCollection
> - ✅ Avoir des méthodes utilitaires (formatage, transformation, questions)
>
> **La validation et la logique métier appartiennent aux Services.**

```php
// ✅ La Config parfaite
final class PerfectConfig extends AbstractConfig
{
    // Méthodes simples retournant des scalaires
    public function host(): string 
    { 
        return getenv('HOST') ?: 'localhost'; 
    }
    
    public function port(): int 
    { 
        return (int) (getenv('PORT') ?: 8080); 
    }
    
    // Méthode utilitaire retournant un Record
    public function url(): UrlRecord 
    { 
        return new UrlRecord(
            scheme: 'http',
            host: $this->host(),
            port: $this->port()
        ); 
    }
    
    // Méthode utilitaire retournant un booléen
    public function isProduction(): bool
    {
        return getenv('APP_ENV') === 'production';
    }
}

// Utilisation
$config = new PerfectConfig();
echo $config->host();                    // 'localhost'
echo $config->url()->toString();         // 'http://localhost:8080'

// Service qui utilise la Config
final class PerfectService
{
    public function __construct(
        private readonly PerfectConfig $config,
    ) {}
    
    public function getConnection(): Connection
    {
        // Validation dans le Service, pas dans la Config
        $port = $this->config->port();
        if ($port <= 0 || $port > 65535) {
            throw new InvalidArgumentException("Invalid port: {$port}");
        }
        
        return new Connection($this->config->host(), $port);
    }
}

if ($config->isProduction()) {
    // Comportement spécifique à la production
}
```
---