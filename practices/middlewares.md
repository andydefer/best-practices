# Principe d'usage des Middlewares (Version finale)

## 1. Définition

Un **Middleware** est un composant qui intercepte une requête HTTP avant qu'elle n'atteigne l'Action, ou une réponse avant qu'elle ne soit envoyée au client. Il est utilisé pour les tâches transversales comme l'authentification, la journalisation, la gestion CORS, etc.

```
Request → Middleware → Action → Middleware → Response
```

```php
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

### 3.3 Enregistrement legacy (Laravel < 11)

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\AuthenticateMiddleware::class,
    'log.request' => \App\Http\Middleware\LogRequestMiddleware::class,
    'redirect.maintenance' => \App\Http\Middleware\RedirectInMaintenanceMiddleware::class,
];

// Ou via le router
$this->app['router']->aliasMiddleware('nemesis.auth', NemesisAuthMiddleware::class);
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
        // ❌ Logique métier - devrait être dans un Service/Worker
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

> **Un middleware simple exécute une action avant ou après l'Action.**

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

> **Un middleware peut interrompre la requête et retourner une réponse prématurée.**

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

> **Un middleware peut recevoir des paramètres via la route (séparés par `:` en dot.case).**

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

> **Le nom de la classe middleware DOIT refléter son action. Il se termine par `Middleware`. Utilisez `PascalCase`.**

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
final class ThrottleMiddleware { ... }

// ❌ MAUVAIS
final class Auth { ... }
final class Logger { ... }
final class Throttle { ... }
```

### 6.2 Alias (⚠️ STRICT)

> **⚠️ Les alias des middlewares sont en `dot.case`. Le nom doit refléter l'action du middleware.**

| Classe | Alias |
|--------|-------|
| `AuthenticateMiddleware` | `auth` |
| `LogRequestMiddleware` | `log.request` |
| `RedirectInMaintenanceMiddleware` | `redirect.maintenance` |
| `CheckUserStatusMiddleware` | `check.status` |
| `ThrottleMiddleware` | `throttle` |

```php
// ✅ BON
$middleware->alias([
    'auth' => AuthenticateMiddleware::class,
    'log.request' => LogRequestMiddleware::class,
    'redirect.maintenance' => RedirectInMaintenanceMiddleware::class,
    'check.status' => CheckUserStatusMiddleware::class,
]);

// ❌ MAUVAIS
$middleware->alias([
    'authentication' => AuthenticateMiddleware::class,  // ❌ trop long
    'log_request' => LogRequestMiddleware::class,       // ❌ snake_case
    'checkStatus' => CheckUserStatusMiddleware::class,  // ❌ camelCase
]);
```

### 6.3 Paramètres (⚠️ STRICT)

> **⚠️ Les paramètres des middlewares sont séparés par `:` et sont en `snake_case`.**

```php
// ✅ BON
Route::get('/api/users', fn() => ...)->middleware('throttle:60,1');
Route::get('/api/admin', fn() => ...)->middleware('check.status:banned');
Route::get('/api/premium', fn() => ...)->middleware('check.role:admin,editor');

// ❌ MAUVAIS
Route::get('/api/users', fn() => ...)->middleware('throttle:60,1');  // OK
Route::get('/api/users', fn() => ...)->middleware('throttle:60|1');  // ❌
Route::get('/api/users', fn() => ...)->middleware('throttle:60/1');  // ❌
```

### 6.4 Localisation

```
app/Http/Middleware/{Action}Middleware.php
```

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

## 7. Méthode `handle()`

> **Tout middleware DOIT avoir une méthode `handle()` qui reçoit une `Request` et un `Closure`.**

```php
public function handle(Request $request, Closure $next): Response
{
    // Avant l'Action
    // ...
    
    $response = $next($request);
    
    // Après l'Action
    // ...
    
    return $response;
}
```

### 7.1 Type de retour

> **La méthode `handle()` DOIT retourner une `Response`.**

```php
// ✅ BON
public function handle(Request $request, Closure $next): Response
{
    return $next($request);
}

// ❌ MAUVAIS
public function handle(Request $request, Closure $next): string  // ❌
{
    return $next($request);
}
```

---

