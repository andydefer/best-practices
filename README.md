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
| [practices/records.md](./practices/records.md) | Structures typées pour la communication interne (Services, Repositories, Workers) |
| [practices/datas.md](./practices/datas.md) | DTOs purs et immutables pour les réponses API (création via `fromRecord()`) |
| [practices/actions.md](./practices/actions.md) | Composants dédiés à UNE SEULE route (web ou API) avec un type de retour unique |
| [practices/services.md](./practices/services.md) | Logique métier pure (calculs, validation) ou services techniques (cache, email) |
| [practices/workers.md](./practices/workers.md) | Orchestration d'opérations complexes avec transaction et multiples effets de bord |
| [practices/tasks.md](./practices/tasks.md) | Actions de même nature (ex: multiples créations DB, logs, appels API) |

### Accès aux données

| Document | Description |
|----------|-------------|
| [practices/repositories.md](./practices/repositories.md) | Interface unique d'accès aux données (point d'entrée unique pour les Models) |
| [practices/models.md](./practices/models.md) | Modèles anémiques (déclarations uniquement, aucune logique métier) |
| [practices/enums.md](./practices/enums.md) | Enums typés avec méthodes de formatage et utilitaires (`getLabel`, `isActive`) |

### Couche HTTP

| Document | Description |
|----------|-------------|
| [practices/form-requests.md](./practices/form-requests.md) | Validation dédiée à UNE SEULE route (avec règles strictes) |
| [practices/middlewares.md](./practices/middlewares.md) | Tâches transversales techniques (auth, logs, CORS) sans logique métier |
| [practices/routes.md](./practices/routes.md) | Séparation stricte web/API et association route → Action → Form Request |

### Tests et données

| Document | Description |
|----------|-------------|
| [practices/seeders.md](./practices/seeders.md) | Données réalistes sans factories, nettoyage obligatoire |
| [practices/tests.md](./practices/tests.md) | Tests unitaires (sans DB) vs fonctionnels (avec DB), conventions de nommage |

---

## 🔑 Principes clés

| Principe | Règle |
|----------|-------|
| **Record → Data** | Un Record ne doit JAMAIS être retourné en réponse API. Utiliser `UserData::fromRecord($record)` |
| **Une Action = une route** | Pas de réutilisation d'Action pour plusieurs routes |
| **Type de retour unique** | Une Action ne peut pas retourner `JsonResponse|RedirectResponse` (sauf exception) |
| **Service ≠ Worker** | Service = logique métier pure, Worker = orchestration d'effets de bord |
| **Task = même nature** | Une Task peut faire plusieurs actions, mais TOUTES de même nature |
| **Repository unique** | Le seul point d'accès aux données. Pas de `User::find()` dans les Services |
| **Model anémique** | Pas de logique métier dans les Models (déplacer dans Enum, Service ou Task) |
| **Form Request par route** | Une Form Request = une route, règles strictes sur `authorize()` |

---

## 📁 Structure de dossiers recommandée

```
app/
├── Actions/           # Une Action par route (web/API séparés)
│   ├── Api/
│   └── Web/
├── Data/              # DTOs pour réponses API (fromRecord)
├── Records/           # Communication interne (Services, Repositories)
├── Services/          # Logique métier pure
├── Workers/           # Orchestration d'opérations complexes
├── Tasks/             # Actions unitaires de même nature
├── Repositories/      # Accès aux données (étendent AbstractRepository)
├── Models/            # Modèles anémiques (déclarations uniquement)
├── Enums/             # Enums typés avec trait Enumable
├── Http/
│   ├── Requests/      # Form Requests (une par route)
│   └── Middlewares/   # Middlewares (auth, logs, CORS)
└── ...
```

---

**Version:** 1.0.1  
**Mainteneur:** Andydefer  
**Dernière mise à jour:** 2026-05-17
```