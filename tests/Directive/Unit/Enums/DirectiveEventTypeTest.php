<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Directive\Unit\Enums;

use AndyDefer\BestPractices\Directive\Enums\DirectiveEventType;
use AndyDefer\BestPractices\Tests\TestCase;

final class DirectiveEventTypeTest extends TestCase
{
    public function test_values_returns_all_event_type_values(): void
    {
        $values = DirectiveEventType::values();

        $this->assertSame(['started', 'finished', 'failed'], $values);
    }

    public function test_names_returns_all_case_names(): void
    {
        $names = DirectiveEventType::names();

        $this->assertSame(['STARTED', 'FINISHED', 'FAILED'], $names);
    }

    public function test_types_in_order_returns_cases_in_definition_order(): void
    {
        $types = DirectiveEventType::typesInOrder();

        $this->assertSame([
            DirectiveEventType::STARTED,
            DirectiveEventType::FINISHED,
            DirectiveEventType::FAILED,
        ], $types);
    }

    public function test_get_label_returns_correct_label_for_each_type(): void
    {
        $this->assertSame('Started', DirectiveEventType::STARTED->getLabel());
        $this->assertSame('Finished', DirectiveEventType::FINISHED->getLabel());
        $this->assertSame('Failed', DirectiveEventType::FAILED->getLabel());
    }

    public function test_is_started_returns_true_only_for_started(): void
    {
        $this->assertTrue(DirectiveEventType::STARTED->isStarted());
        $this->assertFalse(DirectiveEventType::FINISHED->isStarted());
        $this->assertFalse(DirectiveEventType::FAILED->isStarted());
    }

    public function test_is_finished_returns_true_only_for_finished(): void
    {
        $this->assertFalse(DirectiveEventType::STARTED->isFinished());
        $this->assertTrue(DirectiveEventType::FINISHED->isFinished());
        $this->assertFalse(DirectiveEventType::FAILED->isFinished());
    }

    public function test_is_failed_returns_true_only_for_failed(): void
    {
        $this->assertFalse(DirectiveEventType::STARTED->isFailed());
        $this->assertFalse(DirectiveEventType::FINISHED->isFailed());
        $this->assertTrue(DirectiveEventType::FAILED->isFailed());
    }

    public function test_is_valid_returns_true_for_existing_values(): void
    {
        $this->assertTrue(DirectiveEventType::isValid('started'));
        $this->assertTrue(DirectiveEventType::isValid('finished'));
        $this->assertTrue(DirectiveEventType::isValid('failed'));
    }

    public function test_is_valid_returns_false_for_invalid_values(): void
    {
        $this->assertFalse(DirectiveEventType::isValid('invalid'));
        $this->assertFalse(DirectiveEventType::isValid('STARTED')); // Case sensitive
        $this->assertFalse(DirectiveEventType::isValid(''));
    }

    public function test_from_value_returns_correct_enum_for_valid_value(): void
    {
        $this->assertSame(DirectiveEventType::STARTED, DirectiveEventType::fromValue('started'));
        $this->assertSame(DirectiveEventType::FINISHED, DirectiveEventType::fromValue('finished'));
        $this->assertSame(DirectiveEventType::FAILED, DirectiveEventType::fromValue('failed'));
    }

    public function test_from_value_returns_null_for_invalid_value(): void
    {
        $this->assertNull(DirectiveEventType::fromValue('invalid'));
        $this->assertNull(DirectiveEventType::fromValue('STARTED')); // Case sensitive
        $this->assertNull(DirectiveEventType::fromValue(''));
    }

    public function test_from_value_throws_type_error_for_int_value(): void
    {
        // DirectiveEventType est un enum string, passer un int est une erreur de type
        $this->expectException(\TypeError::class);
        DirectiveEventType::fromValue(123);
    }

    public function test_try_from_works_natively(): void
    {
        $this->assertSame(DirectiveEventType::STARTED, DirectiveEventType::tryFrom('started'));
        $this->assertSame(DirectiveEventType::FINISHED, DirectiveEventType::tryFrom('finished'));
        $this->assertSame(DirectiveEventType::FAILED, DirectiveEventType::tryFrom('failed'));
        $this->assertNull(DirectiveEventType::tryFrom('invalid'));
    }

    public function test_from_throws_exception_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        DirectiveEventType::from('invalid');
    }

    public function test_match_exhaustif_returns_correct_value_for_every_case(): void
    {
        // Test que le match est exhaustif (tous les cas sont couverts)
        $this->assertSame('Started', DirectiveEventType::STARTED->getLabel());
        $this->assertSame('Finished', DirectiveEventType::FINISHED->getLabel());
        $this->assertSame('Failed', DirectiveEventType::FAILED->getLabel());

        // Le match fonctionne sans default (exhaustif)
        $cases = DirectiveEventType::cases();
        $this->assertCount(3, $cases);
    }
}
