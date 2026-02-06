<?php
/**
 * includes/traits/BusinessInfoTrait.php
 * Trait for business information convenience methods
 */

trait BusinessInfoTrait {
    public static function getBusinessName() {
        return (string) self::get('business_name', '');
    }

    public static function getBusinessDomain() {
        return (string) self::get('business_domain', '');
    }

    public static function getBusinessEmail() {
        return (string) self::get('business_email', '');
    }

    public static function getBusinessAddressLine1() {
        return trim((string) self::get('business_address', ''));
    }

    public static function getBusinessAddressLine2() {
        return trim((string) self::get('business_address2', ''));
    }

    public static function getBusinessCity() {
        return trim((string) self::get('business_city', ''));
    }

    public static function getBusinessState() {
        return trim((string) self::get('business_state', ''));
    }

    public static function getBusinessPostal() {
        return trim((string) self::get('business_postal', ''));
    }

    public static function getBusinessAddressBlock() {
        $l1 = self::getBusinessAddressLine1();
        $l2 = self::getBusinessAddressLine2();
        $city = self::getBusinessCity();
        $state = self::getBusinessState();
        $zip = self::getBusinessPostal();

        $parts = [];
        if ($l1 !== '') $parts[] = $l1;
        if ($l2 !== '') $parts[] = $l2;
        $cityLine = trim($city . ($city !== '' && $state !== '' ? ', ' : '')) . $state;
        $cityZip = trim($cityLine . ($zip !== '' ? ' ' . $zip : ''));
        if ($cityZip !== '') $parts[] = $cityZip;

        if (empty($parts)) return trim((string) self::get('business_address', ''));
        return implode("\n", $parts);
    }

    public static function getAdminEmail() {
        return (string) self::get('admin_email', '');
    }
}
