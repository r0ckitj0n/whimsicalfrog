<?php

require_once __DIR__ . '/../business_settings_helper.php';

class BusinessDateTimeHelper
{
    public static function nowUtcString(): string
    {
        try {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return gmdate('Y-m-d H:i:s');
        }
    }

    public static function nowString(): string
    {
        $timezone = (string) BusinessSettings::get('business_timezone', 'America/New_York');
        $dstEnabled = BusinessSettings::getBooleanSetting('business_dst_enabled', true);
        return self::formatNowForBusinessTimezone($timezone, $dstEnabled);
    }

    public static function formatNowForBusinessTimezone(string $timezone, bool $dstEnabled): string
    {
        try {
            $tz = new DateTimeZone($timezone);
            $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            if (!$dstEnabled) {
                // Use a fixed standard offset from mid-January (no DST shifts).
                $year = (int) $nowUtc->format('Y');
                $standardPoint = new DateTimeImmutable("{$year}-01-15 12:00:00", new DateTimeZone('UTC'));
                $standardOffsetSeconds = $tz->getOffset($standardPoint);
                $adjusted = $nowUtc->modify(($standardOffsetSeconds >= 0 ? '+' : '') . $standardOffsetSeconds . ' seconds');
                return $adjusted->format('Y-m-d H:i:s');
            }

            return $nowUtc->setTimezone($tz)->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Resolve the configured business timezone (defaults to America/New_York).
     */
    public static function getBusinessTimezone(): DateTimeZone
    {
        $timezone = (string) BusinessSettings::get('business_timezone', 'America/New_York');
        try {
            return new DateTimeZone($timezone !== '' ? $timezone : 'America/New_York');
        } catch (Throwable $e) {
            return new DateTimeZone('America/New_York');
        }
    }

    /**
     * Parse a stored UTC/MySQL datetime into an instant, then project into business time.
     * Naive MySQL values ("Y-m-d H:i:s") are treated as UTC, matching DB storage.
     */
    public static function toBusinessDateTime(?string $utcDateTime): ?DateTimeImmutable
    {
        if ($utcDateTime === null) {
            return null;
        }

        $raw = trim($utcDateTime);
        if ($raw === '') {
            return null;
        }

        try {
            $utcTz = new DateTimeZone('UTC');
            $businessTz = self::getBusinessTimezone();
            $dstEnabled = BusinessSettings::getBooleanSetting('business_dst_enabled', true);

            if (preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}$/', $raw)) {
                $normalized = str_replace('T', ' ', $raw);
                $utc = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $normalized, $utcTz);
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
                $utc = DateTimeImmutable::createFromFormat('Y-m-d', $raw, $utcTz);
            } else {
                $utc = new DateTimeImmutable($raw);
            }

            if (!$utc instanceof DateTimeImmutable) {
                return null;
            }

            $utc = $utc->setTimezone($utcTz);

            if (!$dstEnabled) {
                $year = (int) $utc->format('Y');
                $standardPoint = new DateTimeImmutable("{$year}-01-15 12:00:00", $utcTz);
                $standardOffsetSeconds = $businessTz->getOffset($standardPoint);
                return $utc->modify(($standardOffsetSeconds >= 0 ? '+' : '') . $standardOffsetSeconds . ' seconds');
            }

            return $utc->setTimezone($businessTz);
        } catch (Throwable $e) {
            error_log('[BusinessDateTimeHelper] toBusinessDateTime failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format a UTC datetime for customer-facing display in the business timezone.
     */
    public static function formatForDisplay(?string $utcDateTime, string $format = 'M j, Y'): string
    {
        $dt = self::toBusinessDateTime($utcDateTime);
        if ($dt === null) {
            $fallback = self::toBusinessDateTime(self::nowUtcString());
            return $fallback ? $fallback->format($format) : gmdate($format);
        }
        return $dt->format($format);
    }
}
