import { useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { AUTH, API_ACTION } from '../../core/constants.js';
import { IGlobalColor, useGlobalColors } from './global-entities/useGlobalColors.js';
import { IGlobalSize, useGlobalSizes } from './global-entities/useGlobalSizes.js';
import { IGlobalGender, useGlobalGenders } from './global-entities/useGlobalGenders.js';
import { ISizeTemplate, useSizeTemplates } from './global-entities/useSizeTemplates.js';
import { IColorTemplate, useColorTemplates } from './global-entities/useColorTemplates.js';
import { IGenderTemplate, useGenderTemplates } from './global-entities/useGenderTemplates.js';
import type { IGlobalEntitiesResponse } from '../../types/theming.js';

export * from './global-entities/useGlobalColors.js';
export * from './global-entities/useGlobalSizes.js';
export * from './global-entities/useGlobalGenders.js';
export * from './global-entities/useSizeTemplates.js';
export * from './global-entities/useColorTemplates.js';
export * from './global-entities/useGenderTemplates.js';

export const useGlobalEntities = () => {
    const fetchAll = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [gRes, sRes, cRes, stRes, ctRes, gtRes] = await Promise.all([
                ApiClient.get<IGlobalEntitiesResponse<IGlobalGender[]>>(`/api/global_color_size_management.php?action=${API_ACTION.GET_GLOBAL_GENDERS}&admin_token=${AUTH.ADMIN_TOKEN}`),
                ApiClient.get<IGlobalEntitiesResponse<IGlobalSize[]>>(`/api/global_color_size_management.php?action=${API_ACTION.GET_GLOBAL_SIZES}&admin_token=${AUTH.ADMIN_TOKEN}`),
                ApiClient.get<IGlobalEntitiesResponse<IGlobalColor[]>>(`/api/global_color_size_management.php?action=${API_ACTION.GET_GLOBAL_COLORS}&admin_token=${AUTH.ADMIN_TOKEN}`),
                ApiClient.get<IGlobalEntitiesResponse<ISizeTemplate[]>>(`/api/size_templates.php?action=get_all&admin_token=${AUTH.ADMIN_TOKEN}`),
                ApiClient.get<IGlobalEntitiesResponse<IColorTemplate[]>>(`/api/color_templates.php?action=get_all&admin_token=${AUTH.ADMIN_TOKEN}`),
                ApiClient.get<IGlobalEntitiesResponse<IGenderTemplate[]>>(`/api/gender_templates.php?action=get_all&admin_token=${AUTH.ADMIN_TOKEN}`),
            ]);

            if (gRes?.success) setGenders(gRes.genders || []);
            if (sRes?.success) setSizes(sRes.sizes || []);
            if (cRes?.success) setColors(cRes.colors || []);
            if (stRes?.success) setSizeTemplates(stRes.templates || []);
            if (ctRes?.success) setColorTemplates(ctRes.templates || []);
            if ((gtRes as any)?.success) setGenderTemplates((gtRes as any).templates || (gtRes as any).genderTemplates || []);

            if (!gRes?.success || !sRes?.success || !cRes?.success || !stRes?.success || !ctRes?.success || !(gtRes as any)?.success) {
                setError('Some global attributes failed to load');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchGlobalEntities failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const { colors, setColors, saveColor, deleteColor, isLoading: colorsLoading, error: colorsError, setError: setColorsError } = useGlobalColors(fetchAll);
    const { sizes, setSizes, saveSize, deleteSize, isLoading: sizesLoading, error: sizesError, setError: setSizesError } = useGlobalSizes(fetchAll);
    const { genders, setGenders, saveGender, deleteGender, isLoading: gendersLoading, error: gendersError, setError: setGendersError } = useGlobalGenders(fetchAll);
    const { sizeTemplates, setSizeTemplates, fetchSizeTemplate, saveSizeTemplate, deleteSizeTemplate, duplicateSizeTemplate, isLoading: stLoading, error: stError, setError: setStError } = useSizeTemplates(fetchAll);
    const { colorTemplates, setColorTemplates, fetchColorTemplate, saveColorTemplate, deleteColorTemplate, duplicateColorTemplate, isLoading: ctLoading, error: ctError, setError: setCtError } = useColorTemplates(fetchAll);
    const {
        genderTemplates,
        setGenderTemplates,
        fetchGenderTemplate,
        saveGenderTemplate,
        deleteGenderTemplate,
        duplicateGenderTemplate,
        isLoading: gtLoading,
        error: gtError,
        setError: setGtError
    } = useGenderTemplates(fetchAll);

    const isLoading = colorsLoading || sizesLoading || gendersLoading || stLoading || ctLoading || gtLoading;
    const error = colorsError || sizesError || gendersError || stError || ctError || gtError;

    const setIsLoading = (val: boolean) => {
        // This is a bridge for fetchAll to work
    };

    const setError = (val: string | null) => {
        setColorsError(val);
    };

    return {
        colors,
        sizes,
        genders,
        sizeTemplates,
        colorTemplates,
        genderTemplates,
        isLoading,
        error,
        fetchAll,
        fetchSizeTemplate,
        fetchColorTemplate,
        fetchGenderTemplate,
        saveColor,
        deleteColor,
        saveSize,
        deleteSize,
        saveGender,
        deleteGender,
        saveSizeTemplate,
        saveColorTemplate,
        saveGenderTemplate,
        deleteSizeTemplate,
        deleteColorTemplate,
        deleteGenderTemplate,
        duplicateSizeTemplate,
        duplicateColorTemplate,
        duplicateGenderTemplate
    };
};
