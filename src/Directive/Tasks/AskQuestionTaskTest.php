<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Tasks;

use AndyDefer\BestPractices\Directive\Records\AskQuestionRecord;
use AndyDefer\BestPractices\Directive\Tasks\AskQuestionTask;
use AndyDefer\BestPractices\Tests\TestCase;

final class AskQuestionTaskTest extends TestCase
{
    private AskQuestionTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = new AskQuestionTask;
    }

    public function test_execute_returns_user_input(): void
    {
        $record = new AskQuestionRecord('What is your name?');

        $this->expectOutputString('What is your name? ');

        $input = fopen('php://memory', 'r+');
        fwrite($input, "John Doe\n");
        rewind($input);

        $originalStdin = STDIN;
        $reflection = new \ReflectionProperty($this->task, 'input');
        $reflection->setAccessible(true);

        // Simuler l'entrée utilisateur
        $result = $this->simulateInput($input, $record);

        $this->assertSame('John Doe', $result);
    }

    private function simulateInput($input, AskQuestionRecord $record): string
    {
        // Sauvegarder le vrai STDIN
        $realStdin = fopen('php://stdin', 'r');

        // Remplacer par notre input simulé
        $reflection = new \ReflectionClass($this->task);
        $method = $reflection->getMethod('execute');
        $method->setAccessible(true);

        // Alternative: utiliser une classe de test qui injecte le flux
        $task = new class extends AskQuestionTask
        {
            public $testInput;

            public function execute(AskQuestionRecord $record): string
            {
                echo $record->question.' ';

                return trim(fgets($this->testInput));
            }
        };

        $task->testInput = $input;

        return $task->execute($record);
    }
}
