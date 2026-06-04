# Principe d'usage des Middlewares (Version finale - Mise à jour)

## 1. Définition

Un **Middleware** est un composant qui intercepte une requête HTTP avant qu'elle n'atteigne l'Action, ou une réponse avant qu'elle ne soit envoyée au client. Il est utilisé pour les tâches transversales comme l'authentification, la journalisation, la gestion CORS, etc.

```
Request → Middleware → Action → Middleware → Response
```

```php
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthenticateMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }
        
        return $next($request);
    }
}
```

---

## 2. Problématique à laquelle les Middlewares répondent

| Problème | Solution |
|----------|----------|
| **Code répété dans les Actions** | La vérification d'authentification est dans chaque Action |
| **Tâches transversales** | Logging, CORS, maintenance, throttling |
| **Séparation des préoccupations** | Les middlewares isolent les préoccupations techniques |

---

## 3. Configuration des Middlewares (⚠️ NOUVELLE SYNTAXE LARAVEL 11+)

> **⚠️ Les middlewares se configurent désormais dans le fichier `bootstrap/app.php` avec la nouvelle syntaxe Laravel 11+.**

```php
// bootstrap/app.php
use App\Http\Middleware\AuthenticateMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\LogRequestMiddleware;
use App\Http\Middleware\ThrottleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middlewares globaux (toutes les requêtes)
        $middleware->append([
            LogRequestMiddleware::class,
        ]);
        
        // Middlewares pour le groupe 'web'
        $middleware->web(append: [
            AuthenticateMiddleware::class,
        ]);
        
        // Middlewares pour le groupe 'api'
        $middleware->api(prepend: [
            ThrottleMiddleware::class.':60,1',
            CorsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
```

### 3.1 Alias des middlewares (⚠️ CONVENTION STRICTE)

> **⚠️ Les alias des middlewares sont en `dot.case`. Le nom doit refléter l'action du middleware.**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'auth' => AuthenticateMiddleware::class,
        'cors' => CorsMiddleware::class,
        'throttle' => ThrottleMiddleware::class,
        'log.request' => LogRequestMiddleware::class,
        'redirect.maintenance' => RedirectInMaintenanceMiddleware::class,
        'check.status' => CheckUserStatusMiddleware::class,
    ]);
})
```

### 3.2 Utilisation dans les routes

```php
// routes/web.php
Route::get('/dashboard', fn() => ...)->middleware('auth');
Route::get('/admin', fn() => ...)->middleware(['auth', 'check.status']);

// routes/api.php
Route::get('/users', fn() => ...)->middleware('throttle:60,1');
Route::post('/data', fn() => ...)->middleware(['auth', 'cors']);

// Avec paramètres (séparés par ':')
Route::get('/api/data', fn() => ...)->middleware('throttle:60,1');
Route::get('/api/admin', fn() => ...)->middleware('check.status:banned');
```

---

## 4. Règles fondamentales

### 4.1 Règle fondamentale (⚠️ IMMUABLE)

> **⚠️ Un middleware ne doit contenir AUCUNE logique métier. Il ne traite que des préoccupations transversales techniques (authentification, logging, CORS, throttling, maintenance).**

```php
// ✅ BON - Middleware pour tâche transversale
final class AuthenticateMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }
        
        return $next($request);
    }
}

// ❌ MAUVAIS - Middleware avec logique métier
final class CheckUserSubscriptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ❌ Logique métier - devrait être dans un Service/Task
        $user = $this->userRepository->find($request->user()->id);
        if ($user->subscription_ends_at < now()) {
            return redirect('/subscribe');
        }
        
        return $next($request);
    }
}
```

### 4.2 Utilisation des Enums

> **⚠️ Pour les vérifications de statut, utilisez les méthodes de l'Enum (`isBanned()`, `isActive()`) plutôt que de comparer les valeurs brutes.**

```php
// ✅ BON - Utilisation de l'Enum
final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {  // ✅ Méthode de l'Enum
            return response('Banned', 403);
        }
        
        return $next($request);
    }
}

// ❌ MAUVAIS - Comparaison de valeur brute
if ($user->status === 'banned') { ... }  // ❌
if ($user->status->value === 'banned') { ... }  // ❌
```

### 4.3 Accès aux données

> **⚠️ Si un middleware a besoin d'accéder à la base de données, il DOIT utiliser un Repository. Pas d'accès direct aux Models.**

```php
// ❌ MAUVAIS - Accès direct au Model
final class CheckUserStatusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::find($request->user()->id);  // ❌ Direct
        
        if ($user->status->isBanned()) {
            return response('Banned', 403);
        }
        
        return $next($request);
    }
}

