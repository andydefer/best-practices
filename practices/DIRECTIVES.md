# Principe d'usage des Directives (Version mise à jour)

## 1. Définition

Une **Directive** est un composant qui encapsule la logique d'une **commande CLI unique**. Elle reçoit des arguments et options typés, orchestre des Services/Tasks, et retourne un code de sortie via l'énumération `ExitCode`.

**⚠️ Une Directive a une signature unique. Elle ne peut pas être réutilisée pour plusieurs commandes différentes.**

**⚠️ Une Directive ne dépend JAMAIS directement de l'output ou de l'input. Elle utilise les méthodes du `AbstractDirective`.**

```
CLI → Kernel → Directive → Services/Tasks → ExitCode
```

```php
// Directive simple
final class HelloDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'hello {name?}';
    }

    public function getDescription(): string
    {
        return 'Dit bonjour à quelqu\'un';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('salut');
        $aliases->add('bonjour');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->info("Hello, {$name}!");
        return ExitCode::SUCCESS;
    }
}
```

---

## 2. Les classes fondamentales : AbstractDirective

### 2.1. AbstractDirective

La classe abstraite que **toute Directive doit étendre** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive;

use AndyDefer\Directive\Collections\ParameterCollection;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Contracts\DirectiveInterface;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;
    protected ParameterCollection $options;

    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
        protected ?LaravelBootstrapper $laravelBootstrapper = null,
    ) {
        $this->arguments = new ParameterCollection;
        $this->options = new ParameterCollection;
    }

    public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection;
    }

    public function shouldBootLaravel(): bool
    {
        return false;
    }

    public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped();
    }

    public function getLaravel(): ?object
    {
        return $this->laravelBootstrapper?->getApplication();
    }

    public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): self
    {
        $this->laravelBootstrapper = $bootstrapper;
        return $this;
    }

    public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);
        if ($value === null || $value === true || $value === false) {
            return null;
        }
        return $value;
    }

    public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function option(string $key): bool|string|null
    {
        return $this->options->get($key);
    }

    public function hasOption(string $key): bool
    {
        return $this->options->has($key);
    }

    abstract public function getSignature(): string;
    abstract public function getDescription(): string;
    abstract public function execute(): ExitCode;
}
```

### 2.2. Méthodes d'affichage (via AbstractDirective)

| Méthode | Description | Couleur |
|---------|-------------|---------|
| `line(string $message)` | Message simple | Aucune |
| `info(string $message)` | Information | Vert |
| `error(string $message)` | Erreur | Rouge |
| `warn(string $message)` | Avertissement | Jaune |
| `ask(string $question)` | Question utilisateur | - |
| `confirm(string $question)` | Confirmation (booléen) | - |
| `table(StringTypedCollection $headers, RowCollection $rows)` | Tableau | - |

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **Une Directive est dédiée à UNE SEULE commande. On ne peut pas réutiliser la même Directive pour plusieurs commandes différentes.**

```php
// ✅ BON - Directive dédiée à une commande
final class UserCreateDirective extends AbstractDirective
{
    // Utilisée uniquement pour user:create
}

// ❌ MAUVAIS - Directive réutilisée pour plusieurs commandes
final class UserDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        // Logique différente selon un argument ?
    }
}
```

### 3.1. Pourquoi une Directive par commande ?

| Raison | Explication |
|--------|-------------|
| **SRP** | Chaque commande a sa propre logique |
| **Évolution** | Modification d'une commande sans impacter les autres |
| **Visibilité** | `UserCreateDirective` dit clairement ce qu'il fait |

---

## 4. Format des signatures (⚠️ RÈGLE STRICTE)

> **⚠️ Les signatures doivent respecter un format strict pour garantir la cohérence du code.**

### 4.1. Règles fondamentales

| Règle | Explication |
|-------|-------------|
| **Délimiteurs autorisés** | Seuls `-` (tiret) est autorisé comme séparateur |
| **Caractères autorisés** | Lettres (a-z, A-Z) et chiffres (0-9) |
| **Premier caractère** | Doit être une lettre (pas un chiffre ni un délimiteur) |
| **Pas de délimiteurs consécutifs** | `user--list` est interdit |
| **Pas de délimiteur final** | `user-` est interdit |
| **Pas de délimiteur initial** | `-list` est interdit |

### 4.2. ✅ Exemples valides

| Signature | Explication |
|-----------|-------------|
| `user-list` | Utilisation du délimiteur `-` |
| `cache-clear` | Utilisation du délimiteur `-` |
| `api-user-profile` | Plusieurs délimiteurs `-` |
| `user-v2` | Les chiffres sont autorisés dans une partie |

### 4.3. ❌ Exemples invalides

| Signature | Raison |
|-----------|--------|
| `user:list` | Caractère `:` interdit |
| `create@user` | Caractère `@` interdit |
| `create_user` | Underscore `_` interdit |
| `user-` | Délimiteur final interdit |
| `-list` | Délimiteur initial interdit |
| `user--list` | Délimiteurs consécutifs interdits |
| `123-user` | Premier caractère est un chiffre |

### 4.4. Arguments et options

```php
// Arguments requis
public function getSignature(): string 
{
    return 'user-create {name} {email}';
}

