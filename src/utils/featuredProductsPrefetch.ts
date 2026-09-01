import { ApiClient } from '../core/ApiClient.js';
import type {
    IFeaturedProductsResponse,
    IFeaturedSplashProduct
} from '../types/loadingSplash.js';

let featuredProductsPromise: Promise<IFeaturedSplashProduct[]> | null = null;

const normalize = (data: IFeaturedProductsResponse | null | undefined): IFeaturedSplashProduct[] => {
    if (!data || data.success === false) {
        return [];
    }
    const list = Array.isArray(data.products) ? data.products : [];
    return list.filter(
        (item) =>
            item &&
            typeof item.sku === 'string' &&
            item.sku.length > 0 &&
            typeof item.item_name === 'string' &&
            Number(item.stock) > 0
    );
};

/**
 * Start (or reuse) a single in-flight featured-products fetch.
 * Safe to call repeatedly; never throws.
 * Empty/failed results are not cached permanently so a later retry can succeed.
 */
export const prefetchFeaturedProducts = (): Promise<IFeaturedSplashProduct[]> => {
    if (!featuredProductsPromise) {
        featuredProductsPromise = ApiClient.get<IFeaturedProductsResponse>(
            '/api/featured_products.php'
        )
            .then((data) => {
                const products = normalize(data);
                if (products.length === 0) {
                    featuredProductsPromise = null;
                }
                return products;
            })
            .catch(() => {
                featuredProductsPromise = null;
                return [] as IFeaturedSplashProduct[];
            });
    }
    return featuredProductsPromise;
};
