# Principe d'usage des Tasks

## 1. Définition

Une **Task** est un composant asynchrone qui encapsule une action unique ou récurrente. Elle est stockée sous forme de fichier JSON et exécutée via un poller CLI (commande `directive process-tasks`).

```
Task → Action unique ou récurrente → Stockage JSON → Exécution asynchrone via poller
```

```php
use AndyDefer\Task\AbstractTask;
use AndyDefer\Task\Records\TaskConfigRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class SendWelcomeEmailTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'send-welcome-email',
            description: 'Send welcome email to new user',
            maxAttempts: 3,
        );
    }

    protected function process(): void
    {
        $data = $this->payload->getPayload()->first();
        $email = $data->email;
        $name = $data->name;
        
        Mail::to($email)->send(new WelcomeEmail($name));
        $this->info("Welcome email sent to {$email}");
    }
}
```

---

## 2. Problématique à laquelle les Tasks répondent

| Problème | Solution Laravel Task |
|----------|----------------------|
| Dépendance à Redis/Beanstalkd | Stockage JSON - pas de base de données |
| Configuration complexe | Zéro configuration, prêt à l'emploi |
| Tests difficiles | Testable unitairement (pas de queue mock) |
| Pas de récurrence native | `delaySeconds` pour les tâches récurrentes |
| Pas de gestion des échecs | Retry automatique avec `maxAttempts` |

---

## 3. Les classes fondamentales

### 3.1 AbstractTask

La classe abstraite que **toute Task doit étendre** :

```php
<?php

namespace AndyDefer\Task;

use AndyDefer\Task\Records\TaskConfigRecord;
use AndyDefer\Task\Records\TaskPayloadRecord;

abstract class AbstractTask
{
    private TaskPayloadRecord $payload;
    private ?LoggerInterface $logger = null;
    private string $taskId;
    private string $signature;
    
    /**
     * Configuration de la tâche (obligatoire)
     */
    abstract public function getConfig(): TaskConfigRecord;
    
    /**
     * Logique métier principale (obligatoire)
     */
    abstract protected function process(): void;
    
    /**
     * Hook avant exécution (optionnel)
     */
    protected function before(): void {}
    
    /**
     * Hook après exécution (optionnel)
     */
    protected function after(bool $success, ?string $error = null): void {}
    
    /**
     * Point d'entrée principal (template method)
     */
    final public function execute(TaskPayloadRecord $payload): void
    {
        $this->payload = $payload;
        
        try {
            $this->before();
            $this->process();
            $this->after(true, null);
        } catch (\Throwable $e) {
            $this->after(false, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Logger des informations (logs structurés)
     */
    protected function info(string $message): void {}
    
    /**
     * Logger des erreurs
     */
    protected function error(string $message): void {}
    
    /**
     * Setters pour injection
     */
    public function setLogger(LoggerInterface $logger): self;
    public function setTaskId(string $id): self;
    public function setSignature(string $signature): self;
}
```

### 3.2 TaskConfigRecord

Configuration d'une tâche :

```php
use AndyDefer\Task\Records\TaskConfigRecord;

$config = new TaskConfigRecord(
    signature: 'clear-unconfirmed-orders',  // Identifiant unique
    description: 'Clear orders not confirmed after 30 minutes',
    delaySeconds: 300,   // Toutes les 5 minutes (0 = tâche unique)
    maxAttempts: 3,      // Nombre max de tentatives
    endAt: null,         // Date d'expiration (null = récurrente)
);
```

### 3.3 TaskPayloadRecord

Payload typé de la tâche :

```php
use AndyDefer\Task\Records\TaskPayloadRecord;
use AndyDefer\DomainStructures\Collections\Utility\StrictDataObjectCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$payload = new TaskPayloadRecord(
    type: 'clear_orders',
    payload: StrictDataObjectCollection::from([
        StrictDataObject::from(['minutes' => 30]),
    ]),
);
```

---

## 4. Le cycle de vie d'une tâche

```
execute(TaskPayloadRecord $payload)
    │
    ├── $this->payload = $payload
    │
    ├── before()                    ← Hook optionnel
    │
    ├── process()                   ← Logique métier (obligatoire)
    │
    ├── after($success, $error)     ← Hook optionnel
    │
    └── Log automatique (started/completed/failed)
```

### 4.1 Hooks disponibles

```php
final class MyTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'my-task',
            description: 'My task example',
        );
    }
    
    // Avant l'exécution - initialisation, vérifications
    protected function before(): void
    {
        $this->info("Starting task...");
    }
    
    // Logique métier (obligatoire)
    protected function process(): void
    {
        $data = $this->payload->getPayload()->first();
        // Votre code ici
    }
    
    // Après l'exécution - nettoyage, notifications
    protected function after(bool $success, ?string $error = null): void
    {
        if ($success) {
            $this->info('Task completed successfully');
        } else {
            $this->error("Task failed: {$error}");
        }
    }
}
```

