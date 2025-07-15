<?php
// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load .env file
loadEnv(__DIR__ . '/.env');

// Set the actual environment variables
putenv('SPREADSHEET_ID=1GYsw7aoh_C0EZU9wLFLPQsl3wdD7mxxVpJ029B5L8dk');
putenv('GOOGLE_CLIENT_ID=761395320215-fvv2asammgot6j4qff8ahmhu8n5j51um.apps.googleusercontent.com');

$_ENV['SPREADSHEET_ID'] = '1GYsw7aoh_C0EZU9wLFLPQsl3wdD7mxxVpJ029B5L8dk';
$_ENV['GOOGLE_CLIENT_ID'] = '761395320215-fvv2asammgot6j4qff8ahmhu8n5j51um.apps.googleusercontent.com';

$_SERVER['SPREADSHEET_ID'] = '1GYsw7aoh_C0EZU9wLFLPQsl3wdD7mxxVpJ029B5L8dk';
$_SERVER['GOOGLE_CLIENT_ID'] = '761395320215-fvv2asammgot6j4qff8ahmhu8n5j51um.apps.googleusercontent.com';
?> 