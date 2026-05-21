# Directive : Système de commandes CLI découplé et testable

## 1. Définition

**Directive** est un système de commandes CLI qui remplace l'architecture rigide des commandes Laravel Artisan. Contrairement au système natif, Directive sépare **strictement** la logique métier de l'affichage et des interactions utilisateur.

```php
use AndyDefer\BestPractices\Directive\AbstractDirective;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;

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

    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('salut', 'bonjour', 'hi');
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->info("Hello, {$name}!");
        return ExitCode::SUCCESS;
    }
}
```

**Utilisation :**
```bash
php directive hello John      # Signature originale
php directive salut Marie     # Alias
php directive bonjour         # Alias
php directive hi              # Alias
# Tous produisent : Hello, Marie !
```

---

## 2. Architecture

### 2.1 Structure des fichiers

```
app/Directives/
├── UserDirective.php
├── BackupDirective.php
└── CacheDirective.php

// Fichier exécutable à la racine du projet
directive                    ← Script PHP copié automatiquement

// Configuration optionnelle
config/directive.php
```

### 2.2 Cycle de vie d'une directive

```
1. L'utilisateur exécute : php directive user:create John --role=admin
                                    │
2. DirectiveKernel lit $argv        │
                                    ▼
3. DirectiveExecutionService recherche la directive "user:create"
   → Vérifie la signature originale
   → Vérifie les alias
                                    │
4. DirectiveParserService parse la signature et les arguments
                                    │
5. DirectiveHydratorService instancie la directive
   (injecte DisplayMessageTask, AskQuestionTask, etc.)
                                    │
6. setArguments(['name' => 'John']) │
   setOptions(['role' => 'admin'])  │
                                    ▼
7. $directive->execute() exécute votre logique métier
                                    │
8. Retourne ExitCode (0 = succès)   │
                                    ▼
9. Le script termine avec le code de sortie approprié
```

### 2.3 Pourquoi remplacer les commandes Laravel Artisan ?

| Problème des commandes Laravel | Solution avec Directive |
|-------------------------------|------------------------|
| Dépendances magiques (`$this->input`, `$this->output`) | Dépendances injectées explicitement via Tasks |
| Impossible d'instancier `new MyCommand()` | `new MyDirective()` fonctionne parfaitement |
| Tests nécessitant de booter tout Laravel | Tests purs sans framework |
| I/O couplée (`$this->info()`, `$this->ask()`) | I/O déléguée à des Tasks mockables |
| Logique métier noyée dans `handle()` | `execute()` ne contient QUE la logique métier |

```php
// ❌ LARAVEL : Impossibilité de tester proprement
class CreateUserCommand extends Command
{
    protected $signature = 'user:create {name} {--admin}';
    
    public function handle()
    {
        $name = $this->argument('name');
        $this->info("Creating user: {$name}");
        $confirm = $this->confirm('Continue?');
        User::create(['name' => $name]);
        return 0;
    }
}

// ✅ DIRECTIVE : Testable sans framework
final class CreateUserDirective extends AbstractDirective
{
    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $this->info("Creating user: {$name}");
        
        if ($this->confirm('Continue?')) {
            $this->userService->create($name);
            return ExitCode::SUCCESS;
        }
        
        return ExitCode::FAILURE;
    }
}
```

---

## 3. Format des signatures

### 3.1 Arguments

```php
// Arguments requis
public function getSignature(): string 
{
    return 'user:create {name} {email}';
}

// Arguments optionnels (avec ?)
public function getSignature(): string 
{
    return 'user:create {name?} {email?}';
}
```

**Règles :**
- Les arguments sont positionnels : l'ordre dans la signature définit l'ordre de saisie
- Un argument optionnel peut être omis
- Les arguments non-fournis retournent `null` via `$this->argument()`

### 3.2 Options

```php
// Options avec valeurs
public function getSignature(): string 
{
    return 'user:create {name} {--role=}';
}

// Flags (options booléennes)
public function getSignature(): string 
{
    return 'user:create {--admin} {--force}';
}

// Option avec valeur par défaut
public function getSignature(): string 
{
    return 'user:create {--role=user}';
}

// Option sans valeur (flag simple)
public function getSignature(): string 
{
    return 'user:create {--active}';
}

// Tout mélanger
public function getSignature(): string 
{
    return 'user:create {name} {email} {--role=} {--admin} {--force}';
}
```

**Règles :**
- Les options peuvent être placées n'importe où dans la ligne de commande
- `--role=admin` : option avec valeur explicite
- `--admin` : flag booléen (présent = `true`, absent = `false`)
- `--role=` : option vide (vaut `true`)
- Les options avec valeur par défaut sont optionnelles par nature

