<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Tests\Logger\Feature;

use AndyDefer\BestPractices\Collections\TypedRecords;
use AndyDefer\BestPractices\Logger\Collections\MixedPayloadCollection;
use AndyDefer\BestPractices\Logger\Config\LoggerConfig;
use AndyDefer\BestPractices\Logger\Enums\LogLevel;
use AndyDefer\BestPractices\Logger\Logger;
use AndyDefer\BestPractices\Logger\Records\LogDataRecord;
use AndyDefer\BestPractices\Logger\Records\LogQueryRecord;
use AndyDefer\BestPractices\Logger\Services\LogPathService;
use AndyDefer\BestPractices\Logger\Services\LogSerializerService;
use AndyDefer\BestPractices\Logger\Services\Tasks\QueryLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\StreamLogsTask;
use AndyDefer\BestPractices\Logger\Services\Tasks\WriteLogTask;
use AndyDefer\BestPractices\Records\AbstractRecord;
use AndyDefer\BestPractices\Tests\TestCase;

final class LoggerIntegrationTest extends TestCase
{
    private Logger $logger;

    private string $testLogPath;

    private string $currentDate;

    private LogSerializerService $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentDate = date('Y-m-d');
        $this->testLogPath = sys_get_temp_dir().'/logger_test_'.uniqid();
        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $this->serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $this->serializer);
        $queryTask = new QueryLogsTask($pathService, $this->serializer);
        $streamTask = new StreamLogsTask($pathService, $this->serializer);

        $this->logger = new Logger($writeTask, $queryTask, $streamTask);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testLogPath)) {
            $this->deleteDirectory($this->testLogPath);
        }
        parent::tearDown();
    }

    private function createLogDataRecord(string $type, array $payloadData): LogDataRecord
    {
        $payload = new MixedPayloadCollection;
        foreach ($payloadData as $item) {
            $payload->add($item);
        }

        return new LogDataRecord(type: $type, payload: $payload);
    }

    // ==================== TESTS EXISTANTS ====================

    public function test_complete_logging_workflow(): void
    {
        $this->logger->info($this->currentDate.'T10:26:00Z', $this->createLogDataRecord('user_login', [1, '127.0.0.1']));
        $this->logger->info($this->currentDate.'T11:26:00Z', $this->createLogDataRecord('user_login', [2, '127.0.0.1']));
        $this->logger->error($this->currentDate.'T12:26:00Z', $this->createLogDataRecord('payment_failed', [123, 99.99]));

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T13:00:00Z',
            type: 'user_login',
        ));

        $this->assertSame(2, $results->count());

        $streamResults = $this->logger->stream($this->currentDate);
        $this->assertSame(3, $streamResults->count());
    }

    public function test_logs_are_persisted_between_instances(): void
    {
        $this->logger->info($this->currentDate.'T10:26:00Z', $this->createLogDataRecord('test', ['persisted']));

        $config = new LoggerConfig($this->testLogPath, 30);
        $pathService = new LogPathService($config);
        $serializer = new LogSerializerService;

        $writeTask = new WriteLogTask($pathService, $serializer);
        $queryTask = new QueryLogsTask($pathService, $serializer);
        $streamTask = new StreamLogsTask($pathService, $serializer);

        $newLogger = new Logger($writeTask, $queryTask, $streamTask);

        $results = $newLogger->query(new LogQueryRecord(
            from: $this->currentDate.'T00:00:00Z',
            to: $this->currentDate.'T23:59:59Z',
        ));

        $this->assertSame(1, $results->count());
    }

    public function test_can_log_and_query_complex_data(): void
    {
        $payload = new MixedPayloadCollection;
        $payload->add('order_created');
        $payload->add(12345);
        $payload->add(79.98);
        $payload->add(true);

        $logData = new LogDataRecord(type: 'order_created', payload: $payload);

        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T00:00:00Z',
            to: $this->currentDate.'T23:59:59Z',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $this->assertSame('order_created', $log->data->type);
        $this->assertContains(12345, $log->data->payload->all());
        $this->assertContains(79.98, $log->data->payload->all());
        $this->assertContains(true, $log->data->payload->all());
    }

    public function test_multiple_log_levels_are_correctly_stored(): void
    {
        $this->logger->debug($this->currentDate.'T10:26:00Z', $this->createLogDataRecord('debug_msg', []));
        $this->logger->info($this->currentDate.'T10:27:00Z', $this->createLogDataRecord('info_msg', []));
        $this->logger->warning($this->currentDate.'T10:28:00Z', $this->createLogDataRecord('warning_msg', []));
        $this->logger->error($this->currentDate.'T10:29:00Z', $this->createLogDataRecord('error_msg', []));

        $debugResults = $this->logger->query(new LogQueryRecord(level: LogLevel::DEBUG));
        $this->assertSame(1, $debugResults->count());
        if ($debugResults->isNotEmpty()) {
            $this->assertSame('debug_msg', $debugResults->firstItem()->data->type);
        }

        $infoResults = $this->logger->query(new LogQueryRecord(level: LogLevel::INFO));
        $this->assertSame(1, $infoResults->count());
        if ($infoResults->isNotEmpty()) {
            $this->assertSame('info_msg', $infoResults->firstItem()->data->type);
        }

        $warningResults = $this->logger->query(new LogQueryRecord(level: LogLevel::WARNING));
        $this->assertSame(1, $warningResults->count());
        if ($warningResults->isNotEmpty()) {
            $this->assertSame('warning_msg', $warningResults->firstItem()->data->type);
        }

        $errorResults = $this->logger->query(new LogQueryRecord(level: LogLevel::ERROR));
        $this->assertSame(1, $errorResults->count());
        if ($errorResults->isNotEmpty()) {
            $this->assertSame('error_msg', $errorResults->firstItem()->data->type);
        }
    }

    public function test_large_payload_logging(): void
    {
        $largePayload = new MixedPayloadCollection;
        for ($i = 0; $i < 100; $i++) {
            $largePayload->add($i);
        }

        $logData = new LogDataRecord(type: 'large_payload', payload: $largePayload);

        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
            type: 'large_payload',
        ));

        $this->assertSame(1, $results->count());
        $this->assertCount(100, $results->firstItem()->data->payload->all());
    }

    public function test_query_by_date_range_boundaries(): void
    {
        $this->logger->info($this->currentDate.'T10:00:00Z', $this->createLogDataRecord('boundary_test', []));
        $this->logger->info($this->currentDate.'T10:00:01Z', $this->createLogDataRecord('boundary_test', []));

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:00:00Z',
        ));

        $this->assertSame(1, $results->count());
    }

    // ==================== TESTS AVEC DONNÉES COMPLEXES (stdClass à la lecture) ====================

    public function test_log_with_record_in_payload(): void
    {
        // Créer un Record personnalisé
        $userRecord = new class(1, 'John Doe') extends AbstractRecord
        {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {}

            public function toArray(): array
            {
                return ['id' => $this->id, 'name' => $this->name];
            }
        };

        $payload = new MixedPayloadCollection;
        $payload->add('user_created');
        $payload->add($userRecord);
        $payload->add(true);

        $logData = new LogDataRecord(type: 'user', payload: $payload);
        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
            type: 'user',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $this->assertSame('user', $log->data->type);
        $this->assertSame('user_created', $log->data->payload->firstItem());

        // À la lecture, le Record est devenu un objet stdClass
        $serializedRecord = $log->data->payload->toArray()[1];
        $this->assertIsObject($serializedRecord);
        $this->assertEquals(1, $serializedRecord->id);
        $this->assertEquals('John Doe', $serializedRecord->name);
    }

    public function test_log_with_multiple_records_in_payload(): void
    {
        $record1 = new class(1, 'Product A') extends AbstractRecord
        {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {}

            public function toArray(): array
            {
                return ['id' => $this->id, 'name' => $this->name];
            }
        };

        $record2 = new class(2, 'Product B') extends AbstractRecord
        {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {}

            public function toArray(): array
            {
                return ['id' => $this->id, 'name' => $this->name];
            }
        };

        $payload = new MixedPayloadCollection;
        $payload->add('products_list', $record1, $record2);

        $logData = new LogDataRecord(type: 'products', payload: $payload);
        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('products_list', $payloadArray[0]);

        // Les Records deviennent des stdClass à la lecture
        $this->assertIsObject($payloadArray[1]);
        $this->assertEquals(1, $payloadArray[1]->id);
        $this->assertEquals('Product A', $payloadArray[1]->name);

        $this->assertIsObject($payloadArray[2]);
        $this->assertEquals(2, $payloadArray[2]->id);
        $this->assertEquals('Product B', $payloadArray[2]->name);
    }

    public function test_log_with_nested_typed_records_collection(): void
    {
        $nestedCollection = new TypedRecords('int');
        $nestedCollection->add(100, 200, 300);

        $payload = new MixedPayloadCollection;
        $payload->add('nested_data');
        $payload->add($nestedCollection);
        $payload->add('end');

        $logData = new LogDataRecord(type: 'nested_test', payload: $payload);
        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('nested_data', $payloadArray[0]);
        // La collection TypedRecords devient un stdClass à la lecture
        $this->assertIsObject($payloadArray[1]);
        // stdClass avec des propriétés numériques (0, 1, 2)
        $this->assertEquals(100, $payloadArray[1]->{0});
        $this->assertEquals(200, $payloadArray[1]->{1});
        $this->assertEquals(300, $payloadArray[1]->{2});
        $this->assertSame('end', $payloadArray[2]);
    }

    public function test_log_with_mixed_records_and_typed_records(): void
    {
        $userRecord = new class(1, 'John Doe') extends AbstractRecord
        {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {}

            public function toArray(): array
            {
                return ['id' => $this->id, 'name' => $this->name];
            }
        };

        $tagsCollection = new TypedRecords('string');
        $tagsCollection->add('premium', 'vip', 'active');

        $payload = new MixedPayloadCollection;
        $payload->add('user_profile', $userRecord, $tagsCollection, 'metadata');

        $logData = new LogDataRecord(type: 'profile', payload: $payload);
        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame('user_profile', $payloadArray[0]);

        // Le Record devient stdClass
        $this->assertIsObject($payloadArray[1]);
        $this->assertEquals(1, $payloadArray[1]->id);
        $this->assertEquals('John Doe', $payloadArray[1]->name);

        // La collection TypedRecords devient stdClass avec propriétés numériques
        $this->assertIsObject($payloadArray[2]);
        $this->assertEquals('premium', $payloadArray[2]->{0});
        $this->assertEquals('vip', $payloadArray[2]->{1});
        $this->assertEquals('active', $payloadArray[2]->{2});

        $this->assertSame('metadata', $payloadArray[3]);
    }

    public function test_chaining_payload_add_with_multiple_types(): void
    {
        $payload = new MixedPayloadCollection;

        // Chaining avec multiple valeurs
        $payload->add('start')->add(42, 'middle', true)->add('end');

        $logData = new LogDataRecord(type: 'chaining_test', payload: $payload);
        $this->logger->info($this->currentDate.'T10:26:00Z', $logData);

        $results = $this->logger->query(new LogQueryRecord(
            from: $this->currentDate.'T10:00:00Z',
            to: $this->currentDate.'T10:59:59Z',
        ));

        $this->assertSame(1, $results->count());

        $log = $results->firstItem();
        $payloadArray = $log->data->payload->toArray();

        $this->assertSame(['start', 42, 'middle', true, 'end'], $payloadArray);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
