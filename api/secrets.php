<?php

// Secure Secrets API for Admin Settings
// Actions: save_batch, rotate_keys, export
// - save_batch: accepts JSON or key=value lines; empty value deletes key
// - rotate_keys: re-encrypt all secrets with a fresh key file
// - export: returns list of keys with has_value indicator (no plaintext)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json');

try {
    AuthHelper::requireAdmin();
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function parse_payload($raw)
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    // Try JSON first
    $data = json_decode($raw, true);
    if (is_array($data)) {
        return $data;
    }
    // Fallback: key=value lines
    $lines = preg_split('/\r?\n/', $raw);
    $map = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '//') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $map[trim($k)] = trim($v);
        }
    }
    return $map;
}

try {
    switch ($action) {
        case 'save_batch': {
            $csrf = $_POST['csrf'] ?? ($_GET['csrf'] ?? '');
            if (!csrf_validate('admin_secrets', $csrf)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                break;
            }
            $raw = $_POST['payload'] ?? file_get_contents('php://input');
            // If input is JSON body already
            $body = json_decode($raw, true);
            if (is_array($body) && isset($body['payload'])) {
                $map = parse_payload(is_string($body['payload']) ? $body['payload'] : json_encode($body['payload']));
            } elseif (is_array($body)) {
                $map = $body;
            } else {
                $map = parse_payload($raw);
            }
            if (!is_array($map)) {
                $map = [];
            }

            $saved = 0;
            $deleted = 0;
            foreach ($map as $k => $v) {
                $key = trim((string)$k);
                if ($key === '') {
                    continue;
                }
                $val = (string)$v;
                if ($val === '') {
                    if (secret_has($key)) {
                        if (secret_delete($key)) {
                            $deleted++;
                        }
                    }
                    continue;
                }
                if (secret_set($key, $val)) {
                    $saved++;
                }
            }
            echo json_encode(['success' => true, 'saved' => $saved, 'deleted' => $deleted]);
            break; }

        case 'rotate_keys': {
            $csrf = $_POST['csrf'] ?? ($_GET['csrf'] ?? '');
            if (!csrf_validate('admin_secrets', $csrf)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
                break;
            }
            // Gather all current secrets and decrypt with old key
            $pdo = Database::getInstance();
            secret_table_ensure($pdo);
            $rows = Database::queryAll('SELECT `key`, value_enc FROM secrets');

            // Load old key bytes
            $oldKey = @file_get_contents(secret_key_path());
            if ($oldKey === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Secret key not found']);
                break;
            }

            // Helper: decrypt with provided key (libsodium preferred)
            $dec = function ($encoded) use ($oldKey) {
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
                    $plain = @sodium_crypto_secretbox_open($cipher, $nonce, $oldKey);
                    return $plain === false ? null : $plain;
                }
                if (strlen($raw) < 28) {
                    return null;
                }
                $iv = substr($raw, 0, 12);
                $tag = substr($raw, 12, 16);
                $cipher = substr($raw, 28);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $oldKey, OPENSSL_RAW_DATA, $iv, $tag);
                return $plain === false ? null : $plain;
            };

            // Generate new key
            $newKey = random_bytes(32);

            // Helper: encrypt with provided key
            $enc = function ($plaintext) use ($newKey) {
                if (function_exists('sodium_crypto_secretbox')) {
                    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                    $cipher = sodium_crypto_secretbox($plaintext, $nonce, $newKey);
                    return base64_encode($nonce . $cipher);
                }
                $iv = random_bytes(12);
                $tag = '';
                $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $newKey, OPENSSL_RAW_DATA, $iv, $tag);
                return base64_encode($iv . $tag . $cipher);
            };

            $reenc = 0;
            $failed = 0;
            foreach ($rows as $r) {
                $k = $r['key'];
                $v = $r['value_enc'];
                $plain = $dec($v);
                if ($plain === null) {
                    $failed++;
                    continue;
                }
                $newEnc = $enc($plain);
                Database::execute('UPDATE secrets SET value_enc = ?, updated_at = CURRENT_TIMESTAMP WHERE `key` = ?', [$newEnc, $k]);
                $reenc++;
            }

            // Atomically replace key file
            $keyPath = secret_key_path();
            $dir = dirname($keyPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            $tmp = $keyPath . '.new';
            file_put_contents($tmp, $newKey);
            @chmod($tmp, 0600);
            @rename($tmp, $keyPath);

            echo json_encode(['success' => true, 're_encrypted' => $reenc, 'failed' => $failed]);
            break; }

        case 'export': {
            // List keys only; do not return plaintext values
            $pdo = Database::getInstance();
            secret_table_ensure($pdo);
            $rows = Database::queryAll('SELECT `key` FROM secrets ORDER BY `key` ASC');
            $keys = array_map(fn ($r) => $r['key'], $rows ?: []);
            echo json_encode(['success' => true, 'keys' => $keys]);
            break; }

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
