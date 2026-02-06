import React from 'react';
import type { IAuditItem } from '../../../../types/inventory.js';

interface AuditSectionProps {
    title: string;
    subtitle: string;
    items: IAuditItem[];
    icon: string;
    emptyMessage: string;
    renderRow: (item: IAuditItem) => React.ReactNode;
}

export const AuditSection: React.FC<AuditSectionProps> = ({ title, subtitle, items, icon, emptyMessage, renderRow }) => (
    <div className="bg-white border border-slate-100 rounded-[2rem] shadow-sm flex flex-col h-[400px]">
        <div className="px-8 py-5 border-b border-slate-50 bg-slate-50/30 flex items-center justify-between">
            <div>
                <div className="text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-2">
                    <span>{icon}</span> {title}
                </div>
                <div className="text-[9px] font-bold text-slate-300 uppercase tracking-tight">{subtitle}</div>
            </div>
            <div className={`text-[10px] font-black px-3 py-1 rounded-full ${items.length > 0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'}`}>
                {items.length} Issues
            </div>
        </div>
        <div className="p-6 flex-1 overflow-y-auto space-y-3">
            {!items.length ? (
                <div className="flex flex-col items-center justify-center h-full text-center gap-3 opacity-30">
                    <span className="text-3xl">✅</span>
                    <p className="text-[10px] font-black uppercase tracking-widest text-slate-400">{emptyMessage}</p>
                </div>
            ) : (
                items.map((item, i) => (
                    <div key={i}>{renderRow(item)}</div>
                ))
            )}
        </div>
    </div>
);
