
# 🔧 Mission : Refactoring de Code TypeScript/React – Standard Production

## 🎯 Objectif Général
Refactoriser le code fourni pour atteindre un niveau de qualité **"production-ready"**, conforme aux standards des **grandes plateformes logicielles** (scalabilité, maintenabilité, performance, lisibilité).

## 🧠 Rôle
Agis en tant que **Lead Developer / Architecte Frontend** avec une expertise avancée en **TypeScript**, **React** et **Clean Architecture**.

## 📦 Périmètre d'Intervention
Le refactoring doit s'appliquer à **l'intégralité du fichier fourni**, en préservant **intégralement la logique métier** et le **comportement attendu**.

---

## ✅ Règles de Refactoring

### 1. 📝 Gestion des Commentaires
- **Supprimer** tous les commentaires "aide-mémoire", TODOs personnels, ou commentaires redondants.
- **Conserver uniquement** les commentaires stratégiques expliquant un choix technique complexe ou une règle métier non évidente.

### 2. 🧼 Qualité & Lisibilité du Code
- Appliquer rigoureusement les **principes du Clean Code** (nommage explicite, fonctions pures, pas d'effets de bord cachés).
- **Simplifier** les structures conditionnelles complexes.
- **Éliminer toute duplication** de logique (DRY).
- **Découper** les longues portions de code en petites fonctions utilitaires ou sous-composants dans le même fichier si pertinent car notre but est de faire de la composition.
- Mon idée est de permetre le DRY donc on cree un sous composant puis l'on mappe et au lieu d'avoir des gros blocs de HTML, on a des sous composants ce qui rend plus lisible, 
- Dans les endroits ou tu dois mapper ou dans les gros blocks de code, tu extrais dans des composants.
- Améliorer le **nommage des variables, fonctions, hooks, props et composants** pour une compréhension immédiate.

### 3. ⚛️ Bonnes Pratiques React & TypeScript
- Respecter les **patterns React modernes** (hooks, composition, container/presentational si adapté).
- Utiliser **TypeScript strictement** : typage explicite, éviter `any`, utiliser les `interface`/`type` appropriés.
- **Optimiser les performances** si nécessaire (`useMemo`, `useCallback`, `React.memo`) sans sur-optimisation prématurée.
- Garantir une **architecture testable** : séparation des responsabilités, fonctions pures, hooks personnalisés.

### 4. 📚 Documentation Obligatoire (JSDoc)
**Chaque fonction, hook et composant doit être documenté avec JSDoc**, selon le format suivant :

#### Pour les composants React :
```typescript
/**
 * Renders the patient dashboard with tabs and appointment list.
 *
 * @component
 * @example
 * return (
 *   <PatientDashboard
 *     initialData={data}
 *     onAppointmentSelect={handleSelect}
 *   />
 * )
 *
 * @param {PatientDashboardProps} props - Component props
 * @param {PatientData} props.initialData - Initial patient data to display
 * @param {(id: string) => void} props.onAppointmentSelect - Callback when an appointment is selected
 * @returns {JSX.Element} The rendered dashboard
 */
```

#### Pour les hooks personnalisés :
```typescript
/**
 * Custom hook for managing patient dashboard state and interactions.
 *
 * @param {string} patientId - Unique identifier of the patient
 * @param {Appointment[]} initialAppointments - Initial list of appointments
 * @returns {UsePatientDashboardReturn} Dashboard state and control functions
 *
 * @example
 * const { activeTab, setActiveTab, filteredAppointments } = usePatientDashboard('123', []);
 */
```

#### Pour les fonctions utilitaires :
```typescript
/**
 * Filters and sorts appointments based on the given criteria.
 *
 * @param {Appointment[]} appointments - List of appointments to filter
 * @param {FilterOptions} options - Filtering options (date, status, etc.)
 * @returns {Appointment[]} Filtered and sorted appointments
 *
 * @throws {Error} If appointments list is invalid
 */
```

---

## 🚫 Contraintes Strictes
- **Ne pas casser la logique métier** existante.
- **Ne pas tronquer** le code (fournir le fichier complet refactorisé).
- La langue du code et de la documentation est **l'anglais**.
- Le résultat doit être un fichier **propre, lisible, maintenable, documenté** et prêt pour un environnement de production à grande échelle.

### Voici un modèle de fichier propre:
```tsx 
// resources/js/Components/Select.tsx
import { Check, ChevronDown, X } from 'lucide-react';
import React, { useEffect, useRef, useState, KeyboardEvent } from 'react';

/**
 * Configuration for a selectable option in the dropdown.
 */
export interface SelectOption<T = string | number> {
    value: T;
    label: string;
    icon?: React.ReactNode;
    description?: string;
}

/**
 * Base props shared between single and multiple selection modes.
 */
interface SelectBaseProps<T> {
    label?: string;
    error?: string;
    helperText?: string;
    options: SelectOption<T>[];
    disabled?: boolean;
    required?: boolean;
    className?: string;
    placeholder?: string;
    onBlur?: () => void;
}

/**
 * Props for single selection mode.
 */
interface SingleSelectProps<T> extends SelectBaseProps<T> {
    mode?: 'single';
    value?: T;
    onChange?: (value: T) => void;
}

/**
 * Props for multiple selection mode.
 */
interface MultipleSelectProps<T> extends SelectBaseProps<T> {
    mode: 'multiple';
    value?: T[];
    onChange?: (value: T[]) => void;
}

export type SelectProps<T extends string | number = string> =
    | SingleSelectProps<T>
    | MultipleSelectProps<T>;

/**
 * Hook that manages the selection state and logic for the select component.
 *
 * @param props - The select component props
 * @returns Object containing selected values, setter, and derived state
 */
function useSelectState<T extends string | number>(props: SelectProps<T>) {
    const mode = props.mode ?? 'single';

    const selectedValues: T[] = (() => {
        if (mode === 'multiple') {
            return (props as MultipleSelectProps<T>).value ?? [];
        }
        const singleValue = (props as SingleSelectProps<T>).value;
        return singleValue !== undefined ? [singleValue] : [];
    })();

    const setSelectedValues = (values: T[]) => {
        if (mode === 'multiple') {
            (props as MultipleSelectProps<T>).onChange?.(values);
        } else {
            (props as SingleSelectProps<T>).onChange?.(values[0]);
        }
    };

    const selectedOptions = props.options.filter(opt => selectedValues.includes(opt.value));

    return {
        selectedValues,
        setSelectedValues,
        selectedOptions,
        selectedCount: selectedOptions.length,
        selectedOption: selectedOptions[0],
        mode,
    };
}

/**
 * Hook that manages dropdown open/close state and click outside behavior.
 *
 * @param disabled - Whether the select is disabled
 * @param onBlur - Callback when the select loses focus
 * @returns Object containing open state, focus state, and handlers
 */
function useDropdownState(disabled: boolean, onBlur?: () => void) {
    const [isOpen, setIsOpen] = useState(false);
    const [isFocused, setIsFocused] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setIsFocused(false);
                onBlur?.();
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onBlur]);

    const toggleOpen = () => {
        if (!disabled) {
            setIsOpen(!isOpen);
        }
    };

    const closeDropdown = () => {
        setIsOpen(false);
    };

    return {
        isOpen,
        isFocused,
        containerRef,
        setIsFocused,
        toggleOpen,
        closeDropdown,
    };
}

/**
 * Renders an icon node with consistent styling.
 *
 * @param icon - The icon to render
 * @param className - CSS classes to apply to the icon
 * @returns Rendered icon or null if no icon provided
 */
function renderIcon(icon: React.ReactNode, className: string): JSX.Element | null {
    if (!icon) return null;

    if (typeof icon === 'string') {
        return <span className={className}>{icon}</span>;
    }

    if (React.isValidElement(icon)) {
        return React.cloneElement(icon, { className } as any);
    }

    return null;
}

/**
 * Props for the SelectedChips component.
 */
interface SelectedChipsProps<T> {
    selectedOptions: SelectOption<T>[];
    onRemove: (value: T, event: React.MouseEvent | React.KeyboardEvent) => void;
}

/**
 * Displays selected options as removable chips in multiple selection mode.
 *
 * @component
 * @param props - Component props
 * @returns Rendered chips component
 */
function SelectedChips<T extends string | number>({ selectedOptions, onRemove }: SelectedChipsProps<T>) {
    return (
        <div className="flex items-center gap-2 w-full overflow-hidden">
            <div
                className="flex flex-1 gap-1.5 overflow-x-auto py-2 no-scrollbar"
                style={{
                    scrollbarWidth: 'none',
                    msOverflowStyle: 'none'
                }}
            >
                {selectedOptions.map(opt => (
                    <span
                        key={String(opt.value)}
                        onClick={(e) => onRemove(opt.value, e)}
                        title={`Remove ${opt.label}`}
                        className="group/chip inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-sm font-medium text-primary transition-all duration-150 hover:bg-destructive hover:text-destructive-foreground cursor-pointer"
                    >
                        {opt.icon && renderIcon(opt.icon, 'h-3.5 w-3.5 group-hover/chip:text-destructive-foreground')}
                        <span className="whitespace-nowrap">{opt.label}</span>
                        <X className="h-3.5 w-3.5 shrink-0 opacity-60 group-hover/chip:opacity-100 group-hover/chip:scale-110 transition-transform" />
                    </span>
                ))}
            </div>
            {selectedOptions.length > 1 && (
                <span className="shrink-0 bg-secondary text-secondary-foreground text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-wider">
                    {selectedOptions.length}
                </span>
            )}
        </div>
    );
}

/**
 * Props for the DropdownOption component.
 */
interface DropdownOptionProps<T> {
    option: SelectOption<T>;
    isSelected: boolean;
    onSelect: (value: T) => void;
}

/**
 * Renders an individual option in the dropdown menu.
 *
 * @component
 * @param props - Component props
 * @returns Rendered option component
 */
function DropdownOption<T extends string | number>({ option, isSelected, onSelect }: DropdownOptionProps<T>) {
    return (
        <div
            className="group relative cursor-default select-none py-2.5 pl-10 pr-4 hover:bg-primary/5 transition-colors"
            onClick={() => onSelect(option.value)}
        >
            <div className="flex items-center gap-3">
                {option.icon && renderIcon(option.icon, 'h-5 w-5 shrink-0 text-muted-foreground group-hover:text-primary transition-colors')}
                <div className="flex flex-col min-w-0">
                    <span className={`block truncate ${isSelected ? 'font-semibold text-primary' : 'font-normal'}`}>
                        {option.label}
                    </span>
                    {option.description && (
                        <span className="text-xs text-muted-foreground truncate">{option.description}</span>
                    )}
                </div>
            </div>
            {isSelected && (
                <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-primary">
                    <Check className="h-4 w-4" />
                </span>
            )}
        </div>
    );
}

/**
 * Props for the SelectButton component.
 */
interface SelectButtonProps {
    isOpen: boolean;
    isFocused: boolean;
    hasError: boolean;
    disabled: boolean;
    className: string;
    onClick: () => void;
    onFocus: () => void;
    onBlur: () => void;
    onKeyDown: (event: KeyboardEvent) => void;
    children: React.ReactNode;
}

/**
 * Renders the main button that triggers the dropdown.
 *
 * @component
 * @param props - Component props
 * @returns Rendered button component
 */
function SelectButton({
    isOpen,
    isFocused,
    hasError,
    disabled,
    className,
    onClick,
    onFocus,
    onBlur,
    onKeyDown,
    children
}: SelectButtonProps) {
    const baseButtonStyles = `
        w-full cursor-default rounded-lg bg-background
        min-h-[3.5rem] text-left text-foreground text-md
        border transition-all duration-200
        focus:outline-none focus:border-primary
        hover:border-primary/50 shadow-sm
        disabled:opacity-50 disabled:cursor-not-allowed
        overflow-hidden
    `;

    const errorStyles = hasError ? 'border-destructive' : 'border-border';
    const focusStyles = isFocused && !hasError ? 'border-primary ring-1 ring-primary' : '';
    const buttonClass = `${baseButtonStyles} ${errorStyles} ${focusStyles} ${className}`;

    return (
        <button
            type="button"
            className={buttonClass}
            onClick={onClick}
            onFocus={onFocus}
            onBlur={onBlur}
            onKeyDown={onKeyDown}
            disabled={disabled}
        >
            <div className="grid grid-cols-[1fr_auto] items-center w-full h-full px-3 gap-2">
                <div className="min-w-0 overflow-hidden flex items-center h-full">
                    {children}
                </div>
                <ChevronDown className={`h-5 w-5 shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180 text-primary' : 'text-muted-foreground'}`} />
            </div>
        </button>
    );
}

