<?php

/**
 * Secret Store with encryption-at-rest.
 * - Stores secrets in DB table `secrets` (auto-creates if missing)
 * - Encrypts values with libsodium (preferred) or OpenSSL using a filesystem key file
 *
 * CRITICAL: config/secret.key must never be silently regenerated when secrets already
 * exist. Doing so permanently orphans ciphertext in the secrets table.
 */

require_once __DIR__ . '/../api/config.php';

function secret_db()
{
    // Database::getInstance() returns a PDO
    return Database::getInstance();
}

function secret_table_ensure($pdo)
{
    Database::execute("CREATE TABLE IF NOT EXISTS secrets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(191) NOT NULL UNIQUE,
        value_enc LONGBLOB NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function secret_key_path()
{
    // Default key path; configurable later if needed
    return __DIR__ . '/../config/secret.key';
}

/**
 * Count rows in secrets table (0 if table missing / unavailable).
 */
function secret_row_count()
{
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $row = Database::queryOne('SELECT COUNT(*) AS c FROM secrets');
        return (int) ($row['c'] ?? 0);
    } catch (Exception $e) {
        error_log('secret_row_count error: ' . $e->getMessage());
        return 0;
    }
}

function secret_load_key()
{
    $path = secret_key_path();
    if (!is_file($path)) {
        // Never mint a new key when encrypted secrets already exist — that makes
        // every existing value permanently unreadable.
        if (secret_row_count() > 0) {
            $message = 'Secret encryption key is missing (config/secret.key) but the secrets table has rows. Restore the original key file; do not generate a new one.';
            error_log('CRITICAL: ' . $message);
            throw new RuntimeException($message);
        }

        // Fresh install only: generate a new key securely
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $key = random_bytes(32);
        if (file_put_contents($path, $key) === false) {
            throw new RuntimeException('Failed to write new secret encryption key');
        }
        @chmod($path, 0600);
        return $key;
    }

    $key = file_get_contents($path);
    if ($key === false || strlen($key) !== 32) {
        $message = 'Secret encryption key file is unreadable or invalid (expected 32 bytes)';
        error_log('CRITICAL: ' . $message);
        throw new RuntimeException($message);
    }
    return $key;
}

function secret_encrypt($plaintext)
{
    $key = secret_load_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        // Store nonce + cipher
        return base64_encode($nonce . $cipher);
    }
    // OpenSSL fallback (AES-256-GCM)
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $cipher);
}

function secret_decrypt($encoded)
{
    $key = secret_load_key();
    $raw = base64_decode($encoded, true);
    if ($raw === false) {
        return null;
    }
    if (function_exists('sodium_crypto_secretbox_open')) {
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($raw) < $nonceLen) {
            return null;
        }
        $nonce = substr($raw, 0, $nonceLen);
        $cipher = substr($raw, $nonceLen);
        $plain = @sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return $plain === false ? null : $plain;
    }
    // OpenSSL fallback
    if (strlen($raw) < 28) {
        return null;
    } // 12 IV + 16 tag
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function secret_get($key)
{
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $row = Database::queryOne('SELECT value_enc FROM secrets WHERE `key` = ?', [$key]);
        $enc = $row ? $row['value_enc'] : false;
        if ($enc === false) {
            return null;
        }
        return secret_decrypt($enc);
    } catch (Exception $e) {
        error_log('secret_get error: ' . $e->getMessage());
        return null;
    }
}

function secret_set($key, $value)
{
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $enc = secret_encrypt((string) $value);
        $affected = Database::execute('INSERT INTO secrets (`key`, value_enc) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_enc = VALUES(value_enc), updated_at = CURRENT_TIMESTAMP', [$key, $enc]);
        return $affected !== false;
    } catch (Exception $e) {
        error_log('secret_set error: ' . $e->getMessage());
        return false;
    }
}

function secret_has($key)
{
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $row = Database::queryOne('SELECT 1 AS c FROM secrets WHERE `key` = ?', [$key]);
        return $row ? true : false;
    } catch (Exception $e) {
        error_log('secret_has error: ' . $e->getMessage());
        return false;
    }
}

/**
 * True when a secret row exists and decrypts to a non-null value.
 */
function secret_is_readable($key)
{
    if (!secret_has($key)) {
        return false;
    }
    return secret_get($key) !== null;
}

/**
 * Health report for admin diagnostics (never returns plaintext values).
 *
 * @return array{key_file_exists:bool,key_file_valid:bool,secret_rows:int,readable:string[],unreadable:string[],missing_key_with_rows:bool}
 */
function secret_health_report()
{
    $path = secret_key_path();
    $keyExists = is_file($path);
    $keyValid = false;
    if ($keyExists) {
        $raw = @file_get_contents($path);
        $keyValid = is_string($raw) && strlen($raw) === 32;
    }

    $readable = [];
    $unreadable = [];
    $rows = 0;
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $all = Database::queryAll('SELECT `key` FROM secrets ORDER BY `key` ASC');
        $rows = count($all);
        foreach ($all as $r) {
            $k = (string) ($r['key'] ?? '');
            if ($k === '') {
                continue;
            }
            if (secret_get($k) !== null) {
                $readable[] = $k;
            } else {
                $unreadable[] = $k;
            }
        }
    } catch (Exception $e) {
        error_log('secret_health_report error: ' . $e->getMessage());
    }

    return [
        'key_file_exists' => $keyExists,
        'key_file_valid' => $keyValid,
        'secret_rows' => $rows,
        'readable' => $readable,
        'unreadable' => $unreadable,
        'missing_key_with_rows' => (!$keyExists && $rows > 0),
    ];
}

function secret_delete($key)
{
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $affected = Database::execute('DELETE FROM secrets WHERE `key` = ?', [$key]);
        return $affected !== false;
    } catch (Exception $e) {
        error_log('secret_delete error: ' . $e->getMessage());
        return false;
    }
}
