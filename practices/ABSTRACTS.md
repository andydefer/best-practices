
# Principe d'usage des Abstract Class (Version finale)

## 1. Définition

Une **Abstract Class** est une classe qui ne peut pas être instanciée directement. Elle sert de modèle pour d'autres classes, fournissant une implémentation partielle et des méthodes abstraites que les classes filles doivent implémenter.

```
Abstract Class → Modèle partiel → Implémentation commune → Contrat partiel
```

```php
abstract class AbstractRepository
{
    abstract protected function getModelClass(): string;
    
    public function find(int $id): ?Model
    {
        $class = $this->getModelClass();
        return $class::find($id);
    }
    
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
}
```

---

## 2. Problématique à laquelle les Abstract Class répondent

| Problème | Solution |
|----------|----------|
| **Code dupliqué** | Factorisation dans la classe abstraite |
| **Contrat sans implémentation** | Méthodes abstraites imposées |
| **Comportement commun** | Méthodes concrètes réutilisables |
| **Template method** | Structure d'algorithme définie |

---

## 3. Règles fondamentales

### 3.1 Nommage

```
Abstract{Entity}
```

| Classe abstraite | Classe fille |
|------------------|--------------|
| `AbstractRepository` | `UserRepository` |
| `AbstractAction` | `ShowUserAction` |
| `AbstractRecord` | `UserRecord` |
| `AbstractData` | `UserData` |

```php
// ✅ BON
abstract class AbstractRepository { ... }
abstract class AbstractAction { ... }
abstract class AbstractRecord { ... }

// ❌ MAUVAIS
class BaseRepository { ... }
class RepositoryBase { ... }
```

### 3.2 Localisation

```
app/Abstracts/Abstract{Entity}.php
```

```
app/Abstracts/
├── AbstractRepository.php
├── AbstractAction.php
├── AbstractRecord.php
└── AbstractData.php
```

---

## 4. Conventions de casse (⚠️ STRICTES)

> **Les conventions de casse sont OBLIGATOIRES et dépendent du type de classe.**

| Type de classe | Convention | Exemple |
|----------------|------------|---------|
| **Record** | `snake_case` pour les propriétés | `$user_id`, `$user_name` |
| **Data** | `camelCase` pour les propriétés | `$userId`, `$userName` |
| **Value Object** | `camelCase` pour les propriétés | `$emailAddress`, `$domain` |
| **Enum** | `SCREAMING_SNAKE_CASE` pour les cas | `UserRole::ADMIN` |

### 4.1 Pourquoi ces conventions ?

| Convention | Utilisation | Outil d'hydratation |
|------------|-------------|---------------------|
| `snake_case` (Record) | Communication interne | `StrictDataObject` (préserve la casse) |
| `camelCase` (Data) | Réponses API | `DataObject` (normalise en camelCase) |

```php
// ✅ BON - Record (snake_case) avec StrictDataObject
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// Hydratation avec StrictDataObject
$user = UserRecord::from([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'user_email' => 'john@example.com'
]);

// ✅ BON - Data (camelCase) avec DataObject
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
    ) {}
}

// Hydratation avec DataObject
$userData = UserData::from([
    'userId' => 1,
    'userName' => 'John Doe',
    'userEmail' => 'john@example.com'
]);
```

---

## 5. Méthodes abstraites

> **Une méthode abstraite définit un contrat que les classes filles DOIVENT implémenter.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;

// ✅ BON - Méthode abstraite obligatoire
abstract class AbstractRepository
{
    abstract protected function getModelClass(): string;
    abstract public function find(int $id): ?Model;
}

// ✅ BON - Méthode abstraite avec paramètres typés
abstract class AbstractTask
{
    abstract public function execute(AbstractRecord $record): AbstractData;
}

