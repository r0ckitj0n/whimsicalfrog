<?php
require_once __DIR__ . '/../api/config.php';

class SecretStore {
  private static function getKey(): string {
    $keyFile = __DIR__ . '/../config/secret.key';
    if (!is_file($keyFile)) {
      if (!is_dir(dirname($keyFile))) { mkdir(dirname($keyFile), 0700, true); }
      $bytes = random_bytes(32);
      file_put_contents($keyFile, $bytes);
      chmod($keyFile, 0600);
    }
    $key = file_get_contents($keyFile);
    return hash('sha256', $key, true); // 32-byte key
  }

  public static function set(string $name, string $value): bool {
    $pdo = Database::getInstance();
    $pdo->exec("CREATE TABLE IF NOT EXISTS secrets (name VARCHAR(191) PRIMARY KEY, iv VARBINARY(16) NOT NULL, value LONGBLOB NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($value, 'aes-256-cbc', self::getKey(), OPENSSL_RAW_DATA, $iv);
    $stmt = $pdo->prepare('REPLACE INTO secrets (name, iv, value) VALUES (?, ?, ?)');
    return $stmt->execute([$name, $iv, $cipher]);
  }

  public static function get(string $name): ?string {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare('SELECT iv, value FROM secrets WHERE name = ?');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if (!$row) { return null; }
    $plain = openssl_decrypt($row['value'], 'aes-256-cbc', self::getKey(), OPENSSL_RAW_DATA, $row['iv']);
    return $plain === false ? null : $plain;
  }
}
