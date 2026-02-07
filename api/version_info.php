<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$metadataPath = $projectRoot . '/dist/version-meta.json';
$disableFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
$shellExecEnabled = function_exists('shell_exec') && !in_array('shell_exec', $disableFunctions, true);

$metadata = [];
if (is_file($metadataPath)) {
    $raw = @file_get_contents($metadataPath);
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $metadata = $decoded;
        }
    }
}

$git = [];
if ($shellExecEnabled) {
    $cwd = escapeshellarg($projectRoot);
    $git['commit_hash'] = trim((string) @shell_exec("cd {$cwd} && git rev-parse HEAD 2>/dev/null"));
    $git['commit_short_hash'] = trim((string) @shell_exec("cd {$cwd} && git rev-parse --short HEAD 2>/dev/null"));
    $git['commit_subject'] = trim((string) @shell_exec("cd {$cwd} && git log -1 --pretty=%s 2>/dev/null"));
    $git['commit_committed_at'] = trim((string) @shell_exec("cd {$cwd} && git log -1 --pretty=%cI 2>/dev/null"));
}

$commitHash = $git['commit_hash'] ?: ($metadata['commit_hash'] ?? null);
$commitShortHash = $git['commit_short_hash'] ?: ($metadata['commit_short_hash'] ?? null);
$commitSubject = $git['commit_subject'] ?: ($metadata['commit_subject'] ?? null);
$committedAt = $git['commit_committed_at'] ?: ($metadata['commit_committed_at'] ?? null);
$builtAt = $metadata['built_at'] ?? null;
$deployedAt = $metadata['deployed_at'] ?? null;

$hasGitData = !empty($git['commit_hash']) || !empty($git['commit_committed_at']);
$hasArtifactData = !empty($metadata);
$source = 'unknown';
if ($hasGitData && $hasArtifactData) {
    $source = 'mixed';
} elseif ($hasGitData) {
    $source = 'git';
} elseif ($hasArtifactData) {
    $source = 'artifact';
}

$mode = (file_exists($projectRoot . '/.disable-vite-dev') || getenv('WF_VITE_MODE') === 'prod' || getenv('WF_VITE_DISABLE_DEV') === '1')
    ? 'prod'
    : 'dev';

echo json_encode([
    'success' => true,
    'data' => [
        'commit_hash' => $commitHash ?: null,
        'commit_short_hash' => $commitShortHash ?: null,
        'commit_subject' => $commitSubject ?: null,
        'committed_for_testing_at' => $committedAt ?: null,
        'built_at' => $builtAt ?: null,
        'deployed_for_live_at' => $deployedAt ?: null,
        'server_time' => gmdate('c'),
        'mode' => $mode,
        'source' => $source
    ]
], JSON_UNESCAPED_SLASHES);
