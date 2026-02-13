<?php
/**
 * Whimsical Frog - Central PHP Constants
 * Following .windsurfrules: No magic strings or numbers.
 */

class WF_Constants
{
    // Order Statuses
    const ORDER_STATUS_PENDING = 'Pending';
    const ORDER_STATUS_PROCESSING = 'Processing';
    const ORDER_STATUS_SHIPPED = 'Shipped';
    const ORDER_STATUS_DELIVERED = 'Delivered';
    const ORDER_STATUS_CANCELLED = 'Cancelled';

    // Payment Statuses
    const PAYMENT_STATUS_PENDING = 'Pending';
    const PAYMENT_STATUS_PAID = 'Paid';
    const PAYMENT_STATUS_FAILED = 'Failed';
    const PAYMENT_STATUS_REFUNDED = 'Refunded';

    // Payment Methods
    const PAYMENT_METHOD_SQUARE = 'Square';
    const PAYMENT_METHOD_CASH = 'Cash';
    const PAYMENT_METHOD_CHECK = 'Check';
    const PAYMENT_METHOD_PAYPAL = 'PayPal';
    const PAYMENT_METHOD_VENMO = 'Venmo';
    const PAYMENT_METHOD_OTHER = 'Other';

    // Shipping Methods
    const SHIPPING_METHOD_PICKUP = 'Customer Pickup';
    const SHIPPING_METHOD_LOCAL = 'Local Delivery';
    const SHIPPING_METHOD_USPS = 'USPS';
    const SHIPPING_METHOD_FEDEX = 'FedEx';
    const SHIPPING_METHOD_UPS = 'UPS';

    // Email Statuses
    const EMAIL_STATUS_SENT = 'sent';
    const EMAIL_STATUS_FAILED = 'failed';
    const EMAIL_STATUS_PENDING = 'pending';

    // Email Types
    const EMAIL_TYPE_ORDER_CONFIRMATION = 'order_confirmation';
    const EMAIL_TYPE_ADMIN_NOTIFICATION = 'admin_notification';
    const EMAIL_TYPE_TEST_EMAIL = 'test_email';
    const EMAIL_TYPE_MANUAL_RESEND = 'manual_resend';
    const EMAIL_TYPE_WELCOME = 'welcome';
    const EMAIL_TYPE_PASSWORD_RESET = 'password_reset';

    // Environments
    const ENV_SANDBOX = 'sandbox';
    const ENV_PRODUCTION = 'production';
    const ENV_LOCAL = 'local';
    const ENV_LIVE = 'live';

