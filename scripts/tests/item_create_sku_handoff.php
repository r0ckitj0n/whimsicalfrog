<?php
/**
 * Regression: new-item create must hand the finalized SKU to AI breakdown persist.
 *
 * Mirrors JsonResponseParser unwrap + the previous addItem bug that read only
 * res.data.sku (undefined after unwrap), which wrote cost/price factors onto
 * orphaned WF-TMP-* rows after add_inventory migrated/deleted the temp item.
 */

declare(strict_types=1);

function wf_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * Simulates JsonResponseParser success unwrap of Response::success([...]).
 *
 * @param array<string, mixed> $apiPayload
 * @return array<string, mixed>
 */
function wf_simulate_json_response_unwrap(array $apiPayload): array
{
    $obj = $apiPayload;
    if (!empty($obj['success']) && isset($obj['data']) && is_array($obj['data'])) {
        return array_merge(
            [
                'success' => true,
                'message' => $obj['message'] ?? null,
            ],
            $obj['data']
        );
    }
    return $obj;
}

/**
 * Simulates the fixed addItem finalized-SKU extraction.
 *
 * @param array<string, mixed> $res
 */
function wf_extract_finalized_sku(array $res): string
{
    return trim((string) ($res['sku'] ?? $res['id'] ?? ($res['data']['sku'] ?? null) ?? ($res['data']['id'] ?? null) ?? ''));
}

/**
 * Simulates the fixed persist target selection (never write AI factors to WF-TMP-*
 * after a successful create that should have returned a final SKU).
 */
function wf_sku_for_breakdowns(string $finalizedSku, string $skuToSave): string
{
    if ($finalizedSku !== '') {
        return $finalizedSku;
    }
    if (!preg_match('/^WF-TMP-/i', $skuToSave)) {
        return $skuToSave;
    }
    return '';
}

$wrapped = [
    'success' => true,
    'data' => [
        'message' => 'Item added successfully',
        'id' => 'WF-3DC-391',
        'sku' => 'WF-3DC-391',
        'created' => true,
        'updated' => false,
    ],
];

$unwrapped = wf_simulate_json_response_unwrap($wrapped);
wf_assert(($unwrapped['sku'] ?? null) === 'WF-3DC-391', 'Unwrap must expose top-level sku');
wf_assert(!isset($unwrapped['data']) || !is_array($unwrapped['data'] ?? null) || !isset($unwrapped['data']['sku']), 'Unwrap flattens data envelope');

$buggySku = $unwrapped['data']['sku'] ?? $unwrapped['data']['id'] ?? null;
wf_assert($buggySku === null, 'Pre-fix addItem path must lose sku after unwrap (documents the bug)');

$fixedSku = wf_extract_finalized_sku($unwrapped);
wf_assert($fixedSku === 'WF-3DC-391', 'Fixed extractor must recover finalized SKU from unwrapped response');

$persistTarget = wf_sku_for_breakdowns($fixedSku, 'WF-TMP-123456');
wf_assert($persistTarget === 'WF-3DC-391', 'AI breakdowns must target finalized SKU, not temp SKU');

$refuseTemp = wf_sku_for_breakdowns('', 'WF-TMP-123456');
wf_assert($refuseTemp === '', 'Must refuse persisting AI breakdowns onto temp SKU when finalized sku is missing');

$keepFinalClient = wf_sku_for_breakdowns('', 'WF-3DC-391');
wf_assert($keepFinalClient === 'WF-3DC-391', 'Client-finalized non-temp SKU remains a valid persist target');

// Nested-shape compatibility (if a caller bypasses unwrap)
$nestedOnly = [
    'success' => true,
    'data' => ['sku' => 'WF-3DC-400', 'id' => 'WF-3DC-400'],
];
wf_assert(wf_extract_finalized_sku($nestedOnly) === 'WF-3DC-400', 'Extractor must still support nested data.sku');

echo "item_create_sku_handoff regressions passed\n";
