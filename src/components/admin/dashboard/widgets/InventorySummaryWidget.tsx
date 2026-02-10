import React from 'react';
import { buildAdminUrl } from '../../../../core/admin-url-builder.js';

interface InventorySummaryWidgetProps {
    totalStockUnits: number;
    total_items: number;
    lowStock: number;
    topStockItems: Array<{ name: string; sku: string; stock_quantity: number }>;
}

export const InventorySummaryWidget: React.FC<InventorySummaryWidgetProps> = ({
    totalStockUnits,
    total_items,
    lowStock,
    topStockItems
}) => {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3">
                <div className="bg-[var(--brand-primary)]/5 rounded text-center p-3">
                    <div className="text-lg font-bold text-[var(--brand-primary)]">{totalStockUnits}</div>
                    <div className="text-xs text-[var(--brand-primary)] opacity-80">Total Stock Units</div>
                    <div className="text-[10px] text-gray-500 mt-1">{total_items} items</div>
                </div>
                <div className="bg-red-50 rounded text-center p-3">
                    <div className="text-lg font-bold text-red-600">{lowStock}</div>
                    <div className="text-xs text-red-800">Low Stock</div>
                </div>
            </div>

            {topStockItems && topStockItems.length > 0 && (
                <div className="space-y-1">
                    <div className="text-xs font-medium text-gray-600">Top Stock Items:</div>
                    {topStockItems.map((item, idx) => (
                        <div key={idx} className="flex justify-between items-center text-xs bg-gray-50 rounded p-2">
                            <span className="truncate pr-2">{item.name || item.sku}</span>
                            <span className="font-medium shrink-0">{item.stock_quantity}</span>
                        </div>
                    ))}
                </div>
            )}

            <div className="text-center pt-2">
                <a
                    href={buildAdminUrl('inventory')}
                    className="admin-action-btn btn-icon--external"
                    data-help-id="dashboard-action-manage-inventory"
                >🔗</a>
            </div>
        </div>
    );
};
