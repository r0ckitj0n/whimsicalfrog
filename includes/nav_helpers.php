<?php

function __wf_normalize_key($v): string {
    return strtolower(trim((string)$v));
}

function wf_compute_neighbors(array $list, callable $primaryKeyFn, $openKey, ?callable $secondaryKeyFn = null): array {
    $openKeyNorm = __wf_normalize_key($openKey);

    $rawPrimary = array_values(array_map(static function ($row) use ($primaryKeyFn) {
        return (string)$primaryKeyFn($row);
    }, $list));
    $primaryNorm = array_map('__wf_normalize_key', $rawPrimary);

    $idx = array_search($openKeyNorm, $primaryNorm, true);

    if ($idx === false && $secondaryKeyFn !== null) {
        $rawSecondary = array_values(array_map(static function ($row) use ($secondaryKeyFn) {
            return (string)$secondaryKeyFn($row);
        }, $list));
        $secondaryNorm = array_map('__wf_normalize_key', $rawSecondary);
        $userIdx = array_search($openKeyNorm, $secondaryNorm, true);
        if ($userIdx !== false) {
            $idx = $userIdx;
        }
    }

    $prev = null;
    $next = null;
    if ($idx !== false) {
        if ($idx > 0) {
            $prev = $rawPrimary[$idx - 1];
        }
        if ($idx < count($rawPrimary) - 1) {
            $next = $rawPrimary[$idx + 1];
        }
    }

    return [
        'idx' => ($idx === false ? null : $idx),
        'prev' => $prev,
        'next' => $next,
        'list' => $rawPrimary,
    ];
}
