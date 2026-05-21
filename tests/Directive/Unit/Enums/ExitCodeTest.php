<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Enums;

use AndyDefer\BestPractices\Directive\Enums\ExitCode;
use AndyDefer\BestPractices\Tests\TestCase;

final class ExitCodeTest extends TestCase
{
    public function test_values_returns_all_exit_code_values(): void
    {
        $values = ExitCode::values();

        $this->assertSame([0, 1, 3, 4], $values);
    }

    public function test_names_returns_all_case_names(): void
    {
        $names = ExitCode::names();

        $this->assertSame(['SUCCESS', 'FAILURE', 'NOT_FOUND', 'INVALID_ARGUMENT'], $names);
    }

    public function test_types_in_order_returns_cases_in_definition_order(): void
    {
        $types = ExitCode::typesInOrder();

        $this->assertSame([
            ExitCode::SUCCESS,
            ExitCode::FAILURE,
            ExitCode::NOT_FOUND,
            ExitCode::INVALID_ARGUMENT,
        ], $types);
    }

    public function test_get_label_returns_correct_label_for_each_code(): void
    {
        $this->assertSame('Success', ExitCode::SUCCESS->getLabel());
        $this->assertSame('Failure', ExitCode::FAILURE->getLabel());
        $this->assertSame('Not Found', ExitCode::NOT_FOUND->getLabel());
        $this->assertSame('Invalid Argument', ExitCode::INVALID_ARGUMENT->getLabel());
    }

    public function test_is_success_returns_true_only_for_success(): void
    {
        $this->assertTrue(ExitCode::SUCCESS->isSuccess());
        $this->assertFalse(ExitCode::FAILURE->isSuccess());
        $this->assertFalse(ExitCode::NOT_FOUND->isSuccess());
        $this->assertFalse(ExitCode::INVALID_ARGUMENT->isSuccess());
    }

    public function test_is_valid_returns_true_for_existing_values(): void
    {
        $this->assertTrue(ExitCode::isValid(0));
        $this->assertTrue(ExitCode::isValid(1));
        $this->assertTrue(ExitCode::isValid(3));
        $this->assertTrue(ExitCode::isValid(4));
    }

    public function test_is_valid_returns_false_for_invalid_values(): void
    {
        $this->assertFalse(ExitCode::isValid(2));
        $this->assertFalse(ExitCode::isValid(5));
        $this->assertFalse(ExitCode::isValid(99));
        $this->assertFalse(ExitCode::isValid(-1));
    }

    public function test_is_valid_returns_false_for_string_values(): void
    {
        // ExitCode est un enum int, les strings ne sont pas valides
        $this->assertFalse(ExitCode::isValid('0'));
        $this->assertFalse(ExitCode::isValid('success'));
        $this->assertFalse(ExitCode::isValid(''));
    }

    public function test_from_value_returns_correct_enum_for_valid_int_value(): void
    {
        $this->assertSame(ExitCode::SUCCESS, ExitCode::fromValue(0));
        $this->assertSame(ExitCode::FAILURE, ExitCode::fromValue(1));
        $this->assertSame(ExitCode::NOT_FOUND, ExitCode::fromValue(3));
        $this->assertSame(ExitCode::INVALID_ARGUMENT, ExitCode::fromValue(4));
    }

    public function test_from_value_returns_null_for_invalid_int_value(): void
    {
        $this->assertNull(ExitCode::fromValue(2));
        $this->assertNull(ExitCode::fromValue(5));
        $this->assertNull(ExitCode::fromValue(99));
        $this->assertNull(ExitCode::fromValue(-1));
    }

    public function test_from_value_throws_type_error_for_string_values(): void
    {
        // ExitCode est un enum int, passer une string est une erreur de type
        $this->expectException(\TypeError::class);
        ExitCode::fromValue('0');
    }

    public function test_try_from_works_natively(): void
    {
        // Test de la méthode native tryFrom
        $this->assertSame(ExitCode::SUCCESS, ExitCode::tryFrom(0));
        $this->assertSame(ExitCode::FAILURE, ExitCode::tryFrom(1));
        $this->assertNull(ExitCode::tryFrom(99));
    }

    public function test_try_from_throws_type_error_for_string_value(): void
    {
        // ExitCode est un enum int, tryFrom attend un int
        $this->expectException(\TypeError::class);
        ExitCode::tryFrom('0');
    }

    public function test_from_throws_exception_for_invalid_value(): void
    {
        // Test que from() lance une exception pour valeur invalide
        $this->expectException(\ValueError::class);
        ExitCode::from(99);
    }

    public function test_match_exhaustif_returns_correct_value_for_every_case(): void
    {
        // Test que le match est exhaustif (tous les cas sont couverts)
        $this->assertSame('Success', ExitCode::SUCCESS->getLabel());
        $this->assertSame('Failure', ExitCode::FAILURE->getLabel());
        $this->assertSame('Not Found', ExitCode::NOT_FOUND->getLabel());
        $this->assertSame('Invalid Argument', ExitCode::INVALID_ARGUMENT->getLabel());

        // Le match fonctionne sans default (exhaustif)
        $cases = ExitCode::cases();
        $this->assertCount(4, $cases);
    }

    public function test_exit_code_values_match_unix_convention(): void
    {
        // 0 = succès (convention UNIX)
        $this->assertSame(0, ExitCode::SUCCESS->value);

        // Non-zéro = échec (convention UNIX)
        $this->assertNotSame(0, ExitCode::FAILURE->value);
        $this->assertNotSame(0, ExitCode::NOT_FOUND->value);
        $this->assertNotSame(0, ExitCode::INVALID_ARGUMENT->value);
    }
}