    // API Actions
    const ACTION_LIST = 'list';
    const ACTION_ADD = 'add';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_RENAME = 'rename';
    const ACTION_REORDER = 'reorder';
    const ACTION_GET = 'get';
    const ACTION_SAVE = 'save';
    const ACTION_SYNC = 'sync';
    const ACTION_FETCH = 'fetch';
    const ACTION_STATUS = 'status';
    const ACTION_GET_ALL = 'get_all';
    const ACTION_GET_TYPES = 'get_types';
    const ACTION_TOGGLE_ACTIVE = 'toggle_active';
    const ACTION_GET_STATS = 'get_stats';
    const ACTION_BULK_UPDATE_WEIGHTS = 'bulk_update_weights';
    const ACTION_GET_ALL_SETTINGS = 'get_all_settings';
    const ACTION_GET_SETTING = 'get_setting';
    const ACTION_UPDATE_SETTING = 'update_setting';
    const ACTION_UPSERT_SETTINGS = 'upsert_settings';
    const ACTION_GET_BY_CATEGORY = 'get_by_category';
    const ACTION_GET_BUSINESS_INFO = 'get_business_info';
    const ACTION_GET_SALES_VERBIAGE = 'get_sales_verbiage';
    const ACTION_GET_SIZES = 'get_sizes';
    const ACTION_GET_ALL_SIZES = 'get_all_sizes';
    const ACTION_ADD_SIZE = 'add_size';
    const ACTION_UPDATE_SIZE = 'update_size';
    const ACTION_DELETE_SIZE = 'delete_size';
    const ACTION_SYNC_STOCK = 'sync_stock';
    const ACTION_DISTRIBUTE_GENERAL_STOCK_EVENLY = 'distribute_general_stock_evenly';
    const ACTION_ENSURE_COLOR_SIZES = 'ensure_color_sizes';
    const ACTION_GET_COLORS = 'get_colors';
    const ACTION_GET_ALL_COLORS = 'get_all_colors';
    const ACTION_ADD_COLOR = 'add_color';
    const ACTION_UPDATE_COLOR = 'update_color';
    const ACTION_DELETE_COLOR = 'delete_color';
    const ACTION_CHECK_AVAILABILITY = 'check_availability';
    const ACTION_UPDATE_CELL = 'update_cell';
    const ACTION_TABLE_INFO = 'table_info';
    const ACTION_TABLE_DATA = 'table_data';
    const ACTION_GET_DOCUMENTATION = 'get_documentation';
    const ACTION_LIST_TABLES = 'list_tables';
    const ACTION_GET_ITEM_STRUCTURE = 'get_item_structure';
    const ACTION_ASSIGN_SIZE_TO_ITEM = 'assign_size_to_item';
    const ACTION_REMOVE_SIZE_FROM_ITEM = 'remove_size_from_item';
    const ACTION_GET_AVAILABLE_SIZES = 'get_available_sizes';
    const ACTION_LIST_GROUPS = 'list_groups';
    const ACTION_SAVE_GROUP = 'save_group';
    const ACTION_DELETE_GROUP = 'delete_group';
    const ACTION_LIST_CAMPAIGNS = 'list_campaigns';
    const ACTION_SAVE_CAMPAIGN = 'save_campaign';
    const ACTION_DELETE_CAMPAIGN = 'delete_campaign';
    const ACTION_SEND_CAMPAIGN = 'send_campaign';
    const ACTION_GET_CUSTOMER_GROUPS = 'get_customer_groups';
    const ACTION_TOGGLE_MEMBERSHIP = 'toggle_membership';
    const ACTION_GENERATE_AI_MESSAGE = 'generate_ai_message';
    const ACTION_INIT_DEFAULTS = 'init_defaults';
    const ACTION_IMPERSONATE_CUSTOMER = 'impersonate_customer';
    const ACTION_STOP_IMPERSONATION = 'stop_impersonation';
    const ACTION_LIST_MODELS = 'list_models';
    const ACTION_GET_PROVIDERS = 'get_providers';
    const ACTION_QUERY = 'query';
    const ACTION_EMAIL_LOGS = 'email_logs';
    const ACTION_FIX_SAMPLE_EMAIL = 'fix_sample_email';
    const ACTION_GENERATE_SEO = 'generate_seo';
    const ACTION_SAVE_SEO = 'save_seo';
    const ACTION_GET_SEO = 'get_seo';

    // Pages
    const PAGE_LANDING = 'landing';
    const PAGE_SHOP = 'shop';
    const PAGE_RECEIPT = 'receipt';
    const PAGE_ROOM_MAIN = 'room_main';
    const PAGE_CONTACT = 'contact';
    const PAGE_ABOUT = 'about';
    const PAGE_CART = 'cart';
    const PAGE_HELP = 'help';
    const PAGE_DASHBOARD = 'dashboard';
    const PAGE_POS = 'pos';
    const PAGE_ADMIN = 'admin';

    // Roles
    const ROLE_ADMIN = 'admin';
    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_DEVOPS = 'devops';
    const ROLE_CUSTOMER = 'customer';
    const ROLE_SYSTEM = 'system';
    const ROLE_GUEST = 'guest';

