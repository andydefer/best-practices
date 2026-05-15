# Principe d'usage des Actions (Version finale)

## 1. Définition

Une **Action** est un composant qui encapsule une **opération métier complète** avec des **effets de bord** (création, modification, suppression, emails, événements, notifications).

```
Action → Opération complète → Effets de bord → Retourne void
```

```php
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        // Effets de bord uniquement
    }
}
```

---

## 2. Problématique à laquelle les Actions répondent

| Problème | Sans Action | Avec Action |
|----------|-------------|-------------|
| **Code métier éparpillé** | La logique de création d'une commande est dans le Controller, le Service, le Model... | Tout est centralisé dans `CreateOrderAction` |
| **Effets de bord cachés** | On ne sait pas qu'un email est envoyé ou qu'un événement est déclenché | L'Action explicite tous ses effets de bord |
| **Difficulté à réutiliser** | La même logique est dupliquée dans plusieurs Controllers | Une Action = une logique, appelable partout |
| **Tests complexes** | Il faut tester le Controller + le Service + le Model | On teste uniquement l'Action |
| **Transaction non maîtrisée** | Les transactions sont éparpillées | L'Action gère la transaction de bout en bout |

### 2.1 L'Action comme "Use Case"

> **Une Action représente un use case métier complet de votre application.**

```
Use case : "Un utilisateur s'inscrit"
    ↓
RegisterUserAction
    - Crée l'utilisateur en DB
    - Envoie un email de bienvenue
    - Déclenche un événement UserRegistered
    - Crée un client Stripe
```

```
Use case : "Une commande est annulée"
    ↓
CancelOrderAction
    - Vérifie que la commande peut être annulée
    - Remet les articles en stock
    - Effectue le remboursement
    - Envoie un email de confirmation
    - Déclenche un événement OrderCancelled
```

---

## 3. Règles fondamentales

### 3.1 Nommage

```
{Action}{Entity}Action
```

| Action | Use case |
|--------|----------|
| `CreateOrderAction` | Création d'une commande |
| `RegisterUserAction` | Inscription d'un utilisateur |
| `CancelBookingAction` | Annulation d'une réservation |
| `SendResetPasswordEmailAction` | Envoi d'email de réinitialisation |

### 3.2 Localisation

```
app/Actions/{Domain}/{Action}{Entity}Action.php
```

```
app/Actions/
├── Order/
│   ├── CreateOrderAction.php
│   ├── CancelOrderAction.php
│   └── UpdateOrderStatusAction.php
├── User/
│   ├── RegisterUserAction.php
│   └── DeleteUserAction.php
└── Payment/
    ├── ProcessPaymentAction.php
    └── RefundPaymentAction.php
```

### 3.3 Méthode unique : `execute()`

> **Une Action a UNE SEULE méthode publique : `execute()`.**

```php
// ✅ BON
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        // Logique...
    }
}

// ❌ MAUVAIS - Plusieurs méthodes publiques
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void { ... }
    public function validate(CreateOrderRecord $record): bool { ... }  // ❌
    public function rollback(CreateOrderRecord $record): void { ... }   // ❌
}
```

---

## 4. Signature de la méthode `execute()`

### 4.1 Paramètre : UN SEUL Record (bonne pratique)

> **La bonne pratique est que `execute()` prenne UN SEUL paramètre : un Record.**

```php
// ✅ BON - Un seul Record
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        // Tout est dans $record
        $userId = $record->userId;
        $items = $record->items;
        $country = $record->country;
    }
}

// ⚠️ ACCEPTABLE MAIS MOINS BON - Plusieurs paramètres
final class CreateOrderAction
{
    public function execute(int $userId, array $items, string $country): void
    {
        // ...
    }
}

// ❌ MAUVAIS - Paramètre non typé
final class CreateOrderAction
{
    public function execute(array $data): void  // ❌
    {
        // ...
    }
}
```

### 4.2 Retour : `void`

> **Une Action retourne TOUJOURS `void`. En cas d'échec, elle lève une exception.**

```php
// ✅ BON - Retourne void
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $order = Order::create([...]);
            $this->events->dispatch(new OrderCreated($order));
        });
    }
}

// ✅ BON - Lève une exception en cas d'échec
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        if ($this->orderRepository->exists($record->orderId)) {
            throw new OrderAlreadyExistsException($record->orderId);
        }
        
        // Création...
    }
}

// ❌ MAUVAIS - Retourne une valeur
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): Order  // ❌
    {
        return Order::create([...]);
    }
}

// ❌ MAUVAIS - Retourne un booléen
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): bool  // ❌
    {
        return DB::transaction(fn() => Order::create([...]));
    }
}
```