// ✅ BON - Utilisation d'un Repository
final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            return response('Banned', 403);
        }
        
        return $next($request);
    }
}
```

### 4.4 Logique complexe

> **⚠️ Si un middleware a besoin d'effectuer plusieurs actions (ex: vérification + logging + notification), il DOIT déléguer à une Task.**

```php
// ❌ MAUVAIS - Middleware avec plusieurs actions
final class CheckUserStatusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            // ❌ Plusieurs actions dans le middleware
            Log::warning('Banned user attempted access', ['user_id' => $user->id]);
            Mail::to(config('admin.email'))->send(new BannedUserAlert($user));
            
            return response('Banned', 403);
        }
        
        return $next($request);
    }
}

// ✅ BON - Délégation à une Task
final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly HandleBannedUserTask $handleBannedUser,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            $this->handleBannedUser->execute(new HandleBannedUserRecord(
                userId: $user->id,
                ip: $request->ip(),
                url: $request->fullUrl(),
            ));
            
            return response('Banned', 403);
        }
        
        return $next($request);
    }
}
```

---

## 5. Types de middlewares

### 5.1 Middleware simple

```php
final class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Request started', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ]);
        
        $response = $next($request);
        
        Log::info('Request completed', [
            'status' => $response->getStatusCode(),
        ]);
        
        return $response;
    }
}
```

### 5.2 Middleware avec redirection

```php
final class AuthenticateMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            return redirect('/login');
        }
        
        return $next($request);
    }
}
```

### 5.3 Middleware paramétré

```php
final class ThrottleMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAttempts, int $decayMinutes): Response
    {
        $key = 'throttle:' . $request->ip();
        
        if (Cache::get($key, 0) >= $maxAttempts) {
            return response()->json(['error' => 'Too many attempts'], 429);
        }
        
        Cache::increment($key);
        Cache::expire($key, $decayMinutes * 60);
        
        return $next($request);
    }
}

// Utilisation
Route::get('/api/users', fn() => ...)->middleware('throttle:60,1');
```

---

## 6. Règles de nommage

### 6.1 Nom de la classe (⚠️ STRICT)

| Action | Nom de la classe |
|--------|------------------|
| Authentification | `AuthenticateMiddleware` |
| Journalisation | `LogRequestMiddleware` |
| Limitation de débit | `ThrottleMiddleware` |
| Redirection en maintenance | `RedirectInMaintenanceMiddleware` |
| CORS | `CorsMiddleware` |

```php
// ✅ BON
final class AuthenticateMiddleware { ... }
final class LogRequestMiddleware { ... }

// ❌ MAUVAIS
final class Auth { ... }
final class Logger { ... }
```

### 6.2 Alias (⚠️ STRICT)

| Classe | Alias |
|--------|-------|
| `AuthenticateMiddleware` | `auth` |
| `LogRequestMiddleware` | `log.request` |
| `RedirectInMaintenanceMiddleware` | `redirect.maintenance` |
| `CheckUserStatusMiddleware` | `check.status` |

```php
// ✅ BON
$middleware->alias([
    'auth' => AuthenticateMiddleware::class,
    'log.request' => LogRequestMiddleware::class,
    'check.status' => CheckUserStatusMiddleware::class,
]);

// ❌ MAUVAIS
$middleware->alias([
    'authentication' => AuthenticateMiddleware::class,  // ❌ trop long
    'log_request' => LogRequestMiddleware::class,       // ❌ snake_case
]);
```

### 6.3 Localisation

```
app/Http/Middleware/
├── AuthenticateMiddleware.php
├── LogRequestMiddleware.php
├── ThrottleMiddleware.php
├── CorsMiddleware.php
├── RedirectInMaintenanceMiddleware.php
└── CheckUserStatusMiddleware.php
```

---

## 7. Ce qu'un middleware peut faire

| Action | Autorisé |
|--------|----------|
| Vérifier l'authentification | ✅ Oui |
| Journaliser les requêtes | ✅ Oui |
| Ajouter des headers | ✅ Oui |
| Limiter le débit | ✅ Oui |
| Vérifier le mode maintenance | ✅ Oui |
| Gérer CORS | ✅ Oui |
| Nettoyer les données d'entrée | ✅ Oui |
| Interrompre la requête | ✅ Oui |
| Utiliser des Repositories | ✅ Oui |
| Utiliser des Tasks (1 action) | ✅ Oui |
| Utiliser les méthodes des Enums | ✅ Oui |

---

## 8. Ce qu'un middleware NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Logique métier** | Violation SRP | Service/Task |
| **Accès direct aux Models** | Violation abstraction | Repository |
| **Transactions DB** | Rôle des Tasks | Task |
| **Envoi d'emails** | Rôle des Tasks | Task |
| **Calculs métier** | Violation SRP | Service |
| **Validation métier** | Rôle Form Request | Form Request |
| **Plusieurs actions** | Violation SRP | Task unique |

```php
// ❌ MAUVAIS
final class CheckUserSubscriptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::find($request->user()->id);  // ❌ Accès direct
        
        if ($user->subscription_ends_at < now()) {  // ❌ Logique métier
            Log::warning('Expired');  // ❌ Action supplémentaire
            Mail::send(...);  // ❌ Action supplémentaire
            return redirect('/subscribe');
        }
        
        return $next($request);
    }
}

