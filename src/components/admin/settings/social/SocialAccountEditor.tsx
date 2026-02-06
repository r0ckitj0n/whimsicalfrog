import React, { useState } from 'react';
import type { ISocialAccount } from '../../../../hooks/admin/useSocialMedia.js';
import type { SocialConnectionStatus } from '../../../../core/constants/uienums.js';

interface SocialAccountEditorProps {
    account: ISocialAccount;
    editBuffer: Partial<ISocialAccount>;
    setEditBuffer: React.Dispatch<React.SetStateAction<Partial<ISocialAccount>>>;
    onSave: (e: React.FormEvent) => void;
    onCancel: () => void;
    onVerify: () => Promise<void>;
    isLoading: boolean;
    isVerifying: boolean;
}

const getStatusConfig = (status: SocialConnectionStatus) => {
    switch (status) {
        case 'connected':
            return { color: 'var(--brand-accent)', bg: 'var(--brand-accent-bg)', icon: '✓', label: 'Connected' };
        case 'expired':
            return { color: 'var(--brand-error)', bg: 'var(--brand-error-bg)', icon: '⚠', label: 'Token Expired' };
        case 'error':
            return { color: 'var(--brand-error)', bg: 'var(--brand-error-bg)', icon: '✕', label: 'Error' };
        case 'pending':
            return { color: 'var(--brand-warning)', bg: 'var(--brand-warning-bg)', icon: '⏳', label: 'Pending' };
        default:
            return { color: 'gray', bg: '#f5f5f5', icon: '○', label: 'Disconnected' };
    }
};