---

## 5. Types de tâches

### 5.1 Tâche unique

S'exécute une seule fois, puis est archivée.

```php
final class SendWelcomeEmailTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'send-welcome-email',
            description: 'Send welcome email to new user',
            delaySeconds: 0,           // Pas de récurrence
            maxAttempts: 3,
        );
    }
    
    protected function process(): void
    {
        $data = $this->payload->getPayload()->first();
        Mail::to($data->email)->send(new WelcomeEmail($data->name));
        $this->info("Email sent to {$data->email}");
    }
}
```

### 5.2 Tâche récurrente

S'exécute à intervalles réguliers.

```php
final class CleanLogsTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'clean-logs',
            description: 'Clean old log files',
            delaySeconds: 3600,   // Toutes les heures
            maxAttempts: 3,
            endAt: null,          // Jamais (récurrente à vie)
        );
    }
    
    protected function process(): void
    {
        $deleted = $this->logCleaner->clean(30);
        $this->info("Deleted {$deleted} old log files");
    }
}
```

---

## 6. Enregistrer une tâche

### 6.1 Via TaskRegistryService

```php
use AndyDefer\Task\Services\TaskRegistryService;
use AndyDefer\Task\Records\TaskPayloadRecord;
use AndyDefer\DomainStructures\Collections\Utility\StrictDataObjectCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

final class TaskScheduler
{
    public function __construct(
        private readonly TaskRegistryService $registry,
    ) {}
    
    public function schedule(): void
    {
        $payload = new TaskPayloadRecord(
            type: 'clear_orders',
            payload: StrictDataObjectCollection::from([
                StrictDataObject::from(['minutes' => 30]),
            ]),
        );
        
        // Tâche récurrente (retourne la signature)
        $signature = $this->registry->register(
            taskClass: ClearUnconfirmedOrdersTask::class,
            payload: $payload,
            delaySeconds: 300,
        );
        
        // Tâche unique (retourne un UUID)
        $taskId = $this->registry->register(
            taskClass: SendWelcomeEmailTask::class,
            payload: $payload,
        );
    }
}
```

### 6.2 Dans un contrôleur

```php
final class UserController extends Controller
{
    public function __construct(
        private readonly TaskRegistryService $registry,
    ) {}
    
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userRepository->create($request->getRecord());
        
        // Enregistrement asynchrone
        $payload = new TaskPayloadRecord(
            type: 'welcome_email',
            payload: StrictDataObjectCollection::from([
                StrictDataObject::from([
                    'email' => $user->email,
                    'name' => $user->name,
                ]),
            ]),
        );
        
        $this->registry->register(
            taskClass: SendWelcomeEmailTask::class,
            payload: $payload,
        );
        
        return response()->json(['message' => 'User created, email will be sent'], 201);
    }
}
```

---

## 7. Stockage JSON

### 7.1 Structure des dossiers

```
storage/tasks/
├── pending/          # Tâches uniques en attente
│   └── {uuid}.json
├── recurring/        # Tâches récurrentes (une par signature)
│   └── clear-unconfirmed-orders.json
├── completed/        # Archive par date
│   └── Y-m-d/
│       └── {uuid}.json
└── grace_period/     # Traces des exécutions tardives
    └── {uuid}.json
```

### 7.2 Fichier d'une tâche

```json
{
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "signature": "clear-unconfirmed-orders",
    "class": "App\\Tasks\\ClearUnconfirmedOrdersTask",
    "payload": {
        "type": "clear_orders",
        "payload": [
            ["minutes", 30]
        ]
    },
    "status": "pending",
    "created_at": "2026-06-04T10:00:00+00:00",
    "start_at": "2026-06-04T10:00:00+00:00",
    "end_at": null,
    "delay_seconds": 300,
    "attempts": 0,
    "max_attempts": 3,
    "last_error": null
}
```

---

## 8. Exécution par lots (Batch Processing)

### 8.1 Commande CLI

```bash
# Traiter jusqu'à 50 tâches
./vendor/bin/directive process-tasks --limit=50

# Uniquement les tâches uniques
./vendor/bin/directive process-tasks --unique-only --limit=20

# Uniquement les tâches récurrentes
./vendor/bin/directive process-tasks --recurring-only --limit=10

# Avec affichage détaillé
./vendor/bin/directive process-tasks --verbose
```

### 8.2 Utilisation programmatique

