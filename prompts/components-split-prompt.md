Voici le prompt que tu pourras utiliser pour tout futur refactoring :

---

## PROMPT DE REFACTORING

### Objectif
Refactoriser un composant React en respectant l'architecture suivante :
- Chaque composant a son skeleton intégré (via `Composant.Skeleton`)
- Structure sans ré-exports (`index.ts`)
- Composant principal à la racine, enfants dans des sous-dossiers

### Règles

1. **Structure des dossiers**
```
resources/js/XXX/
├── ComposantPrincipal.tsx           # Le composant principal (à la racine)
├── ComposantPrincipal/              # Dossier contenant les sous-composants
│   ├── components/                  # Composants enfants
│   │   ├── Enfant1.tsx
│   │   ├── Enfant2.tsx
│   ├── hooks/                       # Hooks spécifiques
│   │   ├── useHook1.ts
│   │   ├── useHook2.ts
│   └── types.ts                     # Types partagés
```

2. **PAS de ré-exports**
   - Pas de fichier `index.ts` dans les dossiers `components/` ou `hooks/`
   - Les imports doivent être explicites : 
     ```typescript
     import { Enfant1 } from './ComposantPrincipal/components/Enfant1';
     import { useHook1 } from './ComposantPrincipal/hooks/useHook1';
     import { Types } from './ComposantPrincipal/types';
     ```

3. **Skeleton intégré à chaque composant**
   ```typescript
   const MonComposantSkeleton: React.FC = () => { ... };
   
   export function MonComposant() { ... }
   
   MonComposant.Skeleton = MonComposantSkeleton;
   ```

4. **Composant principal**
   - Il est à la racine (`ComposantPrincipal.tsx`)
   - Il utilise les enfants depuis `./ComposantPrincipal/components/`
   - Il a son propre skeleton qui utilise les skeletons des enfants

### Exemple de code final

```typescript
// ComposantPrincipal.tsx (racine)
import { Enfant1 } from './ComposantPrincipal/components/Enfant1';
import { Enfant2 } from './ComposantPrincipal/components/Enfant2';
import { useHook1 } from './ComposantPrincipal/hooks/useHook1';
import { MyType } from './ComposantPrincipal/types';

const ComposantPrincipalSkeleton: React.FC = () => {
    return (
        <div>
            <Enfant1.Skeleton />
            <Enfant2.Skeleton />
        </div>
    );
};

export function ComposantPrincipal() { ... }

ComposantPrincipal.Skeleton = ComposantPrincipalSkeleton;
```

```typescript
// ComposantPrincipal/components/Enfant1.tsx
const Enfant1Skeleton: React.FC = () => { ... };

export function Enfant1() { ... }

Enfant1.Skeleton = Enfant1Skeleton;
```

### À faire pour chaque composant à refactoriser

1. Analyser la taille et la complexité du composant
2. Identifier les sous-composants qui méritent d'être extraits
3. Créer la structure de dossiers
4. Déplacer chaque sous-composant dans son propre fichier avec son skeleton
5. Créer le fichier `types.ts` pour les types partagés
6. Mettre à jour le composant principal avec les imports explicites
7. Supprimer l'ancien fichier

### À NE PAS FAIRE

- ❌ Créer des fichiers `index.ts` pour ré-exporter
- ❌ Mettre le composant principal dans un dossier avec un `index.ts`
- ❌ Découper un composant qui ne le mérite pas (trop petit)
- ❌ Oublier de créer le skeleton pour un composant extrait

---

**Utilisation :** Copie ce prompt et donne-le moi avec le nom du composant à refactoriser.