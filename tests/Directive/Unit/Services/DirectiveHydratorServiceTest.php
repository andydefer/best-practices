<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Contracts\DirectiveFactoryInterface;
use AndyDefer\BestPractices\Directive\Records\ParsedDirectiveRecord;
use AndyDefer\BestPractices\Directive\Services\DirectiveHydratorService;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;
use AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives\TestDirective;
use AndyDefer\BestPractices\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class DirectiveHydratorServiceTest extends TestCase
{
    private DirectiveFactoryInterface&MockObject $factory;

    private DirectiveHydratorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = $this->createMock(DirectiveFactoryInterface::class);
        $this->service = new DirectiveHydratorService($this->factory);
    }

    private function createTestDirective(): TestDirective
    {
        $displayMessage = $this->createMock(DisplayMessageTask::class);
        $askQuestion = $this->createMock(AskQuestionTask::class);
        $confirmQuestion = $this->createMock(ConfirmQuestionTask::class);
        $displayTable = $this->createMock(DisplayTableTask::class);

        return new TestDirective(
            $displayMessage,
            $askQuestion,
            $confirmQuestion,
            $displayTable,
        );
    }

    public function test_hydrate_calls_factory_and_sets_arguments(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->with(TestDirective::class)
            ->willReturn($directive);

        $arguments = new TypedRecords('string');
        $arguments->add('John Doe', 'name', 'john@example.com', 'email');

        $options = new TypedRecords('string');

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertSame('John Doe', $result->argument('name'));
        $this->assertSame('john@example.com', $result->argument('email'));
    }

    public function test_hydrate_uses_count_method(): void
    {
        $directive = $this->createTestDirective();

        $this->factory->expects($this->once())
            ->method('make')
            ->willReturn($directive);

        $arguments = new TypedRecords('string');
        $arguments->add('value1', 'key1', 'value2', 'key2');

        $options = new TypedRecords('string');

        $parsed = new ParsedDirectiveRecord(
            arguments: $arguments,
            options: $options,
        );

        $result = $this->service->hydrate(TestDirective::class, $parsed);

        $this->assertSame('value1', $result->argument('key1'));
        $this->assertSame('value2', $result->argument('key2'));
        $this->assertEquals(4, $arguments->count());
    }
}
