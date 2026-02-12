<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

AuthHelper::requireAdmin(403, 'Admin access required');
Response::validateMethod('POST');

function wf_ic_now_iso(): string
{
    return gmdate('c');
}

function wf_ic_project_root(): string
{
    return realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
}

function wf_ic_state_dir(): string
{
    $root = wf_ic_project_root();
    $dir = $root . '/.local/state/image_cleanup';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function wf_ic_load_state(string $jobId): array
{
    $path = wf_ic_state_dir() . '/' . $jobId . '.json';
    if (!is_file($path)) {
        throw new RuntimeException('Cleanup job not found');
    }
    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Cleanup state is corrupted');
    }
    return $data;
}

function wf_ic_save_state(string $jobId, array $state): void
{
    $path = wf_ic_state_dir() . '/' . $jobId . '.json';
    file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function wf_ic_normalize_image_ref(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') return '';

    $path = parse_url($raw, PHP_URL_PATH);
    $path = is_string($path) ? $path : $raw;
    $path = trim((string) $path);
    if ($path === '') return '';

    if (str_starts_with($path, '/')) $path = substr($path, 1);

    if (str_starts_with($path, 'images/')) return $path;
    if (str_starts_with($path, 'backgrounds/')) return 'images/' . $path;
    if (str_starts_with($path, 'items/')) return 'images/' . $path;
    if (str_starts_with($path, 'signs/')) return 'images/' . $path;
    if (str_starts_with($path, 'logos/')) return 'images/' . $path;

    return $path;
}

function wf_ic_matches_whitelist(string $relPath, array $patterns): bool
{
    $relPath = ltrim($relPath, '/');
    foreach ($patterns as $p) {
        $p = trim((string) $p);
        if ($p === '') continue;
        $p = ltrim($p, '/');
        if (fnmatch($p, $relPath)) return true;
    }
    return false;
}

function wf_ic_get_asset_whitelist_patterns(): array
{
    Database::execute('CREATE TABLE IF NOT EXISTS `asset_whitelist` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `pattern` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_pattern` (`pattern`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $rows = Database::queryAll('SELECT pattern FROM asset_whitelist ORDER BY id ASC') ?: [];
    $patterns = array_values(array_filter(array_map(static fn($r) => (string) ($r['pattern'] ?? ''), $rows)));

    // Built-in safety: keep critical non-DB assets.
    $patterns[] = 'images/logos/**';
    $patterns[] = 'images/**/.htaccess';
    $patterns[] = 'images/items/placeholder.*';
    $patterns[] = 'images/placeholder.*';

    return array_values(array_unique($patterns));
}

function wf_ic_build_referenced_set(): array
{
    $refs = [];

    $collect = static function ($value) use (&$refs): void {
        $norm = wf_ic_normalize_image_ref((string) $value);
        if ($norm === '') return;
        if (!str_starts_with($norm, 'images/')) return;
        $refs[$norm] = true;
    };

    // Items
    foreach (Database::queryAll('SELECT image_url FROM items WHERE image_url IS NOT NULL AND TRIM(image_url) <> ""') as $row) {
        $collect($row['image_url'] ?? '');
    }
    foreach (Database::queryAll('SELECT image_path, original_path FROM item_images') as $row) {
        $collect($row['image_path'] ?? '');
        $collect($row['original_path'] ?? '');
    }

    // Backgrounds + room settings
    foreach (Database::queryAll('SELECT image_filename, png_filename, webp_filename FROM backgrounds') as $row) {
        $collect($row['image_filename'] ?? '');
        $collect($row['png_filename'] ?? '');
        $collect($row['webp_filename'] ?? '');
    }
    foreach (Database::queryAll('SELECT background_url FROM room_settings WHERE background_url IS NOT NULL AND TRIM(background_url) <> ""') as $row) {
        $collect($row['background_url'] ?? '');
    }

    // Area mappings (shortcuts/signs and overrides)
    foreach (Database::queryAll('SELECT content_image, link_image, image_url FROM area_mappings') as $row) {
        $collect($row['content_image'] ?? '');
        $collect($row['link_image'] ?? '');
        $collect($row['image_url'] ?? '');
    }

    // Business settings sometimes store logo paths, etc.
    foreach (Database::queryAll('SELECT setting_value FROM business_settings WHERE setting_value IS NOT NULL AND TRIM(setting_value) <> ""') as $row) {
        $collect($row['setting_value'] ?? '');
    }

    return $refs;
}

function wf_ic_scan_images(): array
{
    $root = wf_ic_project_root();
    $imagesDir = $root . '/images';
    if (!is_dir($imagesDir)) {
        throw new RuntimeException('Missing images directory');
    }

    $allowedExt = ['png', 'webp', 'jpg', 'jpeg', 'gif', 'svg', 'avif'];
    $out = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($imagesDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $fileInfo) {
        /** @var SplFileInfo $fileInfo */
        if (!$fileInfo->isFile()) continue;
        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, $allowedExt, true)) continue;

        $abs = $fileInfo->getRealPath();
        if (!$abs) continue;
        $rel = 'images/' . ltrim(str_replace($imagesDir, '', $abs), '/');
        $out[] = [
            'abs' => $abs,
            'rel' => $rel,
            'bytes' => (int) $fileInfo->getSize()
        ];
    }

    usort($out, static fn($a, $b) => strcmp((string) $a['rel'], (string) $b['rel']));
    return $out;
}

