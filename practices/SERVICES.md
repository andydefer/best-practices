Voici votre documentation sur les Services mise à jour avec des exemples complets de remplacement de traits et le service parfait :

# Principe d'usage des Services

## 1. Définition

Un **Service** est un conteneur de méthodes qui partagent un même domaine métier. Il ne maintient **AUCUN état interne** et ne possède **AUCUNE propriété privée** (sauf des dépendances injectées).

```
Service → Conteneur de méthodes → Même domaine métier → Aucun état interne
```

---

## 2. Pourquoi les Services remplacent les Traits (⚠️ RÈGLE MAJEURE)

> **Les traits sont une mauvaise pratique. Les Services sont l'alternative propre par composition.**

### 2.1. Problèmes des traits

| Problème | Explication |
|----------|-------------|
| **Couplage implicite** | Le trait dépend de l'état de la classe qui l'utilise (propriétés, méthodes) |
| **Difficulté de test** | Un trait ne peut pas être mocké isolément |
| **Conflits de noms** | Deux traits peuvent avoir des méthodes avec le même nom |
| **Violation du SRP** | Le trait ajoute des responsabilités de manière cachée |
| **Hard debugging** | La résolution des méthodes est complexe à tracer |
| **Pas de constructeur** | Le trait ne peut pas avoir de constructeur pour injecter des dépendances |

### 2.2. Exemple complet : Trait FileCreator (anti-pattern)

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Traits;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

trait FileCreator
{
    private Filesystem $files;

    protected function initFileCreator(): void
    {
        $this->files = new Filesystem;
    }

    protected function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        if ($this->files->exists($destinationPath) && !$force) {
            $this->error("File already exists: {$destinationPath}");
            return false;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        try {
            $stub = $this->files->get($stubPath);
        } catch (FileNotFoundException $e) {
            $this->error("Stub template not found at: {$stubPath}");
            return false;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        if ($this->files->put($destinationPath, $content) === false) {
            $this->error("Cannot create file: {$destinationPath}");
            return false;
        }

        return true;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    protected function toPascalCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    protected function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    protected function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath.'/'.$className : $className,
        ];
    }

    protected function buildNamespace(string $baseNamespace, string $subPath): string
    {
        if (!$subPath) {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $subPath);
    }

    protected function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        $directory = getcwd().$baseDir;
        if ($subPath) {
            $directory .= $subPath.'/';
        }

        return $directory.$className.'.php';
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Traits\FileCreator;

final class CreateTaskDirective extends AbstractDirective
{
    use FileCreator;  // ❌ Couplage implicite, impossible à tester isolément

    public function execute(): ExitCode
    {
        $this->initFileCreator();  // Initialisation manuelle
        
        $stubPath = __DIR__ . '/../Stubs/task.stub';
        $taskName = $this->argument('name');
        $segments = $this->extractPathSegments($taskName);
        
        $namespace = $this->buildNamespace('App\\Tasks', $segments['subPath']);
        $className = $this->toPascalCase($segments['className']);
        
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ signature }}' => $this->toKebabCase($className),
        ];
        
        $destination = $this->getAppPath('/app/Tasks/', $className, $segments['subPath']);
        
        if ($this->createFile($stubPath, $destination, $replacements, $this->option('force'))) {
            $this->info("Task created: {$destination}");
            return ExitCode::SUCCESS;
        }
        
        return ExitCode::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Directives\CreateTaskDirective;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use PHPUnit\Framework\TestCase;

