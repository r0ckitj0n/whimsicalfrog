/**
 * Date Utilities with Timezone Support
 * Automatically respects the business timezone for all formatting.
 */

let businessTimezone = 'America/New_York'; // Default fallback

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

/**
 * Formats a date string, number, or Date object into a localized date string.
 * Uses the business timezone if available.
 */
export function formatDate(date: string | number | Date, options: Intl.DateTimeFormatOptions = {}) {
    try {
        const d = new Date(date);
        if (isNaN(d.getTime())) return 'N/A';

        const defaultOptions: Intl.DateTimeFormatOptions = {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            timeZone: businessTimezone,
            ...options
        };

        return new Intl.DateTimeFormat('en-US', defaultOptions).format(d);
    } catch (e) {
        return 'N/A';
    }
}

/**
 * Formats a date string, number, or Date object into a localized time string.
 */
export function formatTime(date: string | number | Date, options: Intl.DateTimeFormatOptions = {}) {
    try {
        const d = new Date(date);
        if (isNaN(d.getTime())) return '';

        const defaultOptions: Intl.DateTimeFormatOptions = {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
            timeZone: businessTimezone,
            ...options
        };

        return new Intl.DateTimeFormat('en-US', defaultOptions).format(d);
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
