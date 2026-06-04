# Principe d'usage des Seeders (Version finale - Mise à jour)

## 1. Définition

Un **Seeder** est un composant qui permet de remplir la base de données avec des données **réalistes** pour le développement, les tests, ou les données initiales en production.

```
Seeder → Remplissage de la base de données → Données réalistes → Utilise les Repositories
```

```php
final class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function run(): void
    {
        // Nettoyer avant d'insérer
        $this->userRepository->truncate();
        
        // Données réalistes
        $users = [
            ['name' => 'Jean Dupont', 'email' => 'jean.dupont@example.com', 'role' => UserRole::ADMIN],
            ['name' => 'Marie Martin', 'email' => 'marie.martin@example.com', 'role' => UserRole::USER],
            ['name' => 'Dr. Pierre Durand', 'email' => 'pierre.durand@example.com', 'role' => UserRole::DOCTOR],
        ];
        
        foreach ($users as $user) {
            $this->userRepository->create(new UserCreateRecord(
                name: $user['name'],
                email: $user['email'],
                password: 'password',
                role: $user['role'],
            ));
        }
    }
}
```

---

## 2. Problématique à laquelle les Seeders répondent

| Problème | Solution |
|----------|----------|
| Base de données vide | Les seeders remplissent les données initiales |
| Données réalistes pour le développement | Données proches de la production |
| Données de test cohérentes | Données reproductibles |
| Environnement de démonstration | Données présentables |

---

## 3. Règle fondamentale (⚠️ IMMUABLE)

> **⚠️ Un seeder ne doit PAS utiliser les Factories Laravel. Les données doivent être réalistes, explicites et directement définies dans des tableaux ou des fichiers de configuration.**

```php
// ✅ BON - Données réalistes explicites
final class UserSeeder extends Seeder
{
    private const USERS = [
        ['name' => 'Jean Dupont', 'email' => 'jean.dupont@example.com', 'role' => UserRole::ADMIN],
        ['name' => 'Marie Martin', 'email' => 'marie.martin@example.com', 'role' => UserRole::USER],
        ['name' => 'Dr. Pierre Durand', 'email' => 'pierre.durand@example.com', 'role' => UserRole::DOCTOR],
    ];
    
    public function run(): void
    {
        foreach (self::USERS as $user) {
            $this->userRepository->create(new UserCreateRecord(...));
        }
    }
}

// ❌ MAUVAIS - Factory Laravel (données génériques)
final class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(10)->create();  // ❌ Interdit
    }
}
```

---

## 4. Sources de données

### 4.1 Données dans le seeder (≤ 10 éléments)

> **Pour 10 éléments ou moins, définissez les données directement dans le seeder.**

```php
final class RoleSeeder extends Seeder
{
    private const ROLES = [
        ['name' => 'Administrateur', 'slug' => 'admin', 'level' => 100],
        ['name' => 'Utilisateur', 'slug' => 'user', 'level' => 10],
        ['name' => 'Médecin', 'slug' => 'doctor', 'level' => 50],
        ['name' => 'Modérateur', 'slug' => 'moderator', 'level' => 75],
    ];
    
    public function __construct(
        private readonly RoleRepository $roleRepository,
    ) {}
    
    public function run(): void
    {
        $this->roleRepository->truncate();
        
        foreach (self::ROLES as $role) {
            $this->roleRepository->create(new RoleCreateRecord(
                name: $role['name'],
                slug: $role['slug'],
                level: $role['level'],
            ));
        }
    }
}
```

### 4.2 Données externes (> 10 éléments)

> **Pour plus de 10 éléments, créez un fichier de configuration dans `config/seeds/` qui retourne un tableau de données.**

```php
// config/seeds/users.php
<?php

return [
    [
        'name' => 'Jean Dupont',
        'email' => 'jean.dupont@example.com',
        'password' => 'password',
        'role' => 'admin',
        'created_at' => '2024-01-15 10:00:00',
    ],
    [
        'name' => 'Marie Martin',
        'email' => 'marie.martin@example.com',
        'password' => 'password',
        'role' => 'user',
        'created_at' => '2024-01-16 11:30:00',
    ],
    // ... jusqu'à 100+ utilisateurs réalistes
];

// database/seeders/UserSeeder.php
final class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function run(): void
    {
        $this->userRepository->truncate();
        
        $users = config('seeds.users');
        
        foreach ($users as $user) {
            $this->userRepository->create(new UserCreateRecord(
                name: $user['name'],
                email: $user['email'],
                password: $user['password'],
                role: UserRole::from($user['role']),
            ));
        }
    }
}
```

### 4.3 Organisation des fichiers

