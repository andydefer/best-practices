# Principe d'usage des Repositories (Version finale)

## 1. Définition

Un **Repository** est un composant qui encapsule toutes les opérations d'accès aux données pour une entité donnée. Il sert d'interface unique entre l'application et la couche de persistence.

```
Repository → Accès aux données → Une seule entité → Cache les détails d'implémentation
```

```php
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}
```

---

## 2. Problématique à laquelle les Repositories répondent

| Problème | Sans Repository | Avec Repository |
|----------|-----------------|-----------------|
| **Logique DB dispersée** | `User::find()` partout dans les Services | Toute la logique DB est centralisée |
| **Tests complexes** | Mocker Eloquent est difficile | On mock le Repository (facile) |
| **Couplage à Laravel** | Impossible de changer de persistence | Un seul Repository à modifier |
| **Duplication de requêtes** | La même requête est écrite à 10 endroits | Une seule méthode dans le Repository |

```php
// ❌ MAUVAIS - Appel direct au Model dans un Service
final class UserService
{
    public function getUser(int $id): ?User
    {
        return User::find($id);  // ❌ Difficile à mocker
    }
}

// ✅ BON - Passage par Repository
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function getUser(int $id): ?User
    {
        return $this->userRepository->find($id);  // ✅ Facile à mocker
    }
}
```

---

## 3. La puissance du triptyque Repository + Record + Service

> **La combinaison des Repository, Records (snake_case) et Services (normalisation) permet de créer une architecture découplée, testable et réutilisable.**

### 3.1. Architecture complète

```
Base de données → Model Eloquent → Repository → Record (snake_case) → Service → Normalisation → Data (camelCase) → API
```

### 3.2. Exemple complet : De la DB à l'API

```php
// 1. Record (snake_case) - Communication interne
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
        public readonly UserRole $user_role,
        public readonly ?Iso8601DateTime $created_at = null,
    ) {}
}

// 2. Repository - Accès aux données
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
    
    public function findByEmail(string $email): ?UserRecord
    {
        $user = $this->newModel()->where('email', $email)->first();
        
        if (!$user) {
            return null;
        }
        
        // Transformation Model → Record
        return new UserRecord(
            user_id: $user->id,
            user_name: $user->name,
            user_email: $user->email,
            user_role: $user->role,
            created_at: $user->created_at ? new Iso8601DateTime($user->created_at) : null,
        );
    }
    
    public function create(UserRecord $record): UserRecord
    {
        return DB::transaction(function () use ($record) {
            $user = $this->newModel()->create([
                'name' => $record->user_name,
                'email' => $record->user_email,
                'password' => bcrypt($record->user_password),
                'role' => $record->user_role->value,
            ]);
            
            return new UserRecord(
                user_id: $user->id,
                user_name: $user->name,
                user_email: $user->email,
                user_role: $user->role,
                created_at: new Iso8601DateTime($user->created_at),
            );
        });
    }
}

// 3. Service métier - Logique et orchestration
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function getUser(int $id): ?UserRecord
    {
        return $this->userRepository->find($id);
    }
    
    public function createUser(UserRecord $record): UserRecord
    {
        $user = $this->userRepository->create($record);
        
        // Log structuré (snake_case)
        $payload = new StrictDataObject([
            'event' => 'user_created',
            'user_id' => $user->user_id,
            'user_email' => $user->user_email,
        ]);
        
        $this->logger->info(new LogDataRecord(type: 'user', payload: $payload));
        
        // Envoi d'email via un autre service
        $this->emailService->sendWelcome($user);
        
        return $user;
    }
}

// 4. Data (camelCase) - Réponse API
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $userRole,
        public readonly ?string $createdAt = null,
    ) {}
    
}

// 5. Action - Point d'entrée HTTP
final class ShowUserAction extends AbstractAction
{
    public function __construct(
        private readonly UserService $userService,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var ShowUserRecord $request */
        $userRecord = $this->userService->getUser($request->user_id);
        
        if (!$userRecord) {
            abort(404, 'User not found');
        }
        
        // Transformation Record → Data
        $userData = UserData::from($userRecord);
        
        // Normalisation automatique (Data normalizer conserve camelCase)
        return ResponseFactory::json($userData);
    }
}
```

---

## 4. Classe abstraite `AbstractRepository`

> **Tous les Repositories DOIVENT étendre `AbstractRepository` pour bénéficier des méthodes essentielles.**

