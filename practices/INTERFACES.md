# Principe d'usage des Interfaces (Version finale)

## 1. Définition

Une **Interface** est un contrat qui définit les méthodes qu'une classe doit implémenter. Elle ne contient aucune implémentation, seulement des signatures de méthodes.

```
Interface → Contrat pur → Pas d'implémentation → Multiples implémentations
```

```php
interface RepositoryInterface
{
    public function find(int $id): ?Model;
    public function create(AbstractRecord $record): Model;
    public function delete(int $id): bool;
}
```

---

## 2. Problématique à laquelle les Interfaces répondent

| Problème | Solution |
|----------|----------|
| **Couplage fort** | L'interface découple l'implémentation |
| **Tests complexes** | On peut mocker l'interface facilement |
| **Multiples implémentations** | L'interface permet le polymorphisme |
| **Contrat explicite** | L'interface documente l'API |

```php
// ❌ MAUVAIS - Dépendance concrète
final class UserService
{
    public function __construct(
        private readonly UserRepository $userRepository,  // ❌ Dépendance concrète
    ) {}
}

// ✅ BON - Dépendance abstraite
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,  // ✅ Dépendance abstraite
    ) {}
}
```

---

## 3. Règles fondamentales

### 3.1 Nommage (⚠️ RÈGLE STRICTE)

> **Les interfaces se terminent par `able`.**

| Type | Convention | Exemple |
|------|------------|---------|
| **Interface** | `{Entity}able` | `Likeable`, `Commentable`, `Rateable`, `Likerable` |

```php
// ✅ BON
interface Likeable { ... }
interface Likerable { ... }
interface Commentable { ... }
interface Rateable { ... }

// ❌ MAUVAIS - Nommage incorrect
interface ILikeable { ... }          // ❌ Préfixe I
interface LikeableInterface { ... }  // ❌ Redondant
interface HasLikes { ... }           // ❌ Format réservé aux traits
```

### 3.2 Localisation

```
app/Contracts/
├── Likeable.php
├── Likerable.php
├── Commentable.php
├── Rateable.php
└── Taggable.php
```

---

## 4. La puissance des interfaces

> **La véritable puissance des interfaces apparaît quand on conçoit des contrats génériques qui peuvent être implémentés par n'importe quelle entité.**

### 4.1. Le problème de la dépendance concrète

```php
// ❌ MAUVAIS - Service dépend d'une classe concrète
final class LikeService
{
    public function addLike(Post $post, User $user): Like
    {
        return $post->likes()->create([
            'user_id' => $user->id,
            'likeable_id' => $post->id,
            'likeable_type' => $post->getMorphClass(),
        ]);
    }
}

// Problèmes :
// - Impossible d'aimer un Comment, un Video, un Article
// - Impossible qu'un autre type d'entité (Company, Group) aime quelque chose
// - Code dupliqué pour chaque type d'entité
```

### 4.2. La solution : Interfaces génériques

```php
// ✅ BON - Interface pour ce qui peut être aimé
interface Likeable
{
    public function likes(): MorphMany;
    public function getKey(): int;
    public function getMorphClass(): string;
}

// ✅ BON - Interface pour ce qui peut aimer
interface Likerable
{
    public function likesGiven(): MorphMany;
    public function getKey(): int;
    public function getMorphClass(): string;
}

// N'importe quel modèle peut implémenter ces interfaces
final class Post extends Model implements Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

final class Comment extends Model implements Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

final class User extends Model implements Likerable
{
    public function likesGiven(): MorphMany
    {
        return $this->morphMany(Like::class, 'liker');
    }
}

final class Company extends Model implements Likerable
{
    public function likesGiven(): MorphMany
    {
        return $this->morphMany(Like::class, 'liker');
    }
}
```

### 4.3. Service générique basé sur les interfaces

