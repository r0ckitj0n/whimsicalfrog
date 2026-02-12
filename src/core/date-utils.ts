/**
 * Date Utilities with Timezone Support
 * Automatically respects the business timezone for all formatting.
 */

const browserTz = (() => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/New_York';
    } catch {
        return 'America/New_York';
    }
})();

let businessTimezone = browserTz; // Default to viewer's local timezone
let businessLocale = 'en-US';
let businessCurrency = 'USD';
let businessDstEnabled = true;

function parseMySqlDateAsUtc(input: string) {
    const trimmed = input.trim();
    if (!trimmed) return new Date(NaN);
    if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(trimmed)) {
        return new Date(trimmed.replace(' ', 'T') + 'Z');
    }
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(trimmed)) {
        return new Date(trimmed + 'Z');
    }
    return new Date(trimmed);
}

function parseGmtOffsetToMinutes(offsetLabel: string): number | null {
    const match = offsetLabel.match(/GMT([+-])(\d{1,2})(?::(\d{2}))?/i);
    if (!match) return null;
    const sign = match[1] === '-' ? -1 : 1;
    const hours = parseInt(match[2], 10);
    const minutes = parseInt(match[3] || '0', 10);
    return sign * ((hours * 60) + minutes);
}

function getTimezoneOffsetMinutes(date: Date, timezone: string): number | null {
    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: timezone,
            timeZoneName: 'shortOffset'
        }).formatToParts(date);
        const tzName = parts.find(p => p.type === 'timeZoneName')?.value || '';
        return parseGmtOffsetToMinutes(tzName);
    } catch {
        return null;
    }
}

function getStandardTimezoneOffsetMinutes(timezone: string): number | null {
    const now = new Date();
    const jan = new Date(Date.UTC(now.getUTCFullYear(), 0, 15, 12, 0, 0));
    return getTimezoneOffsetMinutes(jan, timezone);
}

function toBusinessDate(date: string | number | Date) {
    const parsed = date instanceof Date
        ? new Date(date.getTime())
        : (typeof date === 'string' ? parseMySqlDateAsUtc(date) : new Date(date));
    if (isNaN(parsed.getTime())) return parsed;

    if (!businessDstEnabled) {
        const standardOffsetMinutes = getStandardTimezoneOffsetMinutes(businessTimezone);
        if (standardOffsetMinutes !== null) {
            return new Date(parsed.getTime() + (standardOffsetMinutes * 60 * 1000));
        }
    }

    return parsed;
}

/**
 * Configure the global business timezone used by all formatting functions.
 * Call this once the business info is loaded from the API.
 */
export function setBusinessTimezone(timezone: string) {
    if (timezone) {
        businessTimezone = timezone;
    }
}

/**
 * Get the current business timezone
 */
export function getBusinessTimezone() {
    return businessTimezone;
}

export function setBusinessFormatting(config: {
    timezone?: string;
    locale?: string;
    currency?: string;
    dstEnabled?: boolean;
}) {
    if (config.timezone) businessTimezone = config.timezone;
    if (config.locale) businessLocale = config.locale;
    if (config.currency) businessCurrency = config.currency;
    if (typeof config.dstEnabled === 'boolean') businessDstEnabled = config.dstEnabled;
}

export function getBusinessLocale() {
    return businessLocale;
}

export function getBusinessCurrency() {
    return businessCurrency;
}

/**
 * Formats a date string, number, or Date object into a localized date string.
 * Uses the business timezone if available.
 */
export function formatDate(date: string | number | Date, options: Intl.DateTimeFormatOptions = {}) {
    try {
        const d = toBusinessDate(date);
        if (isNaN(d.getTime())) return 'N/A';

        const defaultOptions: Intl.DateTimeFormatOptions = {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            timeZone: businessDstEnabled ? businessTimezone : 'UTC',
            ...options
        };

        return new Intl.DateTimeFormat(businessLocale, defaultOptions).format(d);
    } catch (e) {
        return 'N/A';
    }
}

/**
 * Formats a date string, number, or Date object into a localized time string.
 */
export function formatTime(date: string | number | Date, options: Intl.DateTimeFormatOptions = {}) {
    try {
        const d = toBusinessDate(date);
        if (isNaN(d.getTime())) return '';

        const defaultOptions: Intl.DateTimeFormatOptions = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: businessDstEnabled ? businessTimezone : 'UTC',
            ...options
        };

        return new Intl.DateTimeFormat(businessLocale, defaultOptions).format(d);
    } catch (e) {
        return '';
    }
}

/**
 * Formats a date string, number, or Date object into localized { date, time } strings.
 */
export function formatDateTime(date: string | number | Date) {
    return {
        date: formatDate(date),
        time: formatTime(date)
    };
}

export function formatCurrency(amount: number) {
    try {
        return new Intl.NumberFormat(businessLocale, {
            style: 'currency',
            currency: businessCurrency
        }).format(amount);
    } catch {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(amount);
    }
}
