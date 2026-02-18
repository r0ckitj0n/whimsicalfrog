import type { IShopItem } from '../types/inventory.js';

const slugify = (value: string): string => value
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '');

export const productSlugFromItem = (item: Pick<IShopItem, 'item_name' | 'sku'>): string => {
    const nameSlug = slugify(item.item_name || 'product') || 'product';
    const skuSlug = slugify(item.sku || 'sku') || 'sku';
    return `${nameSlug}--${skuSlug}`;
};

export const productPathFromItem = (item: Pick<IShopItem, 'item_name' | 'sku'>): string =>
    `/product/${encodeURIComponent(productSlugFromItem(item))}`;

export const categoryPathFromSlug = (categorySlug: string): string =>
    `/shop/category/${encodeURIComponent(categorySlug)}`;
