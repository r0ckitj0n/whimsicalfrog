<?php
// sections/tools/deploy_stream.php
// Server-Sent Events stream for Deploy actions

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Deploy Manager streaming has been removed.';
exit;

$ROOT = dirname(__DIR__, 2);

// Optional auth
$authPath = $ROOT . '/includes/auth.php';
$authHelperPath = $ROOT . '/includes/auth_helper.php';
if (file_exists($authPath)) {
    require_once $authPath;
    if (file_exists($authHelperPath)) {
        require_once $authHelperPath;
    }
    $loggedIn = class_exists('AuthHelper') ? AuthHelper::isLoggedIn() : (function_exists('isLoggedIn') ? isLoggedIn() : false);
    if (!$loggedIn) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden';
        exit;
    }
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) { @ob_end_flush(); }
ob_implicit_flush(true);

function sse_send($event, $data) {
    $lines = preg_split("/\r?\n/", (string)$data);
    if ($event) echo "event: {$event}\n";
    foreach ($lines as $l) echo 'data: ' . $l . "\n";
    echo "\n";
    @flush(); @ob_flush();
}

$action = $_GET['action'] ?? '';
$dry = ($_GET['dry_run'] ?? '0') === '1' ? '1' : '0';

$cmd = null;
if ($action === 'fast_deploy') {
    $cmd = $dry === '1' ? 'WF_DRY_RUN=1 bash scripts/deploy.sh' : 'bash scripts/deploy.sh';
} elseif ($action === 'full_deploy') {
    $cmd = $dry === '1' ? 'WF_DRY_RUN=1 bash scripts/deploy_full.sh' : 'bash scripts/deploy_full.sh';
} else {
    sse_send('message', 'Unsupported action');
    sse_send('done', '');
    exit;
}

$descriptorspec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$env = [ 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ];
$proc = proc_open($cmd, $descriptorspec, $pipes, $ROOT, $env);
if (!is_resource($proc)) {
    sse_send('message', 'Failed to start process');
    sse_send('done', '');
    exit;
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

$buffers = ['', ''];
$running = true;
while ($running) {
    $out = fread($pipes[1], 8192);
    $err = fread($pipes[2], 8192);
    if ($out !== false && $out !== '') sse_send('message', rtrim($out, "\r\n"));
    if ($err !== false && $err !== '') sse_send('message', rtrim("--- STDERR ---\n" . $err, "\r\n"));
    $status = proc_get_status($proc);
    if (!$status['running']) {
        $running = false;
        $exitCode = $status['exitcode'];
        if ($exitCode === -1) { // sometimes not available; close to get it
            $exitCode = proc_close($proc);
        } else {
            proc_close($proc);
        }
        sse_send('message', "Exit code: {$exitCode}");
        break;
    }
    usleep(150000); // 150ms
}

sse_send('done', '');