## 8. Ce qu'un middleware peut faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Vérifier l'authentification** | ✅ Oui | `auth()->check()` |
| **Journaliser les requêtes** | ✅ Oui | `Log::info(...)` |
| **Ajouter des headers** | ✅ Oui | `$response->header('X-Frame-Options', 'DENY')` |
| **Limiter le débit** | ✅ Oui | Cache d'IP |
| **Vérifier le mode maintenance** | ✅ Oui | `app()->isDownForMaintenance()` |
| **Gérer CORS** | ✅ Oui | Ajout de headers CORS |
| **Vérifier le token CSRF** | ✅ Oui | Vérification du token |
| **Nettoyer les données d'entrée** | ✅ Oui | `$request->merge([...])` |
| **Interrompre la requête** | ✅ Oui | `return response(...)` |
| **Utiliser des Repositories** | ✅ Oui | `$this->userRepository->find(...)` |
| **Utiliser des Tasks (1 action)** | ✅ Oui | `$this->someTask->execute(...)` |
| **Utiliser les méthodes des Enums** | ✅ Oui | `$user->status->isBanned()` |

---

## 9. Ce qu'un middleware NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Contenir de la logique métier** | Violation de responsabilité | Déplacer dans Service/Worker |
| **Accéder directement aux Models** | Violation de l'abstraction | Passer par Repository |
| **Faire des transactions DB** | C'est le rôle des Workers | Déplacer dans Worker |
| **Envoyer des emails** | C'est le rôle des Tasks | Déplacer dans Task |
| **Contenir des calculs métier** | Violation de responsabilité | Déplacer dans Service |
| **Valider des données métier** | C'est le rôle des Form Requests | Déplacer dans Form Request |
| **Charger des relations complexes** | C'est le rôle des Repositories | Déplacer dans Repository |
| **Utiliser des Workers** | Trop lourd pour un middleware | Déplacer la logique |
| **Faire plusieurs actions sans Task** | Violation de responsabilité | Créer une Task |
| **Comparer des valeurs brutes d'Enum** | Utiliser les méthodes de l'Enum | `$user->status->isBanned()` |

```php
// ❌ MAUVAIS - Middleware avec logique métier complexe
final class CheckUserSubscriptionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ❌ Logique métier - devrait être dans un Service
        $user = $this->userRepository->find($request->user()->id);
        
        if ($this->subscriptionService->isExpired($user)) {
            // ❌ Plusieurs actions
            $this->logService->logExpiredSubscription($user);
            $this->mailService->sendExpirationAlert($user);
            
            return redirect('/subscribe');
        }
        
        return $next($request);
    }
}

// ✅ BON - Délégation à une Task
final class CheckUserSubscriptionMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly HandleExpiredSubscriptionTask $handleExpiredSubscription,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->subscription_ends_at < now()) {
            $this->handleExpiredSubscription->execute(new HandleExpiredSubscriptionRecord(
                userId: $user->id,
                ip: $request->ip(),
            ));
            
            return redirect('/subscribe');
        }
        
        return $next($request);
    }
}

// ❌ MAUVAIS - Comparaison de valeur brute d'Enum
if ($user->status === 'banned') { ... }  // ❌

// ✅ BON - Utilisation de la méthode de l'Enum
if ($user->status->isBanned()) { ... }  // ✅
```

---

## 10. Middlewares terminaux (Terminable)

> **Un middleware terminable exécute du code après que la réponse a été envoyée au client.**

```php
final class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
    
    public function terminate(Request $request, Response $response): void
    {
        // Exécuté après l'envoi de la réponse
        Log::info('Request completed', [
            'status' => $response->getStatusCode(),
            'duration' => microtime(true) - LARAVEL_START,
        ]);
    }
}
```

---

## 11. Exemples complets

### 11.1 Middleware d'authentification

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

### 11.2 Middleware de vérification d'utilisateur (avec Repository et Enum)

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\UserRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {  // ✅ Utilisation de l'Enum
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Your account has been banned'], 403);
            }
            
            return redirect('/banned');
        }
        
        return $next($request);
    }
}
```

### 11.3 Middleware avec Task (action unique)

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Records\LogUnauthorizedAccessRecord;
use App\Repositories\UserRepository;
use App\Tasks\LogUnauthorizedAccessTask;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CheckUserStatusMiddleware
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LogUnauthorizedAccessTask $logUnauthorizedAccess,
    ) {}
    
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->userRepository->find($request->user()->id);
        
        if ($user->status->isBanned()) {
            $this->logUnauthorizedAccess->execute(new LogUnauthorizedAccessRecord(
                userId: $user->id,
                ip: $request->ip(),
                url: $request->fullUrl(),
                reason: 'banned_account',
            ));
            
            return response()->json(['error' => 'Your account has been banned'], 403);
        }
        
        return $next($request);
    }
}
```

