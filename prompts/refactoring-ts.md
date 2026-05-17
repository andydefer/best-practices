# 🎯 PROMPT COMPLET – Refactoring & Documentation d'un fichier TypeScript

## Rôle
> Tu es un **expert TypeScript / Node.js**, mainteneur de packages open-source et défenseur du **Clean Code**, des **design patterns**, et des **bonnes pratiques TypeScript** (strict mode, types explicites, immutabilité).
>
> Je vais te fournir le code source complet d'un **module / utilitaire TypeScript** destiné à être publié sur GitHub et npm.
>
> **Ton objectif est de le préparer pour une publication publique professionnelle.**

---

## 🔥 OBJECTIFS PRINCIPAUX

### 1. Nettoyage du code
* Supprimer **tous les commentaires parasites**, temporaires ou personnels :
  * `TODO`, `FIXME`, `HACK`, `BUG`
  * commentaires de réflexion ou de debug
  * étapes de raisonnement
  * commentaires redondants qui expliquent "ce que le code fait ligne par ligne"
* Ne garder **aucun commentaire inutile**

### 2. Documentation professionnelle
* Ajouter une **JSDoc complète et propre** :
  * Pour **chaque classe/interface/type**
  * Pour **chaque méthode publique**
  * Pour toute méthode protégée/privée importante
* Les JSDoc doivent :
  * Expliquer *le rôle métier*
  * Décrire les paramètres et valeurs de retour
  * Mentionner les exceptions/thrown errors quand pertinent
  * Inclure des exemples d'utilisation pour les APIs complexes
* Ton professionnel, clair, orienté utilisateur du package

### 3. Refactor Clean Code
* Refactorer le code pour qu'il :
  * Se lise **comme un roman**
  * Soit **auto-documenté par les noms**
  * Respecte :
    * SRP (Single Responsibility)
    * Nommage clair (métiers > techniques)
    * Méthodes courtes (max 20-25 lignes)
    * Conditions lisibles (éviter les négations complexes)
    * Early returns pour réduire la nesting
* Renommer si nécessaire :
  * méthodes
  * variables
  * classes
  * paramètres
* **Sans casser l'API publique** (Aucune justification ou pretexte)

### 4. Typage Strict TypeScript
* Utiliser au maximum le système de types :
  * Éviter `any` → utiliser `unknown` si nécessaire
  * Préférer les unions/intersections aux `any`
  * Utiliser des **type guards** pour les vérifications complexes
  * Ajouter des **branded types** pour les IDs ou valeurs spécifiques si pertinent
  * Utiliser `readonly` pour l'immutabilité
* Exporter tous les types/interfaces utilisés publiquement
* Ajouter des **const assertions** (`as const`) pour les objets constants

### 5. Cohérence & Lisibilité
* Harmoniser :
  * styles (guillemets simples, point-virgules, trailing commas)
  * noms (camelCase pour variables/méthodes, PascalCase pour classes/types)
  * structures de fichiers
* Réduire la complexité cognitive
* Éviter la duplication (DRY)
* Préparer le code pour :
  * nouveaux contributeurs
  * relectures GitHub
  * long terme

---

## 🧱 CONTRAINTES IMPORTANTES

* ❌ Ne pas ajouter de logique métier inutile
* ❌ Ne pas changer le comportement fonctionnel
* ❌ Ne pas introduire de dépendances externes non nécessaires
* ✅ Respect strict du TypeScript moderne (TS 5.0+)
* ✅ Utiliser `strict: true` dans la configuration
* ✅ Code prêt pour un **package open-source** (compatible ESM et CJS si possible)

---

## 📦 FORMAT DE SORTIE ATTENDU

Pour chaque fichier :

1. Code **complet refactoré**
2. JSDoc :
   * Interfaces/Types
   * Classes
   * Méthodes publiques
3. **Aucun commentaire parasite**
4. Code final directement **copiable / publiable**
5. Si un choix de refactor est non évident → courte justification après le code (en anglais)

---

## 🧠 APPROCHE ATTENDUE

* Penser comme :
  * un **mainteneur**
  * un **contributeur externe**
  * un **lecteur GitHub**
* Priorité :
  1. Lisibilité
  2. Clarté
  3. Stabilité
  4. Élégance

---

## 🌐 LANGUES

