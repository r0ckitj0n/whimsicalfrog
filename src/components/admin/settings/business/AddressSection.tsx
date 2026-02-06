import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface AddressSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string) => void;
}

export const AddressSection: React.FC<AddressSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Address</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Address Line 1</label>
                    <input type="text" value={info.business_address} onChange={e => onChange('business_address', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Address Line 2</label>
                    <input type="text" value={info.business_address2} onChange={e => onChange('business_address2', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">City</label>
                        <input type="text" value={info.business_city} onChange={e => onChange('business_city', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">State / Region</label>
                        <input type="text" value={info.business_state} onChange={e => onChange('business_state', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Postal Code</label>
                        <input type="text" value={info.business_postal} onChange={e => onChange('business_postal', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono" />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Country</label>
                        <input type="text" value={info.business_country} onChange={e => onChange('business_country', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm" />
                    </div>
                </div>
            </div>
        </section>
    );
};
