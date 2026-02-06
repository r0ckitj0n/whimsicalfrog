import { useState, useCallback } from 'react';
import type { SocialPlatform } from '../../core/constants/uienums.js';
import type { ISocialProvider, AuthMethodConfig } from './useSocialMedia.js';

export type AuthStep = 'select' | 'auth_method' | 'credentials' | 'authorizing' | 'preview';

export interface AuthResult {
    platform: SocialPlatform;
    accountName: string;
    accountId: string;
}

interface UseSocialAccountAuthParams {
    onLinkAccount: (platform: SocialPlatform, accountName: string, accountId: string, credentials?: Record<string, string>) => Promise<boolean>;
}

export const useSocialAccountAuth = ({ onLinkAccount }: UseSocialAccountAuthParams) => {
    const [step, setStep] = useState<AuthStep>('select');
    const [selectedProvider, setSelectedProvider] = useState<ISocialProvider | null>(null);
    const [selectedAuthMethod, setSelectedAuthMethod] = useState<AuthMethodConfig | null>(null);
    const [credentials, setCredentials] = useState<Record<string, string>>({});
    const [displayName, setDisplayName] = useState('');
    const [authResult, setAuthResult] = useState<AuthResult | null>(null);
    const [localLoading, setLocalLoading] = useState(false);

    const handleOAuth = useCallback(async (provider: ISocialProvider, dName: string) => {
        setLocalLoading(true);
        // Simulate OAuth flow
        await new Promise(resolve => setTimeout(resolve, 2000));

        const mockResult: AuthResult = {
            platform: provider.id as SocialPlatform,
            accountName: dName || `My ${provider.name} Account`,
            accountId: `${provider.id}_${Date.now()}`
        };

        setAuthResult(mockResult);
        setLocalLoading(false);
        setStep('preview');
    }, []);

    const handleProviderClick = useCallback((provider: ISocialProvider) => {
        setSelectedProvider(provider);
        const initialDisplayName = `My ${provider.name} Account`;
        setDisplayName(initialDisplayName);

        if (provider.authMethods.length === 1) {
            const method = provider.authMethods[0];
            setSelectedAuthMethod(method);
            if (method.type === 'oauth') {
                setStep('authorizing');
                handleOAuth(provider, initialDisplayName);
            } else {
                setStep('credentials');
            }
        } else {
            setStep('auth_method');
        }
    }, [handleOAuth]);

    const handleAuthMethodSelect = useCallback((method: AuthMethodConfig) => {
        setSelectedAuthMethod(method);
        setCredentials({});

        if (method.type === 'oauth') {
            setStep('authorizing');
            handleOAuth(selectedProvider!, displayName);
        } else {
            setStep('credentials');
        }
    }, [selectedProvider, displayName, handleOAuth]);

    const handleCredentialSubmit = useCallback(async (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedProvider || !selectedAuthMethod) return;

        setLocalLoading(true);
        const accountId = credentials.account_id || credentials.page_id || credentials.channel_id || `${selectedProvider.id}_${Date.now()}`;

        const success = await onLinkAccount(
            selectedProvider.id as SocialPlatform,
            displayName,
            accountId,
            credentials
        );

        setLocalLoading(false);
        if (success && window.WFToast) {
            window.WFToast.success(`${selectedProvider.name} account linked successfully!`);
        }
    }, [selectedProvider, selectedAuthMethod, displayName, credentials, onLinkAccount]);

    const handleConfirmLink = useCallback(async () => {
        if (!authResult) return;

        setLocalLoading(true);
        const success = await onLinkAccount(authResult.platform, authResult.accountName, authResult.accountId);
        setLocalLoading(false);

        if (success && window.WFToast) {
            window.WFToast.success(`${selectedProvider?.name} account linked successfully!`);
        }
    }, [authResult, selectedProvider, onLinkAccount]);

    const handleBack = useCallback(() => {
        if (step === 'auth_method') {
            setStep('select');
            setSelectedProvider(null);
        } else if (step === 'credentials' || step === 'authorizing') {
            if (selectedProvider?.authMethods.length === 1) {
                setStep('select');
                setSelectedProvider(null);
            } else {
                setStep('auth_method');
            }
            setSelectedAuthMethod(null);
            setCredentials({});
        } else if (step === 'preview') {
            setStep('select');
            setSelectedProvider(null);
            setSelectedAuthMethod(null);
            setAuthResult(null);
        }
    }, [step, selectedProvider]);

    return {
        step,
        setStep,
        selectedProvider,
        selectedAuthMethod,
        credentials,
        setCredentials,
        displayName,
        setDisplayName,
        authResult,
        localLoading,
        handleProviderClick,
        handleAuthMethodSelect,
        handleCredentialSubmit,
        handleConfirmLink,
        handleBack
    };
};
