import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface IdentitySectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string | boolean) => void;
}

export const IdentitySection: React.FC<IdentitySectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Identity</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Business Name</label>
                    <input type="text" value={info.business_name} onChange={e => onChange('business_name', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-semibold" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Tagline</label>
                    <input type="text" value={info.business_tagline} onChange={e => onChange('business_tagline', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Logo URL</label>
                    <input type="text" value={info.business_logo} onChange={e => onChange('business_logo', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Website URL</label>
                    <input type="text" value={info.business_site_url} onChange={e => onChange('business_site_url', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Description</label>
                    <textarea value={info.business_description} onChange={e => onChange('business_description', e.target.value)} rows={2} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm resize-none" />
                </div>
            </div>
        </section>
    );
};
