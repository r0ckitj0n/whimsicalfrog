import React from 'react';
import { IInventoryFilters } from '../../../hooks/admin/useInventory.js';

interface MainInventoryFiltersProps {
    filters: IInventoryFilters;
    setFilters: (filters: IInventoryFilters) => void;
    categories: string[];
    onRefresh: () => void;
    onAddNew: () => void;
    isModal?: boolean;
}

export const MainInventoryFilters: React.FC<MainInventoryFiltersProps> = ({
    filters,
    setFilters,
    categories,
    onRefresh,
    onAddNew,
    isModal
}) => {
    const handleSearchChange = (val: string) => {
        setFilters({ ...filters, search: val });
    };

    const handleCategoryChange = (val: string) => {
        setFilters({ ...filters, category: val });
    };

    const handleStockChange = (val: string) => {
        setFilters({ ...filters, stock: val });
    };
    const handleStatusChange = (val: 'active' | 'archived' | 'all') => {
        setFilters({ ...filters, status: val });
    };

    const handleReset = () => {
        setFilters({ search: '', category: '', stock: '', status: 'active' });
    };

    return (
        <div className="admin-filter--green bg-white border rounded-[2rem] p-6 shadow-sm space-y-6">
            <div className="flex flex-col md:flex-row gap-4 items-center justify-between">
                <div className="flex-1 w-full flex items-center gap-2">
                    <label htmlFor="inventory-search" className="text-xs font-medium text-gray-600">Search</label>
                    <input
                        id="inventory-search"
                        type="text"
                        value={filters.search}
                        onChange={e => handleSearchChange(e.target.value)}
                        placeholder="Search SKU, name, or description..."
                        className="form-input form-input-search text-sm px-1 py-1"
                    />
                </div>

                <div className="flex flex-wrap gap-3 w-full md:w-auto">
                    <select
                        value={filters.category}
                        onChange={e => handleCategoryChange(e.target.value)}
                        className="form-select flex-1 md:flex-none md:w-48 py-3 rounded-xl bg-gray-50 border-transparent focus:bg-white transition-all shadow-sm font-medium text-gray-600 text-sm"
                    >
                        <option value="">All Categories</option>
                        {categories.map(cat => (
                            <option key={cat} value={cat}>{cat}</option>
                        ))}
                    </select>

                    <select
                        value={filters.stock}
                        onChange={e => handleStockChange(e.target.value)}
                        className="form-select flex-1 md:flex-none md:w-32 py-3 rounded-xl bg-gray-50 border-transparent focus:bg-white transition-all shadow-sm font-medium text-gray-600 text-sm"
                    >
                        <option value="">All Stock</option>
                        <option value="low">Low</option>
                        <option value="out">Out</option>
                        <option value="in">In</option>
                    </select>

                    <select
                        value={filters.status}
                        onChange={e => {
                            const val = e.target.value;
                            if (val === 'active' || val === 'archived' || val === 'all') {
                                handleStatusChange(val);
                            }
                        }}
                        className="form-select flex-1 md:flex-none md:w-32 py-3 rounded-xl bg-gray-50 border-transparent focus:bg-white transition-all shadow-sm font-medium text-gray-600 text-sm"
                    >
                        <option value="active">Active</option>
                        <option value="archived">Archived</option>
                        <option value="all">All Status</option>
                    </select>

                    <button
                        onClick={handleReset}
                        className="btn-standard-icon btn-icon--reset"
                        data-help-id="inventory-filter-reset"
                    />

                    {!isModal && (
                        <>
                            <button
                                onClick={onRefresh}
                                className="btn-standard-icon btn-icon--refresh"
                                data-help-id="inventory-filter-refresh"
                            />

                            <button
                                onClick={onAddNew}
                                className="btn-standard-icon btn-icon--add"
                                data-help-id="inventory-filter-add"
                            />
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};
