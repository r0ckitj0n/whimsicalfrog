import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IPaletteColor, IBrandingTokens, IBrandingResponse, IBrandingActionResponse } from '../../types/theming.js';

// Re-export for backward compatibility
export type { IPaletteColor, IBrandingTokens, IBrandingResponse, IBrandingActionResponse } from '../../types/theming.js';



export const useBranding = () => {
    const [tokens, setTokens] = useState<IBrandingTokens | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const parsePalette = (value: unknown): IPaletteColor[] => {
        if (!value) return [];
        if (Array.isArray(value)) return value as IPaletteColor[];
        if (typeof value === 'string') {
            try {
                return JSON.parse(value) as IPaletteColor[];
            } catch (_) {
                return [];
            }
        }
        return [];
    };

    const fetchTokens = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.get<IBrandingResponse>('/api/branding_tokens.php?action=get_tokens');
            if (response && response.success) {
                const fetchedTokens = response.tokens;
                if (fetchedTokens.business_brand_palette) {
                    fetchedTokens.business_brand_palette = parsePalette(fetchedTokens.business_brand_palette);
                }
                setTokens(fetchedTokens);
            } else {
                setError(response?.message || 'Failed to load branding tokens.');
            }
        } catch (err) {
            logger.error('[Branding] fetch failed', err);
            setError('Unable to load branding configuration.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveTokens = async (nextTokens: Partial<IBrandingTokens>) => {
        setIsLoading(true);
        try {
            const payload = { ...nextTokens };
            if (Array.isArray(payload.business_brand_palette)) {
                payload.business_brand_palette = JSON.stringify(payload.business_brand_palette);
            }

            const response = await ApiClient.post<IBrandingActionResponse | IBrandingTokens>('/api/branding_tokens.php?action=save_tokens', payload);
            if (response) {
                const updatedTokens = 'business_brand_primary' in response ? response : response.tokens;
                if (!updatedTokens) return false;
                if (updatedTokens.business_brand_palette) {
                    updatedTokens.business_brand_palette = parsePalette(updatedTokens.business_brand_palette);
                }
                setTokens(updatedTokens);

                // Trigger CSS variable update
                if (window.dispatchEvent) {
                    window.dispatchEvent(new CustomEvent('wf:branding-updated', { detail: updatedTokens }));
                }

                return true;
            }
        } catch (err) {
            logger.error('[Branding] save failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    const createBackup = async () => {
        setIsLoading(true);
        try {
            const response = await ApiClient.post<IBrandingActionResponse>('/api/branding_tokens.php?action=create_backup', {});
            return !!response?.success;
        } catch (err) {
            logger.error('[Branding] backup failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    const resetToDefaults = async () => {
        setIsLoading(true);
        try {
            const response = await ApiClient.post<IBrandingActionResponse>('/api/branding_tokens.php?action=reset_defaults', {});
            if (response?.success) {
                const updatedTokens = response.tokens;
                if (!updatedTokens) return false;
                if (updatedTokens.business_brand_palette) {
                    updatedTokens.business_brand_palette = parsePalette(updatedTokens.business_brand_palette);
                }
                setTokens(updatedTokens);
                return true;
            }
        } catch (err) {
            logger.error('[Branding] reset failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    useEffect(() => {
        fetchTokens();
    }, [fetchTokens]);

    return {
        tokens,
        isLoading,
        error,
        fetchTokens,
        saveTokens,
        createBackup,
        resetToDefaults
    };
};