### 4.3 Pourquoi `void` ?

| Raison | Explication |
|--------|-------------|
| **L'Action est un ordre** | Tu demandes à l'Action de faire quelque chose, pas de te retourner un résultat |
| **Le résultat est un effet de bord** | La commande est créée, l'email est envoyé, etc. |
| **Si tu as besoin d'un résultat** | C'est un Service (calcul) ou une Query, pas une Action |
| **Lisibilité** | `void` = effet de bord, `Record` = pas d'effet de bord |

---

## 5. SRP : Une Action change UNE SEULE chose dans l'univers

> **Une Action doit avoir un effet de bord unique et cohérent. Elle ne doit pas faire plusieurs choses sans lien.**

```php
// ✅ BON - Une seule chose : créer une commande
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        Order::create([...]);
    }
}

// ✅ BON - Une chose cohérente : créer une commande ET envoyer une confirmation
// (les deux sont liés au même use case)
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            $order = Order::create([...]);
            $this->sendConfirmationEmail->execute($order);  // Cohérent
        });
    }
}

// ❌ MAUVAIS - Plusieurs choses sans lien (God Action)
final class UserAction
{
    public function execute(Recordable $record): void
    {
        // Crée un utilisateur
        User::create([...]);
        
        // Met à jour le stock
        Stock::update([...]);
        
        // Recalcule les statistiques
        Statistics::recalculate();
        
        // Envoie une newsletter
        Newsletter::send([...]);
    }
}
```

### 5.1 Règle du "Bon sens"

> **Si tu peux décrire l'Action en une phrase commençant par "Je veux...", c'est probablement une bonne Action.**

| Action | Description | Qualité |
|--------|-------------|---------|
| `CreateOrderAction` | "Je veux créer une commande" | ✅ Bonne |
| `CancelOrderAction` | "Je veux annuler une commande" | ✅ Bonne |
| `RegisterUserAction` | "Je veux inscrire un utilisateur" | ✅ Bonne |
| `UserAction` | "Je veux faire des trucs sur l'utilisateur" | ❌ Trop vague |

---

## 6. Quand utiliser une Action ?

| Situation | Utiliser une Action ? | Pourquoi |
|-----------|----------------------|----------|
| **Création d'une ressource** | ✅ Oui | `CreateOrderAction` |
| **Modification d'une ressource** | ✅ Oui | `UpdateProfileAction` |
| **Suppression d'une ressource** | ✅ Oui | `DeleteUserAction` |
| **Envoi d'email** | ✅ Oui | `SendWelcomeEmailAction` |
| **Traitement batch** | ✅ Oui | `ProcessPendingOrdersAction` |
| **Orchestration complexe** | ✅ Oui | `CheckoutCartAction` |
| **Simple calcul** | ❌ Non | Utiliser un Service |
| **Validation** | ❌ Non | Utiliser un Service |
| **Transformation de données** | ❌ Non | Utiliser un Service |
| **Lecture seule (Query)** | ❌ Non | Utiliser un Service ou directement le Repository |

---

## 7. Ce qu'une Action peut faire

| Action | Autorisé | Exemple |
|--------|----------|---------|
| **Créer/Modifier/Supprimer en DB** | ✅ Oui | `Order::create([...])` |
| **Appeler des Services** | ✅ Oui | `$this->priceCalculator->calculate(...)` |
| **Appeler d'autres Actions** | ✅ Oui | `$this->sendEmailAction->execute($record)` |
| **Lancer des événements** | ✅ Oui | `event(new OrderCreated($order))` |
| **Envoyer des emails** | ✅ Oui | `Mail::send(...)` |
| **Envoyer des notifications** | ✅ Oui | `Notification::send(...)` |
| **Faire des transactions** | ✅ Oui | `DB::transaction(...)` |
| **Lire la base de données** | ✅ Oui (pour vérifier ou préparer) | `User::find($id)` |
| **Lever des exceptions métier** | ✅ Oui | `throw new OrderNotFoundException(...)` |

---

## 8. Ce qu'une Action NE peut PAS faire

| Action | Pourquoi | Alternative |
|--------|----------|-------------|
| **Retourner une valeur** | Violation du contrat `void` | Utiliser un Service ou une Query |
| **Contenir de la logique métier pure** | C'est le rôle des Services | Déplacer dans un Service |
| **Faire plusieurs choses sans lien** | Violation du SRP | Découper en plusieurs Actions |
| **Accepter des `Data`** | Violation de la couche API | Convertir `Data` → `Record` dans le Controller |