/**
 * Props for the SelectedDisplay component.
 */
interface SelectedDisplayProps<T> {
    mode: 'single' | 'multiple';
    selectedOptions: SelectOption<T>[];
    selectedOption?: SelectOption<T>;
    placeholder: string;
    onRemoveChip: (value: T, event: React.MouseEvent | React.KeyboardEvent) => void;
}

/**
 * Displays the current selection in the button area.
 *
 * @component
 * @param props - Component props
 * @returns Rendered selection display
 */
function SelectedDisplay<T extends string | number>({
    mode,
    selectedOptions,
    selectedOption,
    placeholder,
    onRemoveChip
}: SelectedDisplayProps<T>) {
    if (mode === 'multiple' && selectedOptions.length > 0) {
        return <SelectedChips selectedOptions={selectedOptions} onRemove={onRemoveChip} />;
    }

    return (
        <div className="flex items-center gap-2 py-2 truncate">
            {selectedOption?.icon && renderIcon(selectedOption.icon, 'h-5 w-5 text-muted-foreground')}
            <span className="truncate text-foreground font-normal">
                {selectedOption?.label ?? placeholder}
            </span>
        </div>
    );
}

/**
 * A customizable select dropdown component supporting single and multiple selection modes.
 *
 * @component
 * @example
 * // Single select
 * return (
 *   <Select
 *     label="Specialty"
 *     options={specialties}
 *     value={selectedSpecialty}
 *     onChange={setSelectedSpecialty}
 *   />
 * )
 *
 * @example
 * // Multiple select
 * return (
 *   <Select
 *     mode="multiple"
 *     label="Languages"
 *     options={languages}
 *     value={selectedLanguages}
 *     onChange={setSelectedLanguages}
 *   />
 * )
 *
 * @param props - Component props
 * @returns The rendered select component
 */