```php
// ✅ BON - Service générique qui fonctionne avec n'importe quel Likeable et Likerable
final class LikeService
{
    public function addLike(Likeable $likeable, Likerable $liker): Like
    {
        return $likeable->likes()->create([
            'liker_id' => $liker->getKey(),
            'liker_type' => $liker->getMorphClass(),
            'likeable_id' => $likeable->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
        ]);
    }
    
    public function removeLike(Likeable $likeable, Likerable $liker): bool
    {
        return $likeable->likes()
            ->where('liker_id', $liker->getKey())
            ->where('liker_type', $liker->getMorphClass())
            ->delete() > 0;
    }
    
    public function hasLiked(Likeable $likeable, Likerable $liker): bool
    {
        return $likeable->likes()
            ->where('liker_id', $liker->getKey())
            ->where('liker_type', $liker->getMorphClass())
            ->exists();
    }
    
    public function getLikesCount(Likeable $likeable): int
    {
        return $likeable->likes()->count();
    }
    
    public function getLikedItems(Likerable $liker): Collection
    {
        return $liker->likesGiven()->with('likeable')->get();
    }
}

// Utilisation - Le même service fonctionne pour toutes les combinaisons
$post = Post::find(1);
$comment = Comment::find(1);
$user = User::find(1);
$company = Company::find(1);

// Un utilisateur aime un post
$likeService->addLike($post, $user);

// Une entreprise aime un commentaire
$likeService->addLike($comment, $company);

// Un utilisateur aime un commentaire
$likeService->addLike($comment, $user);
```

### 4.4. Un même modèle peut être à la fois Likeable et Likerable

```php
// ✅ BON - Un modèle peut implémenter les deux interfaces
final class User extends Model implements Likeable, Likerable
{
    // En tant que Likeable (ce qu'on aime)
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    
    // En tant que Likerable (ce qui aime)
    public function likesGiven(): MorphMany
    {
        return $this->morphMany(Like::class, 'liker');
    }
}

// Un utilisateur peut aimer un autre utilisateur !
$user1 = User::find(1);
$user2 = User::find(2);

$likeService->addLike($user2, $user1);  // user1 aime user2
```

---

## 5. Repository avec interfaces

> **Pour maximiser le découplage, on utilise des repositories basés sur les interfaces.**

```php
// Interface pour le repository des Likeable
interface LikeableRepositoryInterface
{
    public function find(int $id, string $type): ?Likeable;
    public function getLikers(Likeable $likeable): Collection;
}

// Interface pour le repository des Likerable
interface LikerableRepositoryInterface
{
    public function find(int $id, string $type): ?Likerable;
    public function getLikedItems(Likerable $liker): Collection;
}

// Implémentation concrète
final class EloquentLikeableRepository implements LikeableRepositoryInterface
{
    private array $modelMap = [
        'post' => Post::class,
        'comment' => Comment::class,
        'user' => User::class,
    ];
    
    public function find(int $id, string $type): ?Likeable
    {
        $modelClass = $this->modelMap[$type] ?? null;
        if (!$modelClass) {
            return null;
        }
        
        return $modelClass::find($id);
    }
    
    public function getLikers(Likeable $likeable): Collection
    {
        return $likeable->likes()
            ->with('liker')
            ->get()
            ->pluck('liker');
    }
}

// Service qui utilise les repositories
final class SocialInteractionService
{
    public function __construct(
        private readonly LikeService $likeService,
        private readonly LikeableRepositoryInterface $likeableRepository,
        private readonly LikerableRepositoryInterface $likerableRepository,
    ) {}
    
    public function addLike(int $likeableId, string $likeableType, int $likerId, string $likerType): Like
    {
        $likeable = $this->likeableRepository->find($likeableId, $likeableType);
        $liker = $this->likerableRepository->find($likerId, $likerType);
        
        if (!$likeable || !$liker) {
            throw new NotFoundException();
        }
        
        return $this->likeService->addLike($likeable, $liker);
    }
}
```

---

## 6. Services avec injection dans le constructeur

> **Un service peut recevoir un objet implémentant une interface dans son constructeur pour travailler sur une entité spécifique.**

```php
// ✅ BON - Service spécialisé pour un Likeable spécifique
final class LikeableService
{
    private Likeable $likeable;
    
    public function __construct(Likeable $likeable)
    {
        $this->likeable = $likeable;
    }
    
    public function getLikesCount(): int
    {
        return $this->likeable->likes()->count();
    }
    
    public function addLike(Likerable $liker): Like
    {
        return $this->likeable->likes()->create([
            'liker_id' => $liker->getKey(),
            'liker_type' => $liker->getMorphClass(),
            'likeable_id' => $this->likeable->getKey(),
            'likeable_type' => $this->likeable->getMorphClass(),
        ]);
    }
    
    public function hasLiked(Likerable $liker): bool
    {
        return $this->likeable->likes()
            ->where('liker_id', $liker->getKey())
            ->where('liker_type', $liker->getMorphClass())
            ->exists();
    }
    
    public function getLikers(): Collection
    {
        return $this->likeable->likes()
            ->with('liker')
            ->get()
            ->pluck('liker');
    }
}

// Utilisation
$post = Post::find(1);
$likeableService = new LikeableService($post);
$likesCount = $likeableService->getLikesCount();
$likeableService->addLike($currentUser);
```

