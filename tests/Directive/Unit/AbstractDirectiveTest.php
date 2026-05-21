<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit;

use AndyDefer\BestPractices\Collections\Utility\StringTypedRecords;
use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Directive\Records\DisplayMessageRecord;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\ConfirmQuestionTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayMessageTask;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;
use AndyDefer\BestPractices\Tests\Directive\Fixtures\Directives\TestConcreteDirective;
use AndyDefer\BestPractices\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class AbstractDirectiveTest extends TestCase
{
    private DisplayMessageTask&MockObject $displayMessage;

    private AskQuestionTask&MockObject $askQuestion;

    private ConfirmQuestionTask&MockObject $confirmQuestion;

    private DisplayTableTask&MockObject $displayTable;

    private TestConcreteDirective $directive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->displayMessage = $this->createMock(DisplayMessageTask::class);
        $this->askQuestion = $this->createMock(AskQuestionTask::class);
        $this->confirmQuestion = $this->createMock(ConfirmQuestionTask::class);
        $this->displayTable = $this->createMock(DisplayTableTask::class);

        $this->directive = new TestConcreteDirective(
            $this->displayMessage,
            $this->askQuestion,
            $this->confirmQuestion,
            $this->displayTable,
        );
    }

    // ==================== Tests des arguments ====================

    public function test_set_arguments_sets_arguments(): void
    {
        $args = ['name' => 'John Doe', 'email' => 'john@example.com'];

        $result = $this->directive->setArguments($args);

        $this->assertSame($this->directive, $result);
        $this->assertSame('John Doe', $this->directive->argument('name'));
        $this->assertSame('john@example.com', $this->directive->argument('email'));
    }

    public function test_argument_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->directive->argument('unknown'));
    }

    public function test_set_arguments_returns_self_for_chaining(): void
    {
        $result = $this->directive->setArguments(['name' => 'John']);

        $this->assertSame($this->directive, $result);
    }

    // ==================== Tests des options ====================

    public function test_set_options_sets_options(): void
    {
        $opts = ['role' => 'admin', 'active' => 'true'];

        $result = $this->directive->setOptions($opts);

        $this->assertSame($this->directive, $result);
        $this->assertSame('admin', $this->directive->option('role'));
        $this->assertSame('true', $this->directive->option('active'));
    }

    public function test_option_returns_null_for_unknown_key(): void
    {
        $this->assertNull($this->directive->option('unknown'));
    }

    public function test_has_option_returns_true_when_option_exists(): void
    {
        $this->directive->setOptions(['force' => 'true']);

        $this->assertTrue($this->directive->hasOption('force'));
        $this->assertFalse($this->directive->hasOption('unknown'));
    }

    public function test_set_options_returns_self_for_chaining(): void
    {
        $result = $this->directive->setOptions(['force' => 'true']);

        $this->assertSame($this->directive, $result);
    }

    // ==================== Tests des méthodes d'affichage ====================

    public function test_line_delegates_to_display_message_task_with_line_type(): void
    {
        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) {
                return $record->message === 'Test message'
                    && $record->type === MessageType::LINE;
            }));

        $this->directive->line('Test message');
    }

    public function test_info_delegates_to_display_message_task_with_info_type(): void
    {
        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) {
                return $record->message === 'Test message'
                    && $record->type === MessageType::INFO;
            }));

        $this->directive->info('Test message');
    }

    public function test_error_delegates_to_display_message_task_with_error_type(): void
    {
        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) {
                return $record->message === 'Test message'
                    && $record->type === MessageType::ERROR;
            }));

        $this->directive->error('Test message');
    }

    public function test_warn_delegates_to_display_message_task_with_warning_type(): void
    {
        $this->displayMessage->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (DisplayMessageRecord $record) {
                return $record->message === 'Test message'
                    && $record->type === MessageType::WARNING;
            }));

        $this->directive->warn('Test message');
    }

    // ==================== Tests des méthodes d'interaction ====================

    public function test_ask_delegates_to_ask_question_task(): void
    {
        $this->askQuestion->expects($this->once())
            ->method('execute')
            ->willReturn('John Doe');

        $result = $this->directive->ask('What is your name?');

        $this->assertSame('John Doe', $result);
    }

    public function test_confirm_delegates_to_confirm_question_task(): void
    {
        $this->confirmQuestion->expects($this->once())
            ->method('execute')
            ->willReturn(true);

        $result = $this->directive->confirm('Continue?');

        $this->assertTrue($result);
    }

    // ==================== Test de la méthode table ====================

    public function test_table_delegates_to_display_table_task(): void
    {
        $headers = ['Name', 'Email'];
        $rows = [['John', 'john@example.com']];

        $this->displayTable->expects($this->once())->method('execute');

        $this->directive->table($headers, $rows);
    }

    // ==================== Test de getAliases ====================

    public function test_get_aliases_returns_empty_string_typed_records_by_default(): void
    {
        $aliases = $this->directive->getAliases();

        $this->assertInstanceOf(StringTypedRecords::class, $aliases);
        $this->assertTrue($aliases->isEmpty());
    }
}
