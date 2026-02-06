import React, { useState, useEffect, useRef } from 'react';
import { useModalContext } from '../../../context/ModalContext.js';
import { ISocialAccount, ISocialProvider, CreateAccountRequest, VerifyResponse } from '../../../hooks/admin/useSocialMedia.js';

import { SocialAccountList } from './social/SocialAccountList.js';
import { SocialAccountEditor } from './social/SocialAccountEditor.js';
import { SocialAccountAdd } from './social/SocialAccountAdd.js';
import type { SocialPlatform } from '../../../core/constants/uienums.js';

interface SocialAccountManagerProps {
    accounts: ISocialAccount[];
    providers: ISocialProvider[];
    isLoading: boolean;
    error: string | null;
    verifyingId: number | null;
    fetchAccounts: () => Promise<void>;
    fetchProviders: () => Promise<void>;
    deleteAccount: (id: number) => Promise<boolean>;
    updateAccount: (account: Partial<ISocialAccount>) => Promise<boolean>;
    getAccount: (id: number) => Promise<ISocialAccount | null>;
    createAccount: (data: CreateAccountRequest) => Promise<{ success: boolean; id?: number; message?: string }>;
    verifyConnection: (id: number) => Promise<VerifyResponse>;
}

export const SocialAccountManager: React.FC<SocialAccountManagerProps> = ({
    accounts,
    providers,
    isLoading,
    error,
    verifyingId,
    fetchAccounts,
    fetchProviders,
    deleteAccount,
    updateAccount,
    getAccount,
    createAccount,
    verifyConnection
}) => {
    const [view, setView] = useState<'list' | 'edit' | 'add'>('list');
    const [selectedAccount, setSelectedAccount] = useState<ISocialAccount | null>(null);
    const [editBuffer, setEditBuffer] = useState<Partial<ISocialAccount>>({});
    const [localVerifying, setLocalVerifying] = useState(false);
    const hasFetched = useRef(false);

    useEffect(() => {
        if (hasFetched.current) return;
        hasFetched.current = true;
        fetchAccounts();
        fetchProviders();
    }, [fetchAccounts, fetchProviders]);

    const handleEdit = (id: number) => {
        const acc = accounts.find(a => a.id === id);
        if (acc) {
            setSelectedAccount(acc);
            setEditBuffer({ ...acc });
            setView('edit');
        }
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        const success = await updateAccount(editBuffer);
        if (success) {
            setView('list');
            if (window.WFToast) {
                window.WFToast.success('Account updated successfully.');
            }
        }
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleDelete = async (id: number, name: string) => {
        const confirmed = await themedConfirm({
            title: 'Disconnect Account',
            message: `Disconnect social account "${name}"? This will stop all scheduled posts for this account.`,
            confirmText: 'Disconnect Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteAccount(id);
            if (success && window.WFToast) {
                window.WFToast.success('Account disconnected.');
            }
        }
    };


    const handleVerify = async (id: number) => {
        const result = await verifyConnection(id);
        if (result.success && window.WFToast) {
            window.WFToast.success('Connection verified successfully!');
        } else if (!result.success && window.WFToast) {
            window.WFToast.error(result.message || 'Connection verification failed.');
        }
    };

    const handleVerifyInEditor = async () => {
        if (!selectedAccount) return;
        setLocalVerifying(true);
        const result = await verifyConnection(selectedAccount.id);
        setLocalVerifying(false);

        // Refresh the selected account data from local accounts after fetchAccounts updates it
        await fetchAccounts();
        const updatedAcc = accounts.find(a => a.id === selectedAccount.id);
        if (updatedAcc) {
            setSelectedAccount(updatedAcc);
        }

        if (result.success && window.WFToast) {
            window.WFToast.success('Connection verified!');
        } else if (!result.success && window.WFToast) {
            window.WFToast.error(result.message || 'Verification failed.');
        }
    };

    const handleLinkAccount = async (platform: SocialPlatform, accountName: string, accountId: string, credentials?: Record<string, string>): Promise<boolean> => {
        // Create the account with provided credentials or simulated token for OAuth
        const result = await createAccount({
            platform,
            account_name: accountName,
            account_id: accountId,
            access_token: credentials?.access_token || credentials?.api_key || `oauth_token_${Date.now()}`,
            ...(credentials || {})
        });

        if (result.success) {
            setView('list');
            return true;
        }
        return false;
    };

    return (
        <div className="social-account-panel">
            {error && (
                <div className="mb-4 p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                    <span className="text-xl">⚠️</span> {error}
                </div>
            )}

            <div className="flex items-center justify-between mb-8">
                <div className="flex flex-col">
                    <h3 className="text-lg font-bold text-slate-800">Connected Accounts</h3>
                    <p className="text-xs text-slate-500 font-medium">Link and manage your social platforms for automatic sharing.</p>
                </div>
            </div>

            {isLoading && !accounts.length && view === 'list' ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400 uppercase tracking-widest font-black text-[10px]">
                    <span className="wf-emoji-loader text-3xl mb-4">📱</span>
                    <p>Loading accounts...</p>
                </div>
            ) : (
                <div className="flex-1">
                    {view === 'list' && (
                        <SocialAccountList
                            accounts={accounts}
                            isLoading={isLoading}
                            verifyingId={verifyingId}
                            onEdit={handleEdit}
                            onDelete={handleDelete}
                            onVerify={handleVerify}
                            onStartLinking={() => setView('add')}
                        />
                    )}

                    {view === 'edit' && selectedAccount && (
                        <SocialAccountEditor
                            account={selectedAccount}
                            editBuffer={editBuffer}
                            setEditBuffer={setEditBuffer}
                            onSave={handleSave}
                            onCancel={() => setView('list')}
                            onVerify={handleVerifyInEditor}
                            isLoading={isLoading}
                            isVerifying={localVerifying || verifyingId === selectedAccount.id}
                        />
                    )}

                    {view === 'add' && (
                        <SocialAccountAdd
                            providers={providers}
                            onLinkAccount={handleLinkAccount}
                            onCancel={() => setView('list')}
                            isLoading={isLoading}
                        />
                    )}
                </div>
            )}
        </div>
    );
};


