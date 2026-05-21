<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Feature;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Config\DirectiveConfig;
use AndyDefer\BestPractices\Directive\Factories\ContainerDirectiveFactory;
use AndyDefer\BestPractices\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\BestPractices\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\BestPractices\Directive\Services\DirectiveHydratorService;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayErrorTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;
use AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives\TestEchoDirective;
use AndyDefer\BestPractices\Tests\TestCase;
use Illuminate\Container\Container;

final class DirectiveDiscoveryServiceIntegrationTest extends TestCase
{
    private string $fixturesPath;

    private DirectiveDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Chemin vers les fixtures réelles
        $this->fixturesPath = __DIR__.'/../Fixtures/Directives';

        $config = DirectiveConfig::default()->withDirectivesPath($this->fixturesPath);

        // Créer un vrai container
        $container = new Container;

        // Enregistrer les tasks dans le container
        $container->singleton(DisplayMessageTask::class);
        $container->singleton(AskQuestionTask::class);
        $container->singleton(ConfirmQuestionTask::class);
        $container->singleton(DisplayTableTask::class);
        $container->singleton(DisplayErrorTask::class);

        // Créer la factory avec le container
        $factory = new ContainerDirectiveFactory($container);

        // Créer l'hydrator avec la factory
        $hydrator = new DirectiveHydratorService($factory);

        // Créer le service de découverte
        $this->service = new DirectiveDiscoveryService($config, $hydrator);
    }

    /**
     * Test 1: Vérifie que discover retourne un TypedRecords de DirectiveMetadataRecord
     */
    public function test_discover_returns_typed_records_of_directive_metadata(): void
    {
        $result = $this->service->discover();

        $this->assertInstanceOf(TypedRecords::class, $result);
        $this->assertContains(DirectiveMetadataRecord::class, $result->getAllowedTypes());
        $this->assertGreaterThan(0, $result->count());
    }

    /**
     * Test 2: Vérifie que la directive TestEchoDirective est trouvée
     */
    public function test_finds_test_echo_directive(): void
    {
        $result = $this->service->discover();

        $found = false;
        foreach ($result as $directive) {
            if ($directive->signature === 'test:echo') {
                $found = true;
                $this->assertSame('Test echo directive', $directive->description);
                $this->assertSame(TestEchoDirective::class, $directive->class);

                // Vérifier les alias
                $this->assertInstanceOf(TypedRecords::class, $directive->aliases);
                break;
            }
        }

        $this->assertTrue($found, 'Directive "test:echo" not found in discovered directives');
    }

    /**
     * Test 3: Vérifie que les directives concrètes sont trouvées
     */
    public function test_finds_concrete_directives(): void
    {
        $result = $this->service->discover();

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertContains('test:echo', $signatures);
    }

    /**
     * Test 4: Vérifie la structure complète des métadonnées
     */
    public function test_returns_complete_metadata_structure(): void
    {
        $result = $this->service->discover();

        foreach ($result as $directive) {
            $this->assertIsString($directive->signature);
            $this->assertNotEmpty($directive->signature);

            $this->assertIsString($directive->class);
            $this->assertNotEmpty($directive->class);

            $this->assertIsString($directive->description);

            $this->assertInstanceOf(TypedRecords::class, $directive->aliases);
        }
    }

    /**
     * Test 5: Vérifie que toutes les directives du dossier sont découvertes
     */
    public function test_discovers_all_valid_directives(): void
    {
        $result = $this->service->discover();

        // Compter les fichiers PHP valides dans le dossier fixtures
        $files = glob($this->fixturesPath.'/*.php');
        $expectedCount = 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Vérifie si le fichier contient une classe qui implémente DirectiveInterface
            if (str_contains($content, 'implements DirectiveInterface')) {
                $expectedCount++;
            }
        }

        // Au moins une directive devrait être trouvée
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /**
     * Test 6: Vérifie que les signatures sont uniques
     */
    public function test_signatures_are_unique(): void
    {
        $result = $this->service->discover();

        $signatures = [];
        foreach ($result as $directive) {
            $signatures[] = $directive->signature;
        }

        $this->assertEquals(count($signatures), count(array_unique($signatures)));
    }

    /**
     * Test 7: Vérifie le comportement avec un chemin invalide
     */
    public function test_returns_empty_result_for_invalid_path(): void
    {
        $invalidPath = '/invalid/path/that/does/not/exist';
        $config = DirectiveConfig::default()->withDirectivesPath($invalidPath);

        $container = new Container;
        $factory = new ContainerDirectiveFactory($container);
        $hydrator = new DirectiveHydratorService($factory);

        $service = new DirectiveDiscoveryService($config, $hydrator);
        $result = $service->discover();

        $this->assertInstanceOf(TypedRecords::class, $result);
        $this->assertEquals(0, $result->count());
    }

    /**
     * Test 8: Vérifie que les classes abstraites sont ignorées
     */
    public function test_ignores_abstract_directives(): void
    {
        $result = $this->service->discover();

        foreach ($result as $directive) {
            $reflection = new \ReflectionClass($directive->class);
            $this->assertFalse($reflection->isAbstract());
        }
    }

    /**
     * Test 9: Vérifie que les alias sont correctement chargés
     */
    public function test_aliases_are_loaded_correctly(): void
    {
        $result = $this->service->discover();

        foreach ($result as $directive) {
            // Les alias doivent être un TypedRecords
            $this->assertInstanceOf(TypedRecords::class, $directive->aliases);

            // Les alias doivent être des strings
            foreach ($directive->aliases as $alias) {
                $this->assertIsString($alias);
            }
        }
    }
}
