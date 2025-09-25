<?php
header('Content-Type: text/plain; charset=utf-8');

$ok = true;

echo "WF Local DB Check\n";

echo "PHP: ".PHP_VERSION."\n";

try {
  require_once __DIR__ . '/../../api/config.php';
  echo "api/config.php: OK\n";
} catch (Throwable $e) {
  echo "api/config.php: ERROR: ".$e->getMessage()."\n";
  $ok = false;
}

try {
  require_once __DIR__ . '/../../includes/database.php';
  echo "includes/database.php: OK\n";
} catch (Throwable $e) {
  echo "includes/database.php: ERROR: ".$e->getMessage()."\n";
  $ok = false;
}

try {
  if (class_exists('Database')) {
    $pdo = Database::getInstance();
    $row = $pdo->query('SELECT 1 as one')->fetch();
    echo "DB connect + SELECT 1: OK (one=".($row['one'] ?? '?').")\n";
    // Try a simple application query if backgrounds table exists
    try {
      $stmt = $pdo->query("SHOW TABLES LIKE 'backgrounds'");
      if ($stmt && $stmt->fetch()) {
        echo "Table backgrounds: PRESENT\n";
        $c = $pdo->query('SELECT COUNT(*) AS c FROM backgrounds')->fetch();
        echo "backgrounds count: ".(int)($c['c'] ?? 0)."\n";
      } else {
        echo "Table backgrounds: NOT FOUND\n";
      }
    } catch (Throwable $e2) {
      echo "backgrounds probe: ERROR: ".$e2->getMessage()."\n";
    }
  } else {
    echo "Database class missing\n";
    $ok = false;
  }
} catch (Throwable $e) {
  echo "DB connect: ERROR: ".$e->getMessage()."\n";
  $ok = false;
}

http_response_code($ok ? 200 : 500);