---

## 4. Alias - Définition approfondie

### 4.1 Qu'est-ce qu'un alias ?

**Un alias est un nom alternatif permettant d'exécuter une directive sans utiliser sa signature originale.**

```php
use AndyDefer\BestPractices\Collections\Utility\StringTypedRecords;

public function getAliases(): StringTypedRecords
{
    // Définition simple
    return new StringTypedRecords('salut', 'bonjour');
    
    // Ou avec chaînage
    $aliases = new StringTypedRecords();
    $aliases->add('salut');
    $aliases->add('bonjour');
    $aliases->add('hi');
    return $aliases;
}
```

### 4.2 Utilité des alias

| Situation | Exemple | Bénéfice |
|-----------|---------|----------|
| **Commandes longues** | `user:create` → `uc` | Gain de temps |
| **Raccourcis fréquents** | `cache:clear` → `cc` | Productivité |
| **Multi-langues** | `hello` → `salut`, `bonjour`, `hi` | Accessibilité |
| **Versionnement** | `migration:run` → `migrate`, `mig` | Flexibilité |
| **Migration progressive** | Ancien nom → Nouveau nom | Rétrocompatibilité |

### 4.3 Exemple concret

```php
final class UserDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user:create';
    }
    
    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('uc', 'create-user', 'new-user');
    }
    
    public function execute(): ExitCode
    {
        $name = $this->argument('name');
        $this->info("Creating user: {$name}");
        return ExitCode::SUCCESS;
    }
}
```

**Toutes ces commandes fonctionnent :**
```bash
php directive user:create John     # Signature originale
php directive uc John              # Alias court
php directive create-user John     # Alias descriptif
php directive new-user John        # Alias alternatif
```

### 4.4 Priorité de résolution

Le système recherche une directive dans cet ordre :

```
1. Signature originale (exact match)
   ↓ (non trouvée)
2. Alias (match dans la liste des alias)
   ↓ (non trouvé)
3. Erreur "Directive not found"
```

```php
final class CacheDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'cache:clear';
    }
    
    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('cc', 'clear-cache');
    }
}
```

| Commande | Résolution |
|----------|------------|
| `php directive cache:clear` | ✅ Signature originale |
| `php directive cc` | ✅ Alias |
| `php directive clear-cache` | ✅ Alias |
| `php directive cache-clear` | ❌ Erreur (pas un alias) |

### 4.5 Bonnes pratiques pour les alias

```php
// ✅ BON - Signature claire, alias pratiques
public function getSignature(): string 
{ 
    return 'cache:clear'; 
}

public function getAliases(): StringTypedRecords 
{ 
    return new StringTypedRecords('cc', 'clear-cache'); 
}

// ✅ BON - Alias multi-langues
public function getSignature(): string 
{ 
    return 'hello'; 
}

public function getAliases(): StringTypedRecords 
{ 
    return new StringTypedRecords('salut', 'bonjour', 'hola', 'ciao'); 
}

// ✅ BON - Alias pour versionnement
public function getSignature(): string 
{ 
    return 'migration:run'; 
}

public function getAliases(): StringTypedRecords 
{ 
    return new StringTypedRecords('migrate', 'mig', 'db:migrate'); 
}

// ❌ MAUVAIS - Signature trop courte, alias inutiles
public function getSignature(): string 
{ 
    return 'c';  // Trop ambigu 
}

public function getAliases(): StringTypedRecords 
{ 
    return new StringTypedRecords('clear', 'cc', 'c', 'clr');  // Redondant
}

// ❌ MAUVAIS - Alias identique à une autre directive
// Ne pas créer d'alias qui pourrait créer une confusion
```

### 4.6 Alias par défaut

```php
// Par défaut, aucune directive n'a d'alias
public function getAliases(): StringTypedRecords
{
    return new StringTypedRecords();  // Collection vide
}
```

### 4.7 Cas d'usage avancé des alias

```php
final class BackupDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'database:backup';
    }
    
    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords(
            'db:backup',      // Alternative avec db:
            'backup-db',      // Format kebab-case
            'dump',           // Nom court
            'save-db'         // Descriptif
        );
    }
    
    public function execute(): ExitCode
    {
        $this->info("Starting database backup...");
        // Logique de backup
        return ExitCode::SUCCESS;
    }
}
```

**Usages possibles :**
```bash
php directive database:backup    # Original
php directive db:backup          # Alias plus court
php directive backup-db          # Style kebab
php directive dump               # Très court
php directive save-db            # Descriptif
```