final class CreateTaskDirectiveTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        chdir($this->tempDir);
    }

    // ❌ Test impossible : on ne peut pas mocker le trait FileCreator
    // Le test doit créer de VRAIS fichiers sur le disque
    public function test_execute_creates_task_file(): void
    {
        $interaction = $this->createMock(DirectiveInteractionService::class);
        $directive = new CreateTaskDirective($interaction);
        
        // Le trait utilise directement le Filesystem réel
        // On ne peut pas le mocker, on crée donc de vrais fichiers
        file_put_contents('/tmp/task.stub', 'class {{ class }}');
        
        // Test qui écrit sur le disque (lent, fragile, non isolé)
        $result = $directive->execute();
        
        $this->assertFileExists('/app/Tasks/UserTask.php');  // Effet de bord réel
        
        // Nettoyage nécessaire
        unlink('/tmp/task.stub');
        unlink('/app/Tasks/UserTask.php');
    }
}
```

### 2.3. Exemple complet : Service FileCreator (bonne pratique)

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

final class FileCreatorService
{
    public function __construct(
        private readonly Filesystem $files,  // ✅ Injection explicite
    ) {}

    public function createFile(
        string $stubPath,
        string $destinationPath,
        array $replacements,
        bool $force = false
    ): bool {
        if ($this->files->exists($destinationPath) && !$force) {
            return false;
        }

        $this->ensureDirectoryExists(dirname($destinationPath));

        try {
            $stub = $this->files->get($stubPath);
        } catch (FileNotFoundException $e) {
            return false;
        }

        $content = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );

        return $this->files->put($destinationPath, $content) !== false;
    }

    public function ensureDirectoryExists(string $path): void
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0755, true);
        }
    }

    public function toPascalCase(string $string): string
    {
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        return str_replace(' ', '', $string);
    }

    public function toKebabCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $string));
    }

    public function extractPathSegments(string $name): array
    {
        $segments = explode('/', $name);
        $className = array_pop($segments);
        $subPath = !empty($segments) ? implode('/', array_map('ucfirst', $segments)) : '';

        return [
            'segments' => $segments,
            'className' => $className,
            'subPath' => $subPath,
            'fullPath' => $subPath ? $subPath.'/'.$className : $className,
        ];
    }

    public function buildNamespace(string $baseNamespace, string $subPath): string
    {
        if (!$subPath) {
            return $baseNamespace;
        }

        return $baseNamespace.'\\'.str_replace('/', '\\', $subPath);
    }

    public function getAppPath(string $baseDir, string $className, string $subPath = ''): string
    {
        $directory = getcwd().$baseDir;
        if ($subPath) {
            $directory .= $subPath.'/';
        }

        return $directory.$className.'.php';
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;

final class CreateTaskDirective extends AbstractDirective
{
    public function __construct(
        private readonly FileCreatorService $fileCreator,  // ✅ Injection du service
    ) {
        parent::__construct();
    }

    public function execute(): ExitCode
    {
        $stubPath = __DIR__ . '/../Stubs/task.stub';
        $taskName = $this->argument('name');
        $segments = $this->fileCreator->extractPathSegments($taskName);
        
        $namespace = $this->fileCreator->buildNamespace('App\\Tasks', $segments['subPath']);
        $className = $this->fileCreator->toPascalCase($segments['className']);
        
        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ signature }}' => $this->fileCreator->toKebabCase($className),
        ];
        
        $destination = $this->fileCreator->getAppPath('/app/Tasks/', $className, $segments['subPath']);
        
        if ($this->fileCreator->createFile($stubPath, $destination, $replacements, $this->option('force'))) {
            $this->info("Task created: {$destination}");
            return ExitCode::SUCCESS;
        }
        
        $this->error("Failed to create task");
        return ExitCode::FAILURE;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace AndyDefer\Directive\Tests\Unit\Directives;

use AndyDefer\Directive\Directives\CreateTaskDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\FileCreatorService;
use AndyDefer\Directive\Services\DirectiveInteractionService;
use PHPUnit\Framework\TestCase;

final class CreateTaskDirectiveTest extends TestCase
{
    private FileCreatorService $fileCreator;
    private DirectiveInteractionService $interaction;
    private CreateTaskDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Mock du service (testable, isolé)
        $this->fileCreator = $this->createMock(FileCreatorService::class);
        $this->interaction = $this->createMock(DirectiveInteractionService::class);
        
        $this->directive = new CreateTaskDirective($this->fileCreator);
        $this->directive->setInteraction($this->interaction);
    }

    public function test_execute_creates_task_successfully(): void
    {
        // Arrange
        $this->directive->setArguments(new ParameterCollection());
        $this->directive->setOptions(new ParameterCollection());
        
        $this->fileCreator->method('extractPathSegments')
            ->willReturn([
                'segments' => ['admin'],
                'className' => 'user',
                'subPath' => 'Admin',
                'fullPath' => 'Admin/user'
            ]);
        
        $this->fileCreator->method('toPascalCase')
            ->with('user')
            ->willReturn('User');
        
        $this->fileCreator->method('buildNamespace')
            ->with('App\\Tasks', 'Admin')
            ->willReturn('App\\Tasks\\Admin');
        
        $this->fileCreator->method('toKebabCase')
            ->with('User')
            ->willReturn('user');
        
        $this->fileCreator->method('createFile')
            ->willReturn(true);
        
        $this->interaction->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Task created'));
        
        // Act
        $result = $this->directive->execute();
        
        // Assert
        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_failure_when_file_creation_fails(): void
    {
        // Arrange
        $this->directive->setArguments(new ParameterCollection());
        $this->directive->setOptions(new ParameterCollection());
        
        $this->fileCreator->method('extractPathSegments')
            ->willReturn(['segments' => [], 'className' => 'user', 'subPath' => '', 'fullPath' => 'user']);
        
        $this->fileCreator->method('toPascalCase')->willReturn('User');
        $this->fileCreator->method('buildNamespace')->willReturn('App\\Tasks');
        $this->fileCreator->method('createFile')->willReturn(false);
        
        $this->interaction->expects($this->once())
            ->method('error')
            ->with('Failed to create task');
        
        // Act
        $result = $this->directive->execute();
        
        // Assert
        $this->assertSame(ExitCode::FAILURE, $result);
    }
}
```

