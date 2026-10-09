<?php

/**
 * Secret Store with encryption-at-rest.
 * - Stores secrets in DB table `secrets` (auto-creates if missing)
 * - Encrypts values with libsodium (preferred) or OpenSSL using a filesystem key file
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

function secret_ciphertext_exists()
{
    try {
        if (!class_exists('Database')) {
            return false;
        }
        $row = Database::queryOne('SELECT 1 AS c FROM secrets LIMIT 1');
        return (bool) $row;
    } catch (Throwable $e) {
        return false;
    }
}

function secret_load_key()
{
    $path = secret_key_path();
    if (is_file($path)) {
        $key = file_get_contents($path);
        if ($key === false || strlen($key) < 16) {
            throw new Exception('config/secret.key is unreadable. Restore the original key file. Do not generate a new one while encrypted secrets exist.');
        }
        return $key;
    }

    // A missing key file used to mint a brand-new key. That orphans every
    // ciphertext already stored in `secrets` (OpenAI, Google, and the rest).
    if (secret_ciphertext_exists()) {
        throw new Exception('config/secret.key is missing, but encrypted secrets already exist. Restore the original key file. Generating a new key would make every saved API key unreadable.');
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $key = random_bytes(32);
    file_put_contents($path, $key);
    @chmod($path, 0600);
    return $key;
}

function secret_unavailable_message($label, $secretKey)
{
    $stored = false;
    try {
        $stored = secret_has($secretKey);
    } catch (Throwable $e) {
        $stored = false;
    }

    if ($stored) {
        return "The saved {$label} API key is stored but cannot be decrypted. Open Admin → AI Settings, paste the {$label} API key again, and save. Do not generate a new encryption key.";
    }

    return "The {$label} API key is not saved. Add it in Admin → AI Settings.";
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
