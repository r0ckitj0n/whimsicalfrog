import { useColorOptions } from './inventory-options/useColorOptions.js';
import { useSizeOptions } from './inventory-options/useSizeOptions.js';
import { useStockManagement } from './inventory-options/useStockManagement.js';
import { useTemplateApplication } from './inventory-options/useTemplateApplication.js';

export const useInventoryOptions = (sku: string) => {
    const { 
        colors, 
        setColors, 
        fetchColors, 
        saveColor, 
        deleteColor, 
        isLoading: colorsLoading, 
        error: colorsError,
        setError: setColorsError
    } = useColorOptions(sku);

    const { 
        sizes, 
        setSizes, 
        fetchSizes, 
        saveSize, 
        deleteSize, 
        isLoading: sizesLoading, 
        error: sizesError,
        setError: setSizesError
    } = useSizeOptions(sku);

    const { 
        syncStock, 
        distributeStockEvenly, 
        ensureColorSizes, 
        isLoading: stockLoading, 
        error: stockError,
        setError: setStockError
    } = useStockManagement(sku, fetchColors, fetchSizes);

    const { 
        applyColorTemplate, 
        applySizeTemplate, 
        isLoading: templateLoading, 
        error: templateError,
        setError: setTemplateError
    } = useTemplateApplication(sku, fetchColors, fetchSizes);

    const isLoading = colorsLoading || sizesLoading || stockLoading || templateLoading;
    const error = colorsError || sizesError || stockError || templateError;

    const setError = (msg: string | null) => {
        setColorsError(msg);
        setSizesError(msg);
        setStockError(msg);
        setTemplateError(msg);
    };

    return {
        colors,
        sizes,
        isLoading,
        error,
        fetchColors,
        fetchSizes,
        saveColor,
        deleteColor,
        saveSize,
        deleteSize,
        syncStock,
        distributeStockEvenly,
        ensureColorSizes,
        applyColorTemplate,
        applySizeTemplate
    };
};

