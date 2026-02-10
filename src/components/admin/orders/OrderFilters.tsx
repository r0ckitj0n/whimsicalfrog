import React from 'react';

interface OrderFiltersProps {
    filters: Record<string, string>;
    onFilterChange: (filters: Record<string, string>) => void;
    dropdownOptions: {
        status: string[];
        payment_method: string[];
        shipping_method: string[];
        payment_status: string[];
    };
    onRefresh: () => void;
    isLoading: boolean;
}

export const OrderFilters: React.FC<OrderFiltersProps> = ({
    filters,
    onFilterChange,
    dropdownOptions,
    onRefresh,
    isLoading
}) => {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const { name, value } = e.target;
        onFilterChange({ ...filters, [name]: value });
    };

    const handleClear = () => {
        onFilterChange({});
    };

    return (
        <div className="admin-filter--orange bg-white p-4 border rounded-xl shadow-sm w-full">
            <div className="flex flex-wrap gap-4 items-end w-full">
                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Order ID</label>
                    <input
                        type="text"
                        name="filter_order_id"
                        value={filters.filter_order_id || ''}
                        onChange={handleChange}
                        placeholder="e.g. 56B10U34"
                        className="form-input text-sm py-1.5"
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Date</label>
                    <input
                        type="date"
                        name="filter_created_at"
                        value={filters.filter_created_at || ''}
                        onChange={handleChange}
                        className="form-input text-sm py-1.5"
                    />
                </div>

                <div className="space-y-1 flex-1 min-w-[200px]">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Items / SKU</label>
                    <input
                        type="text"
                        name="filter_items"
                        value={filters.filter_items || ''}
                        onChange={handleChange}
                        placeholder="Search items..."
                        className="form-input form-input-search text-sm"
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Status</label>
                    <select
                        name="filter_status"
                        value={filters.filter_status || ''}
                        onChange={handleChange}
                        className="form-select text-sm py-1.5"
                    >
                        <option value="">All Status</option>
                        {dropdownOptions.status.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Payment</label>
                    <select
                        name="filter_payment_method"
                        value={filters.filter_payment_method || ''}
                        onChange={handleChange}
                        className="form-select text-sm py-1.5"
                    >
                        <option value="">All Payment</option>
                        {dropdownOptions.payment_method.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Shipping</label>
                    <select
                        name="filter_shipping_method"
                        value={filters.filter_shipping_method || ''}
                        onChange={handleChange}
                        className="form-select text-sm py-1.5"
                    >
                        <option value="">All Shipping</option>
                        {dropdownOptions.shipping_method.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>

                <div className="space-y-1">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Pay Status</label>
                    <select
                        name="filter_payment_status"
                        value={filters.filter_payment_status || ''}
                        onChange={handleChange}
                        className="form-select text-sm py-1.5"
                    >
                        <option value="">All Pay Status</option>
                        {dropdownOptions.payment_status.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                </div>

                <div className="flex gap-2 pb-0.5">
                    <button
                        onClick={() => onFilterChange({ ...filters })}
                        disabled={isLoading}
                        className="btn-standard-icon btn-icon--filter"
                        data-help-id="orders-filter-apply"
                    ></button>
                    <button
                        onClick={onRefresh}
                        disabled={isLoading}
                        className="btn-standard-icon btn-icon--refresh"
                        data-help-id="orders-filter-refresh"
                    ></button>
                    <button
                        onClick={handleClear}
                        className="btn-standard-icon btn-icon--clear"
                        data-help-id="orders-filter-clear"
                    ></button>
                </div>
            </div>
        </div >
    );
};
