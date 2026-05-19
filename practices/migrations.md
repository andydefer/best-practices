# Principe d'usage des Migrations (Version finale)

## 1. Définition

Une **Migration** est un fichier qui définit l'évolution de la structure de la base de données. Elle est versionnée et permet de maintenir la cohérence des schémas entre les environnements.

```
Migration → Évolution structurée → Versionnée → Réversible
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('name');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

## 2. Problématique à laquelle les Migrations répondent

| Problème | Solution |
|----------|----------|
| **Structure non versionnée** | Les migrations sont versionnées dans Git |
| **Équipe désynchronisée** | `php artisan migrate` synchronise tout le monde |
| **Rollback impossible** | `down()` permet d'annuler une migration |
| **Environnement dégradé** | Les migrations sont reproductibles à l'infini |

---

## 3. Règles fondamentales

### 3.1 Nommage des fichiers

```
{YYYY_MM_DD}_{HHMMSS}_{description}_table.php
```

```bash
# ✅ BON
2025_01_15_120000_create_users_table.php
2025_01_15_120001_create_orders_table.php
2025_01_15_120002_add_status_to_users_table.php

# ❌ MAUVAIS
create_users.php
users_migration.php
migration_1.php
```

### 3.2 Conventions de nommage des tables

| Type | Convention | Exemple |
|------|------------|---------|
| Table principale | `snake_case` pluriel | `users`, `orders`, `products` |
| Table pivot (many-to-many) | `snake_case` singulier, ordre alphabétique | `role_user`, `product_category` |
| Table de liaison (has-many) | `snake_case` pluriel | `user_addresses`, `order_items` |

```php
// ✅ BON
Schema::create('users', ...);           // pluriel
Schema::create('role_user', ...);       // pivot (ordre alphabétique)
Schema::create('order_items', ...);     // liaison

// ❌ MAUVAIS
Schema::create('user', ...);            // singulier
Schema::create('user_role', ...);       // ordre non alphabétique
```

### 3.3 Conventions de nommage des colonnes

| Type | Convention | Exemple |
|------|------------|---------|
| Clé primaire | `id` | `id` |
| Clé étrangère | `{table}_id` (singulier) | `user_id`, `order_id` |
| Timestamps | `created_at`, `updated_at` | `created_at`, `updated_at` |
| Soft deletes | `deleted_at` | `deleted_at` |
| Booléen | `is_{adjective}` | `is_active`, `is_verified` |
| Date simple | `{event}_at` | `published_at`, `verified_at` |
| Date/heure | `{event}_at` | `started_at`, `completed_at` |
| Enum | `{name}_type` | `user_type`, `payment_type` |

```php
// ✅ BON
$table->id();
$table->foreignId('user_id')->constrained();
$table->timestamps();
$table->softDeletes();
$table->boolean('is_active')->default(true);
$table->timestamp('published_at')->nullable();
$table->string('user_type');

// ❌ MAUVAIS
$table->increments('user_id');           // ❌ clé primaire non standard
$table->integer('id_user');              // ❌ format incorrect
$table->boolean('active');               // ❌ is_ manquant
$table->date('published');               // ❌ _at manquant
```

---

## 4. Gestion des Enums (⚠️ RÈGLE IMPORTANTE)

> **Ne jamais utiliser `->enum()` dans les migrations. Utilisez `->string()` avec un cast dans le Model vers un Enum PHP.**

### 4.1 Pourquoi éviter `->enum()` ?

| Problème | Solution |
|----------|----------|
| Figer les valeurs dans la base de données | Utiliser `->string()` et un Enum PHP |
| Ajouter une valeur demande une migration | L'Enum PHP se modifie sans migration |
| MySQL enum est problématique à maintenir | Le cast est flexible et évolutif |

### 4.2 Règle d'or

> **Pour tout champ `enum` dans la base de données :**
> 1. Le champ DOIT être une `string` dans la migration
> 2. Le nom de l'Enum PHP = `{Table}{Column}` en `PascalCase`
> 3. Le champ DOIT être casté dans le Model

```php
// Migration - champ enum stocké en string
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('status');  // ← string, pas enum
    $table->string('role');     // ← string, pas enum
    $table->timestamps();
});

// Model - cast vers l'Enum PHP
final class User extends Model
{
    protected $casts = [
        'status' => UserStatus::class,  // Table + Colonne = UserStatus
        'role' => UserRole::class,      // Table + Colonne = UserRole
    ];
}

// Enum PHP
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

enum UserRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case DOCTOR = 'doctor';
}
```

### 4.3 Convention de nommage des Enums

| Champ dans la table | Nom de l'Enum |
|---------------------|---------------|
| `users.status` | `UserStatus` |
| `users.role` | `UserRole` |
| `orders.state` | `OrderState` |
| `payments.method` | `PaymentMethod` |

```php
// ✅ BON - Nom = Table + Colonne
protected $casts = [
    'users.status' => UserStatus::class,
    'orders.state' => OrderState::class,
];

