import React from 'react';

/**
 * SettingsCardProps defines the expected properties for our settings section cards.
 */
interface SettingsCardProps {
    title: string;
    description: string;
    theme: 'emerald' | 'orange' | 'blue' | 'red' | 'purple';
    children: React.ReactNode;
}

/**
 * SettingsCard
 * 
 * A reusable React version of the legacy PHP wf_render_settings_card.
 * Maintains the exact structural and CSS class signatures used by the original
 * admin dashboard for visual consistency.
 */
export const SettingsCard: React.FC<SettingsCardProps> = ({
    title,
    description,
    theme,
    children
}) => {
    const themeClass = `card-theme-${theme}`;

    return (
        <section className={`settings-section ${themeClass}`}>
            <header className="section-header">
                <h3 className="section-title">{title}</h3>
                <p className="section-description">{description}</p>
            </header>
            <div className="section-content">
                {children}
            </div>
        </section>
    );
};
