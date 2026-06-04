# Principe d'usage des Directives (Version finale)

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
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Records\DirectiveBlueprintRecord;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use AndyDefer\Directive\Services\LaravelBootstrapper;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Abstract base class for all CLI directives.
 *
 * This class provides the foundation for creating CLI commands with:
 * - Argument and option management
 * - User interaction methods (ask, confirm, line, info, error, warn)
 * - Table display capabilities
 * - Optional Laravel bootstrapping
 *
 * @author Andy Defer
 */
abstract class AbstractDirective implements DirectiveInterface
{
    protected ParameterCollection $arguments;
    protected ParameterCollection $options;
    protected ?LaravelBootstrapper $laravelBootstrapper = null;

    public function __construct(
        protected readonly DirectiveInteractionService $interaction,
    ) {
        $this->arguments = new ParameterCollection();
        $this->options = new ParameterCollection();
    }

    /**
     * Returns the blueprint record for this directive.
     */
    final public function getBlueprint(): DirectiveBlueprintRecord
    {
        return new DirectiveBlueprintRecord(
            class: static::class,
            signature: $this->getSignature(),
            description: $this->getDescription(),
        );
    }

    /**
     * Returns the aliases for this directive.
     */
    public function getAliases(): StringTypedCollection
    {
        return new StringTypedCollection();
    }

    /**
     * Determines whether Laravel should be bootstrapped before executing this directive.
     */
    public function shouldBootLaravel(): bool
    {
        return false;
    }

    /**
     * Checks if Laravel has been bootstrapped and is available.
     */
    final public function hasLaravel(): bool
    {
        return $this->laravelBootstrapper !== null && $this->laravelBootstrapper->isBootstrapped();
    }

    /**
     * Returns the Laravel application instance if available.
     */
    final public function getLaravel(): ?object
    {
        return $this->laravelBootstrapper?->getApplication();
    }

