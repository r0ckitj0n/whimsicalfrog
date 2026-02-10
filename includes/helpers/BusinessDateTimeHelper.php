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
}
