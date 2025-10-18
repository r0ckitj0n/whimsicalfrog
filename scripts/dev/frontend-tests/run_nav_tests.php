<?php
require_once dirname(__DIR__, 3) . '/includes/nav_helpers.php';

function assert_equal($a, $b, $msg) {
    if ($a !== $b) {
        fwrite(STDERR, "ASSERT FAIL: $msg\n  got: " . var_export($a, true) . "\n  exp: " . var_export($b, true) . "\n");
        exit(1);
    }
}

// Customers dataset (sorted by name already)
$customers = [
    ['id' => '001', 'username' => 'alpha'],
    ['id' => '002', 'username' => 'bravo'],
    ['id' => '003', 'username' => 'charlie'],
];

// Items dataset (sorted by sku)
$items = [
    ['sku' => 'WF-AAA-001'],
    ['sku' => 'WF-AAA-002'],
    ['sku' => 'WF-AAA-003'],
];

// 1) Customers by id
$r = wf_compute_neighbors($customers, fn($c) => $c['id'], '001', fn($c) => $c['username']);
assert_equal($r['idx'], 0, 'customers by id first idx');
assert_equal($r['prev'], null, 'customers by id first prev');
assert_equal($r['next'], '002', 'customers by id first next');

$r = wf_compute_neighbors($customers, fn($c) => $c['id'], '002', fn($c) => $c['username']);
assert_equal($r['idx'], 1, 'customers by id second idx');
assert_equal($r['prev'], '001', 'customers by id second prev');
assert_equal($r['next'], '003', 'customers by id second next');

$r = wf_compute_neighbors($customers, fn($c) => $c['id'], '003', fn($c) => $c['username']);
assert_equal($r['idx'], 2, 'customers by id last idx');
assert_equal($r['prev'], '002', 'customers by id last prev');
assert_equal($r['next'], null, 'customers by id last next');

// 2) Customers by username fallback (open with username instead of id)
$r = wf_compute_neighbors($customers, fn($c) => $c['id'], 'bravo', fn($c) => $c['username']);
assert_equal($r['idx'], 1, 'customers fallback by username');

// 3) Items by sku
$r = wf_compute_neighbors($items, fn($i) => $i['sku'], 'WF-AAA-001');
assert_equal($r['idx'], 0, 'items first idx');
assert_equal($r['prev'], null, 'items first prev');
assert_equal($r['next'], 'WF-AAA-002', 'items first next');

$r = wf_compute_neighbors($items, fn($i) => $i['sku'], 'WF-AAA-002');
assert_equal($r['idx'], 1, 'items second idx');
assert_equal($r['prev'], 'WF-AAA-001', 'items second prev');
assert_equal($r['next'], 'WF-AAA-003', 'items second next');

$r = wf_compute_neighbors($items, fn($i) => $i['sku'], 'WF-AAA-003');
assert_equal($r['idx'], 2, 'items last idx');
assert_equal($r['prev'], 'WF-AAA-002', 'items last prev');
assert_equal($r['next'], null, 'items last next');

echo "OK: nav neighbor tests passed\n";