    /**
     * Sets the Laravel bootstrapper instance.
     */
    final public function setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper): self
    {
        $this->laravelBootstrapper = $bootstrapper;
        return $this;
    }

    /**
     * Sets the interaction service instance.
     */
    final public function setInteraction(DirectiveInteractionService $interaction): self
    {
        $this->interaction = $interaction;
        return $this;
    }

    // ==================== Argument Management ====================

    final public function setArguments(ParameterCollection $arguments): self
    {
        $this->arguments = $arguments;
        return $this;
    }

    final public function argument(string $key): ?string
    {
        $value = $this->arguments->get($key);
        if ($value === null || $value === true || $value === false || $value === '') {
            return null;
        }
        return $value;
    }

    final public function hasArgument(string $key): bool
    {
        $value = $this->arguments->get($key);
        return $value !== null && $value !== '' && $value !== true && $value !== false;
    }

    // ==================== Option Management ====================

    final public function setOptions(ParameterCollection $options): self
    {
        $this->options = $options;
        return $this;
    }

    final public function option(string $key): bool|string|null
    {
        $value = $this->options->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        return $value;
    }

    final public function hasOption(string $key): bool
    {
        $value = $this->options->get($key);
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        return $value !== '';
    }

    // ==================== Display Methods ====================

    final public function line(string $message): void
    {
        $this->interaction->line($message);
    }

    final public function info(string $message): void
    {
        $this->interaction->info($message);
    }

    final public function error(string $message): void
    {
        $this->interaction->error($message);
    }

    final public function warn(string $message): void
    {
        $this->interaction->warn($message);
    }

    // ==================== User Interaction Methods ====================

    final public function ask(string $question): string
    {
        return $this->interaction->ask($question);
    }

    final public function confirm(string $question): bool
    {
        return $this->interaction->confirm($question);
    }

    // ==================== Table Display Methods ====================

    final public function table(StringTypedCollection $headers, RowCollection $rows): void
    {
        $this->interaction->table($headers, $rows);
    }

    final public function newLine(): void
    {
        $this->interaction->newLine();
    }

    final public function separator(string $character = '-', int $length = 80): void
    {
        $this->interaction->separator($character, $length);
    }

    /**
     * Execute the directive's main logic.
     */
    abstract public function execute(): ExitCode;
}
```

### 2.2. Méthodes d'affichage et d'interaction (via AbstractDirective)

| Méthode | Description | Couleur/Type | Paramètres |
|---------|-------------|--------------|------------|
| `line(string $message)` | Message simple | Aucune | `$message` - Le message à afficher |
| `info(string $message)` | Information | Vert | `$message` - Le message à afficher |
| `error(string $message)` | Erreur | Rouge | `$message` - Le message à afficher |
| `warn(string $message)` | Avertissement | Jaune | `$message` - Le message à afficher |
| `newLine()` | Ligne vide | - | Aucun |
| `separator(string $character = '-', int $length = 80)` | Ligne de séparation | - | `$character` - Caractère de séparation<br>`$length` - Longueur de la ligne |
| `ask(string $question)` | Question utilisateur | - | `$question` - La question à poser |
| `confirm(string $question)` | Confirmation (booléen) | - | `$question` - La question de confirmation |
| `table(StringTypedCollection $headers, RowCollection $rows)` | Affichage tableau formaté | - | `$headers` - En-têtes du tableau<br>`$rows` - Lignes du tableau |

### 2.3. Méthodes de gestion des arguments et options

| Méthode | Description | Retour |
|---------|-------------|--------|
| `argument(string $key)` | Récupère la valeur d'un argument | `string\|null` |
| `hasArgument(string $key)` | Vérifie si un argument existe avec valeur non-vide | `bool` |
| `option(string $key)` | Récupère la valeur d'une option | `bool\|string\|null` |
| `hasOption(string $key)` | Vérifie si une option existe avec valeur non-vide | `bool` |

### 2.4. Méthodes de configuration Laravel

| Méthode | Description | Retour |
|---------|-------------|--------|
| `shouldBootLaravel()` | Indique si Laravel doit être bootstrappé | `bool` |
| `hasLaravel()` | Vérifie si Laravel est disponible | `bool` |
| `getLaravel()` | Récupère l'instance Laravel | `object\|null` |

### 2.5. Méthodes de chaînage (Fluent interface)

| Méthode | Description | Retour |
|---------|-------------|--------|
| `setArguments(ParameterCollection $arguments)` | Définit les arguments | `self` |
| `setOptions(ParameterCollection $options)` | Définit les options | `self` |
| `setLaravelBootstrapper(?LaravelBootstrapper $bootstrapper)` | Définit le bootstrapper Laravel | `self` |
| `setInteraction(DirectiveInteractionService $interaction)` | Définit le service d'interaction | `self` |

### 2.6. Exemple d'utilisation complète

```php
final class UserListDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user-list {--role=} {--active}';
    }

    public function getDescription(): string
    {
        return 'List all users with optional filters';
    }

    public function execute(): ExitCode
    {
        $this->separator('=', 50);
        $this->info('🚀 Démarrage de la commande');
        
        $role = $this->option('role');
        $active = $this->hasOption('active');
        
        if (!$role && $this->confirm('Voulez-vous filtrer par rôle ?')) {
            $role = $this->ask('Quel rôle ?');
        }
        
        $headers = new StringTypedCollection(['ID', 'Nom', 'Rôle']);
        $rows = new RowCollection();
        $rows->add(['1', 'John Doe', $role ?? 'user']);
        $this->table($headers, $rows);
        
        $this->newLine();
        $this->warn('⚠️ Attention : certains utilisateurs sont inactifs');
        
        return ExitCode::SUCCESS;
    }
}
```

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **Une Directive est dédiée à UNE SEULE commande. On ne peut pas réutiliser la même Directive pour plusieurs commandes différentes.**

```php
// ✅ BON - Directive dédiée à une commande
final class UserCreateDirective extends AbstractDirective
{
    // Utilisée uniquement pour user-create
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
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

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
| `./vendor/bin/directive cache-c` | ❌ Erreur (pas un alias) |

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
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

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
        return true;
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
            (new RowCollection())->add(['Name', $name], ['Email', $email])
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

> **⚠️ On n'écrit JAMAIS de tests unitaires pour les Directives. Les Directives sont testées exclusivement via des tests d'intégration.**

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

## 11. Tests d'intégration des Directives avec InteractsWithDirectives (⚠️ RÈGLE OBLIGATOIRE)

> **⚠️ Pour tester les directives, on utilise OBLIGATOIREMENT le trait `InteractsWithDirectives` qui fournit un environnement de test isolé sans dépendance au système de fichiers réel.**

### 11.1. Pourquoi InteractsWithDirectives ?

| Problème | Solution apportée par le trait |
|----------|-------------------------------|
| Dépendance au filesystem | Création d'un environnement temporaire isolé |
| Dépendance à l'I/O réel | Capture et assertion sur l'output |
| Bootstrap Laravel | Création d'une structure Laravel minimale |
| Enregistrement des directives | Registry dédié pour les tests |
| Nettoyage après test | Suppression automatique des fichiers temporaires |

### 11.2. Structure de base d'un test de directive

```php
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Testing\InteractsWithDirectives;
use App\Directives\UserCreateDirective;
use PHPUnit\Framework\TestCase;

final class UserCreateDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting();
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_directive_creates_user_successfully(): void
    {
        $directive = new UserCreateDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective(
            UserCreateDirective::class,
            ['John Doe', 'john@example.com', '--role=admin']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('User created', $response->output);
    }
}
```

### 11.3. Méthodes essentielles du trait InteractsWithDirectives

| Méthode | Description | Quand l'utiliser |
|---------|-------------|------------------|
| `initDirectiveTesting(bool $bootLaravel = false)` | Initialise l'environnement de test | Dans `setUp()` |
| `destroyDirectiveTesting()` | Nettoie l'environnement de test | Dans `tearDown()` |
| `registerDirective(AbstractDirective $directive)` | Enregistre une directive pour le test | Pour chaque directive à tester |
| `registerDirectives(array $directives)` | Enregistre plusieurs directives | Pour les tests d'intégration multiple |
| `clearRegisteredDirectives()` | Vide le registre des directives | Entre deux tests si besoin |
| `runDirective(string $className, array $arguments = [])` | Exécute une directive et retourne la réponse | Dans l'act du test |
| `createTestDirective(string $signature, callable $execute)` | Crée une directive temporaire avec closure | Pour les tests rapides |

### 11.4. La classe DirectiveResponseRecord

```php
$response = $this->runDirective('calculator', ['add', '5', '3']);

// Propriétés disponibles
$response->exitCode   // ExitCode enum (SUCCESS, FAILURE, etc.)
$response->output     // string (tout ce qui a été affiché)

// Assertions possibles
$this->assertSame(ExitCode::SUCCESS, $response->exitCode);
$this->assertStringContainsString('8', $response->output);
$this->assertStringNotContainsString('error', $response->output);
$this->assertMatchesRegularExpression('/\d+/', $response->output);
```

### 11.5. Exemples concrets

#### 11.5.1. Tester une directive avec arguments et options

```php
public function test_directive_with_arguments_and_options(): void
{
    $directive = new MyDirective($this->interaction);
    $this->registerDirective($directive);

    $response = $this->runDirective(
        MyDirective::class,
        ['arg1', 'arg2', '--verbose', '--format=json']
    );

    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    $this->assertStringContainsString('Processing in verbose mode', $response->output);
}
```

#### 11.5.2. Créer une directive temporaire avec closure

```php
public function test_temporary_directive(): void
{
    $executed = false;

    $this->createTestDirective('temp-command', function ($d) use (&$executed) {
        $executed = true;
        $d->info('Temporary command executed');
        return ExitCode::SUCCESS;
    });

    $response = $this->runDirective('temp-command');

    $this->assertTrue($executed);
    $this->assertStringContainsString('Temporary command executed', $response->output);
}
```

#### 11.5.3. Tester avec Laravel bootstrappé

```php
protected function setUp(): void
{
    parent::setUp();
    $this->initDirectiveTesting(bootLaravel: true);
}

public function test_directive_needs_database(): void
{
    $directive = new UserListDirective($this->interaction);
    $this->registerDirective($directive);

    $response = $this->runDirective(UserListDirective::class, ['--active']);

    $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
    // Les requêtes Eloquent fonctionnent !
}
```

#### 11.5.4. Tester les cas d'erreur

```php
public function test_directive_returns_invalid_argument_when_missing_parameter(): void
{
    $directive = new CalculatorDirective($this->interaction);
    $this->registerDirective($directive);

    $response = $this->runDirective('calculator', ['add']);

    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('Not enough arguments', $response->output);
}

public function test_directive_handles_division_by_zero(): void
{
    $directive = new CalculatorDirective($this->interaction);
    $this->registerDirective($directive);

    $response = $this->runDirective('calculator', ['div', '10', '0']);

    $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
    $this->assertStringContainsString('Division by zero', $response->output);
}
```

### 11.6. Ce que fait initDirectiveTesting() automatiquement

| Action | Description |
|--------|-------------|
| Création d'un dossier temporaire | `sys_get_temp_dir() . '/directive_test_xxxxx'` |
| Changement du répertoire courant | `chdir()` vers le dossier temporaire |
| (Optionnel) Structure Laravel minimale | `bootstrap/`, `config/`, `storage/` |
| Container IoC | Instance de `Illuminate\Container\Container` |
| Services de directive | Parser, Hydrator, Renderer, etc. |
| Registry de test | `TestDirectiveRegistry` pour stocker les directives |

### 11.7. Nettoyage automatique

```php
protected function tearDown(): void
{
    $this->destroyDirectiveTesting(); // ← Nettoie TOUT
    parent::tearDown();
}
```

`destroyDirectiveTesting()` effectue :
- Vidage du registre des directives
- Suppression récursive du dossier temporaire
- Restauration du répertoire original (`chdir` back)
- Nettoyage des instances Laravel

### 11.8. Récapitulatif des bonnes pratiques

| Bonne pratique | Pourquoi |
|----------------|----------|
| Toujours appeler `initDirectiveTesting()` dans `setUp()` | Initialise l'environnement isolé |
| Toujours appeler `destroyDirectiveTesting()` dans `tearDown()` | Nettoie les fichiers temporaires |
| Utiliser `registerDirective()` avant `runDirective()` | La directive doit être enregistrée |
| Tester à la fois succès et échec | Valider tous les chemins d'exécution |
| Capturer et asserter sur l'output | Vérifier les messages utilisateur |
| Tester les ExitCode appropriés | `SUCCESS`, `FAILURE`, `INVALID_ARGUMENT` |

### 11.9. Exemple complet avec toutes les assertions

```php
#[AllowMockObjectsWithoutExpectations]
final class UserCreateDirectiveTest extends TestCase
{
    use InteractsWithDirectives;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initDirectiveTesting(bootLaravel: true);
    }

    protected function tearDown(): void
    {
        $this->destroyDirectiveTesting();
        parent::tearDown();
    }

    public function test_user_creation_success(): void
    {
        $directive = new UserCreateDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective(
            UserCreateDirective::class,
            ['Jane Doe', 'jane@example.com', '--role=editor', '--notify']
        );

        $this->assertSame(ExitCode::SUCCESS, $response->exitCode);
        $this->assertStringContainsString('✅ User Jane Doe created', $response->output);
        $this->assertStringContainsString('📧 Notification sent', $response->output);
    }

    public function test_user_creation_fails_when_email_invalid(): void
    {
        $directive = new UserCreateDirective($this->interaction);
        $this->registerDirective($directive);

        $response = $this->runDirective(
            UserCreateDirective::class,
            ['Jane Doe', 'invalid-email']
        );

        $this->assertSame(ExitCode::INVALID_ARGUMENT, $response->exitCode);
        $this->assertStringContainsString('Invalid email', $response->output);
    }
}
```

---

## 12. Récapitulatif des contraintes

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
| **Tests unitaires** | ❌ Jamais |
| **Tests d'intégration** | ✅ Obligatoire via `InteractsWithDirectives` |

---

## 13. Règle d'or

> **Une Directive fait une chose : répondre à une commande CLI avec un code de sortie. Elle reçoit des arguments et options typés, orchestre, et retourne un ExitCode via les méthodes d'AbstractDirective. Pas de tests unitaires, uniquement des tests d'intégration avec InteractsWithDirectives.**

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
---