export function Select<T extends string | number = string>(props: SelectProps<T>) {
    const {
        label,
        error,
        helperText,
        options,
        disabled = false,
        required = false,
        className = '',
        placeholder = 'Select an option',
        onBlur,
    } = props;

    const {
        selectedValues,
        setSelectedValues,
        selectedOptions,
        selectedCount,
        selectedOption,
        mode,
    } = useSelectState(props);

    const {
        isOpen,
        isFocused,
        containerRef,
        setIsFocused,
        toggleOpen,
        closeDropdown,
    } = useDropdownState(disabled, onBlur);

    const toggleOption = (optionValue: T) => {
        if (mode === 'multiple') {
            const exists = selectedValues.includes(optionValue);
            const newValues = exists
                ? selectedValues.filter(v => v !== optionValue)
                : [...selectedValues, optionValue];
            setSelectedValues(newValues);
        } else {
            setSelectedValues([optionValue]);
            closeDropdown();
        }
    };

    const removeChip = (optionValue: T, event: React.MouseEvent | React.KeyboardEvent) => {
        event.stopPropagation();
        if (mode === 'multiple') {
            const newValues = selectedValues.filter(v => v !== optionValue);
            setSelectedValues(newValues);
        }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
        if (disabled) return;

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleOpen();
        } else if (event.key === 'Escape') {
            closeDropdown();
        }
    };

    return (
        <div className="w-full space-y-2" ref={containerRef} style={{ maxWidth: '100%' }}>
            {label && (
                <label className="text-md mb-1 block font-medium text-foreground">
                    {label}
                    {required && <span className="ml-1 text-destructive">*</span>}
                </label>
            )}

            <div className="relative w-full">
                <SelectButton
                    isOpen={isOpen}
                    isFocused={isFocused}
                    hasError={!!error}
                    disabled={disabled}
                    className={className}
                    onClick={toggleOpen}
                    onFocus={() => setIsFocused(true)}
                    onBlur={() => setIsFocused(false)}
                    onKeyDown={handleKeyDown}
                >
                    <SelectedDisplay
                        mode={mode}
                        selectedOptions={selectedOptions}
                        selectedOption={selectedOption}
                        placeholder={placeholder}
                        onRemoveChip={removeChip}
                    />
                </SelectButton>

                {isOpen && (
                    <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-lg bg-card py-1 shadow-xl ring-1 ring-border animate-in fade-in zoom-in-95">
                        {options.map((option) => (
                            <DropdownOption
                                key={String(option.value)}
                                option={option}
                                isSelected={selectedValues.includes(option.value)}
                                onSelect={toggleOption}
                            />
                        ))}
                    </div>
                )}
            </div>

            {error && <p className="text-xs font-medium text-destructive px-1">{error}</p>}
            {helperText && !error && <p className="text-xs text-muted-foreground px-1">{helperText}</p>}
        </div>
    );
}

export default Select;
```
---

## 📥 Code Source à Refactoriser