// Arguments optionnels
public function getSignature(): string 
{
    return 'user-create {name?} {email?}';
}

// Options avec valeurs
public function getSignature(): string 
{
    return 'user-create {name} {--role=}';
}

// Flags (options booléennes)
public function getSignature(): string 
{
    return 'user-create {--admin} {--force}';
}

// Option avec valeur par défaut
public function getSignature(): string 
{
    return 'user-create {--role=user}';
}

// Tout mélanger
public function getSignature(): string 
{
    return 'user-create {name} {email} {--role=} {--admin} {--force}';
}
```

---

## 5. Alias (⚠️ RÈGLE IMPORTANTE)

> **Un alias est un nom alternatif permettant d'exécuter une directive sans utiliser sa signature originale.**

### 5.1. Définition des alias

```php
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

public function getAliases(): StringTypedCollection
{
    $aliases = new StringTypedCollection();
    $aliases->add('salut');
    $aliases->add('bonjour');
    $aliases->add('hi');
    return $aliases;
}
```

### 5.2. Utilité des alias

| Situation | Exemple | Bénéfice |
|-----------|---------|----------|
| **Commandes longues** | `user-create` → `uc` | Gain de temps |
| **Raccourcis fréquents** | `cache-clear` → `cc` | Productivité |
| **Multi-langues** | `hello` → `salut`, `bonjour` | Accessibilité |
| **Migration progressive** | Ancien nom → Nouveau nom | Rétrocompatibilité |

### 5.3. Priorité de résolution

Le système recherche une directive dans cet ordre :

```
1. Signature originale (exact match)
   ↓ (non trouvée)
2. Alias (match dans la liste des alias)
   ↓ (non trouvé)
3. Erreur "Directive not found"
```

### 5.4. Exemple concret

```php
final class CacheDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'cache-clear';
    }
    
    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('cc');
        $aliases->add('clear-cache');
        return $aliases;
    }
}
```

| Commande | Résolution |
|----------|------------|
| `./vendor/bin/directive cache-clear` | ✅ Signature originale |
| `./vendor/bin/directive cc` | ✅ Alias |
| `./vendor/bin/directive clear-cache` | ✅ Alias |
| `./vendor/bin/directive cache-clear` | ❌ Erreur (pas un alias) |

---

## 6. Charger Laravel optionnellement

> **⚠️ Par défaut, les directives s'exécutent sans charger Laravel pour des performances optimales.**

### 6.1. Activer Laravel

```php
final class UserListDirective extends AbstractDirective
{
    public function shouldBootLaravel(): bool
    {
        return true; // ← Active Laravel pour cette directive
    }

    public function execute(): ExitCode
    {
        if (!$this->hasLaravel()) {
            $this->error('Laravel is not available!');
            return ExitCode::FAILURE;
        }
        
        $users = User::all(); // Eloquent fonctionne !
        
        return ExitCode::SUCCESS;
    }
}
```

### 6.2. Vérifier Laravel

```php
public function execute(): ExitCode
{
    if ($this->hasLaravel()) {
        $version = $this->getLaravel()->version();
        $this->info("Laravel version: {$version}");
    }
    
    return ExitCode::SUCCESS;
}
```

### 6.3. Performance

Seules les directives qui demandent explicitement Laravel via `shouldBootLaravel()` déclenchent le bootstrap. Les autres directives restent ultra-rapides !

---

## 7. ExitCode - Codes de retour

```php
use AndyDefer\Directive\Enums\ExitCode;

// Retourner un code de succès
return ExitCode::SUCCESS;           // 0

// Retourner un code d'échec générique
return ExitCode::FAILURE;           // 1

// Directive non trouvée
return ExitCode::NOT_FOUND;         // 3