```
config/seeds/
├── users.php
├── roles.php
├── doctors.php
└── appointments.php

database/seeders/
├── DatabaseSeeder.php
├── UserSeeder.php
├── RoleSeeder.php
├── DoctorSeeder.php
└── AppointmentSeeder.php
```

---

## 5. Nettoyage avant insertion (⚠️ OBLIGATOIRE)

> **⚠️ Avant d'exécuter un seeder, vous DEVEZ nettoyer les données des tables concernées pour éviter les doublons.**

```php
final class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ProfileRepository $profileRepository,
    ) {}
    
    public function run(): void
    {
        // Nettoyer dans le bon ordre (respecter les clés étrangères)
        $this->profileRepository->truncate();
        $this->userRepository->truncate();
        
        // Insertion des données
        foreach (self::USERS as $user) {
            $this->userRepository->create(new UserCreateRecord(...));
        }
    }
}
```

### 5.1 Méthodes de nettoyage dans le Repository

```php
final class UserRepository
{
    public function truncate(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
    
    public function deleteAll(): void
    {
        User::query()->delete();
    }
    
    public function deleteByRole(UserRole $role): void
    {
        User::where('role', $role)->delete();
    }
}
```

---

## 6. Logique métier dans les Seeders (⚠️ AUTORISÉ)

> **⚠️ Les seeders PEUVENT contenir de la logique métier, utiliser des Tasks et des Services pour créer des données réalistes.**

```php
final class AppointmentSeeder extends Seeder
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly DoctorRepository $doctorRepository,
        private readonly UserRepository $userRepository,
        private readonly GenerateTimeSlotsTask $generateTimeSlots,
        private readonly BookingService $bookingService,
    ) {}
    
    public function run(): void
    {
        $this->appointmentRepository->truncate();
        
        $doctors = $this->doctorRepository->all();
        $patients = $this->userRepository->findByRole(UserRole::USER);
        
        foreach ($doctors as $doctor) {
            // Task pour générer des créneaux réalistes
            $slots = $this->generateTimeSlots->execute(new GenerateTimeSlotsRecord(
                doctorId: $doctor->id,
                startDate: now(),
                endDate: now()->addDays(30),
                durationMinutes: $doctor->consultation_duration,
            ));
            
            // Service pour créer des rendez-vous
            foreach ($patients->random(5) as $patient) {
                $slot = $slots->random();
                
                $this->bookingService->book(new BookAppointmentRecord(
                    patientId: $patient->id,
                    doctorId: $doctor->id,
                    slotId: $slot->id,
                ));
            }
        }
    }
}
```

---

## 7. Ordre d'exécution

> **L'ordre des seeders dans `DatabaseSeeder` est important pour respecter les contraintes de clés étrangères.**

```php
// database/seeders/DatabaseSeeder.php
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        $this->call([
            // 1. Tables sans dépendances
            RoleSeeder::class,
            PermissionSeeder::class,
            
            // 2. Tables qui dépendent des précédentes
            UserSeeder::class,
            DoctorSeeder::class,
            
            // 3. Tables qui dépendent de plusieurs
            AppointmentSeeder::class,
            ReviewSeeder::class,
        ]);
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
```

---

## 8. Règles de nommage

### 8.1 Nom de la classe

> **Le nom du seeder DOIT refléter la table ou l'entité qu'il remplit. Il se termine par `Seeder`.**

| Table/Entité | Nom du seeder |
|--------------|---------------|
| `users` | `UserSeeder` |
| `roles` | `RoleSeeder` |
| `appointments` | `AppointmentSeeder` |

```php
// ✅ BON
final class UserSeeder extends Seeder { ... }

// ❌ MAUVAIS
final class UsersTableSeeder extends Seeder { ... }
final class FillUsers extends Seeder { ... }
```

### 8.2 Localisation

```
database/seeders/
├── DatabaseSeeder.php
├── UserSeeder.php
├── RoleSeeder.php
├── DoctorSeeder.php
└── AppointmentSeeder.php
```

---

## 9. Méthode `run()` et console

> **Tout seeder DOIT avoir une méthode `run()` et peut utiliser `$this->command` pour afficher des messages.**

```php
public function run(): void
{
    $this->command->info('Seeding users...');
    
    $this->userRepository->truncate();
    
    foreach (self::USERS as $user) {
        $this->userRepository->create(new UserCreateRecord(...));
    }
    
    $this->command->info('Users seeded successfully!');
}
```

---

## 10. Exemples complets

