<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Tasks;

use AndyDefer\BestPractices\Directive\Records\DisplayTableRecord;
use AndyDefer\BestPractices\Directive\Tasks\DisplayTableTask;
use AndyDefer\BestPractices\Tests\TestCase;

final class DisplayTableTaskTest extends TestCase
{
    private DisplayTableTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->task = new DisplayTableTask;
    }

    public function test_execute_displays_table_with_headers_and_rows(): void
    {
        $headers = ['Name', 'Email', 'Age'];
        $rows = [
            ['John Doe', 'john@example.com', 30],
            ['Jane Smith', 'jane@example.com', 25],
        ];

        $record = new DisplayTableRecord($headers, $rows);

        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        $this->assertStringContainsString('| Name', $output);
        $this->assertStringContainsString('| Email', $output);
        $this->assertStringContainsString('| Age', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('john@example.com', $output);
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('jane@example.com', $output);
    }

    public function test_execute_handles_empty_rows(): void
    {
        $headers = ['Name', 'Email'];
        $rows = [];

        $record = new DisplayTableRecord($headers, $rows);

        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        $this->assertStringContainsString('| Name', $output);
        $this->assertStringContainsString('| Email', $output);
    }

    public function test_execute_handles_single_row(): void
    {
        $headers = ['Name', 'Email'];
        $rows = [
            ['John Doe', 'john@example.com'],
        ];

        $record = new DisplayTableRecord($headers, $rows);

        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('john@example.com', $output);
    }

    public function test_execute_handles_mixed_data_types(): void
    {
        $headers = ['Name', 'Age', 'Active'];
        $rows = [
            ['John Doe', 30, true],
            ['Jane Smith', 25, false],
        ];

        $record = new DisplayTableRecord($headers, $rows);

        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('30', $output);
        $this->assertStringContainsString('1', $output); // true devient 1
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('25', $output);
        $this->assertStringContainsString('0', $output); // false devient 0
    }

    public function test_execute_handles_special_characters(): void
    {
        $headers = ['Message'];
        $rows = [
            ['Hello World!'],
            ['Special chars: @#$%'],
        ];

        $record = new DisplayTableRecord($headers, $rows);

        ob_start();
        $this->task->execute($record);
        $output = ob_get_clean();

        $this->assertStringContainsString('Hello World!', $output);
        $this->assertStringContainsString('Special chars: @#$%', $output);
    }
}
