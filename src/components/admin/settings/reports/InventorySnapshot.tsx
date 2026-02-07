import React from 'react';

import { IInventoryStats } from '../../../../types/index.js';

interface InventorySnapshotProps {
    stats: IInventoryStats | null | undefined;
    onGenerateReport?: () => void;
}

export const InventorySnapshot: React.FC<InventorySnapshotProps> = ({ stats, onGenerateReport }) => {
    return (
        <div className="bg-white border rounded-[2rem] p-6 shadow-sm space-y-6 lg:col-span-1">
            <div className="flex items-center justify-between">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Inventory Status</h3>
            </div>

            <div className="grid grid-cols-1 gap-3">
                <div className="p-4 bg-gray-50 rounded-2xl flex justify-between items-center">
                    <div className="text-xs font-bold text-gray-500">Total Items</div>
                    <div className="text-lg font-black text-gray-900">{stats?.total_items || 0}</div>
                </div>
                <div className="p-4 bg-gray-50 rounded-2xl flex justify-between items-center">
                    <div className="text-xs font-bold text-gray-500">Total Stock</div>
                    <div className="text-lg font-black text-[var(--brand-primary)]">{stats?.total_stock || 0}</div>
                </div>
                <div className="p-4 bg-[var(--brand-error-bg)] rounded-2xl flex justify-between items-center border border-[var(--brand-error-border)]">
                    <div className="text-xs font-bold text-[var(--brand-error)]/80">Low Stock Warning</div>
                    <div className="text-lg font-black text-[var(--brand-error)]">{stats?.low_stock_count || 0}</div>
                </div>
            </div>

            <button
                onClick={onGenerateReport}
                className="w-full py-3 bg-transparent border-0 text-gray-900 hover:bg-gray-100 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all"
                data-help-id="reports-inventory-download"
            >
                Download Full Report
            </button>
        </div>
    );
};
