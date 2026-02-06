import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface LegalSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string) => void;
}

export const LegalSection: React.FC<LegalSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Legal & Locale</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Tax ID / EIN</label>
                    <input type="text" value={info.business_tax_id} onChange={e => onChange('business_tax_id', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Timezone</label>
                    <input type="text" value={info.business_timezone} onChange={e => onChange('business_timezone', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Currency</label>
                        <input type="text" value={info.business_currency} onChange={e => onChange('business_currency', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm uppercase" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Locale</label>
                        <input type="text" value={info.business_locale} onChange={e => onChange('business_locale', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm uppercase" />
                    </div>
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Terms URL</label>
                    <input type="text" value={info.business_terms_url} onChange={e => onChange('business_terms_url', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Privacy URL</label>
                    <input type="text" value={info.business_privacy_url} onChange={e => onChange('business_privacy_url', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono" />
                </div>
            </div>
        </section>
    );
};
