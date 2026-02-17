<?php
// includes/database/helpers/DatabaseConfigHelper.php

class DatabaseConfigHelper
{
    /**
     * Get current database configuration
     */
    public static function getConfig()
    {
        global $host, $db, $user, $pass;

        return [
            'success' => true,
            'config' => [
                'host' => $host,
                'database' => $db,
                'username' => $user,
                'password_masked' => str_repeat('*', strlen($pass)),
                'environment' => $GLOBALS['isLocalhost'] ? 'local' : 'production',
                'dsn' => $GLOBALS['dsn']
            ]
        ];
    }

    /**
     * Test connection with provided credentials
     */
    public static function testConnection($input)
    {
        $testHost = $input['host'] ?? '';
        $testDb = $input['database'] ?? '';
        $testUser = $input['username'] ?? '';
        $testPass = $input['password'] ?? '';
        $testSsl = $input['ssl_enabled'] ?? false;
        $testSslCert = $input['ssl_cert'] ?? '';

        if (empty($testHost) || empty($testDb) || empty($testUser)) {
            throw new Exception('Host, database, and username are required');
        }

        $testOptions = [
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        if ($testSsl && !empty($testSslCert)) {
            $testOptions[PDO::MYSQL_ATTR_SSL_CA] = $testSslCert;
            $testOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $dsn = "mysql:host=$testHost;dbname=$testDb;charset=utf8mb4";
        $testPdo = new PDO($dsn, $testUser, $testPass, $testOptions);

        $stmt = $testPdo->query("SELECT VERSION() as version, DATABASE() as current_db");
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $testPdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = " . $testPdo->quote($testDb));
        $tableInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'message' => 'Connection successful',
            'info' => [
                'mysql_version' => $info['version'],
                'current_database' => $info['current_db'],
                'table_count' => $tableInfo['table_count'],
                'ssl_enabled' => $testSsl,
                'connection_time' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Update configuration file
     */
    public static function updateConfig($input)
    {
        $newHost = $input['host'] ?? '';
        $newDb = $input['database'] ?? '';
        $newUser = $input['username'] ?? '';
        $newPass = $input['password'] ?? '';
        $environment = $input['environment'] ?? 'auto';
        $sslEnabled = $input['ssl_enabled'] ?? false;
        $sslCert = $input['ssl_cert'] ?? '';

        if (empty($newHost) || empty($newDb) || empty($newUser)) {
            throw new Exception('Host, database, and username are required');
        }

        $configPath = dirname(__DIR__, 2) . '/api/config.php';
        if (!file_exists($configPath)) {
            throw new Exception('Config file not found at ' . $configPath);
        }

        $configContent = file_get_contents($configPath);

        if ($environment === 'local' || $environment === 'both') {
            $configContent = preg_replace("/(\\\$host = ')([^']*)(';.*?\/\/ Local)/", "\$1$newHost\$3", $configContent);
            $configContent = preg_replace("/(\\\$db   = ')([^']*)(';)/", "\$1$newDb\$3", $configContent);
            $configContent = preg_replace("/(\\\$user = ')([^']*)(';)/", "\$1$newUser\$3", $configContent);
            if (!empty($newPass)) {
                $configContent = preg_replace("/(\\\$pass = ')([^']*)(';)/", "\$1$newPass\$3", $configContent);
            }
        }

        if ($environment === 'production' || $environment === 'both') {
            $configContent = preg_replace("/(\\\$host = ')([^']*)(';.*?\/\/ Real IONOS)/", "\$1$newHost\$3", $configContent);
            $configContent = preg_replace("/(\\\$db   = ')([^']*)(';.*?\/\/ Real IONOS)/", "\$1$newDb\$3", $configContent);
            $configContent = preg_replace("/(\\\$user = ')([^']*)(';.*?\/\/ Real IONOS)/", "\$1$newUser\$3", $configContent);
            if (!empty($newPass)) {
                $configContent = preg_replace("/(\\\$pass = ')([^']*)(';.*?\/\/ IONOS)/", "\$1$newPass\$3", $configContent);
            }
        }

        if ($sslEnabled && !empty($sslCert)) {
            $sslConfig = "\n// SSL Configuration\n";
            $sslConfig .= "if (\$sslEnabled) {\n";
            $sslConfig .= "    \$options[PDO::MYSQL_ATTR_SSL_CA] = '$sslCert';\n";
            $sslConfig .= "    \$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;\n";
            $sslConfig .= "}\n";

            $configContent = str_replace(
                '$dsn = "mysql:host=$host;dbname=$db;charset=$charset";',
                '$sslEnabled = ' . ($sslEnabled ? 'true' : 'false') . ";\n$sslConfig\n" . '$dsn = "mysql:host=$host;dbname=$db;charset=$charset";',
                $configContent
            );
        }

        $backupPath = dirname(__DIR__, 2) . '/api/config_backup_' . date('Y-m-d_H-i-s') . '.php';
        copy($configPath, $backupPath);

        if (file_put_contents($configPath, $configContent) !== false) {
            return [
                'success' => true,
                'message' => 'Database configuration updated successfully',
                'backup_created' => basename($backupPath)
            ];
        } else {
            throw new Exception('Failed to write config file');
        }
    }
}
