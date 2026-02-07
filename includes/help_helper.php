<?php
/**
 * Whimsical Frog Help Helper
 * Extracted from help.php to reduce shell size.
 */

function wf_handle_docs_proxy()
{
    if (!isset($_GET['docs']))
        return;

    header('Content-Type: application/json; charset=utf-8');
    $preferredRoot = realpath(__DIR__ . '/../documentation/help-library');
    $fallbackRoot = realpath(__DIR__ . '/../documentation');
    $root = $preferredRoot ?: $fallbackRoot;

    if (!$root) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Documentation root not found']);
        exit;
    }
    $action = $_GET['docs'];

    $safe = function ($rel) use ($root) {
        $rel = ltrim(str_replace(['\\', '..'], ['/', ''], $rel), '/');
        $path = realpath($root . '/' . $rel);
        return ($path && strpos($path, $root) === 0) ? $path : false;
    };

    if ($action === 'list') {
        $docs = [];
        if (is_dir($root)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($it as $f) {
                if ($f->isFile() && strtolower($f->getExtension()) === 'md') {
                    $path = $f->getPathname();
                    $rel = ltrim(str_replace($root, '', $path), '/');
                    $content = @file_get_contents($path) ?: '';
                    $title = (preg_match('/^#\s+(.+)$/m', $content, $m)) ? trim($m[1]) : basename($path);
                    $parts = explode('/', $rel);
                    $category = count($parts) > 1 ? ucfirst($parts[0]) : 'General';
                    $docs[] = ['filename' => $rel, 'title' => $title, 'category' => $category, 'content' => $content];
                }
            }
        }
        // Sort documents by filename to ensure correct manual order (00, 01, 02...)
        usort($docs, function ($a, $b) {
            return strnatcmp($a['filename'], $b['filename']);
        });
        echo json_encode(['success' => true, 'documents' => $docs], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'get') {
        $rel = $_GET['file'] ?? '';
        $path = $safe($rel);
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        $content = @file_get_contents($path) ?: '';
        $title = (preg_match('/^#\s+(.+)$/m', $content, $m)) ? trim($m[1]) : basename($path);
        echo json_encode(['success' => true, 'document' => ['title' => $title, 'content' => $content]]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid docs action']);
    exit;
}
