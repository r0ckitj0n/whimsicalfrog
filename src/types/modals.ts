export type ModalMode = 'confirm' | 'alert' | 'prompt' | 'component';
export type ModalIconType = 'warning' | 'danger' | 'info' | 'success';

export interface IModalOptions {
    title?: string;
    message?: string;
    subtitle?: string;
    details?: string;
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
