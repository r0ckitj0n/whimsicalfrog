import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface AboutSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string) => void;
}

export const AboutSection: React.FC<AboutSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">About Page</h3>
            <div className="space-y-3">
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Page Title</label>
                    <input type="text" value={info.about_page_title} onChange={e => onChange('about_page_title', e.target.value)} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-bold" />
                </div>
                <div className="space-y-1">
                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Page Content (HTML)</label>
                    <textarea value={info.about_page_content} onChange={e => onChange('about_page_content', e.target.value)} rows={10} className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm resize-none" />
                </div>
            </div>
        </section>
    );
};