    // Activity Categories
    const ACTIVITY_CATEGORY_SYSTEM = 'system';
    const ACTIVITY_CATEGORY_INVENTORY = 'inventory';
    const ACTIVITY_CATEGORY_ORDERS = 'orders';
    const ACTIVITY_CATEGORY_CUSTOMERS = 'customers';
    const ACTIVITY_CATEGORY_SEO = 'seo';
    const ACTIVITY_CATEGORY_MARKETING = 'marketing';
    const ACTIVITY_CATEGORY_CONFIGURATION = 'configuration';
    const ACTIVITY_CATEGORY_USER_MANAGEMENT = 'user_management';
    const ACTIVITY_CATEGORY_POS = 'pos';
    const ACTIVITY_CATEGORY_API = 'api';
    const ACTIVITY_CATEGORY_DATABASE = 'database';
    const ACTIVITY_CATEGORY_EMAIL = 'email';
    const ACTIVITY_CATEGORY_SECURITY = 'security';

    // Stock Statuses
    const STOCK_STATUS_IN_STOCK = 'In Stock';
    const STOCK_STATUS_OUT_OF_STOCK = 'Out of Stock';

    // Item Statuses
    const ITEM_STATUS_ACTIVE = 'active';
    const ITEM_STATUS_DRAFT = 'draft';
    const ITEM_STATUS_ARCHIVED = 'archived';

    // Sales Statuses
    const SALES_STATUS_ACTIVE = 'active';
    const SALES_STATUS_INACTIVE = 'inactive';
    const SALES_STATUS_SCHEDULED = 'scheduled';
    const SALES_STATUS_EXPIRED = 'expired';

    // Cost Categories
    const COST_CATEGORY_MATERIALS = 'materials';
    const COST_CATEGORY_LABOR = 'labor';
    const COST_CATEGORY_ENERGY = 'energy';
    const COST_CATEGORY_EQUIPMENT = 'equipment';

    // Coupon Types
    const COUPON_TYPE_PERCENTAGE = 'percentage';
    const COUPON_TYPE_FIXED = 'fixed';

    // Quality Tiers
    const QUALITY_TIER_PREMIUM = 'premium';
    const QUALITY_TIER_STANDARD = 'standard';
    const QUALITY_TIER_CONSERVATIVE = 'conservative';

    // Sizes (Internal/SEO)
    const SIZE_SMALL = 'small';
    const SIZE_STANDARD = 'standard';
    const SIZE_LARGE = 'large';
    const SIZE_EXTRA_LARGE = 'extra_large';

    // Confidence Levels
    const CONFIDENCE_HIGH = 'high';
    const CONFIDENCE_MEDIUM = 'medium';
    const CONFIDENCE_LOW = 'low';

    // Skill Levels
    const SKILL_BEGINNER = 'beginner';
    const SKILL_INTERMEDIATE = 'intermediate';
    const SKILL_EXPERT = 'expert';

    // Inventory Dimensions (for option cascade/grouping)
    const DIMENSION_GENDER = 'gender';
    const DIMENSION_SIZE = 'size';
    const DIMENSION_COLOR = 'color';

    // AI Providers
    const AI_PROVIDER_JONS_AI = 'jons_ai';
    const AI_PROVIDER_OPENAI = 'openai';
    const AI_PROVIDER_ANTHROPIC = 'anthropic';
    const AI_PROVIDER_GOOGLE = 'google';
    const AI_PROVIDER_META = 'meta';

    // Newsletter Campaign Statuses
    const NEWSLETTER_STATUS_DRAFT = 'draft';
    const NEWSLETTER_STATUS_SENT = 'sent';

    // Common Fields/Keys
    const FIELD_ACTIVE = 'active';

    // Settings Categories
    const SETTINGS_CATEGORY_ECOMMERCE = 'ecommerce';
    const SETTINGS_CATEGORY_BUSINESS_INFO = 'business_info';
    const SETTINGS_CATEGORY_BRANDING = 'branding';
}
