import React from 'react';
import { ISystemConfig } from '../../../../hooks/admin/useSiteMaintenance.js';

interface StatusTabProps {
    systemConfig: ISystemConfig | null;
}

export const StatusTab: React.FC<StatusTabProps> = ({ systemConfig }) => {
    if (!systemConfig) return null;

    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/20 rounded-lg">
                    <h4 className="font-bold text-[var(--brand-primary)] mb-2 flex items-center gap-2">
                        Primary Identity
                    </h4>
                    <div className="text-sm space-y-1 text-[var(--brand-primary)]/80">
                        <p><strong>Identifier:</strong> {systemConfig.system_info.primary_identifier}</p>
                        <p><strong>Format:</strong> {systemConfig.system_info.sku_format}</p>
                        <p><strong>Entity:</strong> {systemConfig.system_info.main_entity}</p>
                    </div>
                </div>
                <div className="p-4 bg-[var(--brand-accent)]/5 border border-[var(--brand-accent)]/20 rounded-lg">
                    <h4 className="font-bold text-[var(--brand-accent)] mb-2 flex items-center gap-2">
                        Quick Stats
                    </h4>
                    <div className="text-sm space-y-1 text-[var(--brand-accent)]/80">
                        <p><strong>Total Items:</strong> {systemConfig.statistics.total_items} ({systemConfig.statistics.total_images} images)</p>
                        <p><strong>Total Orders:</strong> {systemConfig.statistics.total_orders}</p>
                        <p><strong>Categories:</strong> {systemConfig.statistics.categories_count} active</p>
                    </div>
                </div>
            </div>

            <div className="p-4 border rounded-lg">
                <h4 className="font-bold text-gray-800 mb-3">Recent Activity</h4>
                <div className="space-y-4">
                    <div>
                        <div className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Latest Customers</div>
                        <div className="flex flex-wrap gap-2">
                            {systemConfig.id_formats.recent_customers.map(c => (
                                <span key={c.id} className="px-2 py-1 bg-gray-100 rounded text-xs font-mono" data-help-id="maintenance-customer-user">{c.id}</span>
                            ))}
                        </div>
                    </div>
                    <div>
                        <div className="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Latest Orders</div>
                        <div className="flex flex-wrap gap-2">
                            {systemConfig.id_formats.recent_orders.map(o => (
                                <span key={o} className="px-2 py-1 bg-gray-100 rounded text-xs font-mono">{o}</span>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
