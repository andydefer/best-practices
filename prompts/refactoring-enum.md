## PROMPT RÉUTILISABLE POUR GÉNÉRATION D'ENUM

```
Je te donne un enum PHP. Tu dois générer 4 fichiers :

1. L'enum PHP amélioré
2. L'enum TypeScript correspondant
3. Les tests PHPUnit
4. Les tests Vitest (TypeScript)

RÈGLES À RESPECTER :

1. NOMS DE MÉTHODES IDENTIQUES entre PHP et TypeScript
2. TOUJOURS utiliser l'enum Language pour les paramètres de langue
3. Le support des langues (FR/EN) est OBLIGATOIRE pour l'affichage
4. Tu DOIS ÉVALUER la pertinence de chaque méthode selon le contexte
5. Ne copie pas aveuglément des méthodes inutiles
6. Pour le TypeScript, créer un helper object en camelCase

ÉVALUATION DE PERTINENCE À FAIRE :

Regarde l'enum que je te donne et demande-toi :
- Est-ce un enum d'affichage utilisateur ? (ajouter label, labels)
- Est-ce un enum de validation ? (ajouter isValid, fromValue)
- Est-ce qu'une valeur par défaut est utile ? (ajouter fromValueWithFallback)
- Est-ce technique interne ? (seulement values, typesInOrder)

Voici un exemple  :

```php
enum NotificationType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';
}
```

EXEMPLE DE CE QUE JE VEUX POUR CHAQUE FICHIER :

## FICHIER 1: PHP ENUM (App/Enums/NotificationType.php)

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function label(Language $language = Language::FR): string
    {
        return match ($this) {
            self::INFO => $language === Language::EN ? 'Information' : 'Information',
            self::SUCCESS => $language === Language::EN ? 'Success' : 'Succès',
            self::WARNING => $language === Language::EN ? 'Warning' : 'Avertissement',
            self::ERROR => $language === Language::EN ? 'Error' : 'Erreur',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function typesInOrder(): array
    {
        return self::cases();
    }

    public static function labels(Language $language = Language::FR): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label($language);
        }
        return $result;
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    public static function fromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
```

## FICHIER 2: TYPESCRIPT ENUM (resources/js/types/api/enums/NotificationType.ts)

```typescript
import { Language, getFallbackLanguage, isSupportedLanguage } from './Language';

export enum NotificationType {
    INFO = 'info',
    SUCCESS = 'success',
    WARNING = 'warning',
    ERROR = 'error',
}

const LABELS_FR: Record<NotificationType, string> = {
    [NotificationType.INFO]: 'Information',
    [NotificationType.SUCCESS]: 'Succès',
    [NotificationType.WARNING]: 'Avertissement',
    [NotificationType.ERROR]: 'Erreur',
};

const LABELS_EN: Record<NotificationType, string> = {
    [NotificationType.INFO]: 'Information',
    [NotificationType.SUCCESS]: 'Success',
    [NotificationType.WARNING]: 'Warning',
    [NotificationType.ERROR]: 'Error',
};

const TYPES_IN_ORDER: readonly NotificationType[] = [
    NotificationType.INFO,
    NotificationType.SUCCESS,
    NotificationType.WARNING,
    NotificationType.ERROR,
];

function isSupported(language: Language): boolean {
    return isSupportedLanguage(language);
}

function getEffectiveLanguage(language: Language): Language {
    return isSupported(language) ? language : getFallbackLanguage();
}

export function label(type: NotificationType, language: Language = Language.FR): string {
    const effectiveLanguage = getEffectiveLanguage(language);
    return effectiveLanguage === Language.EN ? LABELS_EN[type] : LABELS_FR[type];
}

export function values(): string[] {
    return TYPES_IN_ORDER.map(t => t);
}

export function typesInOrder(): NotificationType[] {
    return [...TYPES_IN_ORDER];
}

export function labels(language: Language = Language.FR): Record<string, string> {
    const effectiveLanguage = getEffectiveLanguage(language);
    const result: Record<string, string> = {};
    for (const type of TYPES_IN_ORDER) {
        result[type] = label(type, effectiveLanguage);
    }
    return result;
}

export function isValid(value: string): boolean {
    return Object.values(NotificationType).includes(value as NotificationType);
}

export function fromValue(value: string): NotificationType | null {
    return isValid(value) ? (value as NotificationType) : null;
}

export const notificationType = {
    label,
    values,
    typesInOrder,
    labels,
    isValid,
    fromValue,
} as const;
```

## FICHIER 3: PHPUNIT TEST (tests/Unit/Enums/NotificationTypeTest.php)

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\NotificationType;
use App\Enums\Language;
use Tests\TestCase;

final class NotificationTypeTest extends TestCase
{
    public function test_label_returns_french_by_default(): void
    {
        $this->assertSame('Information', NotificationType::INFO->label());
        $this->assertSame('Succès', NotificationType::SUCCESS->label());
        $this->assertSame('Avertissement', NotificationType::WARNING->label());
        $this->assertSame('Erreur', NotificationType::ERROR->label());
    }

