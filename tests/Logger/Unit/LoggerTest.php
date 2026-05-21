<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Unit;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Logger\Logger;
use AndyDefer\BestPractices\Logger\Records\LogDataRecord;
use AndyDefer\BestPractices\Logger\Records\LogQueryRecord;
use AndyDefer\BestPractices\Logger\Records\LogRecord;
use AndyDefer\BestPractices\Logger\Services\Tasks\QueryLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\StreamLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\WriteLogTask;
use AndyDefer\BestPractices\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
final class LoggerTest extends TestCase
{
    private Logger $logger;

    private MockObject&WriteLogTask $writeTask;

    private MockObject&QueryLogsTask $queryTask;

    private MockObject&StreamLogsTask $streamTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeTask = $this->createMock(WriteLogTask::class);
        $this->queryTask = $this->createMock(QueryLogsTask::class);
        $this->streamTask = $this->createMock(StreamLogsTask::class);

        $this->logger = new Logger(
            $this->writeTask,
            $this->queryTask,
            $this->streamTask,
        );
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new MixedPayloadCollection;
        foreach ($payloadData as $item) {
            $payload->add($item);
        }

        return new LogDataRecord(
            type: $type,
            payload: $payload,
        );
    }

    public function test_info_creates_info_level_log_record(): void
    {
        $payloadData = [1, 'user_login', '127.0.0.1'];
        $logData = $this->createLogDataRecord('user_login', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::INFO
                    && $record->time === '2026-04-05T10:26:00Z'
                    && $record->data->type === $logData->type;
            }));

        $this->logger->info('2026-04-05T10:26:00Z', $logData);
    }

    public function test_warning_creates_warning_level_log_record(): void
    {
        $payloadData = ['system_warning', 'High memory usage'];
        $logData = $this->createLogDataRecord('system_warning', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::WARNING
                    && $record->data->type === $logData->type;
            }));

        $this->logger->warning('2026-04-05T10:26:00Z', $logData);
    }

    public function test_error_creates_error_level_log_record(): void
    {
        $payloadData = ['payment_failed', 12345, 99.99];
        $logData = $this->createLogDataRecord('payment_failed', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::ERROR
                    && $record->data->type === $logData->type;
            }));

        $this->logger->error('2026-04-05T10:26:00Z', $logData);
    }

    public function test_debug_creates_debug_level_log_record(): void
    {
        $payloadData = ['debug_info', 'test value'];
        $logData = $this->createLogDataRecord('debug_info', $payloadData);

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (LogRecord $record) use ($logData) {
                return $record->level === LogLevel::DEBUG
                    && $record->data->type === $logData->type;
            }));

        $this->logger->debug('2026-04-05T10:26:00Z', $logData);
    }

    public function test_log_calls_write_task_directly(): void
    {
        $payload = new MixedPayloadCollection;
        $payload->add('test', 42, true);

        $logData = new LogDataRecord(type: 'test', payload: $payload);

        $record = new LogRecord(
            time: '2026-04-05T10:26:00Z',
            level: LogLevel::INFO,
            data: $logData,
        );

        $this->writeTask->expects($this->once())
            ->method('execute')
            ->with($record);

        $this->logger->log($record);
    }

    public function test_query_delegates_to_query_task(): void
    {
        $query = new LogQueryRecord(type: 'user_login');
        $expectedResults = new TypedRecords(LogRecord::class);

        $this->queryTask->expects($this->once())
            ->method('execute')
            ->with($query)
            ->willReturn($expectedResults);

        $results = $this->logger->query($query);

        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_delegates_to_stream_task(): void
    {
        $date = '2026-04-05';
        $expectedResults = new TypedRecords(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with($date)
            ->willReturn($expectedResults);

        $results = $this->logger->stream($date);

        $this->assertSame($expectedResults, $results);
    }

    public function test_stream_uses_current_date_when_null(): void
    {
        $expectedResults = new TypedRecords(LogRecord::class);

        $this->streamTask->expects($this->once())
            ->method('execute')
            ->with(null)
            ->willReturn($expectedResults);

        $results = $this->logger->stream();

        $this->assertSame($expectedResults, $results);
    }
}
