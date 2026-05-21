# Codebase Standards & Best Practices

## 📖 À propos

Ce package contient la documentation exhaustive des conventions, bonnes pratiques et règles architecturales à suivre dans l'ensemble de la codebase. Ces documents définissent une architecture standardisée, rigoureuse et cohérente pour garantir la maintenabilité, la testabilité et l'évolutivité du code.

---

## 🎯 Objectifs

| Objectif | Description |
|----------|-------------|
| **Standardisation** | Une seule façon de faire, partout, avec des règles strictes et immuables |
| **Maintenabilité** | Code prévisible, lisible et facile à modifier grâce à une séparation claire des responsabilités |
| **Testabilité** | Architecture découplée (Repositories, Services, Workers, Tasks) qui rend les tests unitaires et fonctionnels simples et robustes |
| **Cohérence** | Les mêmes patterns et conventions sont appliqués dans toute l'application, du contrôleur à la base de données |

---

## 📚 Documentation

### Composants principaux

| Document | Description |
|----------|-------------|
| [practices/records.md](./practices/records.md) | Structures typées pour la communication interne (Services, Repositories, Workers). **⚠️ Les tableaux bruts sont STRICTEMENT INTERDITS : utilisez `TypedRecords`** |
| [practices/typed-records.md](./practices/typed-records.md) | Collection type-safe qui remplace les tableaux bruts. Méthodes `map()`, `filter()`, `sum()`, `groupBy()`, assertions et collections utilitaires (`StringTypedRecords`, `IntTypedRecords`, `FloatTypedRecords`, `BoolTypedRecords`, `NumberTypedRecords`) |
| [practices/datas.md](./practices/datas.md) | DTOs purs et immutables pour les réponses API. **⚠️ Création UNIQUEMENT via `fromRecord()`** |
| [practices/actions.md](./practices/actions.md) | Composants dédiés à UNE SEULE route. **⚠️ Une Action ne reçoit JAMAIS une Request, elle reçoit un Record** |
| [practices/services.md](./practices/services.md) | Logique métier pure (calculs, validation) ou services techniques (cache, email). **⚠️ ZÉRO appel statique, TOUTES les dépendances injectées** |
| [practices/workers.md](./practices/workers.md) | Orchestration d'opérations complexes. **⚠️ ZÉRO transaction, ZÉRO retour de valeur** |
| [practices/tasks.md](./practices/tasks.md) | Actions de même nature (ex: multiples créations DB, logs, appels API). **⚠️ ZÉRO appel statique, TOUTES les dépendances injectées** |
| [practices/logger.md](./practices/logger.md) | Système de logs structurés en JSONL |
| [practices/directives.md](./practices/directives.md) | Système de commandes CLI découplé et testable |

### Accès aux données

| Document | Description |
|----------|-------------|
| [practices/repositories.md](./practices/repositories.md) | Interface unique d'accès aux données. **⚠️ Tests UNIQUEMENT en intégration (pas de tests unitaires). Les méthodes héritées d'`AbstractRepository` sont DÉJÀ testées par le package** |
| [practices/models.md](./practices/models.md) | Modèles anémiques (déclarations uniquement, aucune logique métier) |
| [practices/casts.md](./practices/casts.md) | Casts personnalisés pour les attributs des modèles |
| [practices/migrations.md](./practices/migrations.md) | Structure des migrations et conventions de nommage |
| [practices/seeders.md](./practices/seeders.md) | Données réalistes sans factories Laravel |

### Couche HTTP

| Document | Description |
|----------|-------------|
| [practices/form-requests.md](./practices/form-requests.md) | Validation dédiée à UNE SEULE route. **⚠️ DOIT étendre `AbstractRequest` et implémenter `toRecord()`** |
| [practices/middlewares.md](./practices/middlewares.md) | Tâches transversales techniques (auth, logs, CORS) sans logique métier |
| [practices/routes.md](./practices/routes.md) | Séparation stricte web/API. **⚠️ Les routes DOIVENT appeler `toRecord()` et passer un Record à l'Action** |

### Tests et qualité

| Document | Description |
|----------|-------------|
| [practices/tests.md](./practices/tests.md) | Tests unitaires (sans DB) vs fonctionnels (avec DB). **⚠️ Actions = tests d'intégration uniquement, Repositories = tests d'intégration uniquement** |
| [practices/interfaces.md](./practices/interfaces.md) | Contrats explicites pour le découplage |
| [practices/abstracts.md](./practices/abstracts.md) | Classes de base pour l'infrastructure |
| [practices/traits.md](./practices/traits.md) | Réutilisation horizontale avec convention `Has{Entity}` + `{Entity}able` |

### Enums

| Document | Description |
|----------|-------------|
| [practices/enums.md](./practices/enums.md) | Enums typés avec méthodes utilitaires (`values()`, `names()`, `isValid()`, `fromValue()`) |

---

## 🔑 Principes clés

| Principe | Règle |
|----------|-------|
| **Record → Data** | Un Record ne doit JAMAIS être retourné en réponse API. Utiliser `UserData::fromRecord($record)` |
| **Une Action = une route** | Pas de réutilisation d'Action pour plusieurs routes |
| **Type de retour unique** | Une Action ne peut pas retourner `JsonResponse|RedirectResponse` (sauf exception) |
| **Service ≠ Worker** | Service = logique métier pure, Worker = orchestration d'effets de bord |
| **Task = même nature** | Une Task peut faire plusieurs actions, mais TOUTES de même nature |
| **ZÉRO appel statique** | `Log::`, `Mail::`, `Http::`, `Cache::`, `DB::` sont INTERDITS. TOUTES les dépendances DOIVENT être injectées |
| **ZÉRO transaction dans Worker** | Les transactions sont gérées par les Repositories ou les Services |
| **Repository unique** | Le seul point d'accès aux données. Pas de `User::find()` dans les Services |
| **Model anémique** | Pas de logique métier dans les Models (déplacer dans Enum, Service ou Task) |
| **Form Request → toRecord()** | Une Form Request DOIT implémenter `toRecord()` et l'Action reçoit le Record |
| **TypedRecords** | Les tableaux bruts (`array`) sont STRICTEMENT INTERDITS dans les Records. Utilisez `TypedRecords` |
| **Tests** | Actions et Repositories = tests d'intégration UNIQUEMENT. Services, Tasks, Workers = tests unitaires |
| **Transactions** | Repositories = transaction pour atomicité DB, Services = transaction pour atomicité métier, Workers = ZÉRO transaction |

---

**Version:** 3.0.0  
**Mainteneur:** Andydefer  
**Dernière mise à jour:** 2026-05-21
```