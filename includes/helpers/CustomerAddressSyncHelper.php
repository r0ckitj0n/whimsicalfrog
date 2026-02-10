<?php

class CustomerAddressSyncHelper
{
    /**
     * @return array<string, string|null>|null
     */
    public static function getPrimaryAddress(string $userId): ?array
    {
        if ($userId === '') {
            return null;
        }

        $row = Database::queryOne(
            'SELECT id, owner_type, owner_id, address_name, address_line_1, address_line_2, city, state, zip_code, is_default
             FROM addresses
             WHERE owner_type = ? AND owner_id = ?
             ORDER BY is_default DESC, id ASC
             LIMIT 1',
            ['customer', $userId]
        );

        if (!$row) {
            return null;
        }

        return [
            'id' => isset($row['id']) ? (string) $row['id'] : null,
            'user_id' => isset($row['owner_id']) ? (string) $row['owner_id'] : null,
            'address_name' => isset($row['address_name']) ? (string) $row['address_name'] : null,
            'address_line_1' => isset($row['address_line_1']) ? (string) $row['address_line_1'] : null,
            'address_line_2' => isset($row['address_line_2']) ? (string) $row['address_line_2'] : null,
            'city' => isset($row['city']) ? (string) $row['city'] : null,
            'state' => isset($row['state']) ? (string) $row['state'] : null,
            'zip_code' => isset($row['zip_code']) ? (string) $row['zip_code'] : null,
            'is_default' => isset($row['is_default']) ? (string) $row['is_default'] : '0',
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, string>
     */
    public static function mergeUserWithPrimaryAddress(string $userId, array $user): array
    {
        $merged = [
            'first_name' => self::norm($user['first_name'] ?? null),
            'last_name' => self::norm($user['last_name'] ?? null),
            'email' => self::norm($user['email'] ?? null),
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'state' => '',
            'zip_code' => '',
        ];

        $primary = self::getPrimaryAddress($userId);
        if (!$primary) {
            return $merged;
        }

        foreach (['address_line_1', 'address_line_2', 'city', 'state', 'zip_code'] as $field) {
            $merged[$field] = self::norm($primary[$field] ?? null);
        }

        return $merged;
    }

    /**
     * Upserts the user's primary customer address from provided address fields.
     *
     * @param array<string, mixed> $address
     */
    public static function upsertPrimaryFromUserFields(string $userId, array $address): void
    {
        if ($userId === '') {
            return;
        }

        $normalized = [
            'address_line_1' => self::norm($address['address_line_1'] ?? null),
            'address_line_2' => self::norm($address['address_line_2'] ?? null),
            'city' => self::norm($address['city'] ?? null),
            'state' => self::norm($address['state'] ?? null),
            'zip_code' => self::norm($address['zip_code'] ?? null),
        ];

        if (!self::isAddressComplete($normalized)) {
            return;
        }

        $target = Database::queryOne(
            'SELECT id, address_name FROM addresses WHERE owner_type = ? AND owner_id = ? AND is_default = 1 ORDER BY id ASC LIMIT 1',
            ['customer', $userId]
        );

        if (!$target) {
            $target = Database::queryOne(
                'SELECT id, address_name FROM addresses WHERE owner_type = ? AND owner_id = ? ORDER BY id ASC LIMIT 1',
                ['customer', $userId]
            );
        }

        if ($target && !empty($target['id'])) {
            Database::execute(
                'UPDATE addresses
                 SET address_name = ?, address_line_1 = ?, address_line_2 = ?, city = ?, state = ?, zip_code = ?, is_default = 1
                 WHERE id = ?',
                [
                    self::norm($target['address_name'] ?? '') !== '' ? (string) $target['address_name'] : 'Primary',
                    $normalized['address_line_1'],
                    $normalized['address_line_2'],
                    $normalized['city'],
                    $normalized['state'],
                    $normalized['zip_code'],
                    $target['id'],
                ]
            );
            Database::execute(
                'UPDATE addresses SET is_default = 0 WHERE owner_type = ? AND owner_id = ? AND id <> ?',
                ['customer', $userId, $target['id']]
            );
            return;
        }

        Database::execute(
            'INSERT INTO addresses (owner_type, owner_id, address_name, address_line_1, address_line_2, city, state, zip_code, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                'customer',
                $userId,
                'Primary',
                $normalized['address_line_1'],
                $normalized['address_line_2'],
                $normalized['city'],
                $normalized['state'],
                $normalized['zip_code'],
            ]
        );
    }

    private static function norm($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        return trim((string) $value);
    }

    /**
     * @param array<string, string> $address
     */
    private static function isAddressComplete(array $address): bool
    {
        return $address['address_line_1'] !== ''
            && $address['city'] !== ''
            && $address['state'] !== ''
            && $address['zip_code'] !== '';
    }
}