export const SocialAccountEditor: React.FC<SocialAccountEditorProps> = ({
    account,
    editBuffer,
    setEditBuffer,
    onSave,
    onCancel,
    onVerify,
    isLoading,
    isVerifying
}) => {
    const statusConfig = getStatusConfig(account.connection_status);
    const isTokenExpired = account.connection_status === 'expired';
    const hasTokenExpiry = account.token_expires_at && new Date(account.token_expires_at) > new Date();

    return (
        <div className="max-w-xl mx-auto py-8 animate-in fade-in slide-in-from-bottom-2">
            {/* Provider Header */}
            <div className="flex items-center gap-4 mb-8 pb-6 border-b">
                <div
                    className="w-14 h-14 rounded-xl flex items-center justify-center text-2xl"
                    style={{ backgroundColor: account.provider_color ? `${account.provider_color}15` : '#f5f5f5' }}
                >
                    {account.provider_icon || '📱'}
                </div>
                <div className="flex-1">
                    <div className="text-lg font-bold text-gray-900">{account.provider_name || account.platform}</div>
                    <div className="text-xs text-gray-500 font-mono">ID: {account.account_id}</div>
                </div>
                <div
                    className="px-3 py-1.5 rounded-full text-xs font-bold flex items-center gap-1.5"
                    style={{
                        backgroundColor: statusConfig.bg,
                        color: statusConfig.color
                    }}
                >
                    <span>{statusConfig.icon}</span>
                    {statusConfig.label}
                </div>
            </div>

            {/* Token Expiry Warning */}
            {isTokenExpired && (
                <div className="mb-6 p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                    <span className="text-xl">⚠️</span>
                    <div>
                        <strong>Token Expired</strong>
                        <p className="text-xs mt-1 opacity-80">Your access token has expired. Please reconnect this account to continue posting.</p>
                    </div>
                </div>
            )}

            {hasTokenExpiry && !isTokenExpired && (
                <div className="mb-6 p-3 bg-[var(--brand-warning)]/5 border border-[var(--brand-warning)]/20 text-[var(--brand-warning)] text-xs rounded-xl flex items-center gap-2">
                    <span>⏰</span>
                    <span>Token expires: {new Date(account.token_expires_at!).toLocaleDateString()}</span>
                </div>
            )}

            <form onSubmit={onSave} className="space-y-6">
                {/* Account Details Section */}
                <div className="space-y-4">
                    <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <span>👤</span> Account Details
                    </h3>

                    {/* Display Name */}
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">Display Name</label>
                        <input
                            type="text"
                            required
                            value={editBuffer.account_name || ''}
                            onChange={e => setEditBuffer({ ...editBuffer, account_name: e.target.value })}
                            className="form-input w-full py-3"
                            placeholder="e.g., My Business Page"
                        />
                        <p className="text-[10px] text-gray-400">A friendly name to identify this account in the dashboard.</p>
                    </div>

                    {/* Account ID / Username */}
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">Account ID / Username</label>
                        <input
                            type="text"
                            value={editBuffer.account_id || ''}
                            onChange={e => setEditBuffer({ ...editBuffer, account_id: e.target.value })}
                            className="form-input w-full py-3 font-mono text-sm"
                            placeholder="e.g., @yourbusiness or user_12345"
                        />
                        <p className="text-[10px] text-gray-400">The username or unique identifier for this account on the platform.</p>
                    </div>

                    {/* Profile URL */}
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">Profile URL</label>
                        <input
                            type="url"
                            value={editBuffer.profile_url || ''}
                            onChange={e => setEditBuffer({ ...editBuffer, profile_url: e.target.value })}
                            className="form-input w-full py-3 text-sm"
                            placeholder="https://facebook.com/yourbusiness"
                        />
                    </div>
                </div>

                {/* Credentials Section */}
                <div className="space-y-4 pt-4 border-t">
                    <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        <span>🔐</span> Credentials
                    </h3>

                    {/* API Key / Access Token */}
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">API Key / Access Token</label>
                        <input
                            type="password"
                            value={editBuffer.access_token || ''}
                            onChange={e => setEditBuffer({ ...editBuffer, access_token: e.target.value })}
                            className="form-input w-full py-3 font-mono text-sm"
                            placeholder="Enter new token to update (leave blank to keep existing)"
                        />
                        <p className="text-[10px] text-gray-400">The API access token for authenticating with this platform. Only enter a value if you want to update the existing token.</p>
                    </div>

                    {/* App Secret (optional) */}
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-600 block">App Secret <span className="text-gray-400 font-normal">(optional)</span></label>
                        <input
                            type="password"
                            value={editBuffer.app_secret || ''}
                            onChange={e => setEditBuffer({ ...editBuffer, app_secret: e.target.value })}
                            className="form-input w-full py-3 font-mono text-sm"
                            placeholder="Enter app secret if required by the platform"
                        />
                    </div>
                </div>

                {/* Account Status */}
                <div className="space-y-1 pt-4 border-t">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest block">Account Status</label>
                    <label className="flex items-center gap-3 p-4 border rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                        <input
                            type="checkbox"
                            checked={Boolean(editBuffer.is_active)}
                            onChange={e => setEditBuffer({ ...editBuffer, is_active: e.target.checked })}
                            className="w-5 h-5 rounded text-[var(--brand-primary)]"
                        />
                        <div>
                            <div className="font-bold text-gray-900 text-sm">Account is Active</div>
                            <div className="text-xs text-gray-500">Allow system to post content to this account automatically.</div>
                        </div>
                    </label>
                </div>

                {/* Connection Verification */}
                <div className="space-y-1">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest block">Connection Status</label>
                    <div className="p-4 border rounded-xl bg-gray-50/50">
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-600">
                                {account.last_verified ? (
                                    <span>Last verified: {new Date(account.last_verified).toLocaleString()}</span>
                                ) : (
                                    <span className="text-gray-400">Never verified</span>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={onVerify}
                                disabled={isVerifying}
                                className="px-4 py-2 text-sm font-bold text-[var(--brand-primary)] bg-[var(--brand-primary)]/5 hover:bg-[var(--brand-primary)]/10 rounded-lg transition-colors flex items-center gap-2"
                            >
                                {isVerifying ? (
                                    <>
                                        <span className="wf-emoji-loader">🔄</span>
                                        Verifying...
                                    </>
                                ) : (
                                    <>
                                        <span>🔍</span>
                                        Test Connection
                                    </>
                                )}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Actions */}
                <div className="pt-6 border-t flex justify-between items-center">
                    {isTokenExpired && (
                        <button
                            type="button"
                            className="text-sm font-bold text-[var(--brand-secondary)] hover:text-[var(--brand-secondary-hover)] transition-colors flex items-center gap-1"
                        >
                            <span>🔄</span> Reconnect Account
                        </button>
                    )}
                    <div className={`flex gap-3 ${isTokenExpired ? '' : 'ml-auto'}`}>
                        <button
                            type="button"
                            onClick={onCancel}
                            className="px-6 py-2.5 text-gray-600 hover:text-gray-800 font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isLoading}
                            className="px-8 py-2.5 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-hover)] text-white font-bold rounded-xl transition-colors flex items-center gap-2"
                        >
                            {isLoading ? (
                                <>
                                    <span className="wf-emoji-loader">🔄</span>
                                    Saving...
                                </>
                            ) : (
                                'Save Changes'
                            )}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
};