function wf_ic_make_archive_root(string $jobId): array
{
    $root = wf_ic_project_root();
    $stamp = gmdate('Ymd-His');
    $archiveRootRel = 'backups/image_cleanup/' . $stamp . '-' . $jobId;
    $archiveRootAbs = $root . '/' . $archiveRootRel;
    if (!is_dir($archiveRootAbs)) {
        @mkdir($archiveRootAbs, 0755, true);
    }
    return [$archiveRootRel, $archiveRootAbs];
}

function wf_ic_archive_file(string $srcAbs, string $srcRel, string $archiveRootAbs, string $archiveRootRel, bool $dryRun): string
{
    $root = wf_ic_project_root();
    $srcRel = ltrim($srcRel, '/');
    $destAbs = $archiveRootAbs . '/' . $srcRel;
    $destRel = $archiveRootRel . '/' . $srcRel;

    $destDir = dirname($destAbs);
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    if (!$dryRun) {
        if (!@rename($srcAbs, $destAbs)) {
            // Fall back to copy+unlink if rename fails.
            if (!@copy($srcAbs, $destAbs)) {
                throw new RuntimeException('Failed to archive file: ' . $srcRel);
            }
            @unlink($srcAbs);
        }
        @chmod($destAbs, 0644);
    }

    // Guard: ensure we never move outside the backups directory.
    $destReal = realpath($destAbs) ?: $destAbs;
    $backupsReal = realpath($root . '/backups') ?: ($root . '/backups');
    if (!str_starts_with($destReal, $backupsReal)) {
        throw new RuntimeException('Archive path escaped backups directory');
    }

    return $destRel;
}

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    $action = trim((string) ($input['action'] ?? ''));
    if (!in_array($action, ['start', 'step'], true)) {
        Response::error('Invalid action', null, 400);
    }

    if ($action === 'start') {
        $dryRun = !empty($input['dry_run']);
        $jobId = bin2hex(random_bytes(8));

        [$archiveRootRel, $archiveRootAbs] = wf_ic_make_archive_root($jobId);

        $state = [
            'job_id' => $jobId,
            'started_at' => wf_ic_now_iso(),
            'finished_at' => null,
            'phase' => 'init',
            'status' => 'Initializing...',
            'dry_run' => $dryRun,
            'archive_root_rel' => $archiveRootRel,
            'archive_root_abs' => $archiveRootAbs,
            'files' => [],
            'cursor' => 0,
            'refs' => [],
            'whitelist_patterns' => [],
            'archived_files' => [],
            'skipped_whitelist' => [],
            'errors' => [],
            'referenced_count' => 0
        ];

        wf_ic_save_state($jobId, $state);

        Response::json(['success' => true, 'job_id' => $jobId]);
    }

    $jobId = trim((string) ($input['job_id'] ?? ''));
    if ($jobId === '') {
        Response::error('job_id is required', null, 422);
    }

    $state = wf_ic_load_state($jobId);

    $phase = (string) ($state['phase'] ?? 'init');
    $dryRun = !empty($state['dry_run']);
    $archiveRootRel = (string) ($state['archive_root_rel'] ?? '');
    $archiveRootAbs = (string) ($state['archive_root_abs'] ?? '');

    if ($phase === 'init') {
        $state['phase'] = 'building_references';
        $state['status'] = 'Loading asset whitelist...';
        $state['whitelist_patterns'] = wf_ic_get_asset_whitelist_patterns();
        wf_ic_save_state($jobId, $state);

        Response::json([
            'success' => true,
            'job_id' => $jobId,
            'phase' => 'building_references',
            'status' => $state['status'],
            'progress' => [
                'processed' => 0,
                'total' => 0,
                'archived' => 0,
                'referenced' => 0,
                'whitelisted' => 0
            ]
        ]);
    }

    if ($phase === 'building_references') {
        $state['status'] = 'Scanning database image references...';
        $state['refs'] = wf_ic_build_referenced_set();
        $state['status'] = 'Scanning /images files...';
        $state['files'] = wf_ic_scan_images();
        $state['cursor'] = 0;
        $state['archived_files'] = [];
        $state['skipped_whitelist'] = [];
        $state['errors'] = [];
        $state['referenced_count'] = 0;
        $state['phase'] = 'archiving';
        $state['status'] = 'Archiving unreferenced images...';
        wf_ic_save_state($jobId, $state);

        Response::json([
            'success' => true,
            'job_id' => $jobId,
            'phase' => 'archiving',
            'status' => $state['status'],
            'progress' => [
                'processed' => 0,
                'total' => count($state['files']),
                'archived' => 0,
                'referenced' => 0,
                'whitelisted' => 0
            ]
        ]);
    }

    if ($phase === 'archiving') {
        $files = is_array($state['files'] ?? null) ? $state['files'] : [];
        $cursor = (int) ($state['cursor'] ?? 0);
        $refs = is_array($state['refs'] ?? null) ? $state['refs'] : [];
        $patterns = is_array($state['whitelist_patterns'] ?? null) ? $state['whitelist_patterns'] : [];

        $archivedFiles = is_array($state['archived_files'] ?? null) ? $state['archived_files'] : [];
        $skippedWhitelist = is_array($state['skipped_whitelist'] ?? null) ? $state['skipped_whitelist'] : [];
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        $referencedCount = (int) ($state['referenced_count'] ?? 0);

        $batch = 45;
        $processedThisCall = 0;

        for (; $cursor < count($files) && $processedThisCall < $batch; $cursor++) {
            $f = $files[$cursor];
            $rel = (string) ($f['rel'] ?? '');
            $abs = (string) ($f['abs'] ?? '');
            $bytes = (int) ($f['bytes'] ?? 0);

            if ($rel === '' || $abs === '' || !is_file($abs)) {
                continue;
            }

            if (wf_ic_matches_whitelist($rel, $patterns)) {
                $skippedWhitelist[] = $rel;
                $processedThisCall++;
                continue;
            }

            if (!empty($refs[$rel])) {
                $referencedCount++;
                $processedThisCall++;
                continue;
            }

            try {
                $archivedRel = wf_ic_archive_file($abs, $rel, $archiveRootAbs, $archiveRootRel, $dryRun);
                $archivedFiles[] = [
                    'rel_path' => $rel,
                    'archived_rel_path' => $archivedRel,
                    'bytes' => $bytes
                ];
            } catch (Throwable $archiveErr) {
                $errors[] = $rel . ': ' . $archiveErr->getMessage();
            }
            $processedThisCall++;
        }

        $state['cursor'] = $cursor;
        $state['archived_files'] = $archivedFiles;
        $state['skipped_whitelist'] = array_values(array_unique($skippedWhitelist));
        $state['errors'] = $errors;
        $state['referenced_count'] = $referencedCount;
        $state['status'] = ($cursor >= count($files))
            ? 'Finalizing report...'
            : ('Checking ' . ($cursor + 1) . ' of ' . count($files) . '...');

        if ($cursor >= count($files)) {
            $state['phase'] = 'complete';
            $state['finished_at'] = wf_ic_now_iso();

            $report = [
                'job_id' => $jobId,
                'started_at' => (string) ($state['started_at'] ?? ''),
                'finished_at' => (string) ($state['finished_at'] ?? ''),
                'archive_root_rel' => $archiveRootRel,
                'dry_run' => (bool) $dryRun,
                'total_files' => count($files),
                'referenced_files' => $referencedCount,
                'archived_files' => $archivedFiles,
                'skipped_whitelist' => array_values(array_unique($state['skipped_whitelist'] ?? [])),
                'errors' => $errors
            ];

            // Save report JSON alongside archive for later review.
            $reportAbs = wf_ic_project_root() . '/' . $archiveRootRel . '/report.json';
            @file_put_contents($reportAbs, json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $state['report_rel'] = $archiveRootRel . '/report.json';
            $state['status'] = 'Complete.';
        }

        wf_ic_save_state($jobId, $state);

        $total = count($files);
        $processed = min($cursor, $total);
        $progress = [
            'processed' => $processed,
            'total' => $total,
            'archived' => count($archivedFiles),
            'referenced' => $referencedCount,
            'whitelisted' => count(array_unique($state['skipped_whitelist'] ?? []))
        ];

        $resp = [
            'success' => true,
            'job_id' => $jobId,
            'phase' => (string) ($state['phase'] ?? 'archiving'),
            'status' => (string) ($state['status'] ?? ''),
            'progress' => $progress
        ];

        if (($state['phase'] ?? '') === 'complete') {
            $resp['report'] = [
                'job_id' => $jobId,
                'started_at' => (string) ($state['started_at'] ?? ''),
                'finished_at' => (string) ($state['finished_at'] ?? ''),
                'archive_root_rel' => $archiveRootRel,
                'dry_run' => (bool) $dryRun,
                'total_files' => $total,
                'referenced_files' => $referencedCount,
                'archived_files' => $archivedFiles,
                'skipped_whitelist' => array_values(array_unique($state['skipped_whitelist'] ?? [])),
                'errors' => $errors
            ];
        }

        Response::json($resp);
    }

    if ($phase === 'complete') {
        $report = [];
        if (!empty($state['report_rel'])) {
            $reportAbs = wf_ic_project_root() . '/' . ltrim((string) $state['report_rel'], '/');
            if (is_file($reportAbs)) {
                $raw = file_get_contents($reportAbs);
                $reportDecoded = json_decode((string) $raw, true);
                if (is_array($reportDecoded)) $report = $reportDecoded;
            }
        }
        Response::json([
            'success' => true,
            'job_id' => $jobId,
            'phase' => 'complete',
            'status' => 'Complete.',
            'progress' => [
                'processed' => (int) ($state['cursor'] ?? 0),
                'total' => is_array($state['files'] ?? null) ? count($state['files']) : 0,
                'archived' => is_array($state['archived_files'] ?? null) ? count($state['archived_files']) : 0,
                'referenced' => (int) ($state['referenced_count'] ?? 0),
                'whitelisted' => is_array($state['skipped_whitelist'] ?? null) ? count($state['skipped_whitelist']) : 0
            ],
            'report' => !empty($report) ? $report : null
        ]);
    }

    Response::error('Unhandled job phase', null, 500);
} catch (Throwable $e) {
    Response::error($e->getMessage(), null, 500);
}