// ✅ BON
final class CheckUserSubscriptionMiddleware
{
    public function __construct(
        private readonly UserRepository $repository,
        private readonly HandleExpiredSubscriptionTask $task,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->repository->find($request->user()->id);
        
        if ($user->subscription_ends_at < now()) {
            $this->task->execute(new HandleExpiredSubscriptionRecord(
                userId: $user->id,
                ip: $request->ip(),
            ));
            
            return redirect('/subscribe');
        }
        
        return $next($request);
    }
}
```

---

## 9. Middlewares terminaux (Terminable)

```php
final class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
    
    public function terminate(Request $request, Response $response): void
    {
        Log::info('Request completed', [
            'status' => $response->getStatusCode(),
            'duration' => microtime(true) - LARAVEL_START,
        ]);
    }
}
```

---

## 10. Exemples complets

### 10.1 Middleware d'authentification

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthenticateMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            return redirect('/login');
        }
        
        return $next($request);
    }
}
```

### 10.2 Middleware avec Repository et Task

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\UserRepository;
use App\Tasks\HandleBannedUserTask;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly HandleBannedUserTask $handleBannedUser,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            $this->handleBannedUser->execute(new HandleBannedUserRecord(
                userId: $user->id,
                ip: $request->ip(),
                url: $request->fullUrl(),
            ));
            
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your account has been banned'], 403);
            }
            
            return redirect('/banned');
        }
        
        return $next($request);
    }
}
```

### 10.3 Middleware CORS

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        if ($request->method() === 'OPTIONS') {
            $response->setStatusCode(200);
        }
        
        return $response;
    }
}
```

---

## 11. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom de la classe** | `{Action}Middleware` (PascalCase) |
| **Alias** | `dot.case` (ex: `log.request`) |
| **Paramètres** | Séparés par `:`, en `snake_case` |
| **Logique métier** | ❌ Interdit |
| **Accès direct aux Models** | ❌ Interdit |
| **Plusieurs actions** | ❌ Interdit (déléguer à Task) |
| **Transactions DB** | ❌ Interdit |
| **Emails / Notifications** | ❌ Interdit |
| **Validation métier** | ❌ Interdit |
| **Redirection** | ✅ Autorisé |
| **Journalisation** | ✅ Autorisé |
| **Headers** | ✅ Autorisé |
| **Throttling** | ✅ Autorisé |
| **Repositories** | ✅ Autorisé |
| **Tasks (1 action)** | ✅ Autorisé |
| **Méthodes des Enums** | ✅ Autorisé |

---

## 12. Règle d'or

> **Un middleware ne fait que des tâches transversales techniques. Pas de logique métier. Pas d'accès direct aux Models. Les actions complexes sont déléguées à des Tasks. Les alias sont en `dot.case`. Utilisez les méthodes des Enums (`isBanned()`) plutôt que les valeurs brutes.**

```php
// Le middleware parfait
final class PerfectMiddleware
{
    public function __construct(
        private readonly SomeRepository $repository,
        private readonly SomeTask $task,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->repository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            $this->task->execute(new SomeRecord(
                userId: $user->id,
                ip: $request->ip(),
            ));
            
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        $response = $next($request);
        $response->headers->set('X-App-Version', config('app.version'));
        
        return $response;
    }
}

// Alias
$middleware->alias(['perfect' => PerfectMiddleware::class]);

// Utilisation
Route::get('/api/data', fn() => ...)->middleware('perfect');
```