// ❌ MAUVAIS - Méthode abstraite trop spécifique
abstract class AbstractRepository
{
    abstract public function findUserByEmail(string $email): ?UserRecord;
}
```

---

## 6. Méthodes finales

> **Les méthodes finales ne peuvent pas être surchargées par les classes filles. Elles sont utilisées pour les comportements qui ne doivent pas changer.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Logger\Contracts\LoggerInterface;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// ✅ BON - Méthode finale pour un comportement critique
abstract class AbstractRepository
{
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
    
    final public function save(AbstractRecord $record): Model
    {
        $result = $this->doSave($record);
        $this->logSave($record);
        
        return $result;
    }
    
    abstract protected function doSave(AbstractRecord $record): Model;
    abstract protected function logSave(AbstractRecord $record): void;
}

// ❌ MAUVAIS - Méthode finale qui devrait être personnalisable
abstract class AbstractRepository
{
    final public function find(int $id): ?Model
    {
        return $this->newModel()->find($id);
    }
}
```

---

## 7. Composition via Services (⚠️ RÈGLE MAJEURE)

> **⚠️ L'usage des traits est INTERDIT. On pratique la COMPOSITION via des Services injectés dans le constructeur.**

### 7.1 Pourquoi les traits sont interdits

| Problème | Explication |
|----------|-------------|
| **Couplage implicite** | Le trait dépend de l'état de la classe qui l'utilise |
| **Difficulté de test** | Un trait ne peut pas être mocké isolément |
| **Conflits de noms** | Deux traits peuvent avoir des méthodes avec le même nom |
| **Violation du SRP** | Les traits ajoutent des responsabilités de manière cachée |

### 7.2 Solution : Services injectés

```php
// ❌ MAUVAIS - Trait (interdit)
trait HasLogging
{
    protected function logAction(string $action, array $payload): void
    {
        // ...
    }
}

// ✅ BON - Service injecté (composition)
final class LoggingService
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}
    
    public function logAction(string $action, StrictDataObject $payload): void
    {
        $logData = new LogDataRecord(
            type: 'action_executed',
            payload: $payload
        );
        
        $this->logger->info($logData);
    }
}

// Utilisation dans une classe abstraite
abstract class AbstractAction
{
    public function __construct(
        protected readonly LoggingService $logger,  // ✅ Injection
    ) {}
    
    final public function execute(StrictDataObject $input): ExecutionResult
    {
        $this->logger->logAction(static::class, $input);
        return $this->handle($input);
    }
    
    abstract protected function handle(StrictDataObject $input): ExecutionResult;
}
```

---

## 8. DESIGN PATTERNS AVEC CLASSES ABSTRAITES

### 8.1 Template Method Pattern

> **La classe abstraite définit l'algorithme, les classes filles implémentent les étapes variables.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;

// ✅ BON - Template method sans trait
abstract class AbstractTask
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
    ) {}
    
    final public function execute(AbstractRecord $record): AbstractData
    {
        $validated = $this->validate($record);
        $result = $this->process($validated);
        $this->loggingService->logAction(static::class, $result->toDataObject());
        
        return $result;
    }
    
    protected function validate(AbstractRecord $record): AbstractRecord
    {
        return $record;
    }
    
    abstract protected function process(AbstractRecord $record): AbstractData;
}

final class ProcessOrderTask extends AbstractTask
{
    private readonly OrderService $orderService;
    
    public function __construct(
        LoggerInterface $logger,
        LoggingService $loggingService,
        OrderService $orderService,
    ) {
        parent::__construct($logger, $loggingService);
        $this->orderService = $orderService;
    }
    
    protected function process(AbstractRecord $record): AbstractData
    {
        /** @var OrderRecord $record */
        $result = $this->orderService->processOrder($record);
        return OrderResultData::from($result);
    }
}
```

### 8.2 Factory Method Pattern

> **La classe abstraite déclare la méthode de création, les classes filles instancient la bonne classe concrète.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// ✅ BON - Factory method
abstract class AbstractRepository
{
    abstract protected function getModelClass(): string;
    
    public function find(int $id): ?Model
    {
        $modelClass = $this->getModelClass();
        return $modelClass::find($id);
    }
    
    public function create(StrictDataObject $data): Model
    {
        $modelClass = $this->getModelClass();
        return $modelClass::create($data->toArray());
    }
}

final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}

final class ProductRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Product::class;
    }
}
```