- **Code et documentation technique** (JSDoc, noms de variables, noms de méthodes, commentaires techniques) : **ANGLAIS UNIQUEMENT**
- **Messages utilisateur** (textes retournés dans les réponses API, messages d'erreur, logs utilisateur) : **GARDER LA LANGUE D'ORIGINE** (français dans ce projet)
- **Exceptions** : Les messages d'erreur peuvent être en anglais (convention internationale)

## Autres détails

1. Si vous voyez des annotations de type comme `/** @type {Array<Availability>} */` sur une variable, laissez-les telles quelles, et utilisez uniquement l'anglais dans le code et les commentaires.
2. Si tu constates que les noms de méthodes d'une classe ou le nom de la classe elle-même ne sont pas pertinents, tu peux proposer des changements **à la fin du code généré**, pour les éléments publics.
   Pour les **variables locales** et les **méthodes privées ou encapsulées**, dont le renommage n'a **aucun impact externe**, tu as **carte blanche** : tu peux les renommer librement pour améliorer la clarté et la lisibilité. **N'OUBLIE PAS DE ME PROPOSER LES RENOMAGES POUR LES METHODES AVEC DES NOMS PAS ASSEZ BONS.**
3. Utilisez **les paramètres nommés** (destructuring) pour les fonctions avec plus de 2-3 paramètres.

   Par exemple :
   ```typescript
   // Au lieu de
   function createUser(name: string, email: string, age: number, role: string) {}

   // Utiliser
   function createUser({ name, email, age, role }: CreateUserParams) {}
   ```

4. Préférez `interface` pour les objets publics et `type` pour les unions/utilitaires.
5. Utilisez `readonly` pour les propriétés qui ne doivent pas muter.

---

## RÈGLES DE RENOMMAGE

**NE MODIFIE PAS LES NOMS DES METHODES OU PROPRIETES PUBLIQUES !!! PROPOSE ET MOI MEME JE CHOISIRAI !!!**

---

## TESTS

**POUR LES FICHIERS DE TEST, UTILISE LA STRUCTURE AAA -> Arrange Act Assert**

Ainsi
```typescript
// Arrange : Phrase explicative en anglais
const setup = ...

// Act : Phrase explicative en anglais
const result = ...

// Assert : phrase explicative en anglais
expect(result).toBe(...)
```
LES PHRASES SONT ESSENTIELLES !!!

**Pour les tests asynchrones :**
```typescript
// Arrange
const mockData = ...

// Act
const result = await service.execute()

// Assert
expect(result).toEqual(expected)
```

---

## EXTRACTION DE CODE

**SI TU VOIS DU CODE REPETITIF TU PEUX L'ENCAPSULER DANS DES HELPERS MAIS TOUJOURS BIEN DOCUMENTER COMME UNE FONCTION PRIVÉE**

**SI TU VOIS LA LOGIQUE DANS LE RENDU, EXTRAIT LA LOGIQUE EN HAUT**

DONC UNE ACTION QUI SE REFAIT À PLUSIEURS ENDROITS PEUT ÊTRE ENCAPSULÉE DANS UNE FONCTION HELPER POUR RÉDUIRE LA RÉPÉTITION DE CODE ET FAIRE DU RÉUTILISABLE

**N'OUBLIE SURTOUT PAS LES PHRASES D'EXPLICATION À CÔTÉ DE `// Assert`, `// Act`, `// Arrange`**

---

## EXEMPLE DE FICHIER BIEN ÉCRIT

```typescript
/**
 * Service for managing user session data with encryption and validation.
 * 
 * Handles session creation, validation, refresh, and revocation with automatic
 * cleanup of expired sessions and security event logging.
 * 
 * @example
 * ```typescript
 * const sessionService = new SessionService({
 *   ttl: 3600,
 *   encryptionKey: process.env.SESSION_KEY
 * });
 * 
 * const session = await sessionService.create(userId);
 * ```
 */
export class SessionService {
  private readonly ttl: number;
  private readonly encryption: EncryptionService;
  private sessions: Map<string, Session> = new Map();

  constructor(private readonly config: SessionConfig) {
    this.ttl = config.ttl;
    this.encryption = new EncryptionService(config.encryptionKey);
    this.startCleanupInterval();
  }

  /**
   * Creates a new session for the specified user.
   * 
   * @param userId - Unique identifier of the user
   * @param metadata - Optional additional session metadata
   * @returns The newly created session with token and expiration
   * @throws {ValidationError} If userId is invalid
   * 
   * @example
   * ```typescript
   * const session = await sessionService.create('user-123', {
   *   ip: '192.168.1.1',
   *   userAgent: 'Mozilla/5.0'
   * });
   * console.log(session.token); // Encrypted session token
   * ```
   */
  async create(userId: string, metadata?: SessionMetadata): Promise<Session> {
    this.validateUserId(userId);
    
    // Act : Create and store the session
    const session = this.buildSession(userId, metadata);
    this.sessions.set(session.id, session);
    
    // Assert : Verify session was stored
    this.assertSessionStored(session.id);
    
    return session;
  }

  /**
   * Validates a session token and returns the session if valid.
   * 
   * @param token - Encrypted session token to validate
   * @returns The session if valid and not expired
   * @throws {SessionExpiredError} If session has expired
   * @throws {InvalidTokenError} If token is malformed or tampered
   */
  async validate(token: string): Promise<Session> {
    const sessionId = await this.encryption.decrypt(token);
    const session = this.sessions.get(sessionId);
    
    if (!session) {
      throw new InvalidTokenError('Session not found');
    }
    
    if (this.isExpired(session)) {
      this.sessions.delete(sessionId);
      throw new SessionExpiredError('Session has expired');
    }
    
    return session;
  }

  private buildSession(userId: string, metadata?: SessionMetadata): Session {
    return {
      id: generateId(),
      userId,
      createdAt: new Date(),
      expiresAt: new Date(Date.now() + this.ttl * 1000),
      metadata,
    };
  }

  private isExpired(session: Session): boolean {
    return session.expiresAt.getTime() < Date.now();
  }

  private assertSessionStored(sessionId: string): void {
    if (!this.sessions.has(sessionId)) {
      throw new Error(`Failed to store session: ${sessionId}`);
    }
  }

  private startCleanupInterval(): void {
    setInterval(() => {
      for (const [id, session] of this.sessions.entries()) {
        if (this.isExpired(session)) {
          this.sessions.delete(id);
        }
      }
    }, CLEANUP_INTERVAL_MS);
  }
}
```

---

## ▶️ DÉMARRAGE

Voici le code TypeScript à analyser et améliorer :

[COLLER LE CODE TYPESCRIPT ICI]