// ❌ MAUVAIS - Nom générique
protected $casts = [
    'status' => Status::class,   // ❌ Status est trop générique
    'state' => State::class,     // ❌ State est trop générique
];
```

---

## 5. Limite du nombre de champs (⚠️ RÈGLE IMPORTANTE)

> **Une table ne doit pas avoir plus de 10 champs. Si elle en a plus, créez une autre table et liez-la.**

### 5.1 Problématique

Une table avec trop de champs devient :
- Difficile à maintenir
- Source de conflits de verrouillage
- Complexe à indexer correctement

### 5.2 Solution : Séparer les responsabilités

```php
// ❌ MAUVAIS - Table users avec trop de champs
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('bio');           // ⚠️ Profil
    $table->string('avatar');        // ⚠️ Profil
    $table->string('address');       // ⚠️ Profil
    $table->string('city');          // ⚠️ Profil
    $table->string('postal_code');   // ⚠️ Profil
    $table->string('phone');         // ⚠️ Profil
    $table->string('website');       // ⚠️ Profil
    $table->text('settings');        // ⚠️ Préférences
    // ... 15+ champs
});

// ✅ BON - Tables séparées
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email');
    $table->string('status');
    $table->timestamps();
});

Schema::create('user_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
    $table->string('bio')->nullable();
    $table->string('avatar')->nullable();
    $table->string('address')->nullable();
    $table->string('city')->nullable();
    $table->string('postal_code')->nullable();
    $table->string('phone')->nullable();
    $table->string('website')->nullable();
    $table->timestamps();
});

Schema::create('user_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
    $table->json('settings')->nullable();
    $table->string('theme')->default('light');
    $table->string('language')->default('fr');
    $table->string('timezone')->default('UTC');
    $table->timestamps();
});
```

### 5.3 Comprendre la règle des 10 champs

La règle des 10 champs n'est pas une limite technique stricte.  
C'est une règle architecturale destinée à détecter les responsabilités transversales et à éviter les tables monolithiques.

Lorsqu'une table commence à dépasser 10 champs, cela signifie souvent qu'elle mélange plusieurs responsabilités métier ou qu'elle contient des données réutilisables par plusieurs entités du système.

L'objectif n'est donc pas simplement de "réduire le nombre de colonnes", mais de repérer les groupes de champs qui représentent en réalité une capacité métier autonome.

#### Exemple de champs transversaux

Certains champs apparaissent naturellement dans plusieurs modèles :

| Champ | Entités concernées |
|---|---|
| phone | User, Doctor, Clinic, Company |
| avatar/image | User, Clinic, Category, Product |
| address | User, Clinic, Supplier |
| settings | User, Organization, Team |
| metadata | Plusieurs entités |

Lorsque plusieurs modèles partagent les mêmes groupes de champs, cela indique souvent qu'il faut extraire ces données dans une structure dédiée et réutilisable.

#### Mauvaise approche : duplication

```php
doctors.phone
clinics.phone
companies.phone
users.phone
```

```php
doctors.avatar
clinics.avatar
categories.avatar
products.avatar
```

Cette approche :
- duplique la structure,
- duplique la validation,
- duplique la logique métier,
- rend l'évolution difficile.

#### Bonne approche : extraction d'une responsabilité transverse

```php
Schema::create('phones', function (Blueprint $table) {
    $table->id();
    $table->morphs('phoneable');
    $table->string('country_code');
    $table->string('number');
    $table->timestamps();
});
```

```php
Schema::create('media', function (Blueprint $table) {
    $table->id();
    $table->morphs('mediaable');
    $table->string('path');
    $table->string('type');
    $table->timestamps();
});
```

Cette approche permet :
- la mutualisation de la logique métier,
- la réutilisation des Traits,
- la centralisation des validations,
- la réduction de la duplication,
- l'évolution indépendante du sous-domaine.

#### Règle d'interprétation

Une table qui dépasse 10 champs doit déclencher une réflexion architecturale :

> "Ces champs appartiennent-ils réellement à cette entité, ou représentent-ils une responsabilité transverse réutilisable ailleurs ?"

Si un groupe de champs :
- est partagé entre plusieurs modèles,
- possède sa propre logique métier,
- possède son propre cycle de vie,
- peut évoluer indépendamment,

alors il doit probablement devenir une entité autonome, souvent via une relation polymorphique.

#### Objectif réel de la règle

La règle des 10 champs sert principalement à :

- détecter les responsabilités multiples,
- identifier les sous-domaines réutilisables,
- éviter les tables monolithiques,
- favoriser la mutualisation métier,
- préparer l'évolutivité du système.

---

## 6. Relations polymorphiques (⚠️ RÈGLE IMPORTANTE)

> **Privilégiez les relations polymorphiques plutôt que des clés étrangères directes quand deux modèles ou plus ont besoin de la même relation.**

### 6.1 Problématique

Sans polymorphique, on multiplie les colonnes ou les tables :

```php
// ❌ MAUVAIS - Une colonne par type de relation
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->text('content');
    $table->foreignId('user_id')->constrained();
    $table->foreignId('post_id')->constrained();
    $table->foreignId('product_id')->constrained();
});
```

### 6.2 Solution : Relations polymorphiques

```php
// ✅ BON - Table polymorphique
Schema::create('comments', function (Blueprint $table) {
    $table->id();
    $table->text('content');
    $table->morphs('commentable');  // commentable_id + commentable_type
    $table->timestamps();
});
```

### 6.3 Exemples d'utilisation des relations polymorphiques

| Situation | Avec polymorphique |
|-----------|---------------------|
| Likes sur posts, commentaires, produits | `likeable` (morphs) |
| Notes (ratings) sur plusieurs entités | `rateable` (morphs) |
| Tags sur plusieurs entités | `taggable` (morphs) |
| Images sur plusieurs entités | `imageable` (morphs) |

```php
// ✅ BON - Table polymorphique pour les likes
Schema::create('likes', function (Blueprint $table) {
    $table->id();
    $table->morphs('likeable');  // likeable_id + likeable_type
    $table->morphs('liker');     // liker_id + liker_type (User, Clinic, etc.)
    $table->timestamps();
    
    $table->unique(['likeable_id', 'likeable_type', 'liker_id', 'liker_type']);
});