### 10.1 Seeder simple (≤ 10 éléments)

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Records\UserCreateRecord;
use App\Repositories\UserRepository;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    private const USERS = [
        ['name' => 'Jean Dupont', 'email' => 'jean.dupont@example.com', 'role' => UserRole::ADMIN],
        ['name' => 'Marie Martin', 'email' => 'marie.martin@example.com', 'role' => UserRole::USER],
        ['name' => 'Dr. Pierre Durand', 'email' => 'pierre.durand@example.com', 'role' => UserRole::DOCTOR],
    ];
    
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function run(): void
    {
        $this->command->info('Seeding users...');
        
        $this->userRepository->truncate();
        
        foreach (self::USERS as $user) {
            $this->userRepository->create(new UserCreateRecord(
                name: $user['name'],
                email: $user['email'],
                password: 'password',
                role: $user['role'],
            ));
        }
        
        $this->command->info('Users seeded successfully!');
    }
}
```

### 10.2 Seeder avec données externes

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Records\UserCreateRecord;
use App\Repositories\UserRepository;
use Illuminate\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function run(): void
    {
        $this->command->info('Seeding users from config...');
        
        $this->userRepository->truncate();
        
        $users = config('seeds.users');
        
        if (!$users) {
            $this->command->error('No users data found in config/seeds/users.php');
            return;
        }
        
        foreach ($users as $user) {
            $this->userRepository->create(new UserCreateRecord(
                name: $user['name'],
                email: $user['email'],
                password: $user['password'] ?? 'password',
                role: UserRole::from($user['role']),
            ));
        }
        
        $this->command->info(count($users) . ' users seeded successfully!');
    }
}
```

### 10.3 Seeder avec logique métier et Task

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Records\GenerateTimeSlotsRecord;
use App\Repositories\AppointmentRepository;
use App\Repositories\DoctorRepository;
use App\Repositories\UserRepository;
use App\Tasks\GenerateTimeSlotsTask;
use Illuminate\Database\Seeder;

final class AppointmentSeeder extends Seeder
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly DoctorRepository $doctorRepository,
        private readonly UserRepository $userRepository,
        private readonly GenerateTimeSlotsTask $generateTimeSlots,
    ) {}
    
    public function run(): void
    {
        $this->command->info('Seeding appointments...');
        
        $this->appointmentRepository->truncate();
        
        $doctors = $this->doctorRepository->all();
        $patients = $this->userRepository->findByRole(UserRole::USER);
        
        foreach ($doctors as $doctor) {
            $slots = $this->generateTimeSlots->execute(new GenerateTimeSlotsRecord(
                doctorId: $doctor->id,
                startDate: now(),
                endDate: now()->addDays(30),
                durationMinutes: $doctor->consultation_duration,
            ));
            
            $slotsToBook = $slots->random(floor($slots->count() * 0.3));
            
            foreach ($slotsToBook as $slot) {
                $patient = $patients->random();
                
                $this->appointmentRepository->create(new AppointmentCreateRecord(
                    doctorId: $doctor->id,
                    patientId: $patient->id,
                    startTime: $slot->startTime,
                    endTime: $slot->endTime,
                    status: AppointmentStatus::CONFIRMED,
                ));
            }
        }
        
        $this->command->info('Appointments seeded successfully!');
    }
}
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Entity}Seeder` |
| **Méthode** | `run(): void` |
| **Accès direct aux Models** | ❌ Interdit (utiliser Repository) |
| **Factories Laravel** | ❌ Interdit |
| **Nettoyage avant insertion** | ✅ OBLIGATOIRE |
| **Logique métier** | ✅ Autorisé |
| **Tasks** | ✅ Autorisé |
| **Services** | ✅ Autorisé |
| **Données ≤ 10** | Dans le seeder |
| **Données > 10** | Dans `config/seeds/*.php` |
| **Messages console** | ✅ `$this->command->info()` |

---

## 12. Règle d'or

> **Un seeder remplit la base avec des données réalistes, pas des données génériques de Factory. Il utilise les Repositories pour l'accès aux données. Il peut contenir de la logique métier et utiliser des Tasks. Avant d'insérer, il nettoie les données existantes.**

```php
// Le seeder parfait
final class PerfectSeeder extends Seeder
{
    private const ITEMS = [
        ['name' => 'Item 1', 'value' => 100],
        ['name' => 'Item 2', 'value' => 200],
    ];
    
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly GenerateRelatedDataTask $generateTask,
    ) {}
    
    public function run(): void
    {
        $this->command->info('Seeding perfect data...');
        
        $this->itemRepository->truncate();
        
        foreach (self::ITEMS as $item) {
            $this->itemRepository->create(new ItemCreateRecord(
                name: $item['name'],
                value: $item['value'],
            ));
        }
        
        $this->generateTask->execute(new GenerateRelatedDataRecord(count: 50));
        
        $this->command->info('Perfect data seeded successfully!');
    }
}
```