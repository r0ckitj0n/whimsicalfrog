import React, { useState } from 'react';
import { IEmailSettings } from '../../../../hooks/admin/useEmailSettings.js';

interface EmailSettingsPanelProps {
    settings: IEmailSettings;
    setSettings: (settings: IEmailSettings) => void;
    smtpPassword: string;
    setSmtpPassword: (password: string) => void;
    onSave: () => void;
    isLoading: boolean;
}

export const EmailSettingsPanel: React.FC<EmailSettingsPanelProps> = ({
    settings,
    setSettings,
    smtpPassword,
    setSmtpPassword,
    onSave,
    isLoading
}) => {
    const handleChange = (field: keyof IEmailSettings, value: string | boolean) => {
        setSettings({ ...settings, [field]: value });
    };

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        onSave();
    };

    return (
        <form onSubmit={handleSave} className="space-y-12 animate-in fade-in slide-in-from-bottom-2 duration-500">
            {/* Sender Identity */}
            <section className="space-y-6">
                <div className="flex items-center gap-3 border-b border-gray-100 pb-3">
                    <span className="text-xl">🏷️</span>
                    <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest">Sender Identity</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">From Name</label>
                        <input
                            type="text"
                            value={settings.fromName}
                            onChange={e => handleChange('fromName', e.target.value)}
                            className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-semibold"
                            placeholder="Business Name"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">From Email</label>
                        <input
                            type="email"
                            value={settings.fromEmail}
                            onChange={e => handleChange('fromEmail', e.target.value)}
                            className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-semibold"
                            placeholder="hello@example.com"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Admin Alert Recipient</label>
                        <input
                            type="email"
                            value={settings.adminEmail}
                            onChange={e => handleChange('adminEmail', e.target.value)}
                            className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-semibold"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">BCC Copies To</label>
                        <input
                            type="email"
                            value={settings.bccEmail}
                            onChange={e => handleChange('bccEmail', e.target.value)}
                            className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-semibold"
                        />
                    </div>
                </div>
            </section>

            {/* SMTP Configuration */}
            <section className="space-y-6">
                <div className="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div className="flex items-center gap-3">
                        <span className="text-xl">🚀</span>
                        <h3 className="text-sm font-black text-gray-400 uppercase tracking-widest">SMTP Delivery</h3>
                    </div>
                    <label className="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            className="sr-only peer"
                            checked={settings.smtpEnabled}
                            onChange={e => handleChange('smtpEnabled', e.target.checked)}
                        />
                        <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span className="ms-3 text-xs font-bold text-gray-500 uppercase">Enable SMTP</span>
                    </label>
                </div>

                {settings.smtpEnabled && (
                    <div className="space-y-8 animate-in slide-in-from-top-4 duration-300">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="md:col-span-2 space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">SMTP Host</label>
                                <input
                                    type="text"
                                    value={settings.smtpHost}
                                    onChange={e => handleChange('smtpHost', e.target.value)}
                                    className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-mono"
                                    placeholder="smtp.example.com"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Port</label>
                                <input
                                    type="text"
                                    value={settings.smtpPort}
                                    onChange={e => handleChange('smtpPort', e.target.value)}
                                    className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-mono"
                                    placeholder="587"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Username</label>
                                <input
                                    type="text"
                                    value={settings.smtpUsername}
                                    onChange={e => handleChange('smtpUsername', e.target.value)}
                                    className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-mono"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Password</label>
                                <input
                                    type="password"
                                    value={smtpPassword}
                                    onChange={e => setSmtpPassword(e.target.value)}
                                    className="w-full p-2.5 bg-white border border-gray-100 rounded-xl text-sm font-mono"
                                    placeholder="••••••••••••"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Encryption</label>
                                <select
                                    value={settings.smtpEncryption}
                                    onChange={e => handleChange('smtpEncryption', e.target.value)}
                                    className="w-full p-2.5 bg-gray-50 border-transparent rounded-xl text-sm font-bold appearance-none outline-none focus:ring-2 ring-blue-100"
                                >
                                    <option value="">None</option>
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                            <div className="flex items-end pb-2">
                                <label className="flex items-center gap-3 cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        checked={settings.smtpAuth}
                                        onChange={e => handleChange('smtpAuth', e.target.checked)}
                                        className="w-5 h-5 rounded-lg border-gray-200 text-blue-600 focus:ring-blue-100 transition-all"
                                    />
                                    <span className="text-xs font-bold text-gray-500 group-hover:text-gray-700 transition-colors">Requires Auth</span>
                                </label>
                            </div>
                        </div>
                    </div>
                )}
            </section>


        </form>
    );
};
