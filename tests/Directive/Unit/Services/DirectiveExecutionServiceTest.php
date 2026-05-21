<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveInterface;
use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Directive\Records\DirectiveExecutionRecord;
use AndyDefer\BestPractices\Directive\Records\DirectiveMetadataRecord;
use AndyDefer\BestPractices\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\BestPractices\Directive\Services\DirectiveDiscoveryService;
use AndyDefer\BestPractices\Directive\Services\DirectiveExecutionService;
use AndyDefer\BestPractices\Directive\Services\DirectiveHydratorService;
use AndyDefer\BestPractices\Directive\Services\DirectiveParserService;
use AndyDefer\BestPractices\Directive\Services\DirectiveRendererService;
use AndyDefer\BestPractices\Directive\Tasks\DisplayErrorTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Logger\Contracts\LoggerInterface;
use AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives\TestEchoDirective;
use AndyDefer\BestPractices\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveExecutionServiceTest extends TestCase
{
    private DirectiveDiscoveryService&MockObject $discovery;

    private DirectiveParserService&MockObject $parser;

    private DirectiveHydratorService&MockObject $hydrator;

    private DirectiveRendererService&MockObject $renderer;

    private DisplayMessageTask&MockObject $displayMessage;

    private DisplayErrorTask&MockObject $displayError;

    private LoggerInterface&MockObject $logger;

    private DirectiveExecutionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(DirectiveDiscoveryService::class);
        $this->parser = $this->createMock(DirectiveParserService::class);
        $this->hydrator = $this->createMock(DirectiveHydratorService::class);
        $this->renderer = $this->createMock(DirectiveRendererService::class);

        // ✅ CORRECTION : Pour une méthode void, on utilise expects + willReturn(null) est interdit
        // Il faut utiliser willReturnCallback ou ne pas configurer d'attente
        $this->displayMessage = $this->createMock(DisplayMessageTask::class);
        // Ne pas configurer d'attente pour execute - on s'en fiche dans les tests
        // Ou utiliser $this->any() sans willReturn

        $this->displayError = $this->createMock(DisplayErrorTask::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Configurer le renderer
        $this->renderer->method('renderList')->willReturn('');
        $this->renderer->method('renderHelp')->willReturn('');
        $this->renderer->method('renderNotFound')->willReturn('Not found');

        $emptyDirectives = new TypedRecords(DirectiveMetadataRecord::class);
        $this->discovery->method('discover')->willReturn($emptyDirectives);

        $this->service = new DirectiveExecutionService(
            $this->discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );
    }

    // ==================== TESTS AVEC COLLECTION VIDE ====================

    public function test_execute_returns_success_for_list_command(): void
    {
        $arguments = new TypedRecords('string');
        $record = new DirectiveExecutionRecord(signature: '--list', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_success_for_help_command(): void
    {
        $arguments = new TypedRecords('string');
        $record = new DirectiveExecutionRecord(signature: '--help', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_execute_returns_not_found_when_directive_does_not_exist(): void
    {
        $arguments = new TypedRecords('string');
        $record = new DirectiveExecutionRecord(signature: 'unknown:command', arguments: $arguments);

        $result = $this->service->execute($record);

        $this->assertSame(ExitCode::NOT_FOUND, $result);
    }

    public function test_exists_returns_false_for_non_existing_directive(): void
    {
        $result = $this->service->exists('unknown:command');

        $this->assertFalse($result);
    }

    public function test_list_directives_returns_empty_when_no_directives(): void
    {
        $result = $this->service->listDirectives();

        $this->assertSame(0, $result->count());
    }

    // ==================== TESTS AVEC COLLECTION CONTENANT DES DIRECTIVES ====================

    public function test_execute_returns_success_when_directive_exists(): void
    {
        $aliases = new TypedRecords('string');
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

        $service = new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );

        $parsedRecord = new ParsedDirectiveRecord(
            arguments: new TypedRecords('string'),
            options: new TypedRecords('string'),
        );
        $this->parser->method('parse')->willReturn($parsedRecord);

        $command = $this->createMock(DirectiveInterface::class);
        $command->method('execute')->willReturn(ExitCode::SUCCESS);
        $this->hydrator->method('hydrate')->willReturn($command);

        $arguments = new TypedRecords('string');
        $arguments->add('Hello');
        $record = new DirectiveExecutionRecord(signature: 'test:echo', arguments: $arguments);

        $result = $service->execute($record);

        $this->assertSame(ExitCode::SUCCESS, $result);
    }

    public function test_exists_returns_true_for_existing_directive(): void
    {
        $aliases = new TypedRecords('string');
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

        $service = new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );

        $result = $service->exists('test:echo');

        $this->assertTrue($result);
    }

    public function test_list_directives_returns_all_directives(): void
    {
        $aliases1 = new TypedRecords('string');
        $directive1 = new DirectiveMetadataRecord(
            signature: 'test:echo',
            class: TestEchoDirective::class,
            description: 'Test echo directive',
            aliases: $aliases1,
        );

        $aliases2 = new TypedRecords('string');
        $directive2 = new DirectiveMetadataRecord(
            signature: 'cache:clear',
            class: 'CacheDirective',
            description: 'Clear cache',
            aliases: $aliases2,
        );

        $directives = new TypedRecords(DirectiveMetadataRecord::class);
        $directives->add($directive1, $directive2);

        $discovery = $this->createMock(DirectiveDiscoveryService::class);
        $discovery->method('discover')->willReturn($directives);

        $service = new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );

        $result = $service->listDirectives();

        $this->assertSame(2, $result->count());
    }

    public function test_find_directive_by_signature_returns_directive_when_exists(): void
    {
        $aliases = new TypedRecords('string');
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

        $service = new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );

        $result = $service->findDirectiveBySignature('test:echo');

        $this->assertNotNull($result);
        $this->assertSame('test:echo', $result->signature);
    }

    public function test_find_directive_by_signature_returns_null_when_not_exists(): void
    {
        $result = $this->service->findDirectiveBySignature('unknown:command');

        $this->assertNull($result);
    }

    public function test_find_directive_by_signature_works_with_alias(): void
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

        $service = new DirectiveExecutionService(
            $discovery,
            $this->parser,
            $this->hydrator,
            $this->renderer,
            $this->displayMessage,
            $this->displayError,
            $this->logger,
        );

        $result = $service->findDirectiveBySignature('echo');

        $this->assertNotNull($result);
        $this->assertSame('test:echo', $result->signature);
    }
}
