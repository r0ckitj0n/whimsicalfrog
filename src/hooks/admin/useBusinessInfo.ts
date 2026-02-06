import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { setBusinessTimezone } from '../../core/date-utils.js';
import type { IBusinessInfo, IBusinessInfoResponse } from '../../types/settings.js';

// Re-export for backward compatibility
export type { IBusinessInfo, IBusinessInfoResponse } from '../../types/settings.js';



export const useBusinessInfo = () => {
    const defaultInfo: IBusinessInfo = {
        business_name: '',
        business_email: '',
        business_phone: '',
        business_address: '',
        business_address2: '',
        business_city: '',
        business_state: '',
        business_postal: '',
        business_country: 'US',
        business_owner: '',
        business_hours: '',
        business_site_url: '',
        business_logo: '',
        business_tagline: '',
        business_description: '',
        business_support_email: '',
        business_support_phone: '',
        business_tax_id: '',
        business_timezone: 'America/New_York',
        business_currency: 'USD',
        business_locale: 'en-US',
        business_terms_url: '',
        business_privacy_url: '',
        business_footer_note: '',
        business_footer_html: '',
        business_return_policy: '',
        business_shipping_policy: '',
        business_warranty_policy: '',
        business_policy_url: '',
        business_privacy_policy_content: '',
        business_terms_service_content: '',
        business_store_policies_content: '',
        about_page_title: '',
        about_page_content: ''
    };

    const [info, setInfo] = useState<IBusinessInfo>(defaultInfo);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchInfo = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<any>('/api/business_settings.php?action=get_business_info');
            if (res) {
                const data = res.settings || res;
                if (data.business_timezone) {
                    setBusinessTimezone(data.business_timezone);
                }
                setInfo({
                    ...defaultInfo,
                    ...data
                });
            }
        } catch (err) {
            logger.error('[useBusinessInfo] fetch failed', err);
            setError('Failed to load business information');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveInfo = async (newInfo: IBusinessInfo) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php', {
                action: 'upsert_settings',
                category: 'business_info',
                settings: newInfo
            });

            if (res) {
                setInfo(newInfo);
                return { success: true };
            }
            return { success: false, error: 'Save failed' };
        } catch (err) {
            logger.error('[useBusinessInfo] save failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchInfo();
    }, [fetchInfo]);

    return {
        info,
        isLoading,
        error,
        saveInfo,
        refresh: fetchInfo
    };
};
