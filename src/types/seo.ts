import type { IShopItem } from './inventory.js';

export interface IProductRouteItem extends IShopItem {
    category_slug: string;
    category_label: string;
}

export interface IProductRouteData {
    item: IProductRouteItem | null;
    related: IProductRouteItem[];
}
