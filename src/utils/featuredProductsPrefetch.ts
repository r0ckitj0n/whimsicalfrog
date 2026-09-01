import { ApiClient } from '../core/ApiClient.js';
import type {
    IFeaturedProductsResponse,
    IFeaturedSplashProduct
} from '../types/loadingSplash.js';

let featuredProductsPromise: Promise<IFeaturedSplashProduct[]> | null = null;

const normalize = (data: IFeaturedProductsResponse | null | undefined): IFeaturedSplashProduct[] => {
    if (!data?.success || !Array.isArray(data.products)) {
        return [];
    }
    return data.products.filter(
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
 */
export const prefetchFeaturedProducts = (): Promise<IFeaturedSplashProduct[]> => {
    if (!featuredProductsPromise) {
        featuredProductsPromise = ApiClient.get<IFeaturedProductsResponse>(
            '/api/featured_products.php'
        )
            .then((data) => normalize(data))
            .catch(() => [] as IFeaturedSplashProduct[]);
    }
    return featuredProductsPromise;
};
