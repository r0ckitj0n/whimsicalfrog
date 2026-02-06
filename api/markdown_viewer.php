<?php
// API endpoint for markdown viewer - admin only
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';

header('Content-Type: application/json');

AuthHelper::requireAdmin(403, 'Admin access required');

$baseDir = realpath(dirname(__DIR__) . '/documentation');
$fileParam = isset($_GET['file']) ? (string) $_GET['file'] : '';
$fileParam = trim(str_replace('\\', '/', $fileParam), '/');

if ($fileParam === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing file parameter']);
    exit;
}

// Resolve path safely
if (strpos($fileParam, 'documentation/') === 0) {
    $candidate = realpath(dirname(__DIR__) . '/' . $fileParam);
} else {
    $candidate = realpath($baseDir . '/' . $fileParam);
}

if (!$candidate || strpos($candidate, $baseDir) !== 0 || !is_file($candidate)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'File not found or not permitted']);
    exit;
}

$raw = @file_get_contents($candidate);
if ($raw === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to read file']);
    exit;
}

// Simple markdown to HTML conversion
function wf_md_to_html($md)
{
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $out = [];
    $lines = explode("\n", $md);
    $inCode = false;
    $codeLang = '';
    $para = '';

    $flushPara = function () use (&$out, &$para) {
        $t = trim($para);
        if ($t !== '') {
            $out[] = '<p>' . $t . '</p>';
        }
        $para = '';
    };

    foreach ($lines as $line) {
        if (preg_match('/^```(\w+)?\s*$/', $line, $m)) {
            if ($inCode) {
                $out[] = '</code></pre>';
                $inCode = false;
                $codeLang = '';
            } else {
                if (trim($para) !== '')
                    $flushPara();
                $inCode = true;
                $codeLang = isset($m[1]) ? htmlspecialchars($m[1]) : '';
                $cls = $codeLang ? ' class="language-' . $codeLang . '"' : '';
                $out[] = '<pre><code' . $cls . '>';
            }
            continue;
        }

        if ($inCode) {
            $out[] = htmlspecialchars($line);
            continue;
        }

        if (preg_match('/^(#{1,6})\s*(.+)$/', $line, $m)) {
            $flushPara();
            $lvl = strlen($m[1]);
            $txt = trim($m[2]);
            $out[] = '<h' . $lvl . '>' . htmlspecialchars($txt) . '</h' . $lvl . '>';
            continue;
        }

        if (trim($line) === '') {
            $flushPara();
            continue;
        }

        $l = htmlspecialchars($line);
        $l = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $l);
        $l = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $l);
        $l = preg_replace('/`([^`]+)`/', '<code>$1</code>', $l);

        if ($para !== '')
            $para .= ' ';
        $para .= $l;
    }

    if ($inCode) {
        $out[] = '</code></pre>';
    }
    if (trim($para) !== '') {
        $out[] = '<p>' . $para . '</p>';
    }

    return implode("\n", $out);
}

$title = basename($candidate);
$html = wf_md_to_html($raw);

echo json_encode([
    'success' => true,
    'data' => [
        'title' => $title,
        'html' => $html,
        'raw' => $raw,
    ]
]);
