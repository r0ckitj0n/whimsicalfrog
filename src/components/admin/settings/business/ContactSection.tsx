import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface ContactSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string) => void;
}

export const ContactSection: React.FC<ContactSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Contact & Hours</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Public Email</label>
                    <input type="email" value={info.business_email} onChange={e => onChange('business_email', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Public Phone</label>
                    <input type="text" value={info.business_phone} onChange={e => onChange('business_phone', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Support Email</label>
                    <input type="email" value={info.business_support_email} onChange={e => onChange('business_support_email', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Support Phone</label>
                    <input type="text" value={info.business_support_phone} onChange={e => onChange('business_support_phone', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Business Hours</label>
                    <input type="text" value={info.business_hours} onChange={e => onChange('business_hours', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
            </div>
        </section>
    );
};
