import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';

interface SocialLinksSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string | boolean) => void;
}

const Row: React.FC<{
    label: string;
    value: string;
    placeholder: string;
    onChange: (v: string) => void;
}> = ({ label, value, placeholder, onChange }) => {
    return (
        <div className="space-y-1">
            <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">{label}</label>
            <input
                type="url"
                value={value}
                placeholder={placeholder}
                onChange={e => onChange(e.target.value)}
                className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm"
            />
        </div>
    );
};

export const SocialLinksSection: React.FC<SocialLinksSectionProps> = ({ info, onChange }) => {
    return (
        <section className="space-y-4">
            <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">Public Social Links</h3>
            <div className="space-y-3">
                <Row
                    label="Facebook URL"
                    value={info.business_facebook}
                    placeholder="https://facebook.com/yourpage"
                    onChange={(v) => onChange('business_facebook', v)}
                />
                <Row
                    label="Instagram URL"
                    value={info.business_instagram}
                    placeholder="https://instagram.com/yourhandle"
                    onChange={(v) => onChange('business_instagram', v)}
                />
                <Row
                    label="X URL (or Twitter)"
                    value={info.business_x || info.business_twitter}
                    placeholder="https://x.com/yourhandle"
                    onChange={(v) => onChange('business_x', v)}
                />
                <Row
                    label="LinkedIn URL"
                    value={info.business_linkedin}
                    placeholder="https://www.linkedin.com/company/yourcompany"
                    onChange={(v) => onChange('business_linkedin', v)}
                />
                <Row
                    label="YouTube URL"
                    value={info.business_youtube}
                    placeholder="https://www.youtube.com/@yourchannel"
                    onChange={(v) => onChange('business_youtube', v)}
                />
                <Row
                    label="Pinterest URL"
                    value={info.business_pinterest}
                    placeholder="https://pinterest.com/yourprofile"
                    onChange={(v) => onChange('business_pinterest', v)}
                />
            </div>
        </section>
    );
};

