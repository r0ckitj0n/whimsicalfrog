import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface FooterSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string | boolean) => void;
}

export const FooterSection: React.FC<FooterSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Footer</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Footer Note (Text)</label>
                    <textarea value={info.business_footer_note} onChange={e => onChange('business_footer_note', e.target.value)} rows={3} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm resize-none" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Footer HTML (Custom)</label>
                    <textarea value={info.business_footer_html} onChange={e => onChange('business_footer_html', e.target.value)} rows={5} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono resize-none" />
                </div>
            </div>
        </section>
    );
};