### 8.3 Strategy Pattern (version héritage)

> **La classe abstraite définit le contrat stratégique, les classes filles implémentent chaque variante.**

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;

// ✅ BON - Strategy pattern avec classe abstraite
abstract class AbstractPaymentStrategy
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
    ) {}
    
    abstract public function pay(MoneyRecord $amount, string $paymentMethod): PaymentResultData;
    
    protected function logPayment(PaymentResultData $result): void
    {
        $payload = new StrictDataObject([
            'strategy' => static::class,
            'transaction_id' => $result->transaction_id,
            'amount' => $result->amount_cents,
            'status' => $result->status,
        ]);
        
        $this->loggingService->logAction('payment_processed', $payload);
    }
}

final class CreditCardPaymentStrategy extends AbstractPaymentStrategy
{
    private readonly CreditCardService $creditCardService;
    
    public function __construct(
        LoggerInterface $logger,
        LoggingService $loggingService,
        CreditCardService $creditCardService,
    ) {
        parent::__construct($logger, $loggingService);
        $this->creditCardService = $creditCardService;
    }
    
    public function pay(MoneyRecord $amount, string $paymentMethod): PaymentResultData
    {
        $result = $this->creditCardService->charge($amount, $paymentMethod);
        $this->logPayment($result);
        
        return $result;
    }
}

final class PayPalPaymentStrategy extends AbstractPaymentStrategy
{
    private readonly PayPalService $payPalService;
    
    public function __construct(
        LoggerInterface $logger,
        LoggingService $loggingService,
        PayPalService $payPalService,
    ) {
        parent::__construct($logger, $loggingService);
        $this->payPalService = $payPalService;
    }
    
    public function pay(MoneyRecord $amount, string $paymentMethod): PaymentResultData
    {
        $result = $this->payPalService->processPayment($amount, $paymentMethod);
        $this->logPayment($result);
        
        return $result;
    }
}

final class CheckoutService
{
    private AbstractPaymentStrategy $paymentStrategy;
    
    public function setPaymentStrategy(AbstractPaymentStrategy $strategy): void
    {
        $this->paymentStrategy = $strategy;
    }
    
    public function processOrder(OrderRecord $order): PaymentResultData
    {
        return $this->paymentStrategy->pay(
            $order->total_amount,
            $order->payment_method
        );
    }
}
```

### 8.4 Observer Pattern avec Services

> **La classe abstraite fournit l'implémentation commune de gestion des observateurs, sans traits.**

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;

interface ObserverInterface
{
    public function update(DomainEvent $event): void;
}

// ✅ BON - Observer pattern sans trait
abstract class AbstractSubject
{
    /** @var ObserverInterface[] */
    private array $observers = [];
    
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
    ) {}
    
    public function attach(ObserverInterface $observer): void
    {
        $this->observers[spl_object_hash($observer)] = $observer;
        $this->logObserverAction('attached', $observer);
    }
    
    public function detach(ObserverInterface $observer): void
    {
        unset($this->observers[spl_object_hash($observer)]);
        $this->logObserverAction('detached', $observer);
    }
    
    protected function notify(DomainEvent $event): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event);
        }
        
        $this->logNotification($event);
    }
    
    private function logObserverAction(string $action, ObserverInterface $observer): void
    {
        $payload = new StrictDataObject([
            'subject' => static::class,
            'action' => $action,
            'observer' => $observer::class,
        ]);
        
        $this->loggingService->logAction('observer_action', $payload);
    }
    
    private function logNotification(DomainEvent $event): void
    {
        $payload = new StrictDataObject([
            'subject' => static::class,
            'event_type' => $event->getType(),
            'event_id' => $event->getId(),
        ]);
        
        $this->loggingService->logAction('notification_sent', $payload);
    }
}

final class UserService extends AbstractSubject
{
    private readonly UserRepository $userRepository;
    
    public function __construct(
        LoggerInterface $logger,
        LoggingService $loggingService,
        UserRepository $userRepository,
    ) {
        parent::__construct($logger, $loggingService);
        $this->userRepository = $userRepository;
    }
    
    public function createUser(UserRecord $userRecord): UserRecord
    {
        $user = $this->userRepository->create($userRecord);
        
        $event = new UserCreatedEvent($user->user_id, $user->user_email);
        $this->notify($event);
        
        return $user;
    }
}

final class EmailNotifier implements ObserverInterface
{
    private readonly EmailService $emailService;
    
    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }
    
    public function update(DomainEvent $event): void
    {
        if ($event instanceof UserCreatedEvent) {
            $this->emailService->sendWelcomeEmail(
                $event->getUserId(),
                $event->getUserEmail()
            );
        }
    }
}
```