```php
// ❌ MAUVAIS - Action qui retourne une valeur
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): Order  // ❌
    {
        return Order::create([...]);
    }
}

// ❌ MAUVAIS - Action avec logique métier pure
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        // ❌ Ce calcul devrait être dans un Service
        $tax = $record->subtotal * 0.20;
        
        $order = Order::create([...]);
    }
}

// ✅ BON - Logique métier déléguée à un Service
final class CreateOrderAction
{
    public function __construct(
        private readonly TaxCalculatorService $taxCalculator,
    ) {}
    
    public function execute(CreateOrderRecord $record): void
    {
        $tax = $this->taxCalculator->calculate($record->subtotal, $record->country);
        $order = Order::create([...]);
    }
}
```

---

## 9. Gestion des erreurs

### 9.1 Lever des exceptions métier

> **En cas d'échec, l'Action lève une exception métier explicite.**

```php
// ✅ BON - Exceptions métier explicites
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        if ($this->orderRepository->exists($record->orderId)) {
            throw new OrderAlreadyExistsException($record->orderId);
        }
        
        if ($this->userRepository->isBanned($record->userId)) {
            throw new UserBannedException($record->userId);
        }
        
        // Création...
    }
}
```

### 9.2 Ne pas attraper les exceptions dans l'Action

```php
// ❌ MAUVAIS - Attraper l'exception et retourner (silence dangereux)
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        try {
            $order = Order::create([...]);
        } catch (\Exception $e) {
            return;  // ❌ Silence dangereux
        }
    }
}

// ✅ BON - Laisser l'exception remonter
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        // Les exceptions remontent naturellement
        $order = Order::create([...]);
    }
}
```

---

## 10. Transaction et atomicité

> **Si une Action modifie plusieurs ressources, elle doit être transactionnelle.**

```php
// ✅ BON - Transaction pour plusieurs opérations
final class CreateOrderAction
{
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // 1. Création commande
            $order = Order::create([...]);
            
            // 2. Réservation stock
            $this->inventoryService->reserve($record->items);
            
            // 3. Paiement
            $this->paymentService->charge($record->paymentMethod, $order->total);
            
            // 4. Événement (dans la transaction ou après ?)
            event(new OrderCreated($order));
        });
    }
}
```

### 10.1 Ce qui doit être dans la transaction

| Dans la transaction | Hors transaction |
|---------------------|------------------|
| Écritures DB liées | Envoi d'emails |
| Réservations de stock | Notifications push |
| Paiements | Événements non critiques |
| | Logs |

---

## 11. Exemples complets

### 11.1 Action simple

```php
<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Records\DeleteUserRecord;

final class DeleteUserAction
{
    public function __construct(
        private readonly UserValidatorService $validator,
    ) {}
    
    public function execute(DeleteUserRecord $record): void
    {
        // 1. Validation (via Service)
        if (!$this->validator->canBeDeleted($record->userId)) {
            throw new UserCannotBeDeletedException($record->userId);
        }
        
        // 2. Effet de bord : suppression
        $user = User::find($record->userId);
        $user->delete();
        
        // 3. Effet de bord : événement
        event(new UserDeleted($record->userId));
    }
}
```

### 11.2 Action complexe avec transaction

```php
<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Records\CreateOrderRecord;
use App\Services\PriceCalculatorService;
use App\Services\InventoryService;
use App\Services\PaymentService;

final class CreateOrderAction
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculator,
        private readonly InventoryService $inventoryService,
        private readonly PaymentService $paymentService,
        private readonly SendOrderConfirmationAction $sendConfirmation,
    ) {}
    
    public function execute(CreateOrderRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // 1. Calculs (Services)
            $total = $this->priceCalculator->calculate($record->items, $record->promoCode);
            
            // 2. Réservation stock
            $this->inventoryService->reserve($record->items);
            
            // 3. Paiement
            $payment = $this->paymentService->charge($record->paymentMethod, $total);
            
            // 4. Création commande
            $order = Order::create([
                'user_id' => $record->userId,
                'items' => $record->items,
                'total' => $total,
                'payment_id' => $payment->id,
            ]);
            
            // 5. Événement
            event(new OrderCreated($order));
        });
        
        // 6. Email hors transaction
        $this->sendConfirmation->execute(new SendOrderConfirmationRecord($record->userId, $order->id));
    }
}
```

