import React from 'react';

interface CustomerFiltersProps {
    search: string;
    role: string;
    onSearchChange: (value: string) => void;
    onRoleChange: (value: string) => void;
    onClear: () => void;
    onRefresh: () => void;
    isLoading: boolean;
}

export const CustomerFilters: React.FC<CustomerFiltersProps> = ({
    search,
    role,
    onSearchChange,
    onRoleChange,
    onClear,
    onRefresh,
    isLoading
}) => {
    return (
        <div className="admin-filter--green bg-white p-4 border rounded-xl shadow-sm w-full">
            <div className="flex flex-wrap gap-4 items-end w-full">
                <div className="space-y-1 flex-[2] min-w-[300px]">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Search Customers</label>
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Name, email, or username..."
                        className="form-input form-input-search text-sm"
                    />
                </div>

                <div className="space-y-1 min-w-[150px]">
                    <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest block">Filter Role</label>
                    <select
                        value={role}
                        onChange={(e) => onRoleChange(e.target.value)}
                        className="form-select text-sm py-1.5 w-full"
                    >
                        <option value="all">All Roles</option>
                        <option value="customer">Customer</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div className="flex gap-2 pb-0.5">
                    <button
                        onClick={onRefresh}
                        disabled={isLoading}
                        className="btn-standard-icon btn-icon--refresh"
                        data-help-id="customers-filter-refresh"
                    />
                    <button
                        onClick={onClear}
                        className="btn-standard-icon btn-icon--clear"
                        data-help-id="customers-filter-clear"
                    />
                </div>
            </div>
        </div>
    );
};