---

## 9. Couplage d'une Abstract Class (⚠️ RÈGLE IMPORTANTE)

> **Une classe abstraite peut être couplée à une interface ou à des services injectés.**

### 9.1 Abstract Class + Interface

> **Utilisez ce couplage quand vous avez besoin d'un contrat commun ET d'une implémentation partielle.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;

// ✅ BON - Abstract class + Interface
interface RepositoryInterface
{
    public function find(int $id): ?Model;
    public function save(AbstractRecord $record): Model;
}

abstract class AbstractRepository implements RepositoryInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
    ) {}
    
    abstract protected function getModelClass(): string;
    abstract protected function doSave(AbstractRecord $record): Model;
    
    public function find(int $id): ?Model
    {
        $class = $this->getModelClass();
        return $class::find($id);
    }
    
    public function save(AbstractRecord $record): Model
    {
        $result = $this->doSave($record);
        
        $payload = new StrictDataObject([
            'repository' => static::class,
            'record_type' => $record::class,
        ]);
        
        $this->loggingService->logAction('repository_save', $payload);
        
        return $result;
    }
    
    final protected function newModel(): Model
    {
        $class = $this->getModelClass();
        return new $class();
    }
}

final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
    
    protected function doSave(AbstractRecord $record): Model
    {
        /** @var UserRecord $record */
        return $this->newModel()->updateOrCreate(
            ['user_id' => $record->user_id],
            $record->toArray()
        );
    }
}
```

### 9.2 Abstract Class + Interface + Service

> **Utilisez ce couplage quand vous avez besoin d'un contrat, d'implémentation partielle ET de services transversaux.**

```php
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Logger\Contracts\LoggerInterface;

// ✅ Interface (contrat)
interface Actionable
{
    public function run(StrictDataObject $input): AbstractData;
}

// ✅ Interface pour la validation
interface Validatable
{
    public function validate(StrictDataObject $input): ValidationResult;
}

// ✅ Service pour la validation
final class ValidationService
{
    public function validateRequired(StrictDataObject $input, array $requiredFields): ValidationResult
    {
        $missing = [];
        
        foreach ($requiredFields as $field) {
            if (!$input->has($field)) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            return ValidationResult::error('Missing required fields: ' . implode(', ', $missing));
        }
        
        return ValidationResult::success();
    }
}

// ✅ Abstract class avec services injectés
abstract class AbstractAction implements Actionable, Validatable
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
        protected readonly ValidationService $validationService,
        protected readonly array $requiredFields = [],
    ) {}
    
    abstract public function run(StrictDataObject $input): AbstractData;
    abstract public function validate(StrictDataObject $input): ValidationResult;
    
    final public function execute(StrictDataObject $input): AbstractData
    {
        $validation = $this->validate($input);
        
        if (!$validation->isValid()) {
            $this->loggingService->logAction('validation_failed', $input);
            throw new ValidationException($validation->getErrors());
        }
        
        $this->loggingService->logAction('executing', $input);
        
        return $this->run($input);
    }
}

