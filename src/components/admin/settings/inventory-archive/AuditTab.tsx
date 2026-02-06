import React from 'react';
import { AuditSection } from './AuditSection.js';
import type { IInventoryAudit } from '../../../../types/inventory.js';

interface AuditTabProps {
    audit: IInventoryAudit;
}

export const AuditTab: React.FC<AuditTabProps> = ({ audit }) => {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {/* Pricing Alerts */}
            <AuditSection
                title="Pricing Alerts"
                subtitle="Items where cost exceeds retail price"
                items={audit.pricing_alerts}
                icon="💰"
                emptyMessage="All pricing looks healthy"
                renderRow={(item) => (
                    <div className="flex items-center justify-between p-3 bg-red-50/30 rounded-xl border border-red-100/50">
                        <div>
                            <div className="text-[10px] font-black text-slate-700">{item.name}</div>
                            <div className="text-[8px] font-bold text-slate-400 uppercase">{item.sku}</div>
                        </div>
                        <div className="text-right">
                            <div className="text-[10px] font-black text-red-600">${item.cost_price} cost</div>
                            <div className="text-[9px] font-bold text-slate-400 line-through">${item.retail_price} retail</div>
                        </div>
                    </div>
                )}
            />

            {/* Missing Images */}
            <AuditSection
                title="Visual Inventory Gaps"
                subtitle="Items missing product images"
                items={audit.missing_images}
                icon="🖼️"
                emptyMessage="Catalog is fully indexed with visuals"
                renderRow={(item) => (
                    <div className="flex items-center justify-between p-3 bg-orange-50/10 rounded-xl border border-orange-100/30">
                        <div>
                            <div className="text-[10px] font-black text-slate-700">{item.name}</div>
                            <div className="text-[8px] font-bold text-slate-400 uppercase">{item.sku}</div>
                        </div>
                        <div className="text-[8px] font-black text-orange-500 uppercase tracking-widest bg-orange-50 px-2 py-1 rounded-md">Missing Image</div>
                    </div>
                )}
            />

            {/* Stock Issues */}
            <AuditSection
                title="Stock Discrepancies"
                subtitle="Items with zero or negative inventory"
                items={audit.stock_issues}
                icon="📉"
                emptyMessage="All items have recorded stock"
                renderRow={(item) => (
                    <div className="flex items-center justify-between p-3 bg-amber-50/10 rounded-xl border border-amber-100/30">
                        <div>
                            <div className="text-[10px] font-black text-slate-700">{item.name}</div>
                            <div className="text-[8px] font-bold text-slate-400 uppercase">{item.sku}</div>
                        </div>
                        <div className={`text-[10px] font-black px-3 py-1 rounded-lg ${item.stock_quantity! < 0 ? 'bg-red-500 text-white' : 'bg-amber-100 text-amber-700'}`}>
                            {item.stock_quantity} units
                        </div>
                    </div>
                )}
            />

            {/* Content Gaps */}
            <AuditSection
                title="Content & Metadata Gaps"
                subtitle="Items missing category or description"
                items={audit.content_issues}
                icon="📝"
                emptyMessage="Catalog metadata is complete"
                renderRow={(item) => (
                    <div className="flex items-center justify-between p-3 bg-blue-50/10 rounded-xl border border-blue-100/30">
                        <div>
                            <div className="text-[10px] font-black text-slate-700">{item.name}</div>
                            <div className="text-[8px] font-bold text-slate-400 uppercase">{item.sku}</div>
                        </div>
                        <div className="text-[8px] font-bold text-slate-400 uppercase tracking-tighter italic">Needs Metadata</div>
                    </div>
                )}
            />
        </div>
    );
};
