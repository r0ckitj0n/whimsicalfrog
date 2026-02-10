<?php

class AddressValidationHelper
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, string|int>
     */
    public static function normalize(array $input): array
    {
        return [
            'address_name' => self::cleanString($input['address_name'] ?? 'Primary', 100),
            'address_line_1' => self::cleanString($input['address_line_1'] ?? '', 255),
            'address_line_2' => self::cleanString($input['address_line_2'] ?? '', 255),
            'city' => self::cleanString($input['city'] ?? '', 100),
            'state' => self::cleanString($input['state'] ?? '', 50),
            'zip_code' => self::cleanString($input['zip_code'] ?? '', 20),
            'is_default' => self::toBoolInt($input['is_default'] ?? 0),
        ];
    }

    /**
     * @param array<string, string|int> $address
     */
    public static function assertRequired(array $address): void
    {
        foreach (['address_name', 'address_line_1', 'city', 'state', 'zip_code'] as $field) {
            if (!isset($address[$field]) || trim((string) $address[$field]) === '') {
                throw new InvalidArgumentException("{$field} is required");
            }
        }
    }

    public static function assertOwnerType(string $ownerType): void
    {
        $allowed = ['customer', 'vendor', 'admin', 'business'];
        if (!in_array($ownerType, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported owner_type: {$ownerType}");
        }
    }

    public static function assertOwnerExists(string $ownerType, string $ownerId): void
    {
        self::assertOwnerType($ownerType);
        if ($ownerId === '') {
            throw new InvalidArgumentException('owner_id is required');
        }

        if ($ownerType === 'customer' || $ownerType === 'admin') {
            $row = Database::queryOne('SELECT id FROM users WHERE id = ? LIMIT 1', [$ownerId]);
            if (!$row) {
                throw new InvalidArgumentException("Owner not found for {$ownerType}: {$ownerId}");
            }
            return;
        }
    }

    public static function canMutateCustomerOwner(string $targetUserId): bool
    {
        if ($targetUserId === '') {
            return false;
        }
        $currentUser = getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        $currentUserId = (string) ($currentUser['user_id'] ?? ($currentUser['id'] ?? ''));
        return isAdmin() || $currentUserId === $targetUserId;
    }

    private static function cleanString($value, int $maxLen): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $clean = trim((string) $value);
        if ($maxLen > 0) {
            $clean = substr($clean, 0, $maxLen);
        }
        return $clean;
    }

    private static function toBoolInt($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_numeric($value)) {
            return ((int) $value) > 0 ? 1 : 0;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            return in_array($v, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
        }
        return 0;
    }
}
