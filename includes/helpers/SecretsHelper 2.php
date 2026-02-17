<?php
/**
 * includes/helpers/SecretsHelper.php
 * Helper class for managing secure secrets
 */

class SecretsHelper {
    /**
     * Parse input payload (JSON or key=value)
     */
    public static function parsePayload($raw): array {
        $raw = trim((string)$raw);
        if ($raw === '') return [];
        
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
        
        $lines = preg_split('/\r?\n/', $raw);
        $map = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '//') === 0) continue;
            if (strpos($line, '=') !== false) {
                [$k, $v] = explode('=', $line, 2);
                $map[trim($k)] = trim($v);
            }
        }
        return $map;
    }

    /**
     * Rotate encryption keys for all secrets
     */
    public static function rotateKeys(): array {
        $rows = Database::queryAll('SELECT `key`, value_enc FROM secrets');
        $oldKey = @file_get_contents(secret_key_path());
        if ($oldKey === false) throw new Exception('Secret key not found');

        $newKey = random_bytes(32);
        $reenc = 0;
        $failed = 0;

        foreach ($rows as $r) {
            $plain = self::decrypt($r['value_enc'], $oldKey);
            if ($plain === null) { $failed++; continue; }
            $newEnc = self::encrypt($plain, $newKey);
            Database::execute('UPDATE secrets SET value_enc = ?, updated_at = CURRENT_TIMESTAMP WHERE `key` = ?', [$newEnc, $r['key']]);
            $reenc++;
        }

        $keyPath = secret_key_path();
        $tmp = $keyPath . '.new';
        file_put_contents($tmp, $newKey);
        @chmod($tmp, 0600);
        @rename($tmp, $keyPath);

        return ['re_encrypted' => $reenc, 'failed' => $failed];
    }

    private static function decrypt($encoded, $key) {
        $raw = base64_decode($encoded, true);
        if ($raw === false) return null;
        if (function_exists('sodium_crypto_secretbox_open')) {
            $nonceLen = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            if (strlen($raw) < $nonceLen) return null;
            $nonce = substr($raw, 0, $nonceLen);
            $cipher = substr($raw, $nonceLen);
            $plain = @sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return $plain === false ? null : $plain;
        }
        if (strlen($raw) < 28) return null;
        $iv = substr($raw, 0, 12); $tag = substr($raw, 12, 16); $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    private static function encrypt($plaintext, $key) {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return base64_encode($nonce . $cipher);
        }
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }
}