// ✅ Implémentation finale
final class ShowUserAction extends AbstractAction
{
    private readonly UserService $userService;
    
    public function __construct(
        LoggerInterface $logger,
        LoggingService $loggingService,
        ValidationService $validationService,
        UserService $userService,
    ) {
        parent::__construct($logger, $loggingService, $validationService, ['user_id']);
        $this->userService = $userService;
    }
    
    public function validate(StrictDataObject $input): ValidationResult
    {
        return $this->validationService->validateRequired($input, $this->requiredFields);
    }
    
    public function run(StrictDataObject $input): AbstractData
    {
        $userId = $input->get('user_id');
        $userRecord = $this->userService->getUser($userId);
        
        if ($userRecord === null) {
            throw new NotFoundException('User not found');
        }
        
        return UserData::from($userRecord);
    }
}
```

---

## 10. Abstract Class vs Interface

| Abstract Class | Interface |
|----------------|-----------|
| Peut contenir de l'implémentation | ❌ Pas d'implémentation |
| Peut avoir des propriétés | ❌ Pas de propriétés |
| Une classe ne peut hériter que d'une seule | Une classe peut implémenter plusieurs |
| Peut avoir des méthodes privées/protégées | Toutes les méthodes sont publiques |
| Constructeur autorisé | ❌ Pas de constructeur |

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `Abstract{Entity}` |
| **Méthodes abstraites** | DOIVENT être implémentées |
| **Méthodes finales** | NE peuvent PAS être surchargées |
| **Propriétés** | Préférer `protected` ou injection |
| **Constructeur** | Peut être défini (pour services injectés) |
| **Instanciation** | ❌ Impossible |
| **Traits** | ❌ STRICTEMENT INTERDIT |
| **Composition** | ✅ Services injectés dans constructeur |
| **Record propriétés** | `snake_case` (StrictDataObject) |
| **Data propriétés** | `camelCase` (DataObject) |

---

## 12. Règle d'or

> **Une classe abstraite fournit une implémentation partielle commune. Elle ne contient JAMAIS de traits (pratique interdite). Tous les comportements transversaux sont injectés via des SERVICES dans le constructeur.**
>
> **Les conventions de casse sont STRICTES :**
> - **Record** : propriétés en `snake_case`, hydratation avec `StrictDataObject`
> - **Data** : propriétés en `camelCase`, hydratation avec `DataObject`
> - **Value Object** : propriétés en `camelCase`
> - **Enum** : cas en `SCREAMING_SNAKE_CASE`

```php
// ✅ L'Abstract Class parfaite
abstract class PerfectAbstract
{
    public function __construct(
        protected readonly LoggerInterface $logger,
        protected readonly LoggingService $loggingService,
        protected readonly ValidationService $validationService,
    ) {}
    
    abstract public function execute(StrictDataObject $input): AbstractData;
    
    final public function run(StrictDataObject $input): AbstractData
    {
        $this->loggingService->logAction(static::class, $input);
        return $this->execute($input);
    }
}

// ✅ Record associé (snake_case)
final class PerfectRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $record_id,
        public readonly string $record_name,
    ) {}
}

// ✅ Data associé (camelCase)
final class PerfectData extends AbstractData
{
    public function __construct(
        public readonly int $recordId,
        public readonly string $recordName,
    ) {}
    
   
}

// ✅ Utilisation
$record = PerfectRecord::from([
    'record_id' => 1,
    'record_name' => 'Test',
]);

$data = PerfectData::from($record);

// Hydratation Data (camelCase)
$dataFromArray = PerfectData::from([
    'recordId' => 1,
    'recordName' => 'Test',
]);
```

---