---

## 7. Services qui orchestrent plusieurs repositories

> **Les interfaces permettent à un service de travailler avec plusieurs repositories sans connaître leur implémentation concrète.**

```php
// Interfaces pour les repositories
interface DoctorRepositoryInterface
{
    public function find(int $id): ?DoctorRecord;
    public function getAvailableDoctors(DateTimeInterface $date): array;
}

interface AvailabilityRepositoryInterface
{
    public function findAvailableSlots(int $doctorId, DateTimeInterface $date): array;
    public function bookSlot(int $slotId, int $patientId): bool;
}

interface AppointmentRepositoryInterface
{
    public function create(AppointmentRecord $record): AppointmentRecord;
    public function findConflicting(int $doctorId, DateTimeInterface $date): ?AppointmentRecord;
}

// Service qui orchestre les trois repositories
final class AppointmentBookingService
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository,
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository,
    ) {}
    
    public function bookAppointment(int $doctorId, int $patientId, DateTimeInterface $date): AppointmentRecord
    {
        // Vérifier que le médecin existe
        $doctor = $this->doctorRepository->find($doctorId);
        if (!$doctor) {
            throw new NotFoundException('Doctor not found');
        }
        
        // Vérifier qu'il n'y a pas de conflit
        $conflict = $this->appointmentRepository->findConflicting($doctorId, $date);
        if ($conflict) {
            throw new ConflictException('Time slot already booked');
        }
        
        // Vérifier la disponibilité
        $slots = $this->availabilityRepository->findAvailableSlots($doctorId, $date);
        if (empty($slots)) {
            throw new UnavailableException('No available slots');
        }
        
        // Créer le rendez-vous
        $record = new AppointmentRecord(
            doctor_id: $doctorId,
            patient_id: $patientId,
            appointment_date: $date,
        );
        
        $appointment = $this->appointmentRepository->create($record);
        
        // Réserver le créneau
        $this->availabilityRepository->bookSlot($slots[0]['id'], $patientId);
        
        return $appointment;
    }
    
    public function getAvailableDoctors(DateTimeInterface $date): array
    {
        return $this->doctorRepository->getAvailableDoctors($date);
    }
}
```

---

## 8. Design patterns facilités par les interfaces

### 8.1. Strategy Pattern

> **L'interface définit le contrat des stratégies interchangeables.**

```php
// Interface Strategy
interface PaymentStrategyInterface
{
    public function pay(float $amount, Likerable $customer): PaymentResult;
}

// Stratégies concrètes
final class CreditCardPayment implements PaymentStrategyInterface
{
    public function pay(float $amount, Likerable $customer): PaymentResult
    {
        // Logique paiement carte bleue
        return new PaymentResult(success: true);
    }
}

final class PayPalPayment implements PaymentStrategyInterface
{
    public function pay(float $amount, Likerable $customer): PaymentResult
    {
        // Logique paiement PayPal
        return new PaymentResult(success: true);
    }
}

// Context qui utilise la stratégie
final class CheckoutService
{
    private PaymentStrategyInterface $paymentStrategy;
    
    public function setPaymentStrategy(PaymentStrategyInterface $strategy): void
    {
        $this->paymentStrategy = $strategy;
    }
    
    public function processPayment(float $amount, Likerable $customer): PaymentResult
    {
        return $this->paymentStrategy->pay($amount, $customer);
    }
}
```

### 8.2. Observer Pattern

> **L'interface définit le contrat des observateurs.**

```php
// Interface Observer
interface EventListenerInterface
{
    public function handle(DomainEvent $event): void;
}

// Observateurs concrets
final class SendWelcomeEmailListener implements EventListenerInterface
{
    public function handle(DomainEvent $event): void
    {
        /** @var UserCreatedEvent $event */
        Mail::send(new WelcomeEmail($event->user));
    }
}

final class CreateUserProfileListener implements EventListenerInterface
{
    public function handle(DomainEvent $event): void
    {
        /** @var UserCreatedEvent $event */
        Profile::create(['user_id' => $event->user->id]);
    }
}

// Service qui utilise les observateurs
final class EventDispatcherService
{
    /** @var EventListenerInterface[] */
    private array $listeners = [];
    
    public function addListener(EventListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }
    
    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}
```

