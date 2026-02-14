export type ModalMode = 'confirm' | 'alert' | 'prompt' | 'component';
export type ModalIconType = 'warning' | 'danger' | 'info' | 'success';

export interface IModalOptions {
    title?: string;
    message?: string;
    subtitle?: string;
    /** Optional class overrides for typography/layout tweaks in specific modals. */
    messageClassName?: string;
    subtitleClassName?: string;
    details?: string;
    /** When true, hide the branded header bar (used for minimal confirm modals). */
    hideHeader?: boolean;
    /** When true, render the details block behind a toggle. */
    detailsCollapsible?: boolean;
    /** Label shown next to the details toggle (defaults to "Details"). */
    detailsLabel?: string;
    /** When details are collapsible, whether the details are expanded initially. */
    detailsDefaultOpen?: boolean;
    /**
     * Optional actions rendered inside the details section (only visible when details are shown).
     * Intended for links like "AI Settings" when fallback pricing is in effect.
     */
    detailsActions?: Array<{
        label: string;
        href?: string;
        target?: '_self' | '_blank';
        onClick?: () => void;
        style?: 'secondary' | 'primary' | 'warning';
    }>;
    icon?: string;
    iconType?: ModalIconType;
    iconKey?: string;
    confirmText?: string;
    cancelText?: string;
    confirmStyle?: 'confirm' | 'danger' | 'secondary' | 'primary' | 'warning';
    mode?: ModalMode;
    showCancel?: boolean;
    input?: {
        placeholder?: string;
        defaultValue?: string;
        type?: string;
    };
    /**
     * Optional extra actions rendered as additional buttons in the footer.
     * Intended for "fix it" navigation like "AI Settings" when fallbacks are in effect.
     */
    extraActions?: Array<{
        label: string;
        href?: string;
        target?: '_self' | '_blank';
        onClick?: () => void;
        style?: 'secondary' | 'primary' | 'warning';
    }>;
    component?: string;
    props?: Record<string, any>;
}

export interface IModalState extends IModalOptions {
    isOpen: boolean;
    resolve: (value: unknown) => void;
}
