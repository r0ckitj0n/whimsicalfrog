import { useEffect, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IBusinessLocalizationOptions, IBusinessLocalizationOptionsResponse } from '../../types/settings.js';

const defaultOptions: IBusinessLocalizationOptions = {
    timezones: [],
    currencies: [],
    locales: []
};

export const useBusinessLocalizationOptions = () => {
    const [options, setOptions] = useState<IBusinessLocalizationOptions>(defaultOptions);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const loadOptions = async () => {
            setIsLoading(true);
            setError(null);
            try {
                const res = await ApiClient.get<IBusinessLocalizationOptionsResponse>('/api/business_localization_options.php');
                if (res?.success && res.data) {
                    setOptions(res.data);
                } else {
                    setError(res?.error || 'Failed to load localization options');
                }
            } catch (err) {
                logger.error('[useBusinessLocalizationOptions] fetch failed', err);
                setError('Failed to load localization options');
            } finally {
                setIsLoading(false);
            }
        };

        loadOptions();
    }, []);

    return {
        options,
        isLoading,
        error
    };
};
