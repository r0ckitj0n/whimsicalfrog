<?php
/**
 * File Manager Logic
 */

function sanitizePath($path)
{
    $path = str_replace(['../', '..\\', '../\\'], '', $path);
    $path = ltrim($path, '/\\');
    return $path;
}

function isPathAllowed($path)
{
    $allowedDirectories = ['api', 'components', 'css', 'images', 'includes', 'js', 'sections'];
    if (empty($path) || $path === '.') return true;
    $pathParts = explode('/', $path);
    if (count($pathParts) === 1) return true;
    return in_array($pathParts[0], $allowedDirectories);
}

function getFileExtension($filename)
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function isFileEditable($filename)
{
    $editableExtensions = ['php', 'js', 'css', 'html', 'txt', 'json', 'md', 'xml', 'htaccess', 'yml', 'yaml', 'ini', 'conf', 'cfg'];
    return in_array(getFileExtension($filename), $editableExtensions);
}

function isFileViewable($filename)
{
    $viewableExtensions = ['php', 'js', 'css', 'html', 'txt', 'json', 'md', 'xml', 'htaccess', 'log', 'sh', 'yml', 'yaml', 'ini', 'conf', 'cfg'];
    return in_array(getFileExtension($filename), $viewableExtensions);
}

function listDirectory($path = '')
{
    $path = sanitizePath($path);
    if (!isPathAllowed($path)) return ['success' => false, 'error' => 'Access denied'];
    $fullPath = empty($path) ? '.' : $path;
    if (!is_dir($fullPath)) return ['success' => false, 'error' => 'Not a directory'];

    $items = [];
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $fullPath . '/' . $file;
        $isDirectory = is_dir($filePath);
        $item = [
            'name' => $file,
            'path' => empty($path) ? $file : $path . '/' . $file,
            'type' => $isDirectory ? 'directory' : 'file',
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'modified' => filemtime($filePath)
        ];
        if (!$isDirectory) {
            $item['extension'] = getFileExtension($file);
            $item['editable'] = isFileEditable($file);
            $item['viewable'] = isFileViewable($file);
        }
        $items[] = $item;
    }
    return ['success' => true, 'path' => $path, 'items' => $items];
}

function readFileContent($path)
{
    $path = sanitizePath($path);
    if (!isPathAllowed($path) || !is_file($path)) return ['success' => false, 'error' => 'Invalid file'];
    if (!isFileViewable(basename($path))) return ['success' => false, 'error' => 'Not viewable'];
    $content = file_get_contents($path);
    return $content !== false ? ['success' => true, 'content' => $content, 'path' => $path] : ['success' => false, 'error' => 'Read failed'];
}

function writeFileContent($path, $content)
{
    $path = sanitizePath($path);
    if (!isPathAllowed($path) || !isFileEditable(basename($path))) return ['success' => false, 'error' => 'Invalid path/type'];
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $res = file_put_contents($path, $content);
    return $res !== false ? ['success' => true, 'bytes' => $res] : ['success' => false, 'error' => 'Write failed'];
}
