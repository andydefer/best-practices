<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Directive\Services\DirectiveParserService;
use AndyDefer\BestPractices\Tests\TestCase;

final class DirectiveParserServiceTest extends TestCase
{
    private DirectiveParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DirectiveParserService;
    }

    public function test_parse_with_arguments_only(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('John Doe', 'john@example.com');

        $result = $this->service->parse('user:create {name} {email}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame(['name' => 'John Doe', 'email' => 'john@example.com'], $parsed['arguments']);
        $this->assertSame([], $parsed['options']);
    }

    public function test_parse_with_long_options(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('John Doe', '--role=admin');

        $result = $this->service->parse('user:create {name} {--role=}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame(['name' => 'John Doe'], $parsed['arguments']);
        $this->assertSame(['role' => 'admin'], $parsed['options']);
    }

    public function test_parse_with_flag_option(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('--force');

        $result = $this->service->parse('cache:clear {--force}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame([], $parsed['arguments']);
        $this->assertSame(['force' => true], $parsed['options']);
    }

    public function test_parse_with_short_option(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('-v');

        $result = $this->service->parse('app:run {-v}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame([], $parsed['arguments']);
        $this->assertSame(['v' => true], $parsed['options']);
    }

    public function test_parse_with_mixed_arguments_and_options(): void
    {
        $argv = new TypedRecords('string');
        // ✅ Les arguments doivent être dans l'ordre de la signature
        // Signature: {name} {email} {--role=} {--active}
        // Donc: name, email, puis les options
        $argv->add('John', 'john@example.com', '--role=admin', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $parsed['arguments']);
        $this->assertSame(['role' => 'admin', 'active' => true], $parsed['options']);
    }

    public function test_parse_with_options_between_arguments(): void
    {
        $argv = new TypedRecords('string');
        // Les options peuvent être mélangées, mais les arguments doivent rester dans l'ordre
        $argv->add('John', '--role=admin', 'john@example.com', '--active');

        $result = $this->service->parse('user:create {name} {email} {--role=} {--active}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $parsed['arguments']);
        $this->assertSame(['role' => 'admin', 'active' => true], $parsed['options']);
    }

    public function test_parse_with_optional_argument(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('John');

        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame(['name' => 'John'], $parsed['arguments']);
        $this->assertSame([], $parsed['options']);
    }

    public function test_parse_with_missing_optional_argument(): void
    {
        $argv = new TypedRecords('string');

        $result = $this->service->parse('user:create {name?}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame([], $parsed['arguments']);
        $this->assertSame([], $parsed['options']);
    }

    public function test_parse_with_option_without_value(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('--role=');

        $result = $this->service->parse('user:create {--role=}', $argv);
        $parsed = $this->service->toArray($result);

        $this->assertSame([], $parsed['arguments']);
        $this->assertSame(['role' => true], $parsed['options']);
    }

    public function test_extract_help_with_arguments(): void
    {
        $result = $this->service->extractHelp('user:create {name} {email}');

        $this->assertCount(2, $result);
        $this->assertSame('name', $result[0]['name']);
        $this->assertSame('argument', $result[0]['type']);
        $this->assertTrue($result[0]['required']);
        $this->assertSame('email', $result[1]['name']);
        $this->assertSame('argument', $result[1]['type']);
        $this->assertTrue($result[1]['required']);
    }

    public function test_extract_help_with_options(): void
    {
        $result = $this->service->extractHelp('user:create {--role=} {--active}');

        $this->assertCount(2, $result);
        $this->assertSame('role', $result[0]['name']);
        $this->assertSame('option', $result[0]['type']);
        $this->assertFalse($result[0]['required']);
        $this->assertNull($result[0]['default']);
        $this->assertSame('active', $result[1]['name']);
        $this->assertSame('option', $result[1]['type']);
        $this->assertFalse($result[1]['required']);
        $this->assertNull($result[1]['default']);
    }

    public function test_extract_help_with_option_default_value(): void
    {
        $result = $this->service->extractHelp('user:create {--role=admin}');

        $this->assertCount(1, $result);
        $this->assertSame('role', $result[0]['name']);
        $this->assertSame('option', $result[0]['type']);
        $this->assertSame('admin', $result[0]['default']);
    }

    public function test_extract_help_with_optional_argument(): void
    {
        $result = $this->service->extractHelp('user:create {name?}');

        $this->assertCount(1, $result);
        $this->assertSame('name', $result[0]['name']);
        $this->assertSame('argument', $result[0]['type']);
        $this->assertFalse($result[0]['required']);
    }

    public function test_to_array_converts_parsed_record_correctly(): void
    {
        $argv = new TypedRecords('string');
        $argv->add('John', '--role=admin', '--active');

        $parsed = $this->service->parse('user:create {name} {--role=} {--active}', $argv);
        $result = $this->service->toArray($parsed);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('arguments', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertSame(['name' => 'John'], $result['arguments']);
        $this->assertSame(['role' => 'admin', 'active' => true], $result['options']);
    }

    public function test_to_array_with_empty_parsed_record(): void
    {
        $argv = new TypedRecords('string');
        $parsed = $this->service->parse('test:cmd', $argv);
        $result = $this->service->toArray($parsed);

        $this->assertSame([], $result['arguments']);
        $this->assertSame([], $result['options']);
    }
}
