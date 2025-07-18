<?php

/**
 * WhimsicalFrog File Operation Functions
 * Centralized PHP functions to eliminate duplication
 * Generated: 2025-07-01 23:43:57
 */

// Include file helper dependencies
require_once __DIR__ . '/file_helper.php';

/**
 * Upload file via HTTP
 * @param string $url
 * @param string $filePath
 * @param string $fieldName
 * @param array $additionalData
 * @return mixed
 */
function uploadFile($url, $filePath, $fieldName = 'file', $additionalData = [])
{
    if (!file_exists($filePath)) {
        throw new Exception("File not found: $filePath");
    }

    $data = $additionalData;
    $data[$fieldName] = new CURLFile($filePath);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/**
 * Download file via HTTP
 * @param string $url
 * @param string $savePath
 * @return mixed
 */
function downloadFile($url, $savePath)
{
    $fp = fopen($savePath, 'w+');
    if (!$fp) {
        throw new Exception("Cannot open file for writing: $savePath");
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode !== 200) {
        if (file_exists($savePath)) {
            unlink($savePath);
        }
        throw new Exception("Download failed with HTTP code: $httpCode");
    }

    return $result;
}

/**
 * Move/rename file with error handling
 * @param string $source
 * @param string $destination
 * @param bool $overwrite
 * @return bool
 */
function moveFile($source, $destination, $overwrite = false)
{
    if (!file_exists($source)) {
        throw new Exception("Source file not found: $source");
    }

    if (file_exists($destination) && !$overwrite) {
        throw new Exception("Destination file exists: $destination");
    }

    // Create destination directory if needed
    $directory = dirname($destination);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception("Failed to create destination directory: $directory");
        }
    }

    if (!rename($source, $destination)) {
        throw new Exception("Failed to move file from $source to $destination");
    }

    return true;
}

/**
 * Copy file with error handling
 * @param string $source
 * @param string $destination
 * @param bool $overwrite
 * @return bool
 */
function copyFile($source, $destination, $overwrite = false)
{
    if (!file_exists($source)) {
        throw new Exception("Source file not found: $source");
    }

    if (file_exists($destination) && !$overwrite) {
        throw new Exception("Destination file exists: $destination");
    }

    // Create destination directory if needed
    $directory = dirname($destination);
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0755, true)) {
            throw new Exception("Failed to create destination directory: $directory");
        }
    }

    if (!copy($source, $destination)) {
        throw new Exception("Failed to copy file from $source to $destination");
    }

    return true;
}

/**
 * Create a temporary file
 * @param array $options
 * @return string
 */
function createTempFile($options = [])
{
    $prefix = $options['prefix'] ?? 'temp_';
    $suffix = $options['suffix'] ?? '.tmp';
    $dir = $options['dir'] ?? sys_get_temp_dir();

    return tempnam($dir, $prefix) . $suffix;
}

/**
 * Remove file safely
 * @param string $filePath
 * @return bool
 */
function removeFile($filePath)
{
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return true;
}

/**
 * Get file size
 * @param string $filePath
 * @return int
 */
function getFileSize($filePath)
{
    if (file_exists($filePath)) {
        return filesize($filePath);
    }
    return 0;
}