### 11.4 Middleware de journalisation

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        
        Log::info('Request started', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);
        
        $response = $next($request);
        
        $duration = microtime(true) - $start;
        
        Log::info('Request completed', [
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2) . 'ms',
        ]);
        
        return $response;
    }
}
```

### 11.5 Middleware de limitation de débit

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

final class ThrottleMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAttempts, int $decayMinutes): Response
    {
        $key = 'throttle:' . $request->ip() . ':' . $request->path();
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'error' => 'Too many attempts. Please try again later.',
            ], 429);
        }
        
        Cache::put($key, $attempts + 1, $decayMinutes * 60);
        
        return $next($request);
    }
}
```

### 11.6 Middleware de redirection en maintenance

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RedirectInMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isDownForMaintenance() && !$request->is('admin*')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Service under maintenance'], 503);
            }
            
            return redirect('/maintenance');
        }
        
        return $next($request);
    }
}
```

### 11.7 Middleware CORS

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

## 12. Configuration complète

```php
// bootstrap/app.php
use App\Http\Middleware\AuthenticateMiddleware;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\LogRequestMiddleware;
use App\Http\Middleware\RedirectInMaintenanceMiddleware;
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
        // Middlewares globaux
        $middleware->append([
            LogRequestMiddleware::class,
        ]);
        
        // Middlewares pour le groupe 'web'
        $middleware->web(append: [
            AuthenticateMiddleware::class,
            RedirectInMaintenanceMiddleware::class,
        ]);
        
        // Middlewares pour le groupe 'api'
        $middleware->api(prepend: [
            ThrottleMiddleware::class.':60,1',
            CorsMiddleware::class,
            CheckUserStatusMiddleware::class,
        ]);
        
        // Aliases (dot.case)
        $middleware->alias([
            'auth' => AuthenticateMiddleware::class,
            'cors' => CorsMiddleware::class,
            'throttle' => ThrottleMiddleware::class,
            'log.request' => LogRequestMiddleware::class,
            'redirect.maintenance' => RedirectInMaintenanceMiddleware::class,
            'check.status' => CheckUserStatusMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
```

---

## 13. Récapitulatif des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nom de la classe** | `{Action}Middleware` (PascalCase) |
| **Alias** | `dot.case` (ex: `log.request`) |
| **Paramètres** | Séparés par `:`, en `snake_case` |
| **Méthode** | `handle(Request $request, Closure $next): Response` |
| **Logique métier** | ❌ Interdit |
| **Accès direct aux Models** | ❌ Interdit (utiliser Repository) |
| **Plusieurs actions** | ❌ Interdit (déléguer à une Task) |
| **Transactions DB** | ❌ Interdit |
| **Emails / Notifications** | ❌ Interdit (déléguer à Task) |
| **Calculs métier** | ❌ Interdit |
| **Validation métier** | ❌ Interdit |
| **Workers** | ❌ Interdit (trop lourd) |
| **Comparaison valeur brute Enum** | ❌ Interdit (utiliser `isBanned()`) |
| **Redirection** | ✅ Autorisé |
| **Journalisation** | ✅ Autorisé |
| **Headers** | ✅ Autorisé |
| **Throttling** | ✅ Autorisé |
| **Maintenance** | ✅ Autorisé |
| **CORS** | ✅ Autorisé |
| **Nettoyage d'entrée** | ✅ Autorisé |
| **Repositories** | ✅ Autorisé |
| **Tasks (1 action)** | ✅ Autorisé |
| **Méthodes des Enums** | ✅ Autorisé (`$user->status->isBanned()`) |

---

## 14. Règle d'or

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
        // Vérification technique simple via Repository
        $user = $this->repository->find($request->user()->id);
        
        if ($user->status->isBanned()) {  // ✅ Utilisation de l'Enum
            // Action unique déléguée à une Task
            $this->task->execute(new SomeRecord(
                userId: $user->id,
                ip: $request->ip(),
            ));
            
            return $this->intercept($request);
        }
        
        $response = $next($request);
        
        // Après l'Action
        $response->headers->set('X-App-Version', config('app.version'));
        
        return $response;
    }
    
    private function intercept(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Access denied'], 403);
        }
        
        return redirect('/denied');
    }
}

// Alias
$middleware->alias([
    'perfect' => PerfectMiddleware::class,
]);

// Utilisation
Route::get('/api/data', fn() => ...)->middleware('perfect');