---

## 5. AbstractDirective - Méthodes disponibles

### 5.1 Gestion des arguments

```php
// Récupérer un argument
$name = $this->argument('name');     // 'John'
$email = $this->argument('email');   // 'john@example.com'
$unknown = $this->argument('unknown'); // null
```

### 5.2 Gestion des options

```php
// Récupérer une option
$role = $this->option('role');     // 'admin'
$force = $this->option('force');   // true

// Vérifier l'existence
if ($this->hasOption('admin')) {
    // L'option --admin est présente
}
```

### 5.3 Affichage

| Méthode | Description | Couleur |
|---------|-------------|---------|
| `line(string $message)` | Message simple | Aucune |
| `info(string $message)` | Information | Vert |
| `error(string $message)` | Erreur | Rouge |
| `warn(string $message)` | Avertissement | Jaune |

```php
$this->line("Ligne normale");
$this->info("✅ Succès");
$this->error("❌ Échec");
$this->warn("⚠️ Attention");
```

### 5.4 Interaction utilisateur

```php
// Poser une question
$name = $this->ask("Quel est votre nom ?");

// Demander une confirmation (booléen)
if ($this->confirm("Continuer ?")) {
    $this->info("Ok, on continue !");
} else {
    $this->warn("Annulé");
}
```

### 5.5 Tableaux

```php
$headers = ['Nom', 'Email', 'Rôle'];
$rows = [
    ['John Doe', 'john@example.com', 'admin'],
    ['Jane Smith', 'jane@example.com', 'user'],
];

$this->table($headers, $rows);
```

**Résultat :**
```
| Nom        | Email             | Rôle   |
|------------|-------------------|--------|
| John Doe   | john@example.com  | admin  |
| Jane Smith | jane@example.com  | user   |
```

---

## 6. ExitCode - Codes de retour

```php
use AndyDefer\BestPractices\Directive\Enums\ExitCode;

// Retourner un code de succès
return ExitCode::SUCCESS;           // 0

// Retourner un code d'échec générique
return ExitCode::FAILURE;           // 1

// Directive non trouvée
return ExitCode::NOT_FOUND;         // 3

// Argument invalide
return ExitCode::INVALID_ARGUMENT;  // 4
```

**Vérification :**
```php
if ($exitCode->isSuccess()) { ... }
if ($exitCode->isFailure()) { ... }
if ($exitCode->isNotFound()) { ... }
if ($exitCode->isInvalidArgument()) { ... }
```

---

## 7. Commandes système intégrées

| Commande | Description |
|----------|-------------|
| `php directive --list` ou `-l` | Liste toutes les directives disponibles |
| `php directive --help` ou `-h` | Affiche l'aide générale |

**Résultat de `--list` :**
```
✅ Available directives (3):

Signature     Description           Aliases
--------------------------------------------------
  hello        Dit bonjour           salut, bonjour, hi
  user         Gère les utilisateurs uc, create-user
  backup       Sauvegarde            db:backup, dump

💡 Usage: directive <signature> [arguments] [--options]
```

**Résultat de `--help` :**
```
═══════════════════════════════════════════════════════════════════════════
🎯 Directive System - Command Line Interface
═══════════════════════════════════════════════════════════════════════════

USAGE:
  directive <signature> [arguments] [options]

COMMANDS:
  --list, -l      List all available directives
  --help, -h      Show this help message

EXAMPLES:
  directive hello
  directive user:create John Doe --role=admin
  directive cache:clear --force
  directive --list

CREATE YOUR OWN DIRECTIVE:
  1. Create a file in app/Directives/
  2. Extend AbstractDirective
  3. Implement getSignature(), getDescription() and execute()

═══════════════════════════════════════════════════════════════════════════
```

---

## 8. Cas d'usage

### 8.1 Directive simple avec alias

```php
final class HelloDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'hello';
    }

    public function getDescription(): string
    {
        return 'Dit bonjour à quelqu\'un';
    }

    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('salut', 'bonjour', 'hi');
    }

    public function execute(): ExitCode
    {
        $name = $this->argument('name') ?? 'World';
        $this->info("✨ Hello, {$name} ! ✨");
        
        return ExitCode::SUCCESS;
    }
}
```

**Utilisation :**
```bash
php directive hello John       # Original
php directive salut Marie      # Alias français
php directive bonjour          # Alias français
php directive hi               # Alias anglais
```

### 8.2 Directive complète avec alias