### 2.4. Comparaison : Trait vs Service

| Aspect | Trait | Service |
|--------|-------|---------|
| **Testabilité** | ❌ Impossible à mocker, nécessite des fichiers réels | ✅ Facile à mocker, test isolé |
| **Dépendances** | ❌ Pas de constructeur, instanciation interne | ✅ Injection explicite dans le constructeur |
| **État interne** | ❌ Peut avoir des propriétés privées | ✅ Pas d'état (sauf injections) |
| **Couplage** | ❌ Couplage implicite à la classe utilisatrice | ✅ Découplage explicite |
| **Réutilisabilité** | ✅ Réutilisable (mais dangereux) | ✅ Réutilisable (et sûr) |
| **Debugging** | ❌ Difficile (résolution dynamique) | ✅ Facile (appel explicite) |
| **Effets de bord** | ❌ Modifie la classe qui l'utilise | ✅ Aucun effet de bord |

---

## 3. Exemple complet : Trait HasRatings (anti-pattern)

```php
<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\RatingLevel;
use App\Models\Rating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasRatings
{
    public function receivedRatings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function sentRatings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rater');
    }

    public function averageRating(): float
    {
        $avg = $this->receivedRatings()->avg('rating_level');
        if ($avg === null) {
            return 0.0;
        }
        return round((float) $avg, 2);
    }

    public function averageRatingLevel(): ?RatingLevel
    {
        $avg = $this->averageRating();
        if ($avg <= 1.5) return RatingLevel::ONE;
        if ($avg <= 2.5) return RatingLevel::TWO;
        if ($avg <= 3.5) return RatingLevel::THREE;
        if ($avg <= 4.5) return RatingLevel::FOUR;
        return RatingLevel::FIVE;
    }

    public function ratingsCount(): int
    {
        return $this->receivedRatings()->count();
    }

    public function rate(Model $rateable, RatingLevel|int $ratingLevel, ?string $review = null): Rating
    {
        if (is_int($ratingLevel)) {
            $ratingLevel = RatingLevel::from($ratingLevel);
        }

        $existing = $this->getRatingFor($rateable);
        if ($existing) {
            $existing->update(['rating_level' => $ratingLevel->value, 'review' => $review]);
            return $existing->fresh();
        }

        return Rating::create([
            'rater_id' => $this->getKey(),
            'rater_type' => $this->getMorphClass(),
            'rateable_id' => $rateable->getKey(),
            'rateable_type' => $rateable->getMorphClass(),
            'rating_level' => $ratingLevel->value,
            'review' => $review,
        ]);
    }

    public function getRatingFor(Model $rateable): ?Rating
    {
        return Rating::where('rater_id', $this->getKey())
            ->where('rater_type', $this->getMorphClass())
            ->where('rateable_id', $rateable->getKey())
            ->where('rateable_type', $rateable->getMorphClass())
            ->first();
    }

    public function hasRated(Model $rateable): bool
    {
        return $this->getRatingFor($rateable) !== null;
    }
}

// Utilisation du trait
final class Product extends Model implements Rateable
{
    use HasRatings;  // ❌ Couplage implicite
    
    // Le Product se retrouve avec toutes les méthodes de rating
    // Impossible de tester le rating sans avoir un vrai Product en base
}
```

