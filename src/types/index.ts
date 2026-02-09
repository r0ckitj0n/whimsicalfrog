// Constants (runtime values - use regular export)
export * from '../core/constants.js';

// Type-only exports (interfaces/types - use export type)
export type * from './inventory.js';
export type * from './orders.js';
export type * from './admin.js';
export type * from './pages.js';
export type * from './auth.js';
export type {
    IPricingSummary,
    ICheckoutPayload,
    ISquareSettings,
    ISquareSettingsResponse,
    IUpsellItem,
    IUpsellApiResponse
} from './payment.js';
export type { PaymentMethod, ShippingMethod } from './payment.js';
export type * from './ai.js';
export type * from './ai-prompts.js';
export type * from './room-generation.js';
export type * from './newsletter.js';
// dashboard.ts has IDashboardMetrics which conflicts with admin.ts, use dashboard version
export type {
    ILowStockItem,
    IDashboardSection,
    IDashboardResponse,
    ISalesData,
    IPaymentData,
    IInventoryReportItem,
    IReportSummary,
    IReportsResponse
} from './dashboard.js';
export type * from './email.js';
export type * from './files.js';
export type * from './room.js';
export type * from './square.js';
export type * from './backgrounds.js';
export type * from './theming.js';
// Social types have imports, use named exports
export type {
    ISocialAccount,
    AuthMethod,
    AuthMethodConfig,
    ISocialProvider,
    ISocialPost,
    ISocialPostTemplate,
    ISocialImage
} from './social.js';
export type * from './maintenance.js';
export type * from './commerce.js';
export type * from './settings.js';
export type * from './help.js';
export type * from './marketing.js';
export type * from './shipping.js';
export type * from './pos.js';
