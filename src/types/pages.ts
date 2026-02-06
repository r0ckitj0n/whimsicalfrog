export interface IShopData {
    categories: Record<string, {
        slug: string;
        label: string;
        items: Array<{
            sku: string;
            item_name: string;
            price: string | number;
            stock: number;
            description: string;
            custom_button_text?: string;
            image_url?: string;
        }>;
    }>;
    current_page: string;
}

export interface IPOSData {
    business_name: string;
    items: Array<{
        sku: string;
        name: string;
        category: string;
        retail_price: number;
        current_price: number;
        original_price: number;
        stock: number;
        image_url?: string;
        is_on_sale: boolean;
        sale_discount_percentage: number;
        status: string;
    }>;
    categories: string[];
}

export interface IAboutData {
    title: string;
    content: string;
}

export interface IContactData {
    email: string;
    phone: string;
    address: string;
    owner: string;
    name: string;
    site: string;
    hours: string;
    page_title?: string;
    page_intro?: string;
    csrf?: string;
}

export interface ISiteSettings {
    name: string;
    tagline: string;
    logo: string;
    email: string;
    social: {
        facebook: string;
        instagram: string;
        twitter: string;
        pinterest: string;
    };
    brand_primary: string;
    brand_secondary: string;
}