// ✅ BON - Table polymorphique pour les notes
Schema::create('ratings', function (Blueprint $table) {
    $table->id();
    $table->morphs('rateable');
    $table->foreignId('user_id')->constrained();
    $table->integer('rating');
    $table->timestamps();
});

// ✅ BON - Table polymorphique pour les tags
Schema::create('taggables', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tag_id')->constrained()->onDelete('cascade');
    $table->morphs('taggable');
});
```

---

## 7. Traits pour les relations polymorphiques (⚠️ RÈGLE IMPORTANTE)

> **Toute table polymorphique DOIT fournir un trait réutilisable. Le trait ne contient que la déclaration de la relation, PAS de logique métier.**

### 7.1 Règle d'or

> **Un trait ne contient que la déclaration de la relation (`morphMany`, `morphTo`). Toute logique métier (`addLike`, `isLikedBy`) est déplacée dans une Task.**

```php
// ✅ BON - Trait HasLikes (uniquement la relation)
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

// ❌ MAUVAIS - Trait avec logique métier
trait HasLikes
{
    public function likes(): MorphMany { ... }
    
    public function addLike(Model $liker): void  // ❌ Logique métier
    {
        // ...
    }
    
    public function isLikedBy(Model $liker): bool  // ❌ Logique métier
    {
        // ...
    }
}
```

### 7.2 Convention de nommage

```
Has{Entity}  (ex: HasComments, HasLikes, HasRatings)
```

```php
// ✅ BON - Traits polymorphiques (uniquement les relations)
trait HasComments
{
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

trait HasRatings
{
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }
}
```

### 7.3 Logique métier : Dans les Tasks

```php
// Task pour gérer les likes
final class ToggleLikeTask extends AbstractTask
{
    public function __construct(
        private readonly LikeRepository $likeRepository,
    ) {}
    
    public function execute(ToggleLikeRecord $record): bool
    {
        $exists = $this->likeRepository->exists(new LikeExistsRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        
        if ($exists) {
            $this->likeRepository->delete(new LikeDeleteRecord(
                likeable: $record->likeable,
                liker: $record->liker,
            ));
            return false;
        }
        
        $this->likeRepository->create(new LikeCreateRecord(
            likeable: $record->likeable,
            liker: $record->liker,
        ));
        return true;
    }
}

// Utilisation dans une Action
final class TogglePostLikeAction extends AbstractAction
{
    public function __construct(
        private readonly ToggleLikeTask $toggleLike,
    ) {}
    
    public function run(int $postId, ToggleLikeRequest $request): JsonResponse
    {
        $post = $this->postRepository->find($postId);
        
        if (!$post) {
            return $this->json(null, 404);
        }
        
        $liked = $this->toggleLike->execute(new ToggleLikeRecord(
            likeable: $post,
            liker: auth()->user(),
        ));
        
        return $this->json(['liked' => $liked]);
    }
}
```

---

## 8. Éviter les champs compteurs (⚠️ RÈGLE IMPORTANTE)

> **Ne jamais utiliser de champs compteurs (`count`, `views`, `likes_count`). Utilisez plutôt des tables de relation.**

### 8.1 Problématique

```php
// ❌ MAUVAIS - Champ compteur
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->integer('stock_count');  // ❌ Champ compteur
    $table->integer('views');         // ❌ Champ compteur
});
```

### 8.2 Solution : Table de relation

```php
// ✅ BON - Table de mouvement pour les stocks
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->morphs('itemable');
    $table->string('type');        // 'in', 'out'
    $table->integer('quantity');
    $table->timestamps();
});

