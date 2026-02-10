import { ENVIRONMENT, Environment } from '../core/constants.js';

export interface ISquareSettings {
    square_enabled?: boolean;
    square_application_id: string;
    square_access_token: string;
    square_location_id: string;
    square_environment: Environment;
    square_sync_enabled: boolean;
    square_sandbox_application_id: string;
    square_sandbox_access_token: string;
    square_sandbox_location_id: string;
    square_production_application_id: string;
    square_production_access_token: string;
    square_production_location_id: string;
}

export interface ISquareSettingsApiRecord {
    square_enabled?: string | number | boolean;
    square_environment?: Environment;
    square_application_id?: string;
    square_access_token?: string;
    square_location_id?: string;
    square_sandbox_application_id?: string;
    square_sandbox_access_token?: string;
    square_sandbox_location_id?: string;
    square_production_application_id?: string;
    square_production_access_token?: string;
    square_production_location_id?: string;
    auto_sync_enabled?: string | number | boolean;
    square_sync_enabled?: string | number | boolean;
}

// Re-export for convenience
export type { Environment } from '../core/constants.js';

// Square SDK Types
export interface ISquareCard {
    attach: (elementId: string) => Promise<void>;
    tokenize: (options?: {
        billingContact?: {
            addressLines?: string[];
            city?: string;
            state?: string;
            postalCode?: string;
            countryCode?: string;
        };
        verificationDetails?: {
            intent?: string;
            customerInitiated?: boolean;
            sellerKeyedIn?: boolean;
            amount?: string;
            currencyCode?: string;
            billingContact?: {
                addressLines?: string[];
                city?: string;
                state?: string;
                postalCode?: string;
                countryCode?: string;
            };
        };
    }) => Promise<{ status: string; token?: string; errors?: Array<{ message: string }> }>;
    destroy: () => Promise<void>;
}

export interface ISquarePayments {
    card: (options?: { postalCode?: string }) => Promise<ISquareCard>;
}

export interface ISquare {
    payments: (appId: string, locId: string) => ISquarePayments;
}

export interface ISquareWindow extends Window {
    Square?: ISquare;
}
