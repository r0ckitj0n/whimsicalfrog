export interface IReceiptMessage {
    id: number;
    type: 'shipping' | 'items' | 'categories' | 'default';
    title: string;
    content: string;
    condition_value?: string;
    is_active: boolean;
}

export interface IReceiptVerbiage {
    receipt_thank_you_message: string;
    receipt_next_steps: string;
    receipt_social_sharing: string;
    receipt_return_customer: string;
}

export interface IOptionSettings {
    cascade_order: string[];
    enabled_dimensions: string[];
    grouping_rules: Record<string, unknown>;
}

// Business Info Types (migrated from useBusinessInfo.ts)
export interface IBusinessInfo {
    business_name: string;
    business_email: string;
    business_phone: string;
    business_address: string;
    business_address2: string;
    business_city: string;
    business_state: string;
    business_postal: string;
    business_country: string;
    business_owner: string;
    business_hours: string;
    business_site_url: string;
    business_logo: string;
    business_tagline: string;
    business_description: string;
    business_support_email: string;
    business_support_phone: string;
    business_tax_id: string;
    business_timezone: string;
    business_currency: string;
    business_locale: string;
    business_terms_url: string;
    business_privacy_url: string;
    business_footer_note: string;
    business_footer_html: string;
    business_return_policy: string;
    business_shipping_policy: string;
    business_warranty_policy: string;
    business_policy_url: string;
    business_privacy_policy_content: string;
    business_terms_service_content: string;
    business_store_policies_content: string;
    about_page_title: string;
    about_page_content: string;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/** Response for option settings endpoint */
export interface IOptionSettingsResponse {
    success: boolean;
    settings: {
        cascade_order: string[];
        enabled_dimensions: string[];
        grouping_rules: Record<string, unknown>;
    };
}

/** Response for business info endpoint */
export interface IBusinessInfoResponse {
    success: boolean;
    data?: IBusinessInfo;
    settings?: IBusinessInfo;
    error?: string;
}


