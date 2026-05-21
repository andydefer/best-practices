<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Enums;

use AndyDefer\BestPractices\Directive\Enums\MessageType;
use AndyDefer\BestPractices\Tests\TestCase;

final class MessageTypeTest extends TestCase
{
    public function test_values_returns_all_message_type_values(): void
    {
        $values = MessageType::values();

        $this->assertSame(['info', 'error', 'warning', 'line'], $values);
    }

    public function test_names_returns_all_case_names(): void
    {
        $names = MessageType::names();

        $this->assertSame(['INFO', 'ERROR', 'WARNING', 'LINE'], $names);
    }

    public function test_types_in_order_returns_cases_in_definition_order(): void
    {
        $types = MessageType::typesInOrder();

        $this->assertSame([
            MessageType::INFO,
            MessageType::ERROR,
            MessageType::WARNING,
            MessageType::LINE,
        ], $types);
    }

    public function test_get_color_code_returns_correct_code_for_each_type(): void
    {
        $this->assertSame("\033[32m", MessageType::INFO->getColorCode());
        $this->assertSame("\033[31m", MessageType::ERROR->getColorCode());
        $this->assertSame("\033[33m", MessageType::WARNING->getColorCode());
        $this->assertSame('', MessageType::LINE->getColorCode());
    }

    public function test_get_reset_code_returns_correct_code_for_each_type(): void
    {
        $this->assertSame("\033[0m", MessageType::INFO->getResetCode());
        $this->assertSame("\033[0m", MessageType::ERROR->getResetCode());
        $this->assertSame("\033[0m", MessageType::WARNING->getResetCode());
        $this->assertSame('', MessageType::LINE->getResetCode());
    }

    public function test_get_label_returns_correct_label_for_each_type(): void
    {
        $this->assertSame('Information', MessageType::INFO->getLabel());
        $this->assertSame('Error', MessageType::ERROR->getLabel());
        $this->assertSame('Warning', MessageType::WARNING->getLabel());
        $this->assertSame('Line', MessageType::LINE->getLabel());
    }

    public function test_is_info_returns_true_only_for_info(): void
    {
        $this->assertTrue(MessageType::INFO->isInfo());
        $this->assertFalse(MessageType::ERROR->isInfo());
        $this->assertFalse(MessageType::WARNING->isInfo());
        $this->assertFalse(MessageType::LINE->isInfo());
    }

    public function test_is_error_returns_true_only_for_error(): void
    {
        $this->assertFalse(MessageType::INFO->isError());
        $this->assertTrue(MessageType::ERROR->isError());
        $this->assertFalse(MessageType::WARNING->isError());
        $this->assertFalse(MessageType::LINE->isError());
    }

    public function test_is_warning_returns_true_only_for_warning(): void
    {
        $this->assertFalse(MessageType::INFO->isWarning());
        $this->assertFalse(MessageType::ERROR->isWarning());
        $this->assertTrue(MessageType::WARNING->isWarning());
        $this->assertFalse(MessageType::LINE->isWarning());
    }

    public function test_is_line_returns_true_only_for_line(): void
    {
        $this->assertFalse(MessageType::INFO->isLine());
        $this->assertFalse(MessageType::ERROR->isLine());
        $this->assertFalse(MessageType::WARNING->isLine());
        $this->assertTrue(MessageType::LINE->isLine());
    }

    public function test_is_valid_returns_true_for_existing_values(): void
    {
        $this->assertTrue(MessageType::isValid('info'));
        $this->assertTrue(MessageType::isValid('error'));
        $this->assertTrue(MessageType::isValid('warning'));
        $this->assertTrue(MessageType::isValid('line'));
    }

    public function test_is_valid_returns_false_for_invalid_values(): void
    {
        $this->assertFalse(MessageType::isValid('invalid'));
        $this->assertFalse(MessageType::isValid('INFO')); // Case sensitive
        $this->assertFalse(MessageType::isValid(''));
        $this->assertFalse(MessageType::isValid('debug'));
    }

