<?php


require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'config.php';

// Change working directory to parent directory (project root)
chdir(dirname(__DIR__));

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Security: Define allowed directories (prevent directory traversal)
$allowedDirectories = [
    'api',
    'components', 
    'css',
    'images',
    'includes',
    'js',
    'sections'
];

// Security: Define file extensions that can be edited
$editableExtensions = [
    'php', 'js', 'css', 'html', 'txt', 'json', 'md', 'xml', 'htaccess', 'yml', 'yaml', 'ini', 'conf', 'cfg'
];

// Security: Define file extensions that can be viewed
$viewableExtensions = [
    'php', 'js', 'css', 'html', 'txt', 'json', 'md', 'xml', 'htaccess', 'log', 'sh', 'yml', 'yaml', 'ini', 'conf', 'cfg'
];

function sanitizePath($path) {
    // Remove any directory traversal attempts
    $path = str_replace(['../', '..\\', '../\\'], '', $path);
    $path = ltrim($path, '/\\');
    return $path;
}

function isPathAllowed($path) {
    global $allowedDirectories;
    
    if (empty($path) || $path === '.') {
        return true; // Root directory is allowed
    }
    
    $pathParts = explode('/', $path);
    $firstDir = $pathParts[0];
    
    // If it's a single file in root (no directory separator), allow it
    if (count($pathParts) === 1) {
        return true; // Root level files are allowed
    }
    
    // For subdirectories, check against allowed list
    return in_array($firstDir, $allowedDirectories);
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function isFileEditable($filename) {
    global $editableExtensions;
    $ext = getFileExtension($filename);
    return in_array($ext, $editableExtensions);
}

function isFileViewable($filename) {
    global $viewableExtensions;
    $ext = getFileExtension($filename);
    return in_array($ext, $viewableExtensions);
}


function listDirectory($path = '') {
    $path = sanitizePath($path);
    
    if (!isPathAllowed($path)) {
        return ['success' => false, 'error' => 'Access denied to this directory'];
    }
    
    $fullPath = empty($path) ? '.' : $path;
    

    
    if (!is_dir($fullPath)) {
        return ['success' => false, 'error' => 'Directory not found'];
    }
    
    $items = [];
    $files = scandir($fullPath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $fullPath . '/' . $file;
        $relativePath = empty($path) ? $file : $path . '/' . $file;
        
        $isDirectory = is_dir($filePath);
        
        // For root directory, only show allowed directories and files
        if (empty($path) && $isDirectory) {
            global $allowedDirectories;
            if (!in_array($file, $allowedDirectories)) {
                continue; // Skip non-allowed directories in root
            }
        }
        
        $item = [
            'name' => $file,
            'path' => $relativePath,
            'type' => $isDirectory ? 'directory' : 'file',
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'modified' => filemtime($filePath),
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4)
        ];
        
        if ($item['type'] === 'file') {
            $item['extension'] = getFileExtension($file);
            $item['editable'] = isFileEditable($file);
            $item['viewable'] = isFileViewable($file);
            $item['size_formatted'] = formatFileSize($item['size']);
        }
        
        $items[] = $item;
    }
    
    // Sort: directories first, then files, both alphabetically
    usort($items, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'directory' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });
    
    return [
        'success' => true,
        'path' => $path,
        'items' => $items,
        'parent' => empty($path) ? null : dirname($path)
    ];
}

function readFileContent($path) {
    $path = sanitizePath($path);
    
    if (!isPathAllowed($path)) {
        return ['success' => false, 'error' => 'Access denied to this file'];
    }
    
    if (!file_exists($path)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    if (!is_file($path)) {
        return ['success' => false, 'error' => 'Path is not a file'];
    }
    
    $filename = basename($path);
    if (!isFileViewable($filename)) {
        return ['success' => false, 'error' => 'File type not supported for viewing'];
    }
    
    $content = file_get_contents($path);
    if ($content === false) {
        return ['success' => false, 'error' => 'Failed to read file'];
    }
    
    return [
        'success' => true,
        'content' => $content,
        'filename' => $filename,
        'path' => $path,
        'size' => filesize($path),
        'modified' => filemtime($path),
        'editable' => isFileEditable($filename)
    ];
}

function writeFile($path, $content) {
    $path = sanitizePath($path);
    
    if (!isPathAllowed($path)) {
        return ['success' => false, 'error' => 'Access denied to this location'];
    }
    
    $filename = basename($path);
    if (!isFileEditable($filename)) {
        return ['success' => false, 'error' => 'File type not supported for editing'];
    }
    
    // Create directory if it doesn't exist
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to create directory'];
        }
    }
    
    $result = file_put_contents($path, $content);
    if ($result === false) {
        return ['success' => false, 'error' => 'Failed to write file'];
    }
    
    return [
        'success' => true,
        'message' => 'File saved successfully',
        'bytes_written' => $result
    ];
}

function deleteFile($path) {
    $path = sanitizePath($path);
    
    if (!isPathAllowed($path)) {
        return ['success' => false, 'error' => 'Access denied to this location'];
    }
    
    if (!file_exists($path)) {
        return ['success' => false, 'error' => 'File or directory not found'];
    }
    
    if (is_dir($path)) {
        // Delete directory (must be empty)
        if (rmdir($path)) {
            return ['success' => true, 'message' => 'Directory deleted successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to delete directory (may not be empty)'];
        }
    } else {
        // Delete file
        if (unlink($path)) {
            return ['success' => true, 'message' => 'File deleted successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to delete file'];
        }
    }
}

function createDirectory($path) {
    $path = sanitizePath($path);
    
    if (!isPathAllowed($path)) {
        return ['success' => false, 'error' => 'Access denied to this location'];
    }
    
    if (file_exists($path)) {
        return ['success' => false, 'error' => 'Directory already exists'];
    }
    
    if (mkdir($path, 0755, true)) {
        return ['success' => true, 'message' => 'Directory created successfully'];
    } else {
        return ['success' => false, 'error' => 'Failed to create directory'];
    }
}

// Handle the request
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method . ':' . $action) {
        case 'GET:list':
            $path = $_GET['path'] ?? '';
            $result = listDirectory($path);
            break;
            
        case 'GET:read':
            $path = $_GET['path'] ?? '';
            if (empty($path)) {
                $result = ['success' => false, 'error' => 'Path parameter required'];
            } else {
                $result = readFileContent($path);
            }
            break;
            
        case 'POST:write':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '';
            $content = $input['content'] ?? '';
            
            if (empty($path)) {
                $result = ['success' => false, 'error' => 'Path parameter required'];
            } else {
                $result = writeFile($path, $content);
            }
            break;
            
        case 'DELETE:delete':
            $path = $_GET['path'] ?? '';
            if (empty($path)) {
                $result = ['success' => false, 'error' => 'Path parameter required'];
            } else {
                $result = deleteFile($path);
            }
            break;
            
        case 'POST:mkdir':
            $input = json_decode(file_get_contents('php://input'), true);
            $path = $input['path'] ?? '';
            
            if (empty($path)) {
                $result = ['success' => false, 'error' => 'Path parameter required'];
            } else {
                $result = createDirectory($path);
            }
            break;
            
        default:
            $result = ['success' => false, 'error' => 'Invalid action or method'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 