// ✅ BON - Table de likes polymorphique
Schema::create('likes', function (Blueprint $table) {
    $table->id();
    $table->morphs('likeable');
    $table->morphs('liker');
    $table->timestamps();
});
```

---

## 9. Clés étrangères (⚠️ RÈGLE IMPORTANTE)

> **Utilisez `foreignIdFor()` pour les clés étrangères, jamais `unsignedBigInteger()` seul.**

```php
// ✅ BON - Utilisation de foreignIdFor()
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
    $table->foreignIdFor(Product::class)->constrained()->onDelete('restrict');
    $table->timestamps();
});

// ❌ MAUVAIS - unsignedBigInteger sans contrainte
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');   // ❌ pas de foreign constraint
    $table->unsignedBigInteger('product_id'); // ❌ pas de foreign constraint
    $table->timestamps();
});
```

---

## 10. Garantir le rollback (⚠️ RÈGLE D'OR)

> **`php artisan migrate:rollback` doit toujours fonctionner. Toute migration DOIT être réversible.**

```php
// ✅ BON - Migration réversible
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};

// ❌ MAUVAIS - Migration irréversible
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->delete();  // ❌ Pas de down()
    }

    public function down(): void
    {
        // Vide !
    }
};
```

---

## 11. Types de colonnes recommandés

| Type PHP | Type MySQL | Exemple d'usage |
|----------|------------|-----------------|
| `int` | `BIGINT unsigned` | `$table->id()` |
| `int` | `INT unsigned` | `$table->integer('count')->unsigned()` |
| `string` | `VARCHAR(191)` | `$table->string('email', 191)->unique()` |
| `string` (long) | `TEXT` | `$table->text('description')` |
| `bool` | `TINYINT(1)` | `$table->boolean('is_active')` |
| `float` | `DECIMAL(10,2)` | `$table->decimal('price', 10, 2)` |
| `DateTime` | `DATETIME` | `$table->dateTime('started_at')` |
| `Date` | `DATE` | `$table->date('birth_date')` |
| `Enum` | `VARCHAR(255)` | `$table->string('status')` (casté en Enum) |
| `JSON` | `JSON` | `$table->json('metadata')` |
| `Morphs` | `unsignedBigInteger + string` | `$table->morphs('commentable')` |

---

## 12. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage fichier** | `{YYYY_MM_DD}_{HHMMSS}_{description}_table.php` |
| **Nommage table** | `snake_case` pluriel |
| **Nommage colonne** | `snake_case` |
| **Clé primaire** | `id()` |
| **Clé étrangère** | `foreignIdFor()` |
| **Timestamps** | `timestamps()` |
| **Soft deletes** | `softDeletes()` |
| **Booléen** | `is_{adjective}` |
| **Date** | `{event}_at` |
| **Enum** | `string` + cast dans Model |
| **Max champs par table** | 10 champs maximum |
| **Champs compteurs** | ❌ Interdit |
| **Enum MySQL** | ❌ Interdit |
| **Rollback** | ✅ Doit toujours fonctionner |
| **Relations polymorphiques** | ✅ Privilégier |
| **Trait polymorphique** | ✅ Uniquement la relation, pas de logique |
| **Logique métier** | ✅ Dans les Tasks |

---

## 13. Règle d'or

> **Une migration versionne l'évolution du schéma, pas les données. Elle est réversible, reproductible et ne contient aucune logique métier. Les enums sont stockés en string et castés dans le Model. Privilégiez les relations polymorphiques et évitez les champs compteurs. Une table ne doit pas dépasser 10 champs. Les traits polymorphiques ne contiennent que la déclaration de la relation ; la logique métier est dans les Tasks.**

```php
// La migration parfaite
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->string('status');
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });
        
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
            $table->string('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });
        
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->morphs('likeable');
            $table->morphs('liker');
            $table->timestamps();
            $table->unique(['likeable_id', 'likeable_type', 'liker_id', 'liker_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('likes');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('users');
    }
};

// Trait polymorphique (uniquement la relation)
trait HasLikes
{
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}

// Task pour la logique métier
final class ToggleLikeTask extends AbstractTask
{
    public function __construct(
        private readonly LikeRepository $likeRepository,
    ) {}
    
    public function execute(ToggleLikeRecord $record): bool
    {
        // Logique métier ici
    }
}
```