    public function test_label_returns_english_when_language_is_en(): void
    {
        $this->assertSame('Information', NotificationType::INFO->label(Language::EN));
        $this->assertSame('Success', NotificationType::SUCCESS->label(Language::EN));
        $this->assertSame('Warning', NotificationType::WARNING->label(Language::EN));
        $this->assertSame('Error', NotificationType::ERROR->label(Language::EN));
    }

    public function test_values(): void
    {
        $this->assertEquals(['info', 'success', 'warning', 'error'], NotificationType::values());
    }

    public function test_typesInOrder(): void
    {
        $types = NotificationType::typesInOrder();
        $this->assertCount(4, $types);
        $this->assertSame(NotificationType::INFO, $types[0]);
        $this->assertSame(NotificationType::SUCCESS, $types[1]);
        $this->assertSame(NotificationType::WARNING, $types[2]);
        $this->assertSame(NotificationType::ERROR, $types[3]);
    }

    public function test_labels(): void
    {
        $labels = NotificationType::labels();
        $this->assertSame('Information', $labels['info']);
        $this->assertSame('Succès', $labels['success']);
        $this->assertSame('Avertissement', $labels['warning']);
        $this->assertSame('Erreur', $labels['error']);
    }

    public function test_isValid(): void
    {
        $this->assertTrue(NotificationType::isValid('info'));
        $this->assertTrue(NotificationType::isValid('error'));
        $this->assertFalse(NotificationType::isValid('invalid'));
    }

    public function test_fromValue(): void
    {
        $this->assertSame(NotificationType::INFO, NotificationType::fromValue('info'));
        $this->assertSame(NotificationType::ERROR, NotificationType::fromValue('error'));
        $this->assertNull(NotificationType::fromValue('invalid'));
    }
}
```

## FICHIER 4: VITEST TEST (tests/types/api/enums/NotificationType.test.ts)

```typescript
import { describe, it, expect } from 'vitest';
import {
    NotificationType,
    label,
    values,
    typesInOrder,
    labels,
    isValid,
    fromValue,
    notificationType,
} from '@/types/api/enums/NotificationType';
import { Language } from '@/types/api/enums/Language';

describe('NotificationType', () => {
    describe('label', () => {
        it('should return French by default', () => {
            expect(label(NotificationType.INFO)).toBe('Information');
            expect(label(NotificationType.SUCCESS)).toBe('Succès');
            expect(label(NotificationType.WARNING)).toBe('Avertissement');
            expect(label(NotificationType.ERROR)).toBe('Erreur');
        });

        it('should return English when language is EN', () => {
            expect(label(NotificationType.INFO, Language.EN)).toBe('Information');
            expect(label(NotificationType.SUCCESS, Language.EN)).toBe('Success');
            expect(label(NotificationType.WARNING, Language.EN)).toBe('Warning');
            expect(label(NotificationType.ERROR, Language.EN)).toBe('Error');
        });
    });

    describe('values', () => {
        it('should return all values', () => {
            expect(values()).toEqual(['info', 'success', 'warning', 'error']);
        });
    });

    describe('typesInOrder', () => {
        it('should return all types in order', () => {
            const types = typesInOrder();
            expect(types[0]).toBe(NotificationType.INFO);
            expect(types[1]).toBe(NotificationType.SUCCESS);
            expect(types[2]).toBe(NotificationType.WARNING);
            expect(types[3]).toBe(NotificationType.ERROR);
        });
    });

    describe('labels', () => {
        it('should return French labels by default', () => {
            const result = labels();
            expect(result.info).toBe('Information');
            expect(result.success).toBe('Succès');
            expect(result.warning).toBe('Avertissement');
            expect(result.error).toBe('Erreur');
        });
    });

    describe('isValid', () => {
        it('should return true for valid values', () => {
            expect(isValid('info')).toBe(true);
            expect(isValid('error')).toBe(true);
            expect(isValid('invalid')).toBe(false);
        });
    });

    describe('fromValue', () => {
        it('should return enum for valid values', () => {
            expect(fromValue('info')).toBe(NotificationType.INFO);
            expect(fromValue('invalid')).toBeNull();
        });
    });

    describe('notificationType helper object', () => {
        it('should have all methods', () => {
            expect(notificationType.label(NotificationType.INFO)).toBe('Information');
            expect(notificationType.values()).toEqual(['info', 'success', 'warning', 'error']);
        });
    });
});
```

MAINTENANT, GÉNÈRE LES 4 FICHIERS POUR L'ENUM QUE JE TE DONNE.
TU N'ES PAS OBLIGÉ DE REUTILISER LES MEMES METHODES METHODES PROPOSEE EVALUES LES CHOSES QUI SERONT UTILE POUR L'ENUM CREE MOI ENTRE 6 A 12 METHODES !!

 DONNE MOI UNE COMMANDE UNIQUE A COPIER COLLER DANS MON TERMINAL POUR CREER LES 4 FICHIERS AVEC LEUR CONTENU
 exemple : mkdir -p app/Enums && cat > app/Enums/AmbulanceStatus.php << 'EOF'..... TU VOIS 