import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../core/ApiClient.js';
import { useCart } from './use-cart.js';
import logger from '../core/logger.js';
import { IItemOption, IItemDetails, IItemDetailsResponse, IItemSizesResponse } from '../types/inventory.js';

// Re-export for backward compatibility
export type { IItemOption, IItemDetails } from '../types/inventory.js';

/**
 * useItemDetails v1.0.73
 */
export const useItemDetails = (sku: string) => {
    const [item, setItem] = useState<IItemDetails | null>(null);
    const [options, setOptions] = useState<IItemOption[]>([]);
    const [images, setImages] = useState<Array<{ image_path: string; alt_text?: string; is_primary: boolean; sort_order: number }>>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { addItem } = useCart();

    const fetchDetails = useCallback(async () => {
        if (!sku) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IItemDetailsResponse>('/api/get_item_details.php', { sku });

            if (res && res.success) {
                const itemData = JSON.parse(JSON.stringify(res.item));

                if (!itemData.price && itemData.retail_price) {
                    itemData.price = Number(itemData.retail_price);
                }

                let resolvedImage = itemData.image_url || itemData.image;
                if (!resolvedImage && res.images && res.images.length > 0) {
                    const primary = res.images.find(img => img.is_primary) || res.images[0];
                    resolvedImage = primary.image_path;
                }

                if (resolvedImage) {
                    let cleanPath = resolvedImage.replace(/^\.\//, '/').replace(/^\.\.\//, '/').replace(/\/+/g, '/');
                    if (!cleanPath.startsWith('/') && !cleanPath.startsWith('http')) {
                        cleanPath = '/' + cleanPath;
                    }
                    itemData.image = cleanPath;
                } else {
                    itemData.image = '/images/logos/logo-whimsicalfrog.webp';
                }

                setItem(itemData);
                if (res.images) {
                    setImages(res.images.map(img => {
                        let path = img.image_path.replace(/^\.\//, '/').replace(/^\.\.\//, '/').replace(/\/+/g, '/');
                        if (!path.startsWith('/') && !path.startsWith('http')) {
                            path = '/' + path;
                        }
                        return {
                            ...img,
                            image_path: path
                        };
                    }));
                }
            }

            const optRes = await ApiClient.get<IItemSizesResponse>('/api/item_sizes.php', {
                action: 'get_sizes',
                item_sku: sku
            });

            if (optRes && optRes.sizes) {
                setOptions(optRes.sizes);
            }
        } catch (err) {
            logger.error('[useItemDetails] Failed to fetch item details', err);
            setError('Unable to load item details');
        } finally {
            setIsLoading(false);
        }
    }, [sku]);

    useEffect(() => {
        fetchDetails();
    }, [fetchDetails]);

    const addToCart = useCallback((quantity: number, selectedOptions: Partial<IItemOption>) => {
        if (!item) return;
        const price = Number(item.price || item.retail_price || 0);
        const cartItem = {
            ...item,
            ...selectedOptions,
            price,
            quantity
        };
        addItem(cartItem, quantity);
    }, [item, addItem]);

    return {
        item,
        options,
        images,
        isLoading,
        error,
        addToCart,
        refresh: fetchDetails
    };
};
