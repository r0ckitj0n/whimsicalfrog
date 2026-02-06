// Newsletter Types
export interface INewsletterSubscriber {
    id: number;
    email: string;
    subscribed_at: string;
    is_active: boolean;
    first_name?: string;
    last_name?: string;
}

export interface INewsletterCampaign {
    id: number;
    subject: string;
    content: string;
    target_group_id: number | null;
    group_name?: string;
    status: string;
    created_at: string;
    sent_at: string | null;
}

// API Response Types
export interface INewsletterListResponse {
    success: boolean;
    subscribers?: INewsletterSubscriber[];
    error?: string;
}

export interface ICampaignListResponse {
    success: boolean;
    campaigns?: INewsletterCampaign[];
    error?: string;
}

export interface ISendCampaignResponse {
    success: boolean;
    sent_count?: number;
    error?: string;
    message?: string;
}
