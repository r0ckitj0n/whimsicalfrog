/** Shared types for the storefront whimsical loading splash. */

export interface ILoadingMessagePair {
    id: number;
    line1: string;
    line2: string;
}

export interface IFeaturedSplashProduct {
    sku: string;
    item_name: string;
    price: string | number;
    stock: number;
    image_url?: string;
}

export interface IFeaturedProductsResponse {
    success: boolean;
    products?: IFeaturedSplashProduct[];
    error?: string;
}
