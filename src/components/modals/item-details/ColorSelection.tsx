import React from 'react';

interface Color {
    id: string;
    name: string;
    code: string;
}

interface ColorSelectionProps {
    availableColors: Color[];
    selectedColor: string;
    onSelect: (color_id: string) => void;
}

/**
 * ColorSelection v1.0.73
 */
export const ColorSelection: React.FC<ColorSelectionProps> = ({
    availableColors,
    selectedColor,
    onSelect
}) => {
    if (availableColors.length === 0) return null;

    return (
        <div style={{ marginBottom: '24px', width: '100%' }}>
            <style dangerouslySetInnerHTML={{
                __html: `
                .color-btn-v73 {
                    width: 48px !important;
                    height: 48px !important;
                    border-radius: 9999px !important;
                    border: 3px solid transparent !important;
                    padding: 4px !important;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    background-color: #ffffff !important;
                    cursor: pointer !important;
                    transform: scale(1) !important;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
                    outline: none !important;
                    display: flex !important;
                    align-items: center !important;
                    justifyContent: center !important;
                }
                .color-btn-v73.active {
                    border-color: var(--brand-primary) !important;
                    transform: scale(1.15) !important;
                    box-shadow: 0 8px 20px rgba(var(--brand-primary-rgb), 0.4) !important;
                }
                .color-swatch-v73 {
                    width: 100% !important;
                    height: 100% !important;
                    border-radius: 9999px !important;
                    border: 1px solid rgba(0,0,0,0.1) !important;
                    box-shadow: inset 0 2px 4px rgba(0,0,0,0.1) !important;
                }
            ` }} />
            <label style={{
                fontSize: '12px',
                fontWeight: 900,
                color: '#6b7280',
                textTransform: 'uppercase',
                letterSpacing: '0.15em',
                marginBottom: '16px',
                display: 'block'
            }}>
                Select Color
            </label>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '16px' }}>
                {availableColors.map((c) => (
                    <button
                        key={c.id}
                        type="button"
                        onClick={() => onSelect(String(c.id))}
                        className={`color-btn-v73 ${selectedColor === String(c.id) ? 'active' : ''}`}
                        data-help-id={`color-${c.name.toLowerCase().replace(/\s+/g, '-')}`}
                    >
                        <div
                            className="color-swatch-v73"
                            style={{ backgroundColor: c.code || '#ccc' }}
                        />
                    </button>
                ))}
            </div>
        </div>
    );
};
