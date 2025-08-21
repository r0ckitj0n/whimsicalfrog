<?php
/**
 * Secret Store with encryption-at-rest.
 * - Stores secrets in DB table `secrets` (auto-creates if missing)
 * - Encrypts values with libsodium (preferred) or OpenSSL using a filesystem key file
 */

require_once __DIR__ . '/database.php';

function secret_db() {
    // Database::getInstance() returns a PDO
    return Database::getInstance();
}

function secret_table_ensure($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS secrets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        `key` VARCHAR(191) NOT NULL UNIQUE,
        value_enc LONGBLOB NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function secret_key_path() {
    // Default key path; configurable later if needed
    return __DIR__ . '/../config/secret.key';
}

function secret_load_key() {
    $path = secret_key_path();
    if (!is_file($path)) {
        // Generate a new key securely
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
        $key = random_bytes(32);
        file_put_contents($path, $key);
        @chmod($path, 0600);
        return $key;
    }
    return file_get_contents($path);
}

function secret_encrypt($plaintext) {
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

function secret_decrypt($encoded) {
    $key = secret_load_key();
    $raw = base64_decode($encoded, true);
    if ($raw === false) { return null; }
    if (function_exists('sodium_crypto_secretbox_open')) {
        $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($raw) < $nonceLen) { return null; }
        $nonce = substr($raw, 0, $nonceLen);
        $cipher = substr($raw, $nonceLen);
        $plain = @sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return $plain === false ? null : $plain;
    }
    // OpenSSL fallback
    if (strlen($raw) < 28) { return null; } // 12 IV + 16 tag
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

function secret_get($key) {
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $stmt = $pdo->prepare('SELECT value_enc FROM secrets WHERE `key` = ?');
        $stmt->execute([$key]);
        $enc = $stmt->fetchColumn();
        if ($enc === false) { return null; }
        return secret_decrypt($enc);
    } catch (Exception $e) {
        error_log('secret_get error: ' . $e->getMessage());
        return null;
    }
}

function secret_set($key, $value) {
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $enc = secret_encrypt((string)$value);
        $stmt = $pdo->prepare('INSERT INTO secrets (`key`, value_enc) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_enc = VALUES(value_enc), updated_at = CURRENT_TIMESTAMP');
        return $stmt->execute([$key, $enc]);
    } catch (Exception $e) {
        error_log('secret_set error: ' . $e->getMessage());
        return false;
    }
}

function secret_has($key) {
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $stmt = $pdo->prepare('SELECT 1 FROM secrets WHERE `key` = ?');
        $stmt->execute([$key]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}

function secret_delete($key) {
    try {
        $pdo = secret_db();
        secret_table_ensure($pdo);
        $stmt = $pdo->prepare('DELETE FROM secrets WHERE `key` = ?');
        return $stmt->execute([$key]);
    } catch (Exception $e) {
        return false;
    }
}
