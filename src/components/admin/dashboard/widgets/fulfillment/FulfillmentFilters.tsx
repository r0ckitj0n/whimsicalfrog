import React from 'react';

interface FulfillmentFiltersProps {
    filters: Record<string, string>;
    status_options: string[];
    payment_method_options: string[];
    shipping_method_options: string[];
    payment_status_options: string[];
    onFilterChange: (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => void;
    onApply: (e: React.FormEvent) => void;
    onClear: () => void;
}

export const FulfillmentFilters: React.FC<FulfillmentFiltersProps> = ({
    filters,
    status_options,
    payment_method_options,
    shipping_method_options,
    payment_status_options,
    onFilterChange,
    onApply,
    onClear
}) => {
    return (
        <div className="bg-white border border-gray-200 rounded-lg p-4">
            <form onSubmit={onApply} className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Date</label>
                    <input
                        type="date"
                        name="filter_created_at"
                        value={filters.filter_created_at || ''}
                        onChange={onFilterChange}
                        className="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500"
                    />
                </div>
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Items</label>
                    <div className="flex items-center gap-2 bg-white p-1 rounded border border-gray-300 shadow-inner focus-within:ring-1 focus-within:ring-green-500 focus-within:border-green-500">
                        <span className="text-xs text-gray-400 pl-1">🔍</span>
                        <input
                            type="text"
                            name="filter_items"
                            value={filters.filter_items || ''}
                            onChange={onFilterChange}
                            placeholder="Search..."
                            className="w-full bg-transparent border-none p-0 text-xs focus:ring-0"
                        />
                    </div>
                </div>
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Order Status</label>
                    <select
                        name="filter_status"
                        value={filters.filter_status || ''}
                        onChange={onFilterChange}
                        className="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500"
                    >
                        <option value="">All Order Status</option>
                        {status_options.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Payment</label>
                    <select
                        name="filter_payment_method"
                        value={filters.filter_payment_method || ''}
                        onChange={onFilterChange}
                        className="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500"
                    >
                        <option value="">All Payment</option>
                        {payment_method_options.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Shipping</label>
                    <select
                        name="filter_shipping_method"
                        value={filters.filter_shipping_method || ''}
                        onChange={onFilterChange}
                        className="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500"
                    >
                        <option value="">All Shipping</option>
                        {shipping_method_options.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <label className="block text-xs font-medium text-gray-700">Pay Status</label>
                    <select
                        name="filter_payment_status"
                        value={filters.filter_payment_status || ''}
                        onChange={onFilterChange}
                        className="w-full text-xs border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500"
                    >
                        <option value="">All Pay Status</option>
                        {payment_status_options.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>
                <div className="flex items-end space-x-2">
                    <button
                        type="submit"
                        className="btn-text-primary"
                        data-help-id="fulfillment-filter-apply"
                    >
                        Apply
                    </button>
                    <button
                        type="button"
                        onClick={onClear}
                        className="btn-text-secondary"
                        data-help-id="fulfillment-filter-clear"
                    >
                        Clear
                    </button>
                </div>
            </form>
        </div>
    );
};