### 8.3. Repository Pattern

> **L'interface définit le contrat d'accès aux données.**

```php
// Interface Repository
interface UserRepositoryInterface
{
    public function find(int $id): ?UserRecord;
    public function findByEmail(string $email): ?UserRecord;
    public function create(CreateUserRecord $record): UserRecord;
    public function update(int $id, UpdateUserRecord $record): UserRecord;
    public function delete(int $id): bool;
}

// Implémentation Eloquent
final class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?UserRecord
    {
        $user = User::find($id);
        return $user ? UserRecord::from($user->toArray()) : null;
    }
    
    // ... autres méthodes
}

// Implémentation Redis (pour le cache)
final class RedisUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?UserRecord
    {
        $cached = Redis::get("user:{$id}");
        return $cached ? UserRecord::from(json_decode($cached, true)) : null;
    }
    
    // ... autres méthodes
}

// Service qui utilise le repository
final class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}
    
    public function getUser(int $id): ?UserRecord
    {
        return $this->userRepository->find($id);
    }
}
```

---

## 9. Interface et Services (⚠️ RÈGLE IMPORTANTE)

> **⚠️ On n'injecte JAMAIS de service dans le constructeur d'un Model Eloquent.**
>
> **✅ C'est l'inverse : les services reçoivent les objets qui implémentent l'interface en paramètre de leurs méthodes ou dans leur constructeur.**

### 9.1. Pourquoi ne pas injecter de services dans les Models ?

| Raison | Explication |
|--------|-------------|
| **Couplage fort** | Le Model devient dépendant de services externes |
| **Violation SRP** | Le Model a trop de responsabilités |
| **Difficulté de test** | Tester un Model nécessite de mocker des services |
| **Hydratation complexe** | Laravel hydrate les Models via le constructeur |

### 9.2. Approche recommandée : Services basés sur les interfaces

```php
// ✅ BON - Model implémente l'interface (sans service injecté)
final class Post extends Model implements Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

final class User extends Model implements Likerable
{
    public function likesGiven(): MorphMany
    {
        return $this->morphMany(Like::class, 'liker');
    }
}

// ✅ BON - Service reçoit les interfaces en paramètre
final class LikeService
{
    public function addLike(Likeable $likeable, Likerable $liker): Like
    {
        return $likeable->likes()->create([
            'liker_id' => $liker->getKey(),
            'liker_type' => $liker->getMorphClass(),
            'likeable_id' => $likeable->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
        ]);
    }
}

// ✅ BON - Service spécialisé reçoit l'interface dans son constructeur
final class LikeableService
{
    private Likeable $likeable;
    
    public function __construct(Likeable $likeable)
    {
        $this->likeable = $likeable;
    }
    
    public function addLike(Likerable $liker): Like
    {
        return $this->likeable->likes()->create([
            'liker_id' => $liker->getKey(),
            'liker_type' => $liker->getMorphClass(),
            'likeable_id' => $this->likeable->getKey(),
            'likeable_type' => $this->likeable->getMorphClass(),
        ]);
    }
}
```

---

## 10. Exemples complets

### 10.1. Le modèle User peut être à la fois Likeable et Likerable

```php
// ✅ BON - User implémente les deux interfaces
final class User extends Model implements Likeable, Likerable
{
    // En tant que Likeable (ce qu'on aime)
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
    
    // En tant que Likerable (ce qui aime)
    public function likesGiven(): MorphMany
    {
        return $this->morphMany(Like::class, 'liker');
    }
}

// Service générique
final class SocialService
{
    private LikeService $likeService;
    
    public function __construct(LikeService $likeService)
    {
        $this->likeService = $likeService;
    }
    
    public function followUser(User $follower, User $followed): void
    {
        // Un utilisateur peut "aimer" un autre utilisateur
        $this->likeService->addLike($followed, $follower);
    }
    
    public function getFollowers(User $user): Collection
    {
        return $user->likes()
            ->with('liker')
            ->get()
            ->pluck('liker');
    }
    
    public function getFollowing(User $user): Collection
    {
        return $user->likesGiven()
            ->with('likeable')
            ->get()
            ->pluck('likeable');
    }
}
```

### 10.2. Service avec trois repositories

