Agis en tant qu'expert en analyse de code PHP/Laravel. Je vais te fournir un liste de bugs, faiblesses, correctifs et tu dois générer une liste de tickets techniques (issues) au format suivant :

## 🔴 Tickets Critiques (Sécurité & Bugs Bloquants)
- Priorité : bloquante, sécurité, données sensibles

## 🟠 Tickets Majeurs (Fonctionnalités à risque)  
- Priorité : performance, fiabilité, bugs fonctionnels

## 🟡 Tickets Mineurs (Améliorations & Clean Code)
- Priorité : UI/UX, refactoring, dette technique

Pour chaque ticket, tu dois inclure :
1. **Un titre clair et actionnable** (max 10 mots)
2. **Le fichier et la méthode concernés** (ex: `app/Models/User.php` - `createToken()`)
3. **Une description du problème** (1-2 phrases)
4. **L'impact potentiel** (sécurité, performance, maintenance, UX)
5. **Une action corrective suggérée**
6. **La priorité** (🔴/🟠/🟡)

Format de sortie attendu : Markdown avec emojis, sections claires, et tableau récapitulatif.

Il faut aussi me proposer un nom pour ce fichier, de ce genre :
TECH_DEBT_2026-04-19_NotificationHelper_User_Enum.md → soit [PREFIX]_[DATE]_[SPEC].md

Voici un modele de fichier de ticket :

```markdown

## 🔴 Tickets Critiques (Sécurité & Bugs Bloquants)

### TICKET-001 : Mot de passe stocké en clair dans les métadonnées du token
**Fichier** : `app/Models/User.php`  
**Méthode** : `createToken()`  
**Description** : La propriété `created_at_password` stocke le mot de passe utilisateur en clair dans la base de données.  
**Impact** : Violation RGPD, exposition critique des identifiants.  
**Action** : Supprimer immédiatement `'created_at_password' => $this->password` des métadonnées.  
**Priorité** : 🔴 **Urgent**

### TICKET-002 : Vérification d'historique des mots de passe complètement inopérante
**Fichier** : `app/Models/User.php`  
**Méthode** : `isPasswordInHistory()`  
**Description** : `Hash::make()` génère un salt aléatoire à chaque appel, rendant la comparaison impossible.  
**Impact** : La fonctionnalité d'historique des mots de passe est cassée.  
**Action** : Remplacer par une récupération des hashs + `Hash::check()` en PHP.  
**Priorité** : 🔴 **Urgent**

### TICKET-003 : Typehint PHPDoc incompatible avec les versions PHP < 8.0
**Fichier** : `app/Helpers/NotificationHelper.php`  
**Description** : `@param User|array<int, User>|Collection<int, User>` utilise une syntaxe non supportée.  
**Impact** : Erreurs d'analyse statique, échec potentiel en CI.  
**Action** : Remplacer par `@param User|array<User>|Collection $users`.  
**Priorité** : 🔴 **Critique**

---

## 🟠 Tickets Majeurs (Fonctionnalités à risque)

### TICKET-004 : Gestion fragile des images manquantes dans les notifications
**Fichier** : `app/Helpers/NotificationHelper.php`  
**Description** : Les méthodes (`security()`, `medical()`, etc.) passent `getImageData()` qui peut retourner `null`.  
**Impact** : Notifications sans image si configuration DB incomplète.  
**Action** : Ajouter une validation ou une image par défaut, ou logger l'absence.  
**Priorité** : 🟠 **Haute**

### TICKET-005 : Décodage JSON incorrect dans ImageData
**Fichier** : `app/Data/ImageData.php`  
**Méthode** : `fromModel()`  
**Description** : Condition étrange `$fallbackUrls !== '0'` et gestion silencieuse des erreurs JSON.  
**Impact** : Comportement imprévisible pour les URLs de fallback.  
**Action** : Corriger la logique de validation et ajouter un logging.  
**Priorité** : 🟠 **Haute**

### TICKET-006 : Problème N+1 dans NotificationType::getImageData()
**Fichier** : `app/Enums/NotificationType.php`  
**Description** : Requête DB à chaque appel, potentiellement en boucle.  
**Impact** : Performance dégradée, requêtes multiples.  
**Action** : Déplacer dans un service avec cache ou préchargement.  
**Priorité** : 🟠 **Moyenne**

---

## 🟡 Tickets Mineurs (Améliorations & Clean Code)

### TICKET-007 : Couleurs identiques pour INFO et WARNING
**Fichier** : `app/Enums/NotificationType.php`  
**Description** : `INFO` et `WARNING` utilisent tous deux `text-orange-600`.  
**Impact** : Mauvaise distinction visuelle pour les utilisateurs.  
**Action** : Changer `INFO` pour une couleur différente (bleu, gris).  
**Priorité** : 🟡 **Basse**

### TICKET-008 : Absence de limite pour les rendez-vous patients
**Fichier** : `app/Models/User.php`  
**Méthode** : `loadDashboardData()`  
**Description** : `appointmentsAsPatient` charge TOUS les rendez-vous (sans `limit()`).  
**Impact** : Risque de surcharge pour les patients avec beaucoup d'historique.  
**Action** : Ajouter une limite ou implémenter la pagination.  
**Priorité** : 🟡 **Moyenne**

### TICKET-009 : Couche d'abstraction inutile (NotificationHelper)
**Fichier** : `app/Helpers/NotificationHelper.php`  
**Description** : Wrapper statique autour de `NotificationService` sans valeur ajoutée.  
**Impact** : Complexité inutile, dépendance cachée au container.  
**Action** : Déprécier le helper et injecter `NotificationService` directement.  
**Priorité** : 🟡 **Basse** (refactoring)

### TICKET-010 : Logique métier dans l'Enum NotificationType
**Fichier** : `app/Enums/NotificationType.php`  
**Description** : L'Enum gère couleurs, icônes et requêtes DB.  
**Impact** : Violation SRP, difficile à maintenir.  
**Action** : Extraire dans un service de thème et un repository d'images.  
**Priorité** : 🟡 **Basse** (refactoring)

### TICKET-011 : Duplication de logique dans ImageData::fromArray()
**Fichier** : `app/Data/ImageData.php`  
**Description** : Recalcule `hasDarkVariant` et `hasCustomFallback` au lieu d'utiliser les valeurs du modèle.  
**Impact** : Risque d'incohérence avec le modèle `Image`.  
**Action** : Accepter ces valeurs en paramètres ou déléguer au modèle.  
**Priorité** : 🟡 **Basse**

---

## 📊 Résumé par priorité

| Priorité | Nombre de tickets |
|----------|-------------------|
| 🔴 Urgent | 3 |
| 🟠 Haute | 3 |
| 🟡 Basse | 5 |

**Total** : 11 tickets

---

## 🚀 Suggestions pour la planification

**Sprint 1 (Sécurité)** :
- TICKET-001 (mot de passe en clair)
- TICKET-002 (historique mots de passe)
- TICKET-003 (typehint PHPDoc)

**Sprint 2 (Fiabilité)** :
- TICKET-004 (images manquantes)
- TICKET-005 (décodage JSON)
- TICKET-006 (problème N+1)

**Backlog (Améliorations)** :
- TICKET-007 à 011 (refactoring et UI)

```

Voici le code à analyser :

[COLLER LE CODE ICI]