### 4.1 Ce qu'offre `AbstractRepository`

| Méthode | Description |
|---------|-------------|
| `find(int $id): ?Model` | Trouver un enregistrement par son ID |
| `findBy(FindByRecord $record): Collection` | Trouver plusieurs enregistrements avec filtres |
| `paginate(PaginateRecord $record): LengthAwarePaginator` | Paginer les résultats |
| `create(AbstractRecord $record): Model` | Créer un enregistrement (via Record) |
| `update(int $id, AbstractRecord $record): Model` | Mettre à jour un enregistrement (via Record) |
| `delete(int $id): bool` | Supprimer un enregistrement |
| `count(?AbstractRecord $criteria = null): int` | Compter les enregistrements |
| `exists(AbstractRecord $criteria): bool` | Vérifier l'existence |

### 4.2 Implémentation concrète

```php
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }
}

// Utilisation avec Records (snake_case)
$user = $userRepository->find(1);
$users = $userRepository->findBy(new FindByRecord(limit: 10));
$newUser = $userRepository->create(new UserRecord(
    user_name: 'John Doe',
    user_email: 'john@example.com',
    user_role: UserRole::USER,
));
```

---

## 5. La Record unique pour Create et Update (snake_case)

> **Chaque Model a UNE SEULE Record qui définit tous ses champs `fillable` (tous optionnels pour l'update). Cette Record est utilisée à la fois pour la création et la mise à jour.**

```php
// La Record unique pour User (snake_case obligatoire)
final class UserRecord extends AbstractRecord
{
    use Hydratable;
    
    public function __construct(
        public readonly ?string $user_name = null,
        public readonly ?string $user_email = null,
        public readonly ?string $user_password = null,
        public readonly ?UserRole $user_role = null,
        public readonly ?UserStatus $user_status = null,
        public readonly ?Iso8601DateTime $created_at = null,
    ) {}
}

// Création - tous les champs nécessaires
$user = $userRepository->create(new UserRecord(
    user_name: 'John Doe',
    user_email: 'john@example.com',
    user_password: 'secret',
    user_role: UserRole::USER,
));

// Mise à jour - seuls les champs modifiés sont fournis
$user = $userRepository->update(1, new UserRecord(
    user_name: 'Jane Doe',  // seul le nom change
));
```

---

## 6. Méthodes personnalisées : find et check

> **Un Repository n'autorise que 2 types d'opérations personnalisées avec des prefixes stricts : `find` (lecture) et `check` (vérification).**

### 6.1 Règle fondamentale

**Les méthodes personnalisées (`find` et `check`) sont réservées aux cas complexes où vous avez besoin d'interagir avec d'autres Models ou relations.**

Pour les cas simples, utilisez les méthodes héritées d'`AbstractRepository` (`findBy`, `exists`, `count`).

```php
// ✅ BON - Cas simple : utilisation de findBy()
$users = $userRepository->findBy(new FindByRecord(
    filters: new UserFiltersRecord(user_email: 'john@example.com'),
    limit: 1,
));

// ✅ BON - Cas complexe : méthode personnalisée avec relation
public function findUserWithRecentPosts(int $user_id): ?User
{
    return $this->newModel()
        ->with(['posts' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(5);
        }])
        ->find($user_id);
}

// ❌ MAUVAIS - Méthode inutile (findBy peut le faire)
public function findUserByEmail(string $user_email): ?User  // ❌
{
    return $this->newModel()->where('email', $user_email)->first();
}
```

---

## 7. Transaction et atomicité (⚠️ RÈGLE D'OR)

> **Toute méthode qui modifie l'état de la base de données (create, update, delete) DOIT être exécutée dans une transaction.**

```php
// ✅ BON - Transaction dans AbstractRepository
public function create(AbstractRecord $record): Model
{
    return DB::transaction(fn() => $this->newModel()->create($record->toArray()));
}

// ❌ MAUVAIS - Pas de transaction
public function create(AbstractRecord $record): Model
{
    return $this->newModel()->create($record->toArray());
}
```

---

## 8. Gestion des relations et opérations multi-modèles (⚠️ RÈGLE IMPORTANTE)

> **Pour les opérations impliquant plusieurs Models, utilisez un SERVICE qui encapsule cette logique. Un Repository ne peut pas écrire dans un Model qui n'est pas lié à lui.**

```php
// ❌ MAUVAIS - UserRepository écrit dans OrderRepository
final class UserRepository extends AbstractRepository
{
    public function createUserWithOrder(UserRecord $user_record, OrderRecord $order_record): User
    {
        return DB::transaction(function () use ($user_record, $order_record) {
            $user = $this->create($user_record);
            $this->orderRepository->create($order_record);  // ❌ Violation SRP
            return $user;
        });
    }
}

// ✅ BON - Service dédié
final class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function registerUserWithOrder(UserRecord $user_record, OrderRecord $order_record): UserRecord
    {
        return DB::transaction(function () use ($user_record, $order_record) {
            $user = $this->userRepository->create($user_record);
            
            $order = $this->orderRepository->create(new OrderRecord(
                user_id: $user->user_id,
                order_total: $order_record->order_total,
            ));
            
            // Log structuré (snake_case)
            $payload = new StrictDataObject([
                'event' => 'user_registered_with_order',
                'user_id' => $user->user_id,
                'order_id' => $order->order_id,
            ]);
            
            $this->logger->info(new LogDataRecord(type: 'registration', payload: $payload));
            
            $this->emailService->sendWelcome($user);
            
            return $user;
        });
    }
}
```

---

## 9. Hiérarchie des responsabilités

| Composant | Responsabilité | Gestion des relations |
|-----------|----------------|----------------------|
| **Repository** | Une seule entité (CRUD de base) | ❌ Aucune |
| **Service** | Logique métier, orchestration multi-modèles | ✅ Orchestration |
| **Action** | Point d'entrée HTTP | ❌ Délègue aux Services |

---

## 10. Ce qu'un Repository NE peut PAS faire

| Interdiction | Pourquoi | Alternative |
|--------------|----------|-------------|
| **Logique métier** | Violation SRP | Déplacer dans Service |
| **Méthodes `createWith` ou `createAnd`** | Violation SRP | Créer un Service |
| **Écrire dans un autre Model** | Violation SRP | Créer un Service |
| **Méthodes triviales (`findUserByEmail`)** | `findBy` peut le faire | Utiliser `findBy()` |
| **Méthodes triviales (`checkUserIsActive`)** | `exists` peut le faire | Utiliser `exists()` |
| **Magic numbers** | Cache l'intention | Utiliser Record, Config ou Enum |

---

## 11. Exemple complet : Système de réservation

```php
// Records (snake_case)
final class DoctorRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $doctor_id,
        public readonly string $doctor_name,
        public readonly string $specialty,
    ) {}
}

final class AppointmentRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $appointment_id,
        public readonly int $doctor_id,
        public readonly int $patient_id,
        public readonly Iso8601DateTime $appointment_date,
        public readonly string $status,
    ) {}
}

// Repositories
final class DoctorRepository extends AbstractRepository
{
    protected function getModelClass(): string { return Doctor::class; }
    
    public function findAvailableDoctors(Iso8601DateTime $date): Collection
    {
        return $this->newModel()
            ->whereDoesntHave('appointments', function ($query) use ($date) {
                $query->where('appointment_date', $date->getValue());
            })
            ->get()
            ->map(fn($doctor) => new DoctorRecord(
                doctor_id: $doctor->id,
                doctor_name: $doctor->name,
                specialty: $doctor->specialty,
            ));
    }
}

final class AppointmentRepository extends AbstractRepository
{
    protected function getModelClass(): string { return Appointment::class; }
    
    public function create(AppointmentRecord $record): AppointmentRecord
    {
        $appointment = $this->newModel()->create([
            'doctor_id' => $record->doctor_id,
            'patient_id' => $record->patient_id,
            'appointment_date' => $record->appointment_date->getValue(),
            'status' => $record->status,
        ]);
        
        return new AppointmentRecord(
            appointment_id: $appointment->id,
            doctor_id: $appointment->doctor_id,
            patient_id: $appointment->patient_id,
            appointment_date: new Iso8601DateTime($appointment->appointment_date),
            status: $appointment->status,
        );
    }
}

// Service d'orchestration
final class AppointmentBookingService
{
    public function __construct(
        private readonly DoctorRepository $doctorRepository,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function bookAppointment(int $doctorId, int $patientId, Iso8601DateTime $date): AppointmentRecord
    {
        // Vérifier la disponibilité
        $availableDoctors = $this->doctorRepository->findAvailableDoctors($date);
        $isAvailable = $availableDoctors->contains(fn($d) => $d->doctor_id === $doctorId);
        
        if (!$isAvailable) {
            throw new UnavailableException('Doctor not available at this time');
        }
        
        // Créer le rendez-vous
        $appointment = $this->appointmentRepository->create(new AppointmentRecord(
            appointment_id: 0,
            doctor_id: $doctorId,
            patient_id: $patientId,
            appointment_date: $date,
            status: 'confirmed',
        ));
        
        // Log structuré
        $payload = new StrictDataObject([
            'event' => 'appointment_booked',
            'appointment_id' => $appointment->appointment_id,
            'doctor_id' => $appointment->doctor_id,
            'patient_id' => $appointment->patient_id,
            'appointment_date' => $appointment->appointment_date->getValue(),
        ]);
        
        $this->logger->info(new LogDataRecord(type: 'booking', payload: $payload));
        
        // Notification
        $this->notificationService->sendAppointmentConfirmation($appointment);
        
        return $appointment;
    }
}

// Data pour l'API (camelCase)
final class AppointmentData extends AbstractData
{
    public function __construct(
        public readonly int $appointmentId,
        public readonly int $doctorId,
        public readonly int $patientId,
        public readonly string $appointmentDate,
        public readonly string $status,
    ) {}
}

// Action
final class BookAppointmentAction extends AbstractAction
{
    public function __construct(
        private readonly AppointmentBookingService $bookingService,
    ) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        /** @var BookAppointmentRecord $request */
        $date = new Iso8601DateTime($request->appointment_date);
        
        $appointment = $this->bookingService->bookAppointment(
            doctorId: $request->doctor_id,
            patientId: $request->patient_id,
            date: $date,
        );
        
        $appointmentData = AppointmentData::from($appointment);
        
        return ResponseFactory::json($appointmentData, 201);
    }
}
```

---

## 12. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Héritage** | DOIT étendre `AbstractRepository` |
| **Nommage** | `{Entity}Repository` |
| **Record unique** | Une seule Record par Model pour create/update |
| **Record propriétés** | `snake_case` obligatoire |
| **Cas simples** | Utiliser `findBy()`, `count()`, `exists()` hérités |
| **Méthodes personnalisées** | Uniquement pour cas complexes |
| **Prefixes autorisés** | `find` (lecture) ou `check` (vérification) |
| **Transaction** | Toute méthode qui modifie la DB DOIT être dans une transaction |
| **Multi-modèles** | Utiliser un Service, jamais le Repository |
| **Magic numbers** | ❌ Interdit (utiliser Record, Config ou Enum) |

---

## 13. Règle d'or

> **Un Repository étend `AbstractRepository` et utilise une seule Record par Model pour create/update. Pour les cas simples, utilisez les méthodes héritées (`findBy`, `exists`, `count`). Pour les opérations multi-modèles, utilisez un SERVICE.**
>
> **⚠️ Les Records sont en `snake_case` pour la communication interne.**
> **⚠️ Les Data sont en `camelCase` pour les réponses API.**
> **⚠️ La normalisation automatique convertit les structures complexes.**

```php
// Architecture complète : Repository → Record → Service → Data → API

// 1. Record (snake_case)
final class UserRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $user_id,
        public readonly string $user_name,
        public readonly string $user_email,
    ) {}
}

// 2. Repository
final class UserRepository extends AbstractRepository
{
    protected function getModelClass(): string { return User::class; }
    
    public function findByEmail(string $email): ?UserRecord
    {
        $user = $this->newModel()->where('email', $email)->first();
        return $user ? new UserRecord(...) : null;
    }
}

// 3. Service
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {}
    
    public function getUser(int $id): ?UserRecord
    {
        $user = $this->userRepository->find($id);
        
        $payload = new StrictDataObject([
            'event' => 'user_fetched',
            'user_id' => $id,
            'found' => $user !== null,
        ]);
        
        $this->logger->info(new LogDataRecord(type: 'user', payload: $payload));
        
        return $user;
    }
}

// 4. Data (camelCase)
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
    ) {}
}

// 5. Action
final class ShowUserAction extends AbstractAction
{
    public function __construct(private readonly UserService $userService) {}
    
    protected function handle(AbstractRecord $request): ResponseFactory
    {
        $user = $this->userService->getUser($request->user_id);
        return ResponseFactory::json(UserData::from($user));
    }
}
```
---