    public function test_is_valid_returns_false_for_int_values(): void
    {
        // MessageType est un enum string, les ints ne sont pas valides
        $this->assertFalse(MessageType::isValid(0));
        $this->assertFalse(MessageType::isValid(1));
        $this->assertFalse(MessageType::isValid(99));
    }

    public function test_from_value_returns_correct_enum_for_valid_value(): void
    {
        $this->assertSame(MessageType::INFO, MessageType::fromValue('info'));
        $this->assertSame(MessageType::ERROR, MessageType::fromValue('error'));
        $this->assertSame(MessageType::WARNING, MessageType::fromValue('warning'));
        $this->assertSame(MessageType::LINE, MessageType::fromValue('line'));
    }

    public function test_from_value_returns_null_for_invalid_value(): void
    {
        $this->assertNull(MessageType::fromValue('invalid'));
        $this->assertNull(MessageType::fromValue('INFO')); // Case sensitive
        $this->assertNull(MessageType::fromValue(''));
        $this->assertNull(MessageType::fromValue('debug'));
    }

    public function test_from_value_throws_type_error_for_int_values(): void
    {
        // MessageType est un enum string, passer un int est une erreur de type
        $this->expectException(\TypeError::class);
        MessageType::fromValue(0);
    }

    public function test_try_from_works_natively(): void
    {
        // Test de la méthode native tryFrom
        $this->assertSame(MessageType::INFO, MessageType::tryFrom('info'));
        $this->assertSame(MessageType::ERROR, MessageType::tryFrom('error'));
        $this->assertSame(MessageType::WARNING, MessageType::tryFrom('warning'));
        $this->assertSame(MessageType::LINE, MessageType::tryFrom('line'));
        $this->assertNull(MessageType::tryFrom('invalid'));
    }

    public function test_try_from_throws_type_error_for_int_values(): void
    {
        // MessageType est un enum string, tryFrom attend un string
        $this->expectException(\TypeError::class);
        MessageType::tryFrom(0);
    }

    public function test_from_throws_exception_for_invalid_value(): void
    {
        // Test que from() lance une exception pour valeur invalide
        $this->expectException(\ValueError::class);
        MessageType::from('invalid');
    }

    public function test_match_exhaustif_returns_correct_value_for_every_case(): void
    {
        // Test que le match est exhaustif (tous les cas sont couverts)
        $this->assertSame('Information', MessageType::INFO->getLabel());
        $this->assertSame('Error', MessageType::ERROR->getLabel());
        $this->assertSame('Warning', MessageType::WARNING->getLabel());
        $this->assertSame('Line', MessageType::LINE->getLabel());

        // Le match fonctionne sans default (exhaustif)
        $cases = MessageType::cases();
        $this->assertCount(4, $cases);
    }

    public function test_color_codes_are_valid_ansi_sequences(): void
    {
        // Vérification que les codes ANSI sont valides
        $this->assertMatchesRegularExpression('/^\033\[\d+m$/', MessageType::INFO->getColorCode());
        $this->assertMatchesRegularExpression('/^\033\[\d+m$/', MessageType::ERROR->getColorCode());
        $this->assertMatchesRegularExpression('/^\033\[\d+m$/', MessageType::WARNING->getColorCode());
        $this->assertSame('', MessageType::LINE->getColorCode());
    }

    public function test_reset_code_works_with_color_code(): void
    {
        // INFO et ERROR ont un reset code, LINE non
        $this->assertNotEmpty(MessageType::INFO->getResetCode());
        $this->assertNotEmpty(MessageType::ERROR->getResetCode());
        $this->assertNotEmpty(MessageType::WARNING->getResetCode());
        $this->assertEmpty(MessageType::LINE->getResetCode());
    }

    public function test_message_type_enum_has_correct_number_of_cases(): void
    {
        $cases = MessageType::cases();
        $this->assertCount(4, $cases);
    }
}
