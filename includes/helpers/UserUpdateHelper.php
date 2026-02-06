<?php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/user_meta.php';

class UserUpdateHelper
{
    /**
     * Core logic for updating user profile and metadata.
     * Factorized out from api/update_user.php to allow internal calls.
     */
    public static function update($user_id, array $data)
    {
        $pdo = Database::getInstance();

        // Detect which optional columns actually exist on the users table
        $hasFirstName = true;
        $hasLastName = true;
        $hasPhone = true;
        $hasAddr1 = true;
        $hasAddr2 = true;
        $hasZip = true;
        try {
            $cols = Database::queryAll('SHOW COLUMNS FROM users');
            $map = [];
            foreach (is_array($cols) ? $cols : [] as $col) {
                $n = $col['Field'] ?? ($col['field'] ?? ($col['COLUMN_NAME'] ?? ''));
                if ($n)
                    $map[$n] = true;
            }
            $hasFirstName = isset($map['first_name']);
            $hasLastName = isset($map['last_name']);
            $hasPhone = isset($map['phone_number']);
            $hasAddr1 = isset($map['address_line_1']);
            $hasAddr2 = isset($map['address_line_2']);
            $hasZip = isset($map['zip_code']);
        } catch (Exception $e) {
            // If schema detection fails, fall back to optimistic defaults
        }

        // Define allowed fields for update; optional columns are pruned if missing
        // Note: Password is NOT allowed here as it should be handled by specialized logic with hashing.
        $allowedFields = [
            'username' => 'username',
            'email' => 'email',
            'first_name' => $hasFirstName ? 'first_name' : null,
            'last_name' => $hasLastName ? 'last_name' : null,
            'phone_number' => $hasPhone ? 'phone_number' : null,
            'address_line_1' => $hasAddr1 ? 'address_line_1' : null,
            'address_line_2' => $hasAddr2 ? 'address_line_2' : null,
            'city' => 'city',
            'state' => 'state',
            'zip_code' => $hasZip ? 'zip_code' : null,
            'role' => 'role'
        ];

        // Define metadata fields
        $metaFields = [
            'company',
            'job_title',
            'preferred_contact',
            'preferred_language',
            'marketing_opt_in',
            'status',
            'vip',
            'tax_exempt',
            'referral_source',
            'birthdate',
            'tags',
            'admin_notes'
        ];

        // Build the SQL update query dynamically
        $updateFields = [];
        $params = [];
        $metaData = [];

        foreach ($data as $key => $value) {
            // Skip identifier fields
            if ($key === 'user_id' || $key === 'id' || $key === 'order_history') {
                continue;
            }

            // Handle password field with hashing
            if ($key === 'password' && !empty($value)) {
                $updateFields[] = "password = ?";
                $params[] = password_hash($value, PASSWORD_DEFAULT);
                continue;
            }

            // Handle core fields (those defined in $allowedFields and existing in the table)
            if (isset($allowedFields[$key]) && $allowedFields[$key] !== null) {
                $dbField = $allowedFields[$key];
                $updateFields[] = "$dbField = ?";
                $params[] = $value;
            }
            // Handle metadata fields (explicitly defined or anything else not in core)
            else if (in_array($key, $metaFields) || !isset($allowedFields[$key])) {
                $metaData[$key] = $value;
            }
        }

        // Update core table if there are changes
        if (!empty($updateFields)) {
            $params[] = $user_id;
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            Database::execute($sql, $params);
        }

        // Update metadata if there are changes
        if (!empty($metaData)) {
            set_user_meta_many($user_id, $metaData);
        }

        // Refresh session data if the current user is the one being updated
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
            $currentUser = $_SESSION['user'];
            $currentId = $currentUser['user_id'] ?? ($currentUser['id'] ?? null);
            if ($currentId == $user_id) {
                $updatedUser = Database::queryOne("SELECT * FROM users WHERE id = ?", [$user_id]);
                if ($updatedUser) {
                    $_SESSION['user'] = [
                        'user_id' => $updatedUser['id'],
                        'username' => $updatedUser['username'],
                        'email' => $updatedUser['email'],
                        'role' => $updatedUser['role'],
                        'first_name' => $updatedUser['first_name'] ?? null,
                        'last_name' => $updatedUser['last_name'] ?? null,
                        'phone_number' => $updatedUser['phone_number'] ?? null
                    ];
                }
            }
        }

        return true;
    }
}
