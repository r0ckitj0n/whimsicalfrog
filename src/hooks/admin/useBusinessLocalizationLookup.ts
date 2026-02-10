import { ApiClient } from '../../core/ApiClient.js';
import type { IBusinessLocalizationLookupResponse, IBusinessLocalizationLookupResult } from '../../types/settings.js';

export const lookupBusinessLocalization = async (
    postalCode: string,
    countryCode: string
): Promise<IBusinessLocalizationLookupResult | null> => {
    const res = await ApiClient.get<IBusinessLocalizationLookupResponse>('/api/business_localization_lookup.php', {
        postal_code: postalCode,
        country_code: countryCode
    });

    if (!res?.success || !res.data) {
        return null;
    }

    return res.data;
};
