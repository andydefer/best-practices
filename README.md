Voici le résumé complet réécrit pour votre projet, en intégrant les nouveaux concepts et conventions issus de la refonte de la documentation :

# Codebase Standards & Best Practices

## 📖 À propos

Ce package contient la documentation exhaustive des conventions, bonnes pratiques et règles architecturales à suivre dans l'ensemble de la codebase. Ces documents définissent une architecture standardisée, rigoureuse et cohérente pour garantir la maintenabilité, la testabilité et l'évolutivité du code.

## 🎯 Objectifs

- **Standardisation** : Une seule façon de faire, partout, avec des règles strictes et immuables.
- **Maintenabilité** : Code prévisible, lisible et facile à modifier grâce à une séparation claire des responsabilités.
- **Testabilité** : Architecture découplée (Repositories, Services, Workers, Tasks) qui rend les tests unitaires et fonctionnels simples et robustes.
- **Cohérence** : Les mêmes patterns et conventions sont appliqués dans toute l'application, du contrôleur à la base de données.

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [practices/records.md](./practices/records.md) | Structures typées pour la communication interne (Services, Repositories, Workers) |
| [practices/datas.md](./practices/datas.md) | DTOs purs et immutables pour les réponses API |
| [practices/factories.md](./practices/factories.md) | Transformation explicite des Records en Data DTOs |
| [practices/actions.md](./practices/actions.md) | Composants dédiés à UNE SEULE route (web ou API) avec un type de retour unique |
| [practices/services.md](./practices/services.md) | Logique métier pure (calculs, validation) ou services techniques (cache, email) |
| [practices/workers.md](./practices/workers.md) | Orchestration d'opérations complexes avec transaction et multiples effets de bord |
| [practices/tasks.md](./practices/tasks.md) | Actions de même nature (ex: multiples créations DB, logs, appels API) |
| [practices/repositories.md](./practices/repositories.md) | Interface unique d'accès aux données (point d'entrée unique pour les Models) |
| [practices/models.md](./practices/models.md) | Modèles anémiques (déclarations uniquement, aucune logique métier) |
| [practices/enums.md](./practices/enums.md) | Enums typés avec méthodes de formatage et utilitaires (`getLabel`, `isActive`) |
| [practices/form-requests.md](./practices/form-requests.md) | Validation dédiée à UNE SEULE route (avec règles strictes) |
| [practices/middlewares.md](./practices/middlewares.md) | Tâches transversales techniques (auth, logs, CORS) sans logique métier |
| [practices/routes.md](./practices/routes.md) | Séparation stricte web/API et association route → Action → Form Request |
| [practices/seeders.md](./practices/seeders.md) | Données réalistes sans factories, nettoyage obligatoire |
| [practices/tests.md](./practices/tests.md) | Tests unitaires (sans DB) vs fonctionnels (avec DB), conventions de nommage |
---

**Version:** 2.0.0  
**Mainteneur:** Andydefer  
**Dernière mise à jour:** 2026-05-16