### 11.3 Action qui appelle une autre Action

```php
<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Records\RegisterUserRecord;
use App\Records\SendWelcomeEmailRecord;

final class RegisterUserAction
{
    public function __construct(
        private readonly CreateUserAction $createUser,
        private readonly SendWelcomeEmailAction $sendWelcomeEmail,
        private readonly CreateStripeCustomerAction $createStripeCustomer,
    ) {}
    
    public function execute(RegisterUserRecord $record): void
    {
        DB::transaction(function () use ($record) {
            // 1. Créer l'utilisateur
            $userRecord = new CreateUserRecord(
                name: $record->name,
                email: $record->email,
                password: $record->password,
            );
            $this->createUser->execute($userRecord);
            
            // 2. Créer le client Stripe
            $stripeRecord = new CreateStripeCustomerRecord(
                email: $record->email,
                name: $record->name,
            );
            $this->createStripeCustomer->execute($stripeRecord);
        });
        
        // 3. Email hors transaction
        $emailRecord = new SendWelcomeEmailRecord(
            email: $record->email,
            name: $record->name,
        );
        $this->sendWelcomeEmail->execute($emailRecord);
    }
}
```

---

## 12. Résumé des contraintes

| Contrainte | Règle |
|------------|-------|
| **Nommage** | `{Action}{Entity}Action` |
| **Méthode** | Une seule : `execute()` |
| **Paramètre** | Bonne pratique : un seul Record |
| **Retour** | `void` (ou exception) |
| **SRP** | Une seule chose cohérente dans l'univers |
| **Effets de bord** | ✅ Oui (c'est le but) |
| **Appel de Services** | ✅ Oui |
| **Appel d'Actions** | ✅ Oui |
| **Transaction DB** | ✅ Oui (si multi-opérations) |
| **Événements, emails, notifications** | ✅ Oui |
| **Retour d'une valeur** | ❌ Interdit |
| **Logique métier pure** | ❌ À déléguer aux Services |

---

## 13. Checklist d'acceptance

- [ ] Le nom se termine par `Action`
- [ ] La classe a une seule méthode publique : `execute()`
- [ ] `execute()` retourne `void`
- [ ] `execute()` prend un paramètre (bonne pratique : un Record)
- [ ] Les exceptions métier sont levées en cas d'échec
- [ ] L'Action a un effet de bord unique et cohérent
- [ ] La logique métier pure est déléguée à des Services
- [ ] Les opérations multiples sont transactionnelles
- [ ] L'Action peut être appelée depuis un Controller ou une autre Action

---

## 14. Règle d'or

> **Une Action exécute un ordre. Elle change l'état du monde (base de données, emails, événements). Elle ne retourne rien. Si elle échoue, elle crie (exception).**

```php
// L'Action parfaite
final class PerfectAction
{
    public function __construct(
        private readonly SomeService $service,
        private readonly AnotherAction $anotherAction,
    ) {}
    
    public function execute(PerfectActionRecord $record): void
    {
        // 1. Logique métier (via Services)
        $result = $this->service->calculate($record->data);
        
        // 2. Effets de bord
        DB::transaction(function () use ($record, $result) {
            DB::table('items')->insert([...]);
            event(new SomethingHappened($result));
        });
        
        // 3. Autre Action (email, notification, etc.)
        $this->anotherAction->execute(new AnotherActionRecord(...));
    }
}
```

---

## 15. Flux complet Controller → Action → Service

```
┌─────────────────────────────────────────────────────────────────┐
│                         CONTROLLER                              │
│                                                                 │
│   1. Reçoit une Request (Data)                                  │
│   2. Transforme Data → Record                                   │
│   3. Appelle l'Action avec le Record                            │
│   4. Retourne réponse HTTP (201, 204, etc.)                     │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                          ACTION                                 │
│                                                                 │
│   execute(CreateOrderRecord $record): void                      │
│                                                                 │
│   1. Orchestration                                              │
│   2. Appelle des Services (logique pure)                        │
│   3. Effets de bord (DB, events, emails)                        │
│   4. Lève des exceptions si échec                               │
└─────────────────────────────┬───────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         SERVICE                                 │
│                    (logique métier pure)                        │
│                                                                 │
│   Calcule, valide, transforme                                   │
│   ✅ Même entrée = même sortie                                  │
│   ✅ Pas d'effets de bord                                       │
└─────────────────────────────────────────────────────────────────┘
