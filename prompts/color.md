Je ne veux pas que dans mon composant l'on fasse usage des couleurs en dure du genre text-white, text-gray-500 etc... mais plutot des couleurs dynamique de mon design system de tailwind du genre text-primary, bg-card, bg-background

VOICI MON DESIGN SYSTEME
/* Import de la font Poppins */
@import './be-vietnam-pro-font.css';

/* Tailwind */
@tailwind base;
@tailwind components;
@tailwind utilities;

/* ========================================================= */
/* 🔤 TYPOGRAPHIE GÉNÉRALE */
/* ========================================================= */

html,
body {
    font-family: var(--font-sans) !important;
    scroll-behavior: smooth;
}

/* ========================================================= */
/* 🎨 THÈME CLAIR - BLEU AZUR PLUS FONCÉ */
/* ========================================================= */
:root {
    --background: 0 0% 100%;
    --foreground: 220 30% 12%; /* Plus foncé pour plus de contraste */

    --card: 0 0% 100%;
    --card-foreground: 220 30% 12%;

    /* Bleu principal plus foncé et plus saturé */
    --primary: 210 95% 45%; /* Changé de 200 92% 55% à 210 95% 45% */
    --primary-foreground: 0 0% 100%;
    --primary-light: 210 95% 92%; /* Ajouté pour highlight */
    --primary-dark: 210 95% 35%; /* Plus foncé */

    /* Bleu secondaire plus profond */
    --secondary: 215 90% 50%; /* Changé de 220 85% 60% à 215 90% 50% */
    --secondary-foreground: 0 0% 100%;
    --secondary-light: 215 90% 94%;

    /* Accent bleu marine */
    --accent: 205 85% 50%; /* Changé de 190 80% 60% à 205 85% 50% */
    --accent-foreground: 0 0% 100%;

    /* Actions négatives et positives */
    --destructive: 5 85% 52%;
    --destructive-foreground: 0 0% 100%;

    --success: 160 65% 45%;
    --warning: 40 95% 55%;

    /* Neutres avec une teinte bleutée plus marquée */
    --muted: 210 25% 96%; /* Plus bleuté */
    --muted-foreground: 220 20% 40%; /* Plus foncé */

    --border: 210 25% 80%; /* Plus bleuté */
    --input: 210 25% 96%;
    --ring: 210 95% 45%; /* Synchronisé avec --primary */

    --popover: 0 0% 100%;
    --popover-foreground: 220 30% 12%;

    /* Sidebar avec plus de bleu */
    --sidebar: 210 30% 97%; /* Plus bleuté */
    --sidebar-foreground: 220 30% 35%; /* Plus foncé */

    /* Charts — palette de bleus plus profonds */
    --chart-1: 210 95% 45%; /* Bleu principal */
    --chart-2: 215 90% 50%; /* Bleu secondaire */
    --chart-3: 205 85% 50%; /* Bleu accent */
    --chart-4: 220 80% 55%; /* Bleu marine */
    --chart-5: 195 85% 45%; /* Bleu turquoise foncé */

    /* Rayons */
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-lg: 0.75rem;
    --radius-xl: 1rem;
}