```php
// Interfaces
interface DoctorRepositoryInterface
{
    public function find(int $id): ?DoctorRecord;
    public function getAvailableDoctors(DateTimeInterface $date): array;
}

interface AvailabilityRepositoryInterface
{
    public function findAvailableSlots(int $doctorId, DateTimeInterface $date): array;
    public function bookSlot(int $slotId, int $patientId): bool;
}

interface AppointmentRepositoryInterface
{
    public function create(AppointmentRecord $record): AppointmentRecord;
    public function findConflicting(int $doctorId, DateTimeInterface $date): ?AppointmentRecord;
}

// Service qui orchestre les trois repositories
final class AppointmentBookingService
{
    public function __construct(
        private readonly DoctorRepositoryInterface $doctorRepository,
        private readonly AvailabilityRepositoryInterface $availabilityRepository,
        private readonly AppointmentRepositoryInterface $appointmentRepository,
    ) {}
    
    public function bookAppointment(int $doctorId, int $patientId, DateTimeInterface $date): AppointmentRecord
    {
        $doctor = $this->doctorRepository->find($doctorId);
        if (!$doctor) {
            throw new NotFoundException('Doctor not found');
        }
        
        $conflict = $this->appointmentRepository->findConflicting($doctorId, $date);
        if ($conflict) {
            throw new ConflictException('Time slot already booked');
        }
        
        $slots = $this->availabilityRepository->findAvailableSlots($doctorId, $date);
        if (empty($slots)) {
            throw new UnavailableException('No available slots');
        }
        
        $record = new AppointmentRecord(
            doctor_id: $doctorId,
            patient_id: $patientId,
            appointment_date: $date,
        );
        
        $appointment = $this->appointmentRepository->create($record);
        $this->availabilityRepository->bookSlot($slots[0]['id'], $patientId);
        
        return $appointment;
    }
}
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Entity}able` (ex: `Likeable`, `Likerable`) |
| **Méthodes** | Uniquement signatures (pas d'implémentation) |
| **Propriétés** | ❌ Interdites |
| **Constructeur** | ❌ Interdit |
| **Implémentation** | Une classe peut implémenter plusieurs interfaces |
| **Traits** | ❌ Déconseillés |
| **Services dans Models** | ❌ Ne pas injecter dans le constructeur des Models |
| **Services avec Models** | ✅ Les services reçoivent les interfaces en paramètre |
| **Paramètres** | ✅ Préférer les interfaces aux classes concrètes |

---

## 12. Règle d'or

> **Une interface est un contrat pur. Elle définit CE QUI doit être fait, pas COMMENT.**
>
> **⚠️ Pour maximiser le découplage et la réutilisabilité, préférez toujours les interfaces aux classes concrètes dans les signatures de méthodes.**
>
> **✅ Un service qui attend `Likeable` et `Likerable` fonctionnera avec n'importe quelles classes implémentant ces interfaces, qu'il s'agisse de User, Post, Comment, Company, etc.**
>
> **✅ Une même classe peut implémenter plusieurs interfaces (ex: User à la fois Likeable et Likerable).**
>
> **✅ On n'injecte JAMAIS de service dans le constructeur d'un Model Eloquent. C'est l'inverse : les services reçoivent les interfaces en paramètre.**

```php
// Interfaces
interface Likeable
{
    public function likes(): MorphMany;
    public function getKey(): int;
    public function getMorphClass(): string;
}

interface Likerable
{
    public function likesGiven(): MorphMany;
    public function getKey(): int;
    public function getMorphClass(): string;
}

// Modèles qui implémentent les interfaces
final class Post extends Model implements Likeable { ... }
final class Comment extends Model implements Likeable { ... }
final class User extends Model implements Likeable, Likerable { ... }
final class Company extends Model implements Likerable { ... }

// Service générique basé sur les interfaces
final class LikeService
{
    public function addLike(Likeable $likeable, Likerable $liker): Like
    {
        return $likeable->likes()->create([
            'liker_id' => $liker->getKey(),
            'liker_type' => $liker->getMorphClass(),
            'likeable_id' => $likeable->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
        ]);
    }
    
    public function hasLiked(Likeable $likeable, Likerable $liker): bool
    {
        return $likeable->likes()
            ->where('liker_id', $liker->getKey())
            ->where('liker_type', $liker->getMorphClass())
            ->exists();
    }
}

// Utilisation - Le même service fonctionne pour toutes les combinaisons
$post = Post::find(1);
$comment = Comment::find(1);
$user = User::find(1);
$company = Company::find(1);

$likeService->addLike($post, $user);      // User aime un Post
$likeService->addLike($comment, $company); // Company aime un Comment
$likeService->addLike($user, $user);       // User aime un autre User !
```
---