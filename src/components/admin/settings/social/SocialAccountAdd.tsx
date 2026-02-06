import React from 'react';
import type { ISocialProvider } from '../../../../hooks/admin/useSocialMedia.js';
import type { SocialPlatform } from '../../../../core/constants/uienums.js';
import { useSocialAccountAuth } from '../../../../hooks/admin/useSocialAccountAuth.js';
import { ProviderSelect } from './add-account/ProviderSelect.js';
import { AuthMethodSelect } from './add-account/AuthMethodSelect.js';
import { CredentialsForm } from './add-account/CredentialsForm.js';
import { AuthProgress } from './add-account/AuthProgress.js';
import { AuthPreview } from './add-account/AuthPreview.js';

interface SocialAccountAddProps {
    providers: ISocialProvider[];
    onLinkAccount: (platform: SocialPlatform, accountName: string, accountId: string, credentials?: Record<string, string>) => Promise<boolean>;
    onCancel: () => void;
    isLoading?: boolean;
}

export const SocialAccountAdd: React.FC<SocialAccountAddProps> = ({
    providers,
    onLinkAccount,
    onCancel,
    isLoading = false
}) => {
    const {
        step,
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
    } = useSocialAccountAuth({ onLinkAccount });

    // Step-based rendering
    switch (step) {
        case 'select':
            return (
                <ProviderSelect
                    providers={providers}
                    onProviderClick={handleProviderClick}
                    onCancel={onCancel}
                    isLoading={isLoading || localLoading}
                />
            );

        case 'auth_method':
            return selectedProvider ? (
                <AuthMethodSelect
                    selectedProvider={selectedProvider}
                    onAuthMethodSelect={handleAuthMethodSelect}
                    onBack={handleBack}
                />
            ) : null;

        case 'credentials':
            return (selectedProvider && selectedAuthMethod) ? (
                <CredentialsForm
                    selectedProvider={selectedProvider}
                    selectedAuthMethod={selectedAuthMethod}
                    displayName={displayName}
                    setDisplayName={setDisplayName}
                    credentials={credentials}
                    setCredentials={setCredentials}
                    localLoading={localLoading}
                    onCredentialSubmit={handleCredentialSubmit}
                    onBack={handleBack}
                />
            ) : null;

        case 'authorizing':
            return selectedProvider ? (
                <AuthProgress
                    selectedProvider={selectedProvider}
                    onBack={handleBack}
                />
            ) : null;

        case 'preview':
            return (selectedProvider && authResult) ? (
                <AuthPreview
                    selectedProvider={selectedProvider}
                    authResult={authResult}
                    localLoading={localLoading}
                    onBack={handleBack}
                    onConfirmLink={handleConfirmLink}
                />
            ) : null;

        default:
            return null;
    }
};

export default SocialAccountAdd;