/* ========================================================= */
/* 🌙 THÈME SOMBRE - BLEU PROFOND */
/* ========================================================= */
.dark {
    --background: 220 25% 8%; /* Plus foncé */
    --foreground: 0 0% 98%;

    --card: 220 25% 10%; /* Plus foncé */
    --card-foreground: 0 0% 98%;

    /* Bleus plus intenses en mode sombre */
    --primary: 210 95% 55%; /* Plus lumineux sur fond sombre */
    --primary-foreground: 220 25% 8%;
    --primary-light: 210 95% 20%;
    --primary-dark: 210 95% 40%;

    --secondary: 215 90% 60%;
    --secondary-foreground: 220 25% 8%;
    --secondary-light: 215 90% 20%;

    --accent: 205 85% 60%;
    --accent-foreground: 220 25% 8%;

    --destructive: 5 85% 58%;
    --destructive-foreground: 0 0% 100%;

    --success: 160 65% 55%;
    --warning: 40 95% 60%;

    /* Neutres plus foncés avec une teinte bleue */
    --muted: 220 20% 16%; /* Plus foncé */
    --muted-foreground: 220 15% 70%; /* Plus clair pour contraste */

    --border: 220 20% 22%; /* Plus foncé */
    --input: 220 20% 16%;
    --ring: 210 95% 55%; /* Synchronisé avec --primary */

    --popover: 220 25% 10%;
    --popover-foreground: 0 0% 98%;

    --sidebar: 220 20% 10%; /* Plus foncé */
    --sidebar-foreground: 220 15% 70%;

    /* Charts - palette de bleus plus intenses */
    --chart-1: 210 95% 55%;
    --chart-2: 215 90% 60%;
    --chart-3: 205 85% 60%;
    --chart-4: 220 80% 65%;
    --chart-5: 195 85% 55%;
}

/* ========================================================= */
/* 🛠️ TAILWIND BASE FIXES (inchangé) */
/* ========================================================= */
@layer base {
    /* Conversion pour utilisation hsl(var()) */
    :root,
    .dark {
        --background: hsl(var(--background));
        --foreground: hsl(var(--foreground));
        --card: hsl(var(--card));
        --card-foreground: hsl(var(--card-foreground));
        --popover: hsl(var(--popover));
        --popover-foreground: hsl(var(--popover-foreground));

        --primary: hsl(var(--primary));
        --primary-foreground: hsl(var(--primary-foreground));

        --secondary: hsl(var(--secondary));
        --secondary-foreground: hsl(var(--secondary-foreground));

        --muted: hsl(var(--muted));
        --muted-foreground: hsl(var(--muted-foreground));

        --accent: hsl(var(--accent));
        --accent-foreground: hsl(var(--accent-foreground));

        --destructive: hsl(var(--destructive));
        --destructive-foreground: hsl(var(--destructive-foreground));

        --border: hsl(var(--border));
        --input: hsl(var(--input));
        --ring: hsl(var(--ring));

        --chart-1: hsl(var(--chart-1));
        --chart-2: hsl(var(--chart-2));
        --chart-3: hsl(var(--chart-3));
        --chart-4: hsl(var(--chart-4));
        --chart-5: hsl(var(--chart-5));
    }

    /* Styles de base */
    * {
        border-color: hsl(var(--border));
    }

    body {
        background-color: hsl(var(--background));
        color: hsl(var(--foreground));
        font-feature-settings:
            'rlig' 1,
            'calt' 1;
    }

    :focus-visible {
        outline: 2px solid hsl(var(--ring));
        outline-offset: 2px;
    }
}

@layer base {
    * {
        @apply border-border;
    }
    body {
        @apply bg-background text-foreground;
        transition:
            background-color 0.3s ease,
            color 0.3s ease;
    }
}