---

## 4. Exemple complet : Service RatingService (bonne pratique)

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RatingLevel;
use App\Models\Rating;
use App\Configs\RatingConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class RatingService
{
    public function __construct(
        private readonly RatingConfig $config,  // ✅ Config injectée
    ) {}

    public function getReceivedRatings(Model $model): Collection
    {
        return $this->getReceivedRatingsQuery($model)->get();
    }

    public function getAverageRating(Model $model): float
    {
        $avg = $this->getReceivedRatingsQuery($model)->avg('rating_level');
        return $avg !== null ? round((float) $avg, 2) : $this->config->defaultRating();
    }

    public function getAverageRatingLevel(Model $model): RatingLevel
    {
        $avg = $this->getAverageRating($model);
        
        if ($avg <= 1.5) return RatingLevel::ONE;
        if ($avg <= 2.5) return RatingLevel::TWO;
        if ($avg <= 3.5) return RatingLevel::THREE;
        if ($avg <= 4.5) return RatingLevel::FOUR;
        return RatingLevel::FIVE;
    }

    public function getRatingsCount(Model $model): int
    {
        return $this->getReceivedRatingsQuery($model)->count();
    }

    public function rate(
        Model $rater,
        Model $rateable,
        RatingLevel|int $ratingLevel,
        ?string $review = null
    ): Rating {
        if (is_int($ratingLevel)) {
            $ratingLevel = RatingLevel::from($ratingLevel);
        }

        // Validation avec la Config
        if ($ratingLevel->value < $this->config->minRating() || 
            $ratingLevel->value > $this->config->maxRating()) {
            throw new \InvalidArgumentException('Invalid rating level');
        }

        $existing = $this->getRatingFor($rater, $rateable);
        if ($existing) {
            $existing->update([
                'rating_level' => $ratingLevel->value,
                'review' => $review,
            ]);
            return $existing->fresh();
        }

        return Rating::create([
            'rater_id' => $rater->getKey(),
            'rater_type' => $rater->getMorphClass(),
            'rateable_id' => $rateable->getKey(),
            'rateable_type' => $rateable->getMorphClass(),
            'rating_level' => $ratingLevel->value,
            'review' => $review,
        ]);
    }

    public function getRatingFor(Model $rater, Model $rateable): ?Rating
    {
        return Rating::where('rater_id', $rater->getKey())
            ->where('rater_type', $rater->getMorphClass())
            ->where('rateable_id', $rateable->getKey())
            ->where('rateable_type', $rateable->getMorphClass())
            ->first();
    }

    public function hasRated(Model $rater, Model $rateable): bool
    {
        return $this->getRatingFor($rater, $rateable) !== null;
    }

    public function deleteRatingFor(Model $rater, Model $rateable): bool
    {
        $rating = $this->getRatingFor($rater, $rateable);
        return $rating ? (bool) $rating->delete() : false;
    }

    public function getRatingsDistribution(Model $model): array
    {
        $distribution = [];
        foreach (RatingLevel::cases() as $level) {
            $distribution[$level->value] = $this->getReceivedRatingsQuery($model)
                ->where('rating_level', $level->value)
                ->count();
        }
        return $distribution;
    }

    public function getPositiveRatingPercentage(Model $model): float
    {
        $total = $this->getRatingsCount($model);
        if ($total === 0) {
            return 0.0;
        }

        $positive = $this->getReceivedRatingsQuery($model)
            ->whereIn('rating_level', [RatingLevel::FOUR->value, RatingLevel::FIVE->value])
            ->count();

        return round(($positive / $total) * 100, 2);
    }

    private function getReceivedRatingsQuery(Model $model)
    {
        return Rating::where('rateable_id', $model->getKey())
            ->where('rateable_type', $model->getMorphClass());
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Configs;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;

final class RatingConfig extends AbstractConfig
{
    public function defaultRating(): float
    {
        return (float) (getenv('DEFAULT_RATING') ?: 0.0);
    }

    public function minRating(): int
    {
        return (int) (getenv('MIN_RATING') ?: 1);
    }

    public function maxRating(): int
    {
        return (int) (getenv('MAX_RATING') ?: 5);
    }

    public function requireReviewForLowRating(): bool
    {
        return getenv('REQUIRE_REVIEW_FOR_LOW_RATING') === 'true';
    }

    public function lowRatingThreshold(): int
    {
        return (int) (getenv('LOW_RATING_THRESHOLD') ?: 3);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Actions\Api\Products;

use App\Services\RatingService;
use App\Records\RateProductRecord;
use App\Data\RatingData;
use AndyDefer\Actions\AbstractAction;
use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class RateProductAction extends AbstractAction
{
    public function __construct(
        private readonly RatingService $ratingService,  // ✅ Injection du service
    ) {}

    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var RateProductRecord $request */
        
        $rating = $this->ratingService->rate(
            rater: $request->currentUserId,
            rateable: $request->productId,
            ratingLevel: $request->rating,
            review: $request->review,
        );

        return ResponseFactory::json(RatingData::from($rating), 201);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\RatingService;
use App\Configs\RatingConfig;
use App\Models\Rating;
use App\Models\User;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

final class RatingServiceTest extends TestCase
{
    private RatingService $service;
    private RatingConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Config mockée
        $this->config = $this->createMock(RatingConfig::class);
        $this->config->method('defaultRating')->willReturn(0.0);
        $this->config->method('minRating')->willReturn(1);
        $this->config->method('maxRating')->willReturn(5);
        
        // ✅ Service testable sans base de données
        $this->service = new RatingService($this->config);
    }

    public function test_get_average_rating_returns_default_when_no_ratings(): void
    {
        // Arrange
        $product = $this->createMock(Product::class);
        $product->method('getKey')->willReturn(1);
        $product->method('getMorphClass')->willReturn('product');
        
        // On mocke la requête pour retourner une moyenne null
        // ...
        
        // Act
        $result = $this->service->getAverageRating($product);
        
        // Assert
        $this->assertEquals(0.0, $result);
    }

    public function test_rate_throws_exception_when_rating_out_of_bounds(): void
    {
        // Arrange
        $user = $this->createMock(User::class);
        $product = $this->createMock(Product::class);
        
        $this->config->method('minRating')->willReturn(1);
        $this->config->method('maxRating')->willReturn(5);
        
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->service->rate($user, $product, 10);
    }
}
```

---

## 5. Caractéristiques fondamentales

| Caractéristique | Règle |
|-----------------|-------|
| **État interne** | ❌ AUCUNE propriété privée (sauf dépendances injectées) |
| **Constructeur** | ✅ Uniquement pour injecter des dépendances (Services, Repositories, Configs) |
| **Paramètres des méthodes** | ✅ Tout ce dont la méthode a besoin est passé en paramètre |
| **Stockage de données** | ❌ Ne stocke rien entre les appels |
| **Classe finale** | ❌ **NE PEUT PAS** être déclarée `final` (doit être mockable) |
| **Instanciation interne** | ❌ INTERDIT (tout doit être injecté) |

---

## 6. Le Service parfait

Voici un exemple de service qui respecte toutes les règles :

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Configs\OrderConfig;
use App\Records\OrderRecord;
use App\Data\OrderTotalData;

/**
 * Service de calcul des commandes.
 * 
 * ✅ Pas d'état interne
 * ✅ Dépendances injectées dans le constructeur
 * ✅ Toutes les données arrivent en paramètres
 * ✅ Pas de `final`
 * ✅ Testable et mockable
 */
class OrderCalculatorService
{
    public function __construct(
        private readonly TaxService $taxService,           // ✅ Service injecté
        private readonly DiscountService $discountService, // ✅ Service injecté
        private readonly OrderConfig $config,              // ✅ Config injectée
    ) {}

    /**
     * Calcule le sous-total d'une commande.
     * 
     * @param OrderRecord $order La commande (passée en paramètre)
     * @return float Le sous-total
     */
    public function calculateSubtotal(OrderRecord $order): float
    {
        $total = 0.0;
        foreach ($order->items as $item) {
            $total += $item->price * $item->quantity;
        }
        
        if ($this->config->applyRounding()) {
            $total = round($total, $this->config->roundingPrecision());
        }
        
        return $total;
    }

    /**
     * Calcule le total d'une commande (sous-total + taxes - remises).
     * 
     * @param OrderRecord $order La commande
     * @param string $country Le pays pour la TVA
     * @param string|null $promoCode Code promo optionnel
     * @return OrderTotalData Le total détaillé
     */
    public function calculateTotal(OrderRecord $order, string $country, ?string $promoCode = null): OrderTotalData
    {
        $subtotal = $this->calculateSubtotal($order);
        
        $tax = $this->taxService->calculate($subtotal, $country);
        $discount = $this->discountService->apply($subtotal, $promoCode);
        
        // Application des frais de livraison (depuis la Config)
        $shippingFee = $order->total > $this->config->freeShippingThreshold() 
            ? 0.0 
            : $this->config->standardShippingFee();
        
        $total = $subtotal + $tax - $discount + $shippingFee;
        
        return new OrderTotalData(
            subtotal: $subtotal,
            tax: $tax,
            discount: $discount,
            shippingFee: $shippingFee,
            total: round($total, 2)
        );
    }

    /**
     * Vérifie si une commande est éligible à la livraison gratuite.
     * 
     * @param OrderRecord $order La commande
     * @return bool
     */
    public function isEligibleForFreeShipping(OrderRecord $order): bool
    {
        return $order->total >= $this->config->freeShippingThreshold();
    }

    /**
     * Calcule le nombre total d'articles dans une commande.
     * 
     * @param OrderRecord $order La commande
     * @return int
     */
    public function getTotalItemsCount(OrderRecord $order): int
    {
        $count = 0;
        foreach ($order->items as $item) {
            $count += $item->quantity;
        }
        return $count;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Configs;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;

final class OrderConfig extends AbstractConfig
{
    public function freeShippingThreshold(): float
    {
        return (float) (getenv('FREE_SHIPPING_THRESHOLD') ?: 50.0);
    }

    public function standardShippingFee(): float
    {
        return (float) (getenv('STANDARD_SHIPPING_FEE') ?: 5.99);
    }

    public function applyRounding(): bool
    {
        return getenv('APPLY_ROUNDING') === 'true';
    }

    public function roundingPrecision(): int
    {
        return (int) (getenv('ROUNDING_PRECISION') ?: 2);
    }

    public function maxItemsPerOrder(): int
    {
        return (int) (getenv('MAX_ITEMS_PER_ORDER') ?: 100);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\OrderCalculatorService;
use App\Services\TaxService;
use App\Services\DiscountService;
use App\Configs\OrderConfig;
use App\Records\OrderRecord;
use PHPUnit\Framework\TestCase;

final class OrderCalculatorServiceTest extends TestCase
{
    private OrderCalculatorService $service;
    private TaxService $taxService;
    private DiscountService $discountService;
    private OrderConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ✅ Tous les services peuvent être mockés
        $this->taxService = $this->createMock(TaxService::class);
        $this->discountService = $this->createMock(DiscountService::class);
        $this->config = $this->createMock(OrderConfig::class);
        
        $this->service = new OrderCalculatorService(
            $this->taxService,
            $this->discountService,
            $this->config
        );
    }

    public function test_calculate_subtotal_with_multiple_items(): void
    {
        // Arrange
        $this->config->method('applyRounding')->willReturn(true);
        $this->config->method('roundingPrecision')->willReturn(2);
        
        $order = new OrderRecord(
            items: [
                new OrderItemRecord(price: 10.0, quantity: 2),
                new OrderItemRecord(price: 15.5, quantity: 1),
            ]
        );
        
        // Act
        $subtotal = $this->service->calculateSubtotal($order);
        
        // Assert
        $this->assertEquals(35.5, $subtotal);
    }

    public function test_calculate_total_applies_tax_and_discount(): void
    {
        // Arrange
        $this->config->method('freeShippingThreshold')->willReturn(50.0);
        $this->config->method('standardShippingFee')->willReturn(5.99);
        
        $this->taxService->method('calculate')->willReturn(7.0);
        $this->discountService->method('apply')->willReturn(10.0);
        
        $order = new OrderRecord(
            items: [new OrderItemRecord(price: 100.0, quantity: 1)],
            total: 100.0
        );
        
        // Act
        $result = $this->service->calculateTotal($order, 'FR', 'PROMO10');
        
        // Assert
        $this->assertEquals(100.0, $result->subtotal);
        $this->assertEquals(7.0, $result->tax);
        $this->assertEquals(10.0, $result->discount);
        $this->assertEquals(0.0, $result->shippingFee); // Gratuit car > 50
        $this->assertEquals(97.0, $result->total);
    }

    public function test_is_eligible_for_free_shipping_when_above_threshold(): void
    {
        // Arrange
        $this->config->method('freeShippingThreshold')->willReturn(50.0);
        
        $order = new OrderRecord(items: [], total: 75.0);
        
        // Act
        $result = $this->service->isEligibleForFreeShipping($order);
        
        // Assert
        $this->assertTrue($result);
    }
}
```

---

## 7. Ce qu'un Service NE peut PAS faire

| Action | Pourquoi |
|--------|----------|
| **Avoir des propriétés privées** (sauf injections) | État interne interdit |
| **Stocker des données volatiles** (compteur, dernier ID, cache) | Violation du principe sans état |
| **Stocker des paramètres de configuration** (chemin, clé API) | Utiliser une Config à la place |
| **Être `final`** | Empêche le mocking et l'extension |
| **Instancier ses dépendances en interne** | Crée un couplage fort, impossible à tester |

---

## 8. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Propriétés privées** | ❌ AUCUNE (sauf injections) |
| **État interne** | ❌ INTERDIT |
| **Stockage de Configs** | ✅ AUTORISÉ (immuables) |
| **Stockage de Services** | ✅ AUTORISÉ (dépendances) |
| **Stockage de données volatiles** | ❌ INTERDIT |
| **Constructeur** | ✅ Uniquement pour l'injection |
| **Classe `final`** | ❌ INTERDIT |
| **Instanciation interne** | ❌ INTERDIT |

---

## 9. Règle d'or

> **Un Service est un conteneur pur de méthodes. Il n'a pas de mémoire, pas d'état interne, pas de cache. Toute donnée dont il a besoin lui est fournie au moment de l'appel.**
>
> **⚠️ Les Services remplacent les traits : là où on aurait utilisé un trait (couplage implicite, testabilité impossible), on utilise un service injecté (composition explicite, testabilité parfaite).**
>
> **La composition est la clé : un service peut utiliser d'autres services, tous injectés dans le constructeur. Jamais d'instanciation interne, jamais de stockage d'état.**

```php
// ✅ Le Service parfait
class PerfectService
{
    // ✅ Uniquement des injections dans le constructeur
    public function __construct(
        private readonly AnotherService $service,  // Service injecté
        private readonly AppConfig $config,        // Config injectée
        private readonly UserRepository $repo,     // Repository injecté
    ) {}

    // ✅ Toutes les données arrivent en paramètres
    public function execute(OrderRecord $order, User $user): Result
    {
        // ✅ Pas d'état interne
        // ✅ Pas de cache
        // ✅ Pas de compteur
        // ✅ Utilisation des dépendances injectées
        
        $subtotal = $this->calculateSubtotal($order);
        $tax = $this->service->calculateTax($subtotal, $user->country);
        
        return new Result($subtotal + $tax);
    }
    
    private function calculateSubtotal(OrderRecord $order): float
    {
        return array_reduce($order->items, fn($c, $i) => $c + ($i->price * $i->quantity), 0);
    }
}

// ✅ Test parfait
final class PerfectServiceTest extends TestCase
{
    public function test_execute(): void
    {
        // Tous les services peuvent être mockés
        $service = $this->createMock(AnotherService::class);
        $config = $this->createMock(AppConfig::class);
        $repo = $this->createMock(UserRepository::class);
        
        $perfectService = new PerfectService($service, $config, $repo);
        
        // Test isolé, sans effets de bord, sans fichiers réels, sans base de données
        $result = $perfectService->execute($order, $user);
        
        $this->assertInstanceOf(Result::class, $result);
    }
}
```
---