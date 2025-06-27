<?php
/**
 * Centralized File Operations Helper
 * Handles file operations with proper error handling and security
 */

class FileHelper {
    private static $allowedExtensions = [
        'txt', 'json', 'xml', 'csv', 'log', 'md', 'yml', 'yaml',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx',
        'zip', 'tar', 'gz'
    ];

    private static $maxFileSize = 50 * 1024 * 1024; // 50MB default

    /**
     * Read file contents with error handling
     */
    public static function read($filePath, $useIncludePath = false, $context = null, $offset = 0, $length = null) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("File not found: $filePath");
            }

            if (!is_readable($filePath)) {
                throw new Exception("File not readable: $filePath");
            }

            $fileSize = filesize($filePath);
            if ($fileSize > self::$maxFileSize) {
                throw new Exception("File too large: $filePath (max " . self::formatBytes(self::$maxFileSize) . ")");
            }

            if ($length !== null) {
                $content = file_get_contents($filePath, $useIncludePath, $context, $offset, $length);
            } else {
                $content = file_get_contents($filePath, $useIncludePath, $context, $offset);
            }

            if ($content === false) {
                throw new Exception("Failed to read file: $filePath");
            }

            return $content;
        } catch (Exception $e) {
            Logger::error("File read error: " . $e->getMessage(), [
                'file' => $filePath,
                'offset' => $offset,
                'length' => $length
            ]);
            throw $e;
        }
    }

    /**
     * Write content to file with error handling
     */
    public static function write($filePath, $data, $flags = 0, $context = null) {
        try {
            // Create directory if it doesn't exist
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0755, true)) {
                    throw new Exception("Failed to create directory: $directory");
                }
            }

            // Check if directory is writable
            if (!is_writable($directory)) {
                throw new Exception("Directory not writable: $directory");
            }

            // Validate file extension if it's a new file
            if (!file_exists($filePath)) {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (!empty($extension) && !in_array($extension, self::$allowedExtensions)) {
                    throw new Exception("File extension not allowed: $extension");
                }
            }

            $result = file_put_contents($filePath, $data, $flags, $context);

            if ($result === false) {
                throw new Exception("Failed to write file: $filePath");
            }

            return $result;
        } catch (Exception $e) {
            Logger::error("File write error: " . $e->getMessage(), [
                'file' => $filePath,
                'data_length' => strlen($data),
                'flags' => $flags
            ]);
            throw $e;
        }
    }

    /**
     * Append content to file
     */
    public static function append($filePath, $data, $context = null) {
        return self::write($filePath, $data, FILE_APPEND | LOCK_EX, $context);
    }

    /**
     * Read JSON file and decode
     */
    public static function readJson($filePath, $associative = true) {
        $content = self::read($filePath);
        $data = json_decode($content, $associative);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in file $filePath: " . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Write data as JSON to file
     */
    public static function writeJson($filePath, $data, $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) {
        $json = json_encode($data, $flags);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON encoding error: " . json_last_error_msg());
        }
        
        return self::write($filePath, $json);
    }

    /**
     * Read CSV file
     */
    public static function readCsv($filePath, $delimiter = ',', $enclosure = '"', $escape = '\\') {
        if (!file_exists($filePath)) {
            throw new Exception("CSV file not found: $filePath");
        }

        $data = [];
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new Exception("Failed to open CSV file: $filePath");
        }

        try {
            while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                $data[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $data;
    }

    /**
     * Write CSV file
     */
    public static function writeCsv($filePath, $data, $delimiter = ',', $enclosure = '"', $escape = '\\') {
        $handle = fopen($filePath, 'w');
        
        if ($handle === false) {
            throw new Exception("Failed to create CSV file: $filePath");
        }

        try {
            foreach ($data as $row) {
                if (fputcsv($handle, $row, $delimiter, $enclosure, $escape) === false) {
                    throw new Exception("Failed to write CSV row to: $filePath");
                }
            }
        } finally {
            fclose($handle);
        }

        return true;
    }

    /**
     * Copy file with error handling
     */
    public static function copy($source, $destination, $overwrite = false) {
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
     * Move/rename file with error handling
     */
    public static function move($source, $destination, $overwrite = false) {
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
     * Delete file with error handling
     */
    public static function delete($filePath) {
        if (!file_exists($filePath)) {
            return true; // Already deleted
        }

        if (!is_writable(dirname($filePath))) {
            throw new Exception("Directory not writable for deletion: " . dirname($filePath));
        }

        if (!unlink($filePath)) {
            throw new Exception("Failed to delete file: $filePath");
        }

        return true;
    }

    /**
     * Get file information
     */
    public static function getInfo($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $stat = stat($filePath);
        
        return [
            'path' => $filePath,
            'name' => basename($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'size' => $stat['size'],
            'size_formatted' => self::formatBytes($stat['size']),
            'created' => $stat['ctime'],
            'modified' => $stat['mtime'],
            'accessed' => $stat['atime'],
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
            'is_readable' => is_readable($filePath),
            'is_writable' => is_writable($filePath),
            'mime_type' => mime_content_type($filePath)
        ];
    }

    /**
     * Check if file is safe to process
     */
    public static function isSafe($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($extension, self::$allowedExtensions)) {
            return false;
        }

        // Check file size
        if (file_exists($filePath) && filesize($filePath) > self::$maxFileSize) {
            return false;
        }

        return true;
    }

    /**
     * Format bytes to human readable format
     */
    public static function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Create temporary file
     */
    public static function createTemp($prefix = 'wf_', $suffix = '.tmp') {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, $prefix);
        
        if ($tempFile === false) {
            throw new Exception("Failed to create temporary file");
        }

        // Add suffix if provided
        if ($suffix && $suffix !== '.tmp') {
            $newTempFile = $tempFile . $suffix;
            if (!rename($tempFile, $newTempFile)) {
                unlink($tempFile);
                throw new Exception("Failed to rename temporary file");
            }
            $tempFile = $newTempFile;
        }

        return $tempFile;
    }

    /**
     * Read file in chunks (for large files)
     */
    public static function readChunks($filePath, $chunkSize = 8192, $callback = null) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new Exception("Failed to open file: $filePath");
        }

        $chunks = [];
        
        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false) {
                    throw new Exception("Failed to read chunk from file: $filePath");
                }
                
                if ($callback && is_callable($callback)) {
                    $callback($chunk);
                } else {
                    $chunks[] = $chunk;
                }
            }
        } finally {
            fclose($handle);
        }

        return $callback ? true : implode('', $chunks);
    }

    /**
     * Set allowed extensions
     */
    public static function setAllowedExtensions(array $extensions) {
        self::$allowedExtensions = array_map('strtolower', $extensions);
    }

    /**
     * Set maximum file size
     */
    public static function setMaxFileSize($size) {
        self::$maxFileSize = $size;
    }
}

// Convenience functions
function file_read($filePath, $useIncludePath = false, $context = null, $offset = 0, $length = null) {
    return FileHelper::read($filePath, $useIncludePath, $context, $offset, $length);
}

function file_write($filePath, $data, $flags = 0, $context = null) {
    return FileHelper::write($filePath, $data, $flags, $context);
}

function file_append($filePath, $data, $context = null) {
    return FileHelper::append($filePath, $data, $context);
}

function file_read_json($filePath, $associative = true) {
    return FileHelper::readJson($filePath, $associative);
}

function file_write_json($filePath, $data, $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) {
    return FileHelper::writeJson($filePath, $data, $flags);
}

function file_safe_delete($filePath) {
    return FileHelper::delete($filePath);
}

function file_get_info($filePath) {
    return FileHelper::getInfo($filePath);
} 