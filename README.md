# Codebase Standards & Best Practices

## 📖 À propos

Ce package contient la documentation des conventions et bonnes pratiques à suivre dans l'ensemble de la codebase. Ces documents définissent l'architecture standardisée pour garantir la maintenabilité, la testabilité et la cohérence du code.

## 🎯 Objectifs

- **Standardisation** : Une seule façon de faire, partout
- **Maintenabilité** : Code prévisible et facile à modifier
- **Testabilité** : Séparation claire des responsabilités
- **Cohérence** : Mêmes patterns dans toute l'application

## 📚 Documentation

| Document | Description |
|----------|-------------|
| [records.md](./records.md) | Structures typées pour la communication interne (Services, Repositories) |
| [datas.md](./datas.md) | DTOs purs et immutables pour les réponses API |
| [factories.md](./factories.md) | Création des Data DTOs à partir de Models ou Records |
| [services.md](./services.md) | Logique métier pure (calculs, validation, accès techniques) |
| [actions.md](./actions.md) | Opérations complètes avec effets de bord (création, modification, email, events) |

## 🔑 Principes fondamentaux

### 1. Records vs Data

| | **Record** | **Data** |
|-|-----------|----------|
| Usage | Interne (Services, Repositories) | Externe (API responses) |
| Peut répondre à une API ? | ❌ Non | ✅ Oui |
| Propriétés | `public` (readonly optionnel) | `public readonly` |
| Dates | `string` ISO 8601 | `string` ISO 8601 |
| Héritage | `AbstractRecord` | `AbstractData` |

### 2. Services vs Actions

| | **Service** | **Action** |
|-|-------------|------------|
| Rôle | Logique métier / Accès technique | Opération complète avec effets de bord |
| Retour | Valeur (float, Record, array) | `void` |
| Transaction DB | ❌ Non | ✅ Oui |
| Exemple | `PriceCalculatorService::calculate()` | `CreateOrderAction::execute()` |

### 3. Règle d'or des effets de bord

> **Une méthode qui s'appelle `calculate` doit uniquement calculer. Une méthode qui s'appelle `send` peut envoyer un email. Le nom est le contrat.**

| Nom de méthode | Effet de bord attendu |
|----------------|----------------------|
| `calculate`, `get`, `validate`, `transform` | ❌ Aucun |
| `set`, `save`, `delete`, `send`, `log` | ✅ Oui |

## 📁 Structure de dossiers

```
app/
├── Data/           # DTOs pour les réponses API (étendent AbstractData)
├── Records/        # Structures internes typées (étendent AbstractRecord)
├── Factories/      # Transforment Record → Data
├── Services/       # Logique métier (calculs, validation, cache, email)
├── Actions/        # Opérations complètes avec effets de bord
└── Enums/          # Énumérations typées
```

---

**Version:** 1.0.0  
**Mainteneur:** Andydefer  
**Dernière mise à jour:** 2026-05-15
