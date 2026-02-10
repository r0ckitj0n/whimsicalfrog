import React from 'react';
import { IBusinessInfo } from '../../../../hooks/admin/useBusinessInfo.js';
import { ISelectOption } from '../../../../types/settings.js';

interface LegalSectionProps {
    info: IBusinessInfo;
    onChange: (key: keyof IBusinessInfo, value: string | boolean) => void;
    timezoneOptions?: ISelectOption[];
    currencyOptions?: ISelectOption[];
    localeOptions?: ISelectOption[];
    optionsLoading?: boolean;
}

export const LegalSection: React.FC<LegalSectionProps> = ({
    info,
    onChange,
    timezoneOptions = [],
    currencyOptions = [],
    localeOptions = [],
    optionsLoading = false
}) => {
    const renderValueOption = (value: string, fallbackLabel: string) => {
        if (!value) return null;
        return <option value={value}>{fallbackLabel}</option>;
    };

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
                    <select
                        value={info.business_timezone}
                        onChange={e => onChange('business_timezone', e.target.value)}
                        className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm"
                        disabled={optionsLoading}
                    >
                        {renderValueOption(info.business_timezone, `${info.business_timezone} (Current)`)}
                        {timezoneOptions.map(tz => (
                            <option key={tz.value} value={tz.value}>{tz.label}</option>
                        ))}
                    </select>
                </div>
                <div className="space-y-1">
                    <label className="inline-flex items-center gap-2 text-[10px] font-bold text-gray-500 uppercase ml-1">
                        <input
                            type="checkbox"
                            checked={!!info.business_dst_enabled}
                            onChange={e => onChange('business_dst_enabled', e.target.checked)}
                            className="h-3.5 w-3.5 accent-[var(--brand-primary)]"
                        />
                        Recognize Daylight Saving Time
                    </label>
                </div>
                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Currency</label>
                        <select
                            value={info.business_currency}
                            onChange={e => onChange('business_currency', e.target.value)}
                            className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm uppercase"
                            disabled={optionsLoading}
                        >
                            {renderValueOption(info.business_currency, `${info.business_currency} (Current)`)}
                            {currencyOptions.map(currency => (
                                <option key={currency.value} value={currency.value}>{currency.label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Locale</label>
                        <select
                            value={info.business_locale}
                            onChange={e => onChange('business_locale', e.target.value)}
                            className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm uppercase"
                            disabled={optionsLoading}
                        >
                            {renderValueOption(info.business_locale, `${info.business_locale} (Current)`)}
                            {localeOptions.map(locale => (
                                <option key={locale.value} value={locale.value}>{locale.label}</option>
                            ))}
                        </select>
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
