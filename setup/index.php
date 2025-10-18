<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$baseDir = realpath(__DIR__);
if ($baseDir === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Setup directory missing.";
    exit;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$requestPath = (string) $requestPath;

if (stripos($requestPath, '/setup') === 0) {
    $relative = substr($requestPath, strlen('/setup')) ?: '';
} else {
    $relative = $requestPath;
}
$relative = ltrim($relative, '/');

if ($relative === '' || $relative === 'index.php') {
    header('Content-Type: text/html; charset=utf-8');
    $entries = array_filter(scandir($baseDir) ?: [], function (string $entry) use ($baseDir): bool {
        if ($entry[0] === '.') {
            return false;
        }
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
        return is_file($fullPath) || is_dir($fullPath);
    });
    sort($entries);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Setup Assets</title>
        <style>
            body {
                font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                margin: 2rem;
            }
            h1 {
                margin-bottom: 1rem;
            }
            ul {
                list-style: none;
                padding: 0;
            }
            li {
                margin: 0.5rem 0;
            }
            a {
                color: #2563eb;
                text-decoration: none;
            }
            a:hover {
                text-decoration: underline;
            }
            code {
                background: #f3f4f6;
                padding: 0.1rem 0.25rem;
                border-radius: 0.25rem;
            }
        </style>
    </head>
    <body>
        <h1>Setup Assets</h1>
        <p>The following files are restricted to administrators and can be downloaded directly.</p>
        <ul>
            <?php foreach ($entries as $entry): ?>
                <?php
                if ($entry === 'index.php') {
                    continue;
                }
                $fullPath = $baseDir . DIRECTORY_SEPARATOR . $entry;
                $label = is_dir($fullPath) ? $entry . '/' : $entry;
                ?>
                <li><a href="<?= htmlspecialchars($entry, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <p>To bootstrap a new project, download <code>bootstrap_guardrails.sh</code> and the accompanying documentation.</p>
    </body>
    </html>
    <?php
    exit;
}

$targetPath = realpath($baseDir . DIRECTORY_SEPARATOR . $relative);
if ($targetPath === false || strpos($targetPath, $baseDir) !== 0 || !is_file($targetPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "File not found.";
    exit;
}

$extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
switch ($extension) {
    case 'sh':
        $contentType = 'text/plain; charset=utf-8';
        $download = true;
        break;
    case 'md':
        $contentType = 'text/markdown; charset=utf-8';
        $download = false;
        break;
    case 'txt':
        $contentType = 'text/plain; charset=utf-8';
        $download = false;
        break;
    case 'json':
        $contentType = 'application/json; charset=utf-8';
        $download = false;
        break;
    default:
        $contentType = 'application/octet-stream';
        $download = true;
        break;
}

header('Content-Type: ' . $contentType);
if ($download) {
    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
}

readfile($targetPath);
exit;
