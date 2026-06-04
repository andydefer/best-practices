
# Best Practices – Architecture & Conventions

## Vue d'ensemble

Ce dépôt centralise les bonnes pratiques d'architecture PHP pour les projets Laravel : découplage, immutabilité, typage fort, testabilité.

## Sommaire des pratiques

| Fichier | Sujet |
|---------|-------|
| [INTERFACES.md](./practices/INTERFACES.md) | Contrats (`Likeable`, `Likerable`) |
| [REPOSITORIES.md](./practices/REPOSITORIES.md) | Accès aux données, extends `AbstractRepository` |
| [ENUMS.md](./practices/ENUMS.md) | Backed enums (string), nom `{Model}{Field}` |
| [LOGGING.md](./practices/LOGGING.md) | Logs structurés JSONL (pas de facade) |
| [SEEDING.md](./practices/SEEDING.md) | Données réalistes, pas de factories |
| [TESTS.md](./practices/TESTS.md) | Pas de `final` sur les classes mockables |
| [MODELS.md](./practices/MODELS.md) | Aucune logique métier, uniquement déclarations |
| [TASKS.md](./practices/TASKS.md) | Actions asynchrones (fichiers JSON) |
| [RECORDS.md](./practices/RECORDS.md) | DTO internes, propriétés `snake_case` |
| [SERVICES.md](./practices/SERVICES.md) | Pas d'état interne, injection de dépendances |
| [ABSTRACTS.md](./practices/ABSTRACTS.md) | Classes abstraites, composition (pas de traits) |
| [MIGRATIONS.md](./practices/MIGRATIONS.md) | `string` pour enums (pas `->enum()`) |
| [NORMALIZATION.md](./practices/NORMALIZATION.md) | Normalisation récursive via `NormalizerChain` |
| [DATA_OBJECTS.md](./practices/DATA_OBJECTS.md) | `StrictDataObject` pour Records, `DataObject` pour Data |
| [ACTIONS.md](./practices/ACTIONS.md) | Une route = une Action, retourne `ResponseFactory` |
| [FORM_REQUESTS.md](./practices/FORM_REQUESTS.md) | `getRecord(): AbstractRecord` (jamais injecté dans l'Action) |
| [ROUTING.md](./practices/ROUTING.md) | `ActionRoute` pour lier Request → Action |
| [MIDDLEWARES.md](./practices/MIDDLEWARES.md) | Tâches transversales, pas de logique métier |
| [CONFIGS.md](./practices/CONFIGS.md) | Classes sans propriétés, méthodes typées |
| [VALUE_OBJECTS.md](./practices/VALUE_OBJECTS.md) | Validation dans le constructeur, immutables |
| [TYPED_COLLECTIONS.md](./practices/TYPED_COLLECTIONS.md) | Pas de `array` brut, toujours `TypedCollection` |
| [HYDRATABLES.md](./practices/HYDRATABLES.md) | Hydratation automatique via `from()` / `fromJson()` |
| [CASTS.md](./practices/CASTS.md) | Transformations pures DB ↔ Application |
| [DIRECTIVES.md](./practices/DIRECTIVES.md) | Commandes CLI, alias en `dot.case` |
| [STRUCTURATION.md](./practices/STRUCTURATION.md) | Mini-packages, injection obligatoire (pas de helpers) |

## Règles d'or

- **Zéro helper** retournant une instance de classe
- **Zéro tableau brut** dans Records / Data / Value Objects
- **Zéro appel statique** métier (Log, Cache, DB…)
- **Zéro final** sur les classes à mocker (Services, Tasks, Repositories)
- **Toute injection** est explicite dans le constructeur
- **snake_case** pour Records, **camelCase** pour Data et Value Objects

## Utilisation rapide

```bash
# Consulter une pratique
cat practices/INTERFACES.md

# Exemple de record (snake_case)
$user = UserRecord::from(['user_id' => 1, 'user_name' => 'John']);
```

## Licence

Interne – non redistribuable.