```php
final class UserDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'user {action} {name} {--email=} {--role=user} {--admin}';
    }

    public function getDescription(): string
    {
        return 'Gère les utilisateurs (création, suppression, liste)';
    }

    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('u', 'utilisateur');
    }

    public function execute(): ExitCode
    {
        $action = $this->argument('action');
        $name = $this->argument('name');
        
        return match($action) {
            'create' => $this->createUser($name),
            'delete' => $this->deleteUser($name),
            'show' => $this->showUser($name),
            default => $this->showHelp()
        };
    }

    private function createUser(string $name): ExitCode
    {
        $email = $this->option('email') ?? "{$name}@example.com";
        $role = $this->option('role');
        $isAdmin = $this->hasOption('admin');
        
        $this->info("📝 Création de l'utilisateur : {$name}");
        $this->line("  • Email : {$email}");
        $this->line("  • Rôle : {$role}");
        
        if ($isAdmin) {
            $this->warn("  • ⚠️ Compte administrateur !");
        }
        
        if ($this->confirm("Confirmer la création ?")) {
            $this->info("✅ Utilisateur {$name} créé avec succès !");
            return ExitCode::SUCCESS;
        }
        
        $this->error("❌ Création annulée");
        return ExitCode::FAILURE;
    }

    private function deleteUser(string $name): ExitCode
    {
        $this->warn("⚠️ Suppression de l'utilisateur : {$name}");
        
        if ($this->confirm("Êtes-vous sûr ?")) {
            $this->info("✅ Utilisateur supprimé");
            return ExitCode::SUCCESS;
        }
        
        return ExitCode::FAILURE;
    }

    private function showUser(string $name): ExitCode
    {
        $this->line("🔍 Recherche de l'utilisateur : {$name}");
        
        $email = $this->ask("Email de l'utilisateur ?");
        
        $this->table(
            ['Nom', 'Email'],
            [[$name, $email]]
        );
        
        return ExitCode::SUCCESS;
    }

    private function showHelp(): ExitCode
    {
        $this->error("❌ Action inconnue");
        $this->line("Actions disponibles : create, delete, show");
        return ExitCode::INVALID_ARGUMENT;
    }
}
```

**Utilisation des alias :**
```bash
php directive user create John --admin           # Original
php directive u create John --admin              # Alias court
php directive utilisateur create John --admin    # Alias français
```

### 8.3 Directive de migration avec alias

```php
final class MigrateDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'migration:run {--fresh} {--seed}';
    }

    public function getDescription(): string
    {
        return 'Exécute les migrations';
    }

    public function getAliases(): StringTypedRecords
    {
        return new StringTypedRecords('migrate', 'mig', 'db:migrate');
    }

    public function execute(): ExitCode
    {
        $fresh = $this->hasOption('fresh');
        $seed = $this->hasOption('seed');
        
        if ($fresh) {
            $this->warn("⚠️ Rollback et remigration complète !");
        }
        
        if (!$this->confirm("Confirmer l'exécution ?")) {
            return ExitCode::FAILURE;
        }
        
        $this->info("✅ Migrations exécutées");
        
        if ($seed) {
            $this->info("✅ Seeders exécutés");
        }
        
        return ExitCode::SUCCESS;
    }
}
```

**Utilisation :**
```bash
php directive migration:run --fresh --seed    # Original
php directive migrate --fresh --seed          # Alias
php directive mig --fresh --seed              # Alias court
php directive db:migrate --fresh --seed       # Style database
```

---

## 9. Tests unitaires

### 9.1 Test d'une directive avec alias

```php
final class HelloDirectiveTest extends TestCase
{
    public function test_get_aliases_returns_expected_aliases(): void
    {
        $displayMessage = $this->createMock(DisplayMessageTask::class);
        $askQuestion = $this->createMock(AskQuestionTask::class);
        $confirmQuestion = $this->createMock(ConfirmQuestionTask::class);
        $displayTable = $this->createMock(DisplayTableTask::class);
        
        $directive = new HelloDirective(
            $displayMessage,
            $askQuestion,
            $confirmQuestion,
            $displayTable
        );
        
        $aliases = $directive->getAliases();
        
        $this->assertInstanceOf(StringTypedRecords::class, $aliases);
        $this->assertTrue($aliases->contains('salut'));
        $this->assertTrue($aliases->contains('bonjour'));
        $this->assertTrue($aliases->contains('hi'));
    }
    
    public function test_execute_returns_success(): void
    {
        $directive = new HelloDirective(...);
        $directive->setArguments(['name' => 'John']);
        
        $result = $directive->execute();
        
        $this->assertEquals(ExitCode::SUCCESS, $result);
    }
}
```

