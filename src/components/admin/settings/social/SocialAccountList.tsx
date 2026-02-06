import React from 'react';
import type { ISocialAccount } from '../../../../hooks/admin/useSocialMedia.js';
import type { SocialConnectionStatus } from '../../../../core/constants/uienums.js';

interface SocialAccountListProps {
    accounts: ISocialAccount[];
    isLoading: boolean;
    verifyingId: number | null;
    onEdit: (id: number) => void;
    onDelete: (id: number, name: string) => void;
    onVerify: (id: number) => void;
    onStartLinking: () => void;
}

const getStatusConfig = (status: SocialConnectionStatus) => {
    switch (status) {
        case 'connected':
            return { color: 'var(--brand-accent)', bg: 'var(--brand-accent-bg)', icon: '✓', label: 'Connected', pulse: true };
        case 'expired':
            return { color: 'var(--brand-error)', bg: 'var(--brand-error-bg)', icon: '⚠', label: 'Expired', pulse: false };
        case 'error':
            return { color: 'var(--brand-error)', bg: 'var(--brand-error-bg)', icon: '✕', label: 'Error', pulse: false };
        case 'pending':
            return { color: 'var(--brand-warning)', bg: 'var(--brand-warning-bg)', icon: '⏳', label: 'Pending', pulse: false };
        default:
            return { color: '#94a3b8', bg: '#f1f5f9', icon: '○', label: 'Disconnected', pulse: false };
    }
};

export const SocialAccountList: React.FC<SocialAccountListProps> = ({
    accounts,
    isLoading,
    verifyingId,
    onEdit,
    onDelete,
    onVerify,
    onStartLinking
}) => {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 animate-in fade-in slide-in-from-bottom-2">
            {accounts.map((acc) => {
                const statusConfig = getStatusConfig(acc.connection_status);
                const isVerifying = verifyingId === acc.id;

                return (
                    <div
                        key={`${acc.platform}-${acc.account_id}-${acc.id}`}
                        className="group relative border-2 rounded-2xl p-5 bg-white hover:border-[var(--brand-primary)]/30 hover:shadow-lg transition-all"
                    >
                        {/* Provider Header */}
                        <div className="flex items-start justify-between mb-4">
                            <div className="flex items-center gap-3">
                                <div
                                    className="w-11 h-11 rounded-xl flex items-center justify-center text-xl"
                                    style={{ backgroundColor: acc.provider_color ? `${acc.provider_color}15` : '#f5f5f5' }}
                                >
                                    {acc.provider_icon || '📱'}
                                </div>
                                <div>
                                    <div className="text-xs font-black text-gray-400 uppercase tracking-wider">
                                        {acc.provider_name || acc.platform}
                                    </div>
                                    <div className="font-bold text-gray-900 text-sm">{acc.account_name}</div>
                                </div>
                            </div>

                            {/* Status Badge */}
                            <div
                                className={`px-2.5 py-1 rounded-full text-[10px] font-bold flex items-center gap-1 ${statusConfig.pulse ? 'animate-pulse' : ''}`}
                                style={{ backgroundColor: statusConfig.bg, color: statusConfig.color }}
                            >
                                <span>{statusConfig.icon}</span>
                                <span>{statusConfig.label}</span>
                            </div>
                        </div>

                        {/* Account ID */}
                        <div className="text-[10px] text-gray-400 font-mono mb-4 truncate">
                            {acc.account_id}
                        </div>

                        {/* Last Activity */}
                        <div className="pt-3 border-t border-gray-100 flex items-center justify-between">
                            <div className="text-[10px] text-gray-400">
                                {acc.last_verified ? (
                                    <span>Verified {new Date(acc.last_verified).toLocaleDateString()}</span>
                                ) : acc.last_sync ? (
                                    <span>Last sync {new Date(acc.last_sync).toLocaleDateString()}</span>
                                ) : (
                                    <span className="italic">Never verified</span>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button
                                    onClick={() => onVerify(acc.id)}
                                    disabled={isVerifying}
                                    className={`admin-action-btn btn-icon--search ${isVerifying ? 'is-loading' : ''}`}
                                    data-help-id="social-test-connection"
                                />
                                <button
                                    onClick={() => onEdit(acc.id)}
                                    className="admin-action-btn btn-icon--settings"
                                    data-help-id="common-settings"
                                />
                                <button
                                    onClick={() => onDelete(acc.id, acc.account_name)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="social-disconnect"
                                />
                            </div>
                        </div>
                    </div>
                );
            })}

            {/* Empty State */}
            {accounts.length === 0 && !isLoading && (
                <div className="col-span-full py-16 flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl text-gray-400">
                    <span className="text-5xl opacity-30 mb-4">🌐</span>
                    <p className="text-sm font-medium mb-1">No social accounts connected</p>
                    <p className="text-xs text-gray-400 mb-6">Link your first account to start posting</p>
                    <button
                        onClick={onStartLinking}
                        className="px-6 py-2.5 bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-hover)] text-white font-bold text-sm rounded-xl transition-colors"
                    >
                        Connect Account
                    </button>
                </div>
            )}

            {/* Add Account Card */}
            {accounts.length > 0 && (
                <button
                    onClick={onStartLinking}
                    className="border-2 border-dashed border-gray-200 rounded-2xl p-5 flex flex-col items-center justify-center gap-3 text-gray-400 hover:border-[var(--brand-primary)]/30 hover:text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all min-h-[180px]"
                >
                    <span className="text-3xl">➕</span>
                    <span className="text-sm font-bold">Add Account</span>
                </button>
            )}
        </div>
    );
};

