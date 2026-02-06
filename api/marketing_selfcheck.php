<?php

// Marketing AI self-check endpoint
// Verifies marketing_suggestions schema and get_marketing_data wiring.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/data_manager.php';

// Admin only
AuthHelper::requireAdmin();

header('Content-Type: application/json');

$checks = [];

try {
    $pdo = Database::getInstance();

    // 1) Schema check: table + columns
    $schemaStatus = 'pass';
    $schemaDetails = [];
    try {
        $hasTable = (bool) Database::queryOne("SHOW TABLES LIKE 'marketing_suggestions'");
        $schemaDetails['has_table_marketing_suggestions'] = $hasTable;
        if (!$hasTable) {
            $schemaStatus = 'fail';
        } else {
            $colConf = Database::queryOne("SHOW COLUMNS FROM marketing_suggestions LIKE 'confidence_score'");
            $colReason = Database::queryOne("SHOW COLUMNS FROM marketing_suggestions LIKE 'recommendation_reasoning'");
            $hasConf = !empty($colConf);
            $hasReason = !empty($colReason);
            $schemaDetails['has_confidence_score_column'] = $hasConf;
            $schemaDetails['has_recommendation_reasoning_column'] = $hasReason;
            if (!$hasConf || !$hasReason) {
                $schemaStatus = 'fail';
            }
        }
    } catch (Throwable $e) {
        $schemaStatus = 'fail';
        $schemaDetails['error'] = $e->getMessage();
    }
    $checks[] = [
        'id' => 'schema',
        'label' => 'Marketing suggestions schema (confidence_score, recommendation_reasoning)',
        'status' => $schemaStatus,
        'details' => $schemaDetails,
    ];

    // 2) Data presence check: any rows with meta set
    $dataStatus = 'warn';
    $dataDetails = [];
    $sampleSku = null;
    try {
        $row = Database::queryOne(
            "SELECT sku, confidence_score, recommendation_reasoning, updated_at, created_at
             FROM marketing_suggestions
             WHERE confidence_score IS NOT NULL OR (recommendation_reasoning IS NOT NULL AND recommendation_reasoning <> '')
             ORDER BY updated_at DESC, created_at DESC
             LIMIT 1"
        );
        if ($row) {
            $dataStatus = 'pass';
            $sampleSku = $row['sku'];
            $dataDetails['sample_sku'] = $row['sku'];
            $dataDetails['confidence_score'] = $row['confidence_score'];
            $dataDetails['has_reasoning'] = ($row['recommendation_reasoning'] !== null && $row['recommendation_reasoning'] !== '');
            $dataDetails['updated_at'] = $row['updated_at'] ?? $row['created_at'] ?? null;
        } else {
            $dataStatus = 'warn';
            $dataDetails['message'] = 'No marketing_suggestions rows with confidence/reasoning yet. Generate AI marketing for at least one item.';
        }
    } catch (Throwable $e) {
        $dataStatus = 'fail';
        $dataDetails['error'] = $e->getMessage();
    }
    $checks[] = [
        'id' => 'data_presence',
        'label' => 'Marketing suggestions data with AI meta present',
        'status' => $dataStatus,
        'details' => $dataDetails,
    ];

    // 3) get_marketing_data wiring check using getMarketingData() helper
    $pipelineStatus = 'warn';
    $pipelineDetails = [];
    if ($sampleSku) {
        try {
            // Capture output of getMarketingData($pdo)
            $oldGet = $_GET;
            $_GET['sku'] = $sampleSku;
            ob_start();
            getMarketingData($pdo);
            $json = ob_get_clean();
            $_GET = $oldGet;

            $decoded = json_decode($json, true);
            $pipelineDetails['raw_response'] = $decoded;

            if (is_array($decoded) && !empty($decoded['success']) && !empty($decoded['data'])) {
                $data = $decoded['data'];
                $hasConf = array_key_exists('confidence_score', $data);
                $hasReason = array_key_exists('recommendation_reasoning', $data);
                $pipelineDetails['has_confidence_score'] = $hasConf;
                $pipelineDetails['has_recommendation_reasoning'] = $hasReason;
                if ($hasConf && $hasReason) {
                    $pipelineStatus = 'pass';
                } else {
                    $pipelineStatus = 'fail';
                }
            } else {
                $pipelineStatus = 'fail';
                $pipelineDetails['error'] = 'getMarketingData did not return success/data for sample SKU.';
            }
        } catch (Throwable $e) {
            $pipelineStatus = 'fail';
            $pipelineDetails['error'] = $e->getMessage();
        }
    } else {
        $pipelineStatus = 'warn';
        $pipelineDetails['message'] = 'Skipped pipeline check because no sample SKU with AI meta was found.';
    }
    $checks[] = [
        'id' => 'get_marketing_data_pipeline',
        'label' => 'marketing_manager get_marketing_data → marketing_suggestions pipeline',
        'status' => $pipelineStatus,
        'details' => $pipelineDetails,
    ];

    // Summarize
    $summary = [
        'pass' => 0,
        'fail' => 0,
        'warn' => 0,
    ];
    foreach ($checks as $c) {
        $st = $c['status'] ?? 'warn';
        if (!isset($summary[$st])) { $summary[$st] = 0; }
        $summary[$st]++;
    }

    Response::json([
        'success' => true,
        'summary' => $summary,
        'checks' => $checks,
    ]);

} catch (Throwable $e) {
    Response::json([
        'success' => false,
        'error' => 'Self-check failed: ' . $e->getMessage(),
    ], 500);
}