// Argument invalide
return ExitCode::INVALID_ARGUMENT;  // 4
```

### 7.1. Vérification

```php
if ($exitCode->isSuccess()) { ... }
if ($exitCode->isFailure()) { ... }
if ($exitCode->isNotFound()) { ... }
if ($exitCode->isInvalidArgument()) { ... }
```

---

## 8. Définition d'une Directive complète

```php
<?php

declare(strict_types=1);

namespace App\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Collections\RowCollection;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Records\Collections\Utility\StringTypedCollection;

final class UserCreateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-create {name} {email} {--role=user} {--admin} {--notify}';
    }

    public function getDescription(): string
    {
        return 'Create a new user account';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('uc');
        $aliases->add('create-user');
        return $aliases;
    }

    public function shouldBootLaravel(): bool
    {
        return true; // Besoin de la base de données
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        
        if ($name === null || $email === null) {
            $this->error('Name and email are required');
            return ExitCode::INVALID_ARGUMENT;
        }
        
        if (!$this->hasLaravel()) {
            $this->error('Database not available');
            return ExitCode::FAILURE;
        }
        
        $role = $this->option('role');
        $isAdmin = $this->hasOption('admin');
        $notify = $this->hasOption('notify');
        
        $this->info("📝 Creating user: {$name}");
        $this->line("  • Email: {$email}");
        $this->line("  • Role: {$role}");
        
        if ($isAdmin) {
            $this->warn("  • ⚠️ Administrator account!");
        }
        
        if (!$this->confirm("Confirm user creation?")) {
            $this->error("❌ Creation cancelled");
            return ExitCode::FAILURE;
        }
        
        // Logique métier ici...
        
        $this->table(
            new StringTypedCollection(['Field', 'Value']),
            (new RowCollection())->add(new RowCollection(['Name', $name], ['Email', $email]))
        );
        
        $this->info("✅ User {$name} created successfully!");
        
        if ($notify) {
            $this->info("📧 Notification sent to {$email}");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

---

## 9. Commandes système intégrées

| Commande | Description |
|----------|-------------|
| `./vendor/bin/directive --list` ou `-l` | Liste toutes les directives disponibles |
| `./vendor/bin/directive --help` ou `-h` | Affiche l'aide générale |
| `./vendor/bin/directive --version` ou `-v` | Affiche la version |

---

## 10. Règle : Pas de tests unitaires pour les Directives (⚠️ RÈGLE IMPORTANTE)

> **⚠️ On n'écrit JAMAIS de tests unitaires pour les Directives. Les Directives sont testées exclusivement via des tests d'intégration car elles dépendent de l'input/output.**

### 10.1. Pourquoi ?

| Raison | Explication |
|--------|-------------|
| **Dépendance I/O** | Les Directives dépendent de l'interaction utilisateur |
| **Dépendance à Laravel** | Certaines directives peuvent avoir besoin de Laravel |
| **Test d'intégration suffisant** | Les appels CLI réels testent le comportement complet |

### 10.2. Récapitulatif des types de tests

| Composant | Tests unitaires | Tests d'intégration |
|-----------|----------------|---------------------|
| **Directive** | ❌ Jamais | ✅ Toujours |
| **Service** | ✅ Oui | ❌ Rarement |
| **Task** | ✅ Oui | ❌ Rarement |

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractDirective` |
| **Signature** | Format strict (tirets, lettres, chiffres) |
| **Arguments** | Positionnels, requis ou optionnels |
| **Options** | Avec `--nom` ou `--nom=valeur` |
| **Alias** | Collection de noms alternatifs |
| **Laravel** | Activable via `shouldBootLaravel()` |
| **Retour** | `ExitCode::SUCCESS` ou `ExitCode::FAILURE` |
| **Route unique** | Une Directive = une commande |
| **Tests unitaires** | ❌ Jamais (uniquement tests d'intégration) |

---

## 12. Règle d'or

> **Une Directive fait une chose : répondre à une commande CLI avec un code de sortie. Elle reçoit des arguments et options typés, orchestre, et retourne un ExitCode via les méthodes d'AbstractDirective. Pas de tests unitaires, uniquement des tests d'intégration.**

```php
// La Directive parfaite
final class PerfectDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'perfect {name}';
    }

    public function getDescription(): string
    {
        return 'Une directive parfaite';
    }

    public function getAliases(): StringTypedCollection
    {
        $aliases = new StringTypedCollection();
        $aliases->add('p');
        return $aliases;
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->info("Hello, {$name}!");
        return ExitCode::SUCCESS;
    }
}
```