import React from 'react';
import { ILowStockItem } from '../../../../hooks/admin/useDashboard.js';
import { buildAdminUrl } from '../../../../core/admin-url-builder.js';

interface LowStockWidgetProps {
    items: ILowStockItem[];
}

export const LowStockWidget: React.FC<LowStockWidgetProps> = ({ items }) => {
    return (
        <div className="space-y-3">
            {items.length > 0 ? (
                <>
                    <div className="space-y-2">
                        {items.map((item) => (
                            <div key={item.sku} className="flex justify-between items-center bg-red-50 rounded p-2">
                                <div className="min-w-0 flex-1 pr-4">
                                    <div className="font-medium text-sm">{item.name || item.sku}</div>
                                    <div className="text-xs text-gray-600">{item.sku}</div>
                                </div>
                                <div className="text-right">
                                    <div className="text-sm font-medium text-red-600">{item.stock_quantity} left</div>
                                    <div className="text-xs text-gray-500">Reorder: {item.reorder_point}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="text-center pt-2">
                        <a
                            href={buildAdminUrl('inventory')}
                            className="admin-action-btn btn-icon--external"
                            data-help-id="dashboard-action-manage-inventory"
                        />
                    </div>
                </>
            ) : (
                <div className="text-center py-8">
                    <p className="text-sm text-gray-500 italic">All items well stocked</p>
                </div>
            )}
        </div>
    );
};
