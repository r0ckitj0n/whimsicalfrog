import React, { useEffect, useRef } from 'react';

interface EditableSelectProps {
    value: string;
    choices: string[];
    onSave: (newValue: string) => void;
    onCancel: () => void;
    className?: string;
}

export const EditableSelect: React.FC<EditableSelectProps> = ({
    value,
    choices,
    onSave,
    onCancel,
    className = ""
}) => {
    const selectRef = useRef<HTMLSelectElement>(null);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (selectRef.current) {
                selectRef.current.focus();
            }
        }, 100);
        return () => clearTimeout(timer);
    }, []);

    const handleBlur = (e: React.FocusEvent) => {
        // Re-enable onBlur with a safe delay to allow clicks to register
        setTimeout(() => {
            onCancel();
        }, 200);
    };

    return (
        <div 
            className="relative w-full min-w-[120px] z-[var(--wf-z-modal)]" 
            onClick={(e) => e.stopPropagation()}
            onMouseDown={(e) => e.stopPropagation()}
        >
            <select
                ref={selectRef}
                value={value || ''}
                onChange={(e) => {
                    const newVal = e.target.value;
                    onSave(newVal);
                }}
                onBlur={handleBlur}
                className={`text-xs border-2 border-[var(--brand-primary)] rounded px-1 py-1 w-full bg-white font-bold focus:outline-none shadow-lg text-black cursor-pointer ${className}`}
            >
                {!value && <option value="" className="text-black">— Select —</option>}
                {choices.map(c => (
                    <option key={c} value={c} className="text-black bg-white">
                        {c}
                    </option>
                ))}
            </select>
        </div>
    );
};
