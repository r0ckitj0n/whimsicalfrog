import React from 'react';
import { ImageGallery } from '../ImageGallery.js';
import { NestedInventoryTable } from '../NestedInventoryTable.js';
import { InventoryOptionsSummary } from '../InventoryOptionsSummary.js';
import { FieldLockIcon } from '../FieldLockIcon.js';
import type { IItemImage } from '../../../../types/inventory.js';

interface MediaAndVariantsSectionProps {
    sku: string;
    isAdding: boolean;
    mode: 'edit' | 'view' | 'add' | '';
    isReadOnly: boolean;
    onStockChange: (newTotal: number) => void;
    onOpenInventoryOptions?: () => void;
    formData: {
        weight_oz: number;
        package_length_in: number;
        package_width_in: number;
        package_height_in: number;
    };
    onFieldChange: (field: string, value: string | number) => void;
    lockedFields?: Record<string, boolean>;
    onToggleFieldLock?: (field: string) => void;
    onImagesChanged?: (images: IItemImage[]) => void;
}

export const MediaAndVariantsSection: React.FC<MediaAndVariantsSectionProps> = ({
    sku,
    isAdding,
    mode,
    isReadOnly,
    onStockChange,
    onOpenInventoryOptions,
    formData,
    onFieldChange,
    lockedFields = {},
    onToggleFieldLock,
    onImagesChanged
}) => {
    return (
        <div className="flex flex-col gap-5 pt-0 bg-gradient-to-b from-slate-50 to-slate-100/70 border-t border-slate-200">
            <div className="bg-gradient-to-br from-amber-50 via-orange-50/70 to-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                <div className="px-4 py-2.5 bg-amber-50/80 border-b border-amber-200">
                    <h3 className="text-[10px] font-bold text-amber-800 uppercase tracking-widest flex items-center gap-2">
                        <span>🖼️</span> Item Images
                    </h3>
                </div>
                <div className="p-4">
                <ImageGallery
                    sku={sku}
                    isEdit={mode === 'edit' || isAdding}
                    isReadOnly={isReadOnly}
                    onImagesChanged={onImagesChanged}
                />
                </div>
            </div>

            <div className="bg-gradient-to-br from-slate-50 via-white to-slate-100/60 rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
                <div className="px-4 py-2.5 bg-slate-100/80 border-b border-slate-200">
                    <h3 className="text-[10px] font-bold text-slate-700 uppercase tracking-widest flex items-center gap-2">
                        <span>🚚</span> Shipping Dimensions
                    </h3>
                </div>
                <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1 mb-1">
                            Weight (oz)
                            <FieldLockIcon
                                isLocked={!!lockedFields.weight_oz}
                                onToggle={() => onToggleFieldLock?.('weight_oz')}
                                fieldName="Weight (oz)"
                                disabled={isReadOnly || !onToggleFieldLock}
                            />
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            value={formData.weight_oz}
                            onChange={(e) => onFieldChange('weight_oz', parseFloat(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2 bg-white"
                            placeholder="0.0"
                        />
                    </div>
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1 mb-1">
                            Length (in)
                            <FieldLockIcon
                                isLocked={!!lockedFields.package_length_in}
                                onToggle={() => onToggleFieldLock?.('package_length_in')}
                                fieldName="Package Length (in)"
                                disabled={isReadOnly || !onToggleFieldLock}
                            />
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            value={formData.package_length_in}
                            onChange={(e) => onFieldChange('package_length_in', parseFloat(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2 bg-white"
                            placeholder="L"
                        />
                    </div>
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1 mb-1">
                            Width (in)
                            <FieldLockIcon
                                isLocked={!!lockedFields.package_width_in}
                                onToggle={() => onToggleFieldLock?.('package_width_in')}
                                fieldName="Package Width (in)"
                                disabled={isReadOnly || !onToggleFieldLock}
                            />
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            value={formData.package_width_in}
                            onChange={(e) => onFieldChange('package_width_in', parseFloat(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2 bg-white"
                            placeholder="W"
                        />
                    </div>
                    <div>
                        <label className="text-[10px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-1 mb-1">
                            Height (in)
                            <FieldLockIcon
                                isLocked={!!lockedFields.package_height_in}
                                onToggle={() => onToggleFieldLock?.('package_height_in')}
                                fieldName="Package Height (in)"
                                disabled={isReadOnly || !onToggleFieldLock}
                            />
                        </label>
                        <input
                            type="number"
                            step="0.1"
                            value={formData.package_height_in}
                            onChange={(e) => onFieldChange('package_height_in', parseFloat(e.target.value) || 0)}
                            disabled={isReadOnly}
                            className="form-input w-full py-2 bg-white"
                            placeholder="H"
                        />
                    </div>
                </div>
            </div>

            {!isAdding && (
                <div className="bg-gradient-to-br from-emerald-50 via-green-50/70 to-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
                    <div className="px-4 py-2.5 bg-emerald-50/80 border-b border-emerald-200 flex items-center justify-between gap-3">
                        <h3 className="text-[10px] font-bold text-emerald-800 uppercase tracking-widest flex items-center gap-2">
                            <span>🧩</span> Variants & Options
                        </h3>
                        <button
                            type="button"
                            className="btn btn-secondary px-3 py-1.5 text-[10px] font-black uppercase tracking-widest"
                            onClick={onOpenInventoryOptions}
                            disabled={!onOpenInventoryOptions}
                            data-help-id="inventory-open-inventory-options"
                        >
                            Inventory Options
                        </button>
                    </div>
                    <div className="p-4 grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <p className="text-xs text-slate-600">
                                Variant editor (inventory rows) and the configured option rules (from Inventory Options).
                            </p>
                            <NestedInventoryTable
                                sku={sku}
                                isReadOnly={isReadOnly}
                                onStockChange={onStockChange}
                            />
                        </div>
                        <InventoryOptionsSummary sku={sku} isReadOnly={isReadOnly} />
                    </div>
                </div>
            )}
        </div>
    );
};
