import React from 'react';
import { IItemColor } from '../../../types/index.js';

interface InventoryFiltersProps {
    genderFilter: string;
    setGenderFilter: (val: string) => void;
    colorFilter: string;
    setColorFilter: (val: string) => void;
    searchTerm: string;
    setSearchTerm: (val: string) => void;
    sortBy: 'code' | 'name' | 'stock';
    setSortBy: (val: 'code' | 'name' | 'stock') => void;
    showInactive: boolean;
    setShowInactive: (val: boolean) => void;
    colors: IItemColor[];
}

export const InventoryFilters: React.FC<InventoryFiltersProps> = ({
    genderFilter,
    setGenderFilter,
    colorFilter,
    setColorFilter,
    searchTerm,
    setSearchTerm,
    sortBy,
    setSortBy,
    showInactive,
    setShowInactive,
    colors
}) => {
    return (
        <div className="bg-gray-50 p-3 rounded-lg border mb-4">
            <div className="flex flex-wrap items-center gap-4">
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-600">Gender</label>
                    <select value={genderFilter} onChange={e => setGenderFilter(e.target.value)} className="border border-gray-300 rounded text-sm p-1 min-w-[100px]">
                        <option value="">All</option>
                        <option value="Unisex">Unisex</option>
                        <option value="Men">Men</option>
                        <option value="Women">Women</option>
                        <option value="Boys">Boys</option>
                        <option value="Girls">Girls</option>
                        <option value="Baby">Baby</option>
                    </select>
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-600">Color</label>
                    <select value={colorFilter} onChange={e => setColorFilter(e.target.value)} className="border border-gray-300 rounded text-sm p-1 min-w-[120px]">
                        <option value="">All Colors</option>
                        {colors.map(c => (
                            <option key={c.id} value={String(c.id)}>{c.color_name}</option>
                        ))}
                    </select>
                </div>
                <div className="flex flex-col gap-1 flex-1">
                    <label className="text-xs font-medium text-gray-600">Search</label>
                    <input
                        type="text"
                        value={searchTerm}
                        onChange={e => setSearchTerm(e.target.value)}
                        placeholder="Size name/code…"
                        className="form-input form-input-search text-sm px-2 py-1"
                    />
                </div>
                <div className="flex flex-col gap-1">
                    <label className="text-xs font-medium text-gray-600">Sort</label>
                    <select value={sortBy} onChange={e => setSortBy(e.target.value as 'code' | 'name' | 'stock')} className="border border-gray-300 rounded text-sm p-1">
                        <option value="code">Size Code</option>
                        <option value="name">Size Name</option>
                        <option value="stock">Stock</option>
                    </select>
                </div>
                <div className="flex items-center gap-2 self-end pb-1">
                    <label className="text-xs text-gray-600 inline-flex items-center gap-1 cursor-pointer">
                        <input type="checkbox" checked={showInactive} onChange={e => setShowInactive(e.target.checked)} className="rounded" />
                        Show inactive
                    </label>
                </div>
            </div>
        </div>
    );
};
