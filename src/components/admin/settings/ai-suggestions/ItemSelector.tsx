import React from 'react';
import { IInventoryItemMinimal } from '../../../../hooks/admin/useAIContentGenerator.js';
import { FieldLockIcon } from '../../inventory/FieldLockIcon.js';

interface ItemSelectorProps {
    items: IInventoryItemMinimal[];
    selectedSku: string;
    onSelectedSkuChange: (sku: string) => void;
    isLoadingItems: boolean;
    isLoadingSuggestions: boolean;
    currentItem: IInventoryItemMinimal | null;
    nameValue?: string;
    descriptionValue?: string;
    categoryValue?: string;
    categoryOptions?: string[];
    onNameChange?: (value: string) => void;
    onDescriptionChange?: (value: string) => void;
    onCategoryChange?: (value: string) => void;
    lockedFields?: Record<string, boolean>;
    onToggleFieldLock?: (field: string) => void;
    lockedWords?: Record<string, string>;
    onLockedWordsChange?: (field: string, value: string) => void;
    isReadOnly?: boolean;
    onGenerate: () => void;
}

export const ItemSelector: React.FC<ItemSelectorProps> = ({
    currentItem,
    nameValue,
    descriptionValue,
    categoryValue,
    categoryOptions = [],
    onNameChange,
    onDescriptionChange,
    onCategoryChange,
    lockedFields = {},
    onToggleFieldLock,
    lockedWords = {},
    onLockedWordsChange,
    isReadOnly = false
}) => {
    const [editingField, setEditingField] = React.useState<null | 'name' | 'category' | 'description'>(null);
    const [draftValue, setDraftValue] = React.useState<string>('');

    const startEdit = (field: typeof editingField, initialValue: string) => {
        setEditingField(field);
        setDraftValue(initialValue);
    };

    const commitEdit = () => {
        if (!editingField) return;
        const value = draftValue.trim();
        if (editingField === 'name' && onNameChange) onNameChange(value);
        if (editingField === 'description' && onDescriptionChange) onDescriptionChange(value);
        if (editingField === 'category' && onCategoryChange) onCategoryChange(value);
        setEditingField(null);
    };

    const cancelEdit = () => {
        setEditingField(null);
        setDraftValue('');
    };

    const renderLockedWordsRow = (field: 'name' | 'description') => {
        if (!lockedFields[field]) return null;
        return (
            <div className="py-2 border-b last:border-0">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-gray-600 text-xs font-semibold">Locked Words ({field})</span>
                    <input
                        type="text"
                        value={lockedWords[field] || ''}
                        onChange={(e) => onLockedWordsChange?.(field, e.target.value)}
                        disabled={isReadOnly}
                        className="w-64 text-right border border-gray-300 rounded px-2 py-1 text-xs text-gray-700 focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
                        placeholder="Use quotes for exact phrases"
                    />
                </div>
                <p className="mt-1 text-[10px] text-gray-500 text-right">
                    Lock enforces words, but AI can still rewrite around them.
                </p>
            </div>
        );
    };

    return (
        <div className="space-y-4 flex-1 flex flex-col">
            {!currentItem && (
                <div className="flex-1 flex items-center justify-center text-gray-400 text-sm italic text-center p-4">
                    Select an item from the header dropdown above
                </div>
            )}

            {currentItem && (
                <div className="animate-in fade-in slide-in-from-top-2">
                    {/* Item Details */}
                    <div className="border border-gray-200 rounded bg-white overflow-hidden">
                        <div className="px-3 py-2 bg-gray-50/50 text-[10px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">
                            Item Summary
                        </div>
                        <div className="px-3 py-2 space-y-1">
                            <div className="flex items-center justify-between gap-2 py-2 border-b last:border-0">
                                <span className="text-gray-800 text-sm font-semibold flex items-center gap-2">
                                    Name
                                    {onToggleFieldLock && (
                                        <FieldLockIcon
                                            isLocked={!!lockedFields.name}
                                            onToggle={() => onToggleFieldLock('name')}
                                            fieldName="Name"
                                            disabled={isReadOnly}
                                        />
                                    )}
                                </span>
                                {editingField === 'name' ? (
                                    <input
                                        type="text"
                                        value={draftValue}
                                        onChange={(e) => setDraftValue(e.target.value)}
                                        onBlur={commitEdit}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') commitEdit();
                                            if (e.key === 'Escape') cancelEdit();
                                        }}
                                        autoFocus
                                        className="w-56 text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
                                    />
                                ) : (
                                    <span
                                        className="text-sm font-bold text-gray-900 text-right max-w-[60%] cursor-pointer hover:underline"
                                        onClick={() => startEdit('name', nameValue ?? currentItem.name)}
                                    >
                                        {nameValue ?? currentItem.name}
                                    </span>
                                )}
                            </div>
                            {renderLockedWordsRow('name')}
                            <div className="flex items-center justify-between gap-2 py-2 border-b last:border-0">
                                <span className="text-gray-800 text-sm font-semibold">SKU</span>
                                <span className="text-sm font-bold text-gray-900">{currentItem.sku}</span>
                            </div>
                            <div className="flex items-center justify-between gap-2 py-2 border-b last:border-0">
                                <span className="text-gray-800 text-sm font-semibold">Category</span>
                                {editingField === 'category' ? (
                                    <select
                                        value={draftValue}
                                        onChange={(e) => {
                                            setDraftValue(e.target.value);
                                            if (onCategoryChange) onCategoryChange(e.target.value);
                                        }}
                                        onBlur={commitEdit}
                                        autoFocus
                                        className="text-right border border-gray-300 rounded px-2 py-1 text-sm font-bold focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20 bg-white"
                                    >
                                        {categoryOptions.map((option) => (
                                            <option key={option} value={option}>{option}</option>
                                        ))}
                                        {!categoryOptions.includes(draftValue) && draftValue && (
                                            <option value={draftValue}>{draftValue}</option>
                                        )}
                                    </select>
                                ) : (
                                    <span
                                        className="text-sm font-bold text-gray-900 cursor-pointer hover:underline"
                                        onClick={() => startEdit('category', (categoryValue ?? currentItem.category) || '')}
                                    >
                                        {(categoryValue ?? currentItem.category) || 'None'}
                                    </span>
                                )}
                            </div>
                            {(descriptionValue || currentItem.description) && (
                                <>
                                    <div className="flex items-start justify-between gap-2 py-2 border-b last:border-0">
                                        <span className="text-gray-800 text-sm font-semibold flex items-center gap-2">
                                            Description
                                            {onToggleFieldLock && (
                                                <FieldLockIcon
                                                    isLocked={!!lockedFields.description}
                                                    onToggle={() => onToggleFieldLock('description')}
                                                    fieldName="Description"
                                                    disabled={isReadOnly}
                                                />
                                            )}
                                        </span>
                                        {editingField === 'description' ? (
                                            <textarea
                                                value={draftValue}
                                                onChange={(e) => setDraftValue(e.target.value)}
                                                onBlur={commitEdit}
                                                rows={3}
                                                autoFocus
                                                className="w-64 text-right border border-gray-300 rounded px-2 py-1 text-xs text-gray-700 focus:ring-1 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)]/20"
                                            />
                                        ) : (
                                            <span
                                                className="text-xs text-gray-600 text-right max-w-[60%] line-clamp-3 cursor-pointer hover:underline"
                                                onClick={() => startEdit('description', (descriptionValue ?? currentItem.description) || '')}
                                            >
                                                {descriptionValue ?? currentItem.description}
                                            </span>
                                        )}
                                    </div>
                                    {renderLockedWordsRow('description')}
                                </>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