/* Améliorations pour les composants shadcn */
@layer components {
    /* Boutons améliorés */
    .btn {
        @apply rounded-lg font-medium transition-all duration-200 ease-out;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary {
        @apply bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
        box-shadow: 0 4px 14px 0 hsl(var(--primary) / 0.3);
    }

    .btn-secondary {
        @apply bg-secondary text-secondary-foreground hover:bg-secondary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
    }

    .btn-destructive {
        @apply bg-destructive text-destructive-foreground hover:bg-destructive/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
    }

    .btn-outline {
        @apply border border-input bg-background hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
    }

    .btn-ghost {
        @apply hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
    }

    .btn-link {
        @apply text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2;
    }

    /* Cartes améliorées */
    .card {
        @apply rounded-xl border bg-card text-card-foreground shadow-sm transition-all duration-300;
    }

    .card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    /* Inputs améliorés */
    .input {
        @apply rounded-lg border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50;
        transition:
            border-color 0.2s ease,
            box-shadow 0.2s ease;
    }

    .input:focus {
        box-shadow: 0 0 0 3px hsl(var(--ring) / 0.1);
    }

    /* Checkbox et Radio améliorés */
    .checkbox,
    .radio {
        @apply rounded-md border border-input focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2;
    }

    /* Badges améliorés */
    .badge {
        @apply inline-flex items-center rounded-full border px-2.5 py-0.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2;
    }

    /* Alertes améliorées */
    .alert {
        @apply relative w-full rounded-lg border p-4;
    }

    .alert-destructive {
        @apply border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive;
    }

    /* Tooltip amélioré */
    .tooltip {
        @apply animate-in fade-in-0 zoom-in-95;
    }

    /* Dropdown amélioré */
    .dropdown-content {
        @apply animate-in fade-in-80 zoom-in-95;
        box-shadow: var(--shadow-lg);
    }

    /* Tableaux améliorés */
    .table {
        @apply w-full caption-bottom text-sm;
    }

    .table th {
        @apply h-12 px-4 text-left align-middle font-medium text-muted-foreground;
    }

    .table td {
        @apply p-4 align-middle;
    }

    .table tr {
        @apply transition-colors hover:bg-muted/50;
    }

    /* Progress bar améliorée */
    .progress {
        @apply h-2 overflow-hidden rounded-full bg-secondary;
    }

    .progress-value {
        @apply h-full transition-all duration-300;
        background: linear-gradient(
            90deg,
            hsl(var(--primary)),
            hsl(var(--primary-light))
        );
    }

    /* Skeleton amélioré */
    .skeleton {
        @apply animate-pulse rounded-md bg-muted;
    }

    /* Tabs améliorés */
    .tabs-list {
        @apply inline-flex h-10 items-center justify-center rounded-md bg-muted p-1 text-muted-foreground;
    }

    .tabs-trigger {
        @apply inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-background transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50;
    }

    .tabs-trigger[data-state='active'] {
        @apply bg-background text-foreground shadow-sm;
    }

    /* Accordion amélioré */
    .accordion-item {
        @apply border-b;
    }

    .accordion-trigger {
        @apply flex flex-1 items-center justify-between py-4 font-medium transition-all hover:underline;
    }

    .accordion-content {
        @apply overflow-hidden text-sm transition-all;
    }

    /* Dialog amélioré */
    .dialog-overlay {
        @apply fixed inset-0 z-50 bg-background/80 backdrop-blur-sm;
    }

    .dialog-content {
        @apply fixed left-[50%] top-[50%] z-50 grid w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 border bg-background p-6 shadow-lg duration-200;
        box-shadow: var(--shadow-lg);
    }
}

@layer utilities {
    /* Scrollbar personnalisée */
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        -webkit-overflow-scrolling: touch;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
        height: 6px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 2px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: rgba(156, 163, 175, 0.5);
        border-radius: 3px;
        transition: background-color 0.3s;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background-color: rgba(156, 163, 175, 0.7);
    }

    .custom-scrollbar:hover::-webkit-scrollbar {
        opacity: 1;
    }

    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
    }

    /* Cacher scrollbar */
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }

    .scrollbar-hide {
        scrollbar-width: none;
    }

    /* Text shadow pour améliorer la lisibilité */
    .text-shadow {
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .text-shadow-none {
        text-shadow: none;
    }

    /* Animation d'entrée personnalisée */
    .animate-in {
        animation-duration: 0.3s;
        animation-fill-mode: both;
    }

    .fade-in {
        animation-name: fadeIn;
    }

    .slide-in-from-top {
        animation-name: slideInFromTop;
    }

    .slide-in-from-bottom {
        animation-name: slideInFromBottom;
    }

    .slide-in-from-left {
        animation-name: slideInFromLeft;
    }

    .slide-in-from-right {
        animation-name: slideInFromRight;
    }

    /* Animations keyframes */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideInFromTop {
        from {
            transform: translateY(-10px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes slideInFromBottom {
        from {
            transform: translateY(10px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    @keyframes slideInFromLeft {
        from {
            transform: translateX(-10px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideInFromRight {
        from {
            transform: translateX(10px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
}

.translated {
    transform: translateY(-1rem) !important;
}

/* Styles pour React DatePicker */
.react-datepicker {
    font-family: var(--font-sans);
    border: 1px solid hsl(var(--border));
    border-radius: var(--radius-lg);
    background-color: hsl(var(--card));
    color: hsl(var(--foreground));
    box-shadow: var(--shadow-lg);
    padding: 1rem;
    z-index: 100;
}

.react-datepicker__header {
    background-color: hsl(var(--card));
    border-bottom: 1px solid hsl(var(--border));
    padding: 0.5rem 0;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
}

.react-datepicker__current-month {
    font-size: 1rem;
    font-weight: 600;
    color: hsl(var(--foreground)) !important;
    margin-bottom: 0.5rem;
}

.react-datepicker__day-names {
    display: flex;
    justify-content: space-around;
    margin-bottom: 0.5rem;
}

.react-datepicker__day-name {
    color: hsl(var(--muted-foreground));
    font-size: 0.875rem;
    font-weight: 500;
    width: 2.5rem;
    line-height: 2.5rem;
}

.react-datepicker__month {
    margin: 0;
}

.react-datepicker__week {
    display: flex;
    justify-content: space-around;
}

.react-datepicker__day {
    width: 2.5rem;
    height: 2.5rem;
    line-height: 2.5rem;
    border-radius: var(--radius);
    margin: 0.125rem;
    font-size: 0.875rem;
    color: hsl(var(--foreground));
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.react-datepicker__day:hover {
    background-color: hsl(var(--primary-light));
    color: hsl(var(--primary-foreground));
}

.react-datepicker__day--selected,
.react-datepicker__day--keyboard-selected {
    background-color: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    font-weight: 600;
}

.react-datepicker__day--today {
    font-weight: 700;
    position: relative;
}

.react-datepicker__day--today:not(.react-datepicker__day--selected):not(
        .react-datepicker__day--keyboard-selected
    ) {
    color: hsl(var(--primary));
}

.react-datepicker__day--today:not(.react-datepicker__day--selected):not(
        .react-datepicker__day--keyboard-selected
    ):after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 4px;
    height: 4px;
    background-color: hsl(var(--primary));
    border-radius: 50%;
}

.react-datepicker__day--disabled {
    color: hsl(var(--muted-foreground) / 0.5);
    cursor: not-allowed;
}

.react-datepicker__day--disabled:hover {
    background-color: transparent;
    color: hsl(var(--muted-foreground) / 0.5);
}

.react-datepicker__day--outside-month {
    color: hsl(var(--muted-foreground) / 0.3);
}

.react-datepicker__day--weekend:not(.react-datepicker__day--disabled):not(
        .react-datepicker__day--selected
    ):not(.react-datepicker__day--keyboard-selected) {
    color: hsl(var(--muted-foreground));
}

.react-datepicker__navigation {
    top: 1rem;
    width: 2rem;
    height: 2rem;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: transparent;
    border: 1px solid hsl(var(--border));
    transition: all 0.2s ease;
}

.react-datepicker__navigation:hover {
    background-color: hsl(var(--muted));
    border-color: hsl(var(--input));
}

.react-datepicker__navigation--previous {
    left: 1rem;
}

.react-datepicker__navigation--next {
    right: 1rem;
}

.react-datepicker__navigation-icon {
    position: relative;
    top: 0;
    left: 0;
    width: 0.75rem;
    height: 0.75rem;
    border-style: solid;
    border-width: 2px 2px 0 0;
    border-color: hsl(var(--muted-foreground));
    transform: rotate(45deg);
}

.react-datepicker__navigation-icon--next {
    transform: rotate(45deg);
}

.react-datepicker__navigation-icon--previous {
    transform: rotate(-135deg);
}

.react-datepicker__navigation:hover .react-datepicker__navigation-icon {
    border-color: hsl(var(--foreground));
}

/* Styles pour le mode sombre */
.dark .react-datepicker {
    background-color: hsl(var(--card));
    border-color: hsl(var(--border));
}

.dark .react-datepicker__header {
    background-color: hsl(var(--card));
    border-bottom-color: hsl(var(--border));
}

.dark .react-datepicker__day {
    color: hsl(var(--foreground));
}

.dark .react-datepicker__day:hover {
    background-color: hsl(var(--primary-light));
    color: hsl(var(--primary-foreground));
}

.dark .react-datepicker__day--disabled {
    color: hsl(var(--muted-foreground) / 0.4);
}

.dark .react-datepicker__day--outside-month {
    color: hsl(var(--muted-foreground) / 0.2);
}

.dark
    .react-datepicker__day--weekend:not(.react-datepicker__day--disabled):not(
        .react-datepicker__day--selected
    ):not(.react-datepicker__day--keyboard-selected) {
    color: hsl(var(--muted-foreground) / 0.7);
}

.dark .react-datepicker__navigation {
    border-color: hsl(var(--border));
}

.dark .react-datepicker__navigation:hover {
    background-color: hsl(var(--muted));
    border-color: hsl(var(--input));
}

/* Styles responsives */
@media (max-width: 640px) {
    .react-datepicker {
        width: 100%;
        max-width: 320px;
        padding: 0.75rem;
    }

    .react-datepicker__day {
        width: 2.25rem;
        height: 2.25rem;
        line-height: 2.25rem;
        margin: 0.1rem;
    }

    .react-datepicker__day-name {
        width: 2.25rem;
        line-height: 2.25rem;
    }
}

/* Animation d'apparition */
@keyframes datepickerFadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.react-datepicker {
    animation: datepickerFadeIn 0.2s ease-out;
}

/* Accessibilité - focus states */
.react-datepicker__day:focus-visible {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 2px;
}

.react-datepicker__navigation:focus-visible {
    outline: 2px solid hsl(var(--ring));
    outline-offset: 2px;
}

.relevance-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 500;
    background-color: #dbeafe;
    color: #1e40af;
    margin-bottom: 0.5rem;
}

.relevance-badge svg {
    margin-right: 0.25rem;
    width: 0.75rem;
    height: 0.75rem;
}

/* Améliorations générales pour l'UX */
.focus-ring {
    @apply focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2;
}

.smooth-transition {
    @apply transition-all duration-200 ease-out;
}

.hover-lift {
    @apply transition-transform duration-200 ease-out hover:-translate-y-0.5;
}

.hover-lift-md {
    @apply transition-transform duration-200 ease-out hover:-translate-y-1;
}

/* Effets de profondeur */
.shadow-depth-1 {
    box-shadow:
        0 1px 3px 0 rgba(0, 0, 0, 0.1),
        0 1px 2px 0 rgba(0, 0, 0, 0.06);
}

.shadow-depth-2 {
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.shadow-depth-3 {
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Gradient pour les éléments importants */
.gradient-primary {
    background: linear-gradient(
        135deg,
        hsl(var(--primary)),
        hsl(var(--primary-dark))
    );
}

.gradient-accent {
    background: linear-gradient(
        135deg,
        hsl(var(--accent)),
        hsl(var(--warning))
    );
}

/* Styles pour les états de chargement */
.pulse-soft {
    animation: pulseSoft 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulseSoft {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.8;
    }
}

/* Amélioration de la sélection de texte */
::selection {
    background-color: hsl(var(--primary) / 0.2);
    color: hsl(var(--foreground));
}

/* Styles pour les séparateurs */
.separator {
    @apply border-border;
}

.separator-horizontal {
    @apply h-px w-full;
}

.separator-vertical {
    @apply h-full w-px;
}


REFACTORE MON CODE ;
