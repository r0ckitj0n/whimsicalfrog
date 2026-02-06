import React from 'react';

interface FieldLockIconProps {
    /** Whether the field is currently locked */
    isLocked: boolean;
    /** Callback when lock is toggled */
    onToggle: () => void;
    /** Field name for tooltip */
    fieldName: string;
    /** Whether the lock is interactive (false in read-only mode) */
    disabled?: boolean;
}

/**
 * FieldLockIcon - A toggle button to lock/unlock AI-generated fields
 * 
 * When locked (🔒), the field will not be overwritten by AI generation.
 * When unlocked (🔓), the field can be updated by AI suggestions.
 */
export const FieldLockIcon: React.FC<FieldLockIconProps> = ({
    isLocked,
    onToggle,
    fieldName,
    disabled = false
}) => {
    const handleClick = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (!disabled) {
            onToggle();
        }
    };

    const title = disabled
        ? `${fieldName} is ${isLocked ? 'protected' : 'unprotected'}`
        : isLocked
            ? `🔒 ${fieldName} is protected from AI updates. Click to unlock.`
            : `🔓 ${fieldName} can be updated by AI. Click to protect.`;

    return (
        <button
            type="button"
            onClick={handleClick}
            disabled={disabled}
            className={`
                field-lock-icon inline-flex items-center justify-center
                w-6 h-6 rounded-full transition-all duration-200
                ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-110 active:scale-95'}
                ${isLocked
                    ? 'bg-amber-50 hover:bg-amber-100'
                    : 'bg-gray-100 text-gray-400 opacity-40 hover:opacity-80 hover:bg-gray-200 hover:text-gray-600'
                }
            `}
            title={title}
            data-help-id={`field-lock-${fieldName.toLowerCase().replace(/\s+/g, '-')}`}
        >
            <span className="text-sm leading-none">
                {isLocked ? '🔐' : '🔓'}
            </span>
        </button>
    );
};
