import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface PoliciesSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string | boolean) => void;
}

export const PoliciesSection: React.FC<PoliciesSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-6">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Store Policies</h3>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 text-dark-forced">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Return Policy</label>
                    <textarea value={info.business_return_policy} onChange={e => onChange('business_return_policy', e.target.value)} rows={6} className="form-input w-full p-3 bg-gray-50 border-transparent rounded-xl text-sm leading-relaxed resize-none" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Shipping Policy</label>
                    <textarea value={info.business_shipping_policy} onChange={e => onChange('business_shipping_policy', e.target.value)} rows={6} className="form-input w-full p-3 bg-gray-50 border-transparent rounded-xl text-sm leading-relaxed resize-none" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Warranty Info</label>
                    <textarea value={info.business_warranty_policy} onChange={e => onChange('business_warranty_policy', e.target.value)} rows={6} className="form-input w-full p-3 bg-gray-50 border-transparent rounded-xl text-sm leading-relaxed resize-none" />
                </div>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 text-dark-forced">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Privacy Policy</label>
                    <textarea value={info.business_privacy_policy_content} onChange={e => onChange('business_privacy_policy_content', e.target.value)} rows={6} className="form-input w-full p-3 bg-gray-50 border-transparent rounded-xl text-sm leading-relaxed resize-none" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Terms of Service</label>
                    <textarea value={info.business_terms_service_content} onChange={e => onChange('business_terms_service_content', e.target.value)} rows={6} className="form-input w-full p-3 bg-gray-50 border-transparent rounded-xl text-sm leading-relaxed resize-none" />
                </div>
            </div>
        </section>
    );
};