### 9.2 Test de l'exécution avec alias

```php
final class DirectiveExecutionServiceTest extends TestCase
{
    public function test_execute_works_with_alias(): void
    {
        $aliases = new TypedRecords('string');
        $aliases->add('echo');
        
        $directiveMetadata = new DirectiveMetadataRecord(
            signature: 'test:echo',
            class: TestEchoDirective::class,
            description: 'Test echo directive',
            aliases: $aliases,
        );
        
        $directives = new TypedRecords(DirectiveMetadataRecord::class);
        $directives->add($directiveMetadata);
        
        $discovery = $this->createMock(DirectiveDiscoveryService::class);
        $discovery->method('discover')->willReturn($directives);
        
        $service = new DirectiveExecutionService($discovery, ...);
        
        // Vérifie que l'alias 'echo' trouve la directive 'test:echo'
        $result = $service->findDirectiveBySignature('echo');
        
        $this->assertNotNull($result);
        $this->assertSame('test:echo', $result->signature);
    }
}
```

---

## 10. Bonnes pratiques

### 10.1 Structure d'une directive

```php
final class MyDirective extends AbstractDirective
{
    // 1. Signature claire
    public function getSignature(): string { ... }
    
    // 2. Description concise
    public function getDescription(): string { ... }
    
    // 3. Alias utiles (optionnel)
    public function getAliases(): StringTypedRecords { ... }
    
    // 4. Validation (privée)
    private function validate(): bool
    {
        if (!$this->argument('required')) {
            $this->error("Argument manquant");
            return false;
        }
        return true;
    }
    
    // 5. Logique métier découpée
    public function execute(): ExitCode
    {
        if (!$this->validate()) {
            return ExitCode::INVALID_ARGUMENT;
        }
        
        try {
            // Traitement...
            $this->info("✅ Succès");
            return ExitCode::SUCCESS;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return ExitCode::FAILURE;
        }
    }
}
```

### 10.2 Nommage des signatures

| ✅ Bon | ❌ Éviter |
|--------|----------|
| `user:create` | `userCreate` (pas de namespace) |
| `cache:clear` | `cache_clear` (utiliser : pas _) |
| `backup:run` | `BackupRun` (pas de majuscule) |
| `db:backup` | `databaseBackup` (trop long sans :) |

### 10.3 Nommage des alias

| ✅ Bon | ❌ Éviter |
|--------|----------|
| `uc` (user:create) | `c` (trop vague) |
| `cc` (cache:clear) | `clr` (pas intuitif) |
| `mig` (migration:run) | `m` (ambigu) |
| `salut` (hello) | `s` (trop court) |

### 10.4 Messages utilisateur

```php
// ✅ Messages clairs
$this->info("✅ Succès");
$this->error("❌ Échec");
$this->warn("⚠️ Attention");
$this->line("→ En cours...");

// ❌ Éviter les messages vagues
$this->info("OK");
$this->error("Error");
```

### 10.5 Validation des arguments

```php
private function validate(): bool
{
    $required = ['name', 'email'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!$this->argument($field)) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        $this->error("Arguments manquants : " . implode(', ', $missing));
        return false;
    }
    
    return true;
}
```

---

## 11. Règles d'or

> **ZÉRO appel statique. TOUTES les dépendances injectées. La directive ne contient QUE la logique métier. Les méthodes d'affichage sont des helpers purs, sans état.**

```php
// ✅ La directive parfaite
final class PerfectDirective extends AbstractDirective
{
    public function getSignature(): string { return 'perfect {name}'; }
    public function getDescription(): string { return 'Une directive parfaite'; }
    public function getAliases(): StringTypedRecords { return new StringTypedRecords('p'); }
    
    public function execute(): ExitCode
    {
        $this->info("Hello, {$this->argument('name')}!");
        return ExitCode::SUCCESS;
    }
}
```

**Principes fondamentaux :**

| Principe | Application |
|----------|-------------|
| **Séparation I/O** | Les Tasks gèrent l'affichage, la Directive la logique |
| **Injection explicite** | Toutes les dépendances sont injectées |
| **Testabilité maximale** | Test sans container, sans framework |
| **Records immutables** | Les DTO sont des records readonly |
| **Exit codes métier** | Enums typés pour les retours |
| **Alias pratiques** | Raccourcis utiles, signatures explicites |

> **Rappel final : DÉCOUPLÉ + TESTABLE + INJECTION + ALIAS UTILES + SIMPLE = MAINTENABLE**