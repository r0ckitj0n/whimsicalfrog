import React from 'react';

interface GlobalModalIconProps {
    iconKey?: string;
    icon?: string | React.ReactNode;
    iconType?: 'success' | 'danger' | 'info' | 'warning';
}

export const GlobalModalIcon: React.FC<GlobalModalIconProps> = ({ iconKey, icon, iconType }) => {
    if (iconKey) {
        // Map common keys to emoji classes
        const classMap: Record<string, string> = {
            'edit': 'btn-icon--edit',
            'delete': 'btn-icon--delete',
            'sparkles': 'btn-icon--sparkles',
            'rotate': 'btn-icon--refresh',
            'rocket': 'btn-icon--rocket'
        };

        if (classMap[iconKey]) {
            return <span className={`admin-action-btn ${classMap[iconKey]}`} aria-hidden="true" />;
        }
    }

    if (icon && typeof icon === 'string') return <span className="text-xl">{icon}</span>;
    if (React.isValidElement(icon)) return icon as React.ReactElement;

    const typeMap: Record<string, string> = {
        'success': 'btn-icon--check',
        'danger': 'btn-icon--warning',
        'info': 'btn-icon--info',
        'warning': 'btn-icon--warning'
    };

    const typeClass = iconType ? typeMap[iconType] : 'btn-icon--info';
    const textColor = iconType === 'success' ? 'text-[var(--brand-accent)]' :
        iconType === 'danger' ? 'text-[var(--brand-error)]' :
            iconType === 'info' ? 'text-[var(--brand-primary)]' :
                'text-[var(--brand-secondary)]';

};