```php
use AndyDefer\Task\Services\TaskBatchService;

final class TaskController extends Controller
{
    public function __construct(
        private readonly TaskBatchService $batch,
    ) {}
    
    public function process(): JsonResponse
    {
        $result = $this->batch->process(50);
        
        return response()->json([
            'unique_success' => $result->uniqueSuccess,
            'unique_failed' => $result->uniqueFailed,
            'recurring_success' => $result->recurringSuccess,
            'recurring_failed' => $result->recurringFailed,
            'errors' => $result->errors->toArray(),
        ]);
    }
}
```

---

## 9. Traitement des erreurs et réessais

### 9.1 Configuration des tentatives

```php
public function getConfig(): TaskConfigRecord
{
    return new TaskConfigRecord(
        signature: 'my-task',
        description: 'My task',
        delaySeconds: 300,
        maxAttempts: 5,  // 5 tentatives max
    );
}
```

### 9.2 Comportement en cas d'échec

```
Tentative 1 → Échec → attempts = 1, réenregistrée
Tentative 2 → Échec → attempts = 2, réenregistrée
Tentative 3 → Échec → attempts = 3, réenregistrée
Tentative 4 → Échec → attempts = 4, réenregistrée
Tentative 5 → Échec → ARCHIVE (FAILED)
```

---

## 10. Logging structuré

### 10.1 Logs automatiques

Le package logue automatiquement via `laravel-logger` :
- `task_started` - Début de l'exécution
- `task_completed` - Exécution réussie
- `task_failed` - Exécution échouée
- `task_output` - Messages `info()` et `error()`

### 10.2 Logs personnalisés

```php
protected function process(): void
{
    $this->info("Processing started");
    $this->info("Step 1 complete");
    
    if ($error) {
        $this->error("Something went wrong");
    }
}
```

---

## 11. Tests unitaires

### 11.1 Tester une tâche

```php
<?php

namespace Tests\Unit\Tasks;

use AndyDefer\DomainStructures\Collections\Utility\StrictDataObjectCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Task\Records\TaskPayloadRecord;
use App\Tasks\ClearUnconfirmedOrdersTask;
use Tests\TestCase;
use App\Models\Order;

final class ClearUnconfirmedOrdersTaskTest extends TestCase
{
    private ClearUnconfirmedOrdersTask $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->task = new ClearUnconfirmedOrdersTask();
        $this->task->setTaskId('test-123');
        $this->task->setSignature('clear-unconfirmed-orders');
    }

    public function test_execute_deletes_unconfirmed_orders(): void
    {
        // Arrange
        Order::create([
            'status' => 'pending',
            'created_at' => now()->subMinutes(40),
        ]);
        
        $payload = new TaskPayloadRecord(
            type: 'clear_orders',
            payload: StrictDataObjectCollection::from([
                StrictDataObject::from(['minutes' => 30]),
            ]),
        );
        
        // Act
        $this->task->execute($payload);
        
        // Assert
        $this->assertDatabaseCount('orders', 0);
    }
}
```

---

## 12. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | Étend `AbstractTask` |
| **Méthode obligatoire** | `getConfig(): TaskConfigRecord` |
| **Méthode obligatoire** | `process(): void` |
| **Hooks** | `before()` et `after()` (optionnels) |
| **Nommage** | `{Action}{Entity}Task` |
| **Payload** | `TaskPayloadRecord` avec `StrictDataObjectCollection` |
| **Stockage** | Fichiers JSON (pas de base de données) |
| **Exécution** | Asynchrone via `directive process-tasks` |
| **Récurrence** | `delaySeconds > 0` |
| **Tentatives** | `maxAttempts` dans la config |
| **Logging** | `$this->info()` / `$this->error()` |

---

## 13. Règle d'or

> **Une Task est une unité asynchrone stockée en JSON. Elle peut être unique ou récurrente. Elle est exécutée par le poller `directive process-tasks`. Les tentatives et la gestion des échecs sont automatiques.**

```php
// Tâche récurrente
final class CleanLogsTask extends AbstractTask
{
    public function getConfig(): TaskConfigRecord
    {
        return new TaskConfigRecord(
            signature: 'clean-logs',
            description: 'Clean old log files',
            delaySeconds: 3600,   // Toutes les heures
            maxAttempts: 3,
        );
    }
    
    protected function process(): void
    {
        $data = $this->payload->getPayload()->first();
        $days = $data->days ?? 30;
        
        $deleted = $this->logCleaner->clean($days);
        $this->info("Deleted {$deleted} old log files");
    }
}

// Enregistrement
$payload = new TaskPayloadRecord(
    type: 'clean_logs',
    payload: StrictDataObjectCollection::from([
        StrictDataObject::from(['days' => 30]),
    ]),
);

$registry->register(
    taskClass: CleanLogsTask::class,
    payload: $payload,
    delaySeconds: 3600,
);

// Exécution via cron
// * * * * * cd /var/www && php vendor/bin/directive process-tasks --limit=50
```
---