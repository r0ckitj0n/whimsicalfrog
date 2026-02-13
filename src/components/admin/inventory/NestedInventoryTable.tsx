import React, { useState, useEffect, useMemo } from 'react';
import { useInventoryOptions } from '../../../hooks/admin/useInventoryOptions.js';
import { IItemColor, IItemSize } from '../../../types/index.js';
import { InventoryFilters } from './InventoryFilters.js';
import { ColorGroup } from './ColorGroup.js';

interface NestedInventoryTableProps {
    sku: string;
    isReadOnly?: boolean;
    onStockChange?: (new_total: number) => void;
}

export const NestedInventoryTable: React.FC<NestedInventoryTableProps> = ({ sku, isReadOnly = false, onStockChange }) => {
    const {
        colors,
        sizes,
        isLoading,
        error,
        fetchColors,
        fetchSizes,
        saveSize,
        syncStock,
        distributeStockEvenly,
        ensureColorSizes
    } = useInventoryOptions(sku);

    const [genderFilter, setGenderFilter] = useState('');
    const [colorFilter, setColorFilter] = useState('');
    const [searchTerm, setSearchTerm] = useState('');
    const [sortBy, setSortBy] = useState<'code' | 'name' | 'stock'>('code');
    const [showInactive, setShowInactive] = useState(false);
    const [expandedGenders, setExpandedGenders] = useState<Set<string>>(new Set());

    useEffect(() => {
        if (sku) {
            fetchColors();
            fetchSizes();
        }
    }, [sku, fetchColors, fetchSizes]);

    const genders = useMemo(() => {
        const set = new Set<string>();
        sizes.forEach(s => {
            set.add(s.gender || 'Unisex');
        });
        if (set.size === 0) set.add('Unisex');
        return Array.from(set).filter(g => !genderFilter || g === genderFilter);
    }, [sizes, genderFilter]);

    const filteredSizes = useMemo(() => {
        return sizes.filter(s => {
            const matchesGender = !genderFilter || (s.gender || 'Unisex') === genderFilter;
            const matchesColor = !colorFilter || String(s.color_id) === colorFilter;
            const matchesSearch = !searchTerm ||
                (s.size_name || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
                (s.size_code || '').toLowerCase().includes(searchTerm.toLowerCase());
            const matchesActive = showInactive || s.is_active;
            return matchesGender && matchesColor && matchesSearch && matchesActive;
        }).sort((a, b) => {
            if (sortBy === 'name') return (a.size_name || '').localeCompare(b.size_name || '');
            if (sortBy === 'stock') return a.stock_level - b.stock_level;
            return (a.size_code || '').localeCompare(b.size_code || '', undefined, { numeric: true, sensitivity: 'base' });
        });
    }, [sizes, genderFilter, colorFilter, searchTerm, sortBy, showInactive]);

    const toggleGender = (gender: string) => {
        const next = new Set(expandedGenders);
        if (next.has(gender)) next.delete(gender);
        else next.add(gender);
        setExpandedGenders(next);
    };

    const handleSyncStock = async () => {
        const new_total = await syncStock();
        if (new_total !== null && new_total !== undefined && onStockChange) onStockChange(new_total);
    };

    if (!sku) return null;

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <div className="text-sm font-bold text-emerald-900 uppercase tracking-wide">Inventory Variant Editor</div>
                {!isReadOnly && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={ensureColorSizes}
                            className="admin-action-btn btn-icon--add"
                            data-help-id="inventory-action-ensure-sizes"
                        />
                        <button
                            type="button"
                            onClick={distributeStockEvenly}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="inventory-action-distribute-stock"
                        />
                        <button
                            type="button"
                            onClick={handleSyncStock}
                            className="admin-action-btn btn-icon--save"
                            data-help-id="inventory-action-sync-stock"
                        />
                    </div>
                )}
            </div>

            <InventoryFilters
                genderFilter={genderFilter}
                setGenderFilter={setGenderFilter}
                colorFilter={colorFilter}
                setColorFilter={setColorFilter}
                searchTerm={searchTerm}
                setSearchTerm={setSearchTerm}
                sortBy={sortBy}
                setSortBy={setSortBy}
                showInactive={showInactive}
                setShowInactive={setShowInactive}
                colors={colors}
            />

            {error && <div className="p-3 mb-4 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded-lg">{error}</div>}

            <div className="space-y-3">
                {genders.map(gender => {
                    const genderSizes = filteredSizes.filter(s => (s.gender || 'Unisex') === gender);
                    if (genderSizes.length === 0) return null;

                    const isExpanded = expandedGenders.has(gender);
                    const total_stock = genderSizes.reduce((sum, s) => sum + s.stock_level, 0);

                    return (
                        <div key={gender} className="border rounded-lg overflow-hidden bg-white">
                            <button
                                type="button"
                                onClick={() => toggleGender(gender)}
                                className="w-full flex items-center justify-between px-4 py-2 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
                            >
                                <div className="flex items-center gap-3">
                                    <span className={`transform transition-transform ${isExpanded ? 'rotate-90' : ''}`}>▶</span>
                                    <span className="font-medium text-gray-900">{gender}</span>
                                    <span className="text-[var(--brand-primary)] text-[10px] font-semibold uppercase tracking-wider">Group</span>
                                </div>
                                <div className="text-sm font-normal text-gray-600">
                                    Total: <span className="text-gray-900">{total_stock}</span>
                                </div>
                            </button>

                            {isExpanded && (
                                <div className="p-4 space-y-4">
                                    {colors.filter(c => !colorFilter || String(c.id) === colorFilter).map(color => {
                                        const colorSizes = genderSizes.filter(s => s.color_id === color.id);
                                        if (colorSizes.length === 0) return null;

                                        return (
                                            <ColorGroup
                                                key={color.id}
                                                color={color}
                                                colorSizes={colorSizes}
                                                isReadOnly={isReadOnly}
                                                isLoading={isLoading}
                                                onStockUpdate={(sizeId, newStock) => saveSize({ id: sizeId, stock_level: newStock })}
                                            />
                                        );
                                    })}

                                    {(!colorFilter || colorFilter === 'general') && (() => {
                                        const generalSizes = genderSizes.filter(s => !s.color_id);
                                        if (generalSizes.length === 0) return null;
                                        const generalTotal = generalSizes.reduce((sum, s) => sum + s.stock_level, 0);

                                        return (
                                            <div className="border border-[var(--brand-secondary)]/20 rounded-lg overflow-hidden bg-[var(--brand-secondary)]/5">
                                                <div className="px-3 py-2 border-b border-[var(--brand-secondary)]/20 flex items-center justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-normal text-[var(--brand-secondary)]">General Sizes (Not tied to a color)</span>
                                                        <span className="text-[10px] text-[var(--brand-secondary)] font-semibold uppercase tracking-widest">General</span>
                                                    </div>
                                                    <div className="text-xs font-medium text-gray-900">
                                                        Total: {generalTotal}
                                                    </div>
                                                </div>
                                                <table className="min-w-full divide-y divide-[var(--brand-secondary)]/20">
                                                    <thead className="bg-[var(--brand-secondary)]/10">
                                                        <tr>
                                                            <th className="px-4 py-2 text-left text-xs font-medium text-[var(--brand-secondary)] uppercase">Size</th>
                                                            <th className="px-4 py-2 text-left text-xs font-medium text-[var(--brand-secondary)] uppercase">Code</th>
                                                            <th className="px-4 py-2 text-right text-xs font-medium text-[var(--brand-secondary)] uppercase">Stock</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-[var(--brand-secondary)]/20">
                                                        {generalSizes.map(size => (
                                                            <tr key={size.id} className={size.is_active ? '' : 'opacity-60'}>
                                                                <td className="px-4 py-2 text-sm text-[var(--brand-secondary)]">{size.size_name}</td>
                                                                <td className="px-4 py-2 text-sm text-[var(--brand-secondary)]/80">{size.size_code}</td>
                                                                <td className="px-4 py-2 text-right">
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        defaultValue={size.stock_level}
                                                                        className="w-20 text-right border border-[var(--brand-secondary)]/30 rounded px-2 py-1 text-sm bg-white focus:ring-1 focus:ring-[var(--brand-secondary)]"
                                                                        disabled={isReadOnly || isLoading}
                                                                        onBlur={(e) => saveSize({ id: size.id, stock_level: parseInt(e.target.value, 10) })}
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        );
                                    })()}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
