<?php
// Shipping Rates API (USPS/UPS/FedEx) with 24h caching and safe fallbacks
// POST JSON: { items:[{sku,qty,weightOz?,dims?}], from:{zip}, to:{zip}, carrier?: 'USPS'|'UPS'|'FedEx', debug?:true }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/secret_store.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        Response::error('Invalid JSON', null, 400);
    }
    $carrier = strtoupper(trim((string)($input['carrier'] ?? '')));
    $items = $input['items'] ?? [];
    $from = $input['from'] ?? [];
    $to = $input['to'] ?? [];
    $debug = !empty($input['debug']);

    // Normalize items hash for caching
    $lines = [];
    if (is_array($items)) {
        foreach ($items as $i) {
            $sku = isset($i['sku']) ? (string)$i['sku'] : '';
            $qty = (int)($i['qty'] ?? $i['quantity'] ?? 0);
            if ($sku && $qty > 0) $lines[] = $sku . 'x' . $qty;
        }
    }
    sort($lines);
    $itemsHash = sha1(implode('|', $lines));
    $fromZip = isset($from['zip']) ? trim((string)$from['zip']) : '';
    $toZip = isset($to['zip']) ? trim((string)$to['zip']) : '';

    // Ensure cache table
    try {
        Database::execute("CREATE TABLE IF NOT EXISTS shipping_rate_cache (
            cache_key VARCHAR(96) PRIMARY KEY,
            carrier VARCHAR(16),
            from_zip VARCHAR(16),
            to_zip VARCHAR(16),
            items_hash CHAR(40),
            rates_json JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY(carrier), KEY(from_zip), KEY(to_zip), KEY(items_hash), KEY(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    $carrierKey = $carrier ?: 'ALL';
    $cacheKey = substr(sha1($carrierKey . '|' . $fromZip . '|' . $toZip . '|' . $itemsHash), 0, 96);

    // Try cache (24h TTL)
    $cached = Database::queryOne("SELECT rates_json, created_at FROM shipping_rate_cache WHERE cache_key = ?", [$cacheKey]);
    if ($cached) {
        $age = strtotime('now') - strtotime($cached['created_at']);
        if ($age >= 0 && $age <= 86400) {
            $rates = json_decode($cached['rates_json'], true);
            Response::success(['rates' => $rates, 'cached' => true]);
        }
    }

    // Load provider keys from BusinessSettings, then prefer secrets if present
    $uspsUserId = (string) BusinessSettings::get('usps_webtools_userid', '');
    $upsKey = (string) BusinessSettings::get('ups_access_key', '');
    $upsSecret = (string) BusinessSettings::get('ups_secret', '');
    $fedexKey = (string) BusinessSettings::get('fedex_key', '');
    $fedexSecret = (string) BusinessSettings::get('fedex_secret', '');
    try {
        $v = secret_get('usps_webtools_userid'); if (is_string($v) && $v !== '') $uspsUserId = $v;
    } catch (Exception $e) {}
    try {
        $v = secret_get('ups_access_key'); if (is_string($v) && $v !== '') $upsKey = $v;
    } catch (Exception $e) {}
    try {
        $v = secret_get('ups_secret'); if (is_string($v) && $v !== '') $upsSecret = $v;
    } catch (Exception $e) {}
    try {
        $v = secret_get('fedex_key'); if (is_string($v) && $v !== '') $fedexKey = $v;
    } catch (Exception $e) {}
    try {
        $v = secret_get('fedex_secret'); if (is_string($v) && $v !== '') $fedexSecret = $v;
    } catch (Exception $e) {}

    $rates = [];

    // Without provider keys, we will not call external APIs by default
    $wantUSPS = (!$carrier || $carrier === 'USPS' || $carrier === 'ALL');
    $wantUPS  = (!$carrier || $carrier === 'UPS'  || $carrier === 'ALL');
    $wantFedEx= (!$carrier || $carrier === 'FEDEX'|| $carrier === 'ALL');

    // USPS simple estimate via Web Tools if key available
    if ($wantUSPS && $uspsUserId !== '' && $fromZip && $toZip) {
        // Minimal parcel estimate: sum weight (ounces), default 16oz per item if unknown
        $totalOz = 0;
        foreach ($items as $i) {
            $qty = (int)($i['qty'] ?? $i['quantity'] ?? 1);
            $w = isset($i['weightOz']) ? (float)$i['weightOz'] : 16.0; // default 1 lb per item
            $totalOz += max(0, $w) * max(1, $qty);
        }
        $pounds = floor($totalOz / 16);
        $ounces = max(1, round($totalOz - $pounds * 16));
        $xml = '<RateV4Request USERID="' . htmlspecialchars($uspsUserId) . '">' .
               '<Revision>2</Revision>' .
               '<Package ID="1ST">' .
               '<Service>PRIORITY</Service>' .
               '<ZipOrigination>' . htmlspecialchars($fromZip) . '</ZipOrigination>' .
               '<ZipDestination>' . htmlspecialchars($toZip) . '</ZipDestination>' .
               '<Pounds>' . $pounds . '</Pounds>' .
               '<Ounces>' . $ounces . '</Ounces>' .
               '<Container>VARIABLE</Container>' .
               '<Size>REGULAR</Size>' .
               '<Machinable>true</Machinable>' .
               '</Package>' .
               '</RateV4Request>';
        $url = 'https://secure.shippingapis.com/ShippingAPI.dll?API=RateV4&XML=' . urlencode($xml);
        $resp = @file_get_contents($url);
        if ($resp) {
            // crude parse
            if (preg_match('/<Rate>([^<]+)<\\/Rate>/', $resp, $m)) {
                $amount = (float)$m[1];
                $rates[] = ['carrier' => 'USPS', 'service' => 'Priority Mail', 'amount' => round($amount, 2)];
            }
        }
    }

    // UPS/FedEx: require keys; if absent we leave empty so frontend can fallback
    // We intentionally skip implementing their auth flows here to keep this endpoint safe and optional

    // Localhost demo mode: If running on localhost and no keys are configured, return heuristic estimates
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocalhost = (strpos($host, 'localhost') !== false) || (strpos($host, '127.0.0.1') !== false);
    $noCarrierKeys = ($uspsUserId === '' && $upsKey === '' && $fedexKey === '');
    if ($isLocalhost && $noCarrierKeys && $fromZip && $toZip) {
        // Estimate total weight in pounds
        $totalOz = 0.0;
        foreach ($items as $i) {
            $qty = (int)($i['qty'] ?? $i['quantity'] ?? 1);
            $w = isset($i['weightOz']) ? (float)$i['weightOz'] : 16.0;
            $totalOz += max(0, $w) * max(1, $qty);
        }
        $lbs = max(0.5, round($totalOz / 16, 2));
        // Get approximate distance via internal distance API (haversine fallback will trigger w/o ORS key)
        $miles = null;
        try {
            $payload = json_encode(['from' => ['zip' => $fromZip], 'to' => ['zip' => $toZip], 'debug' => false]);
            $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 6]];
            $ctx = stream_context_create($opts);
            $distResp = @file_get_contents((isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'http') . '://' . $host . '/api/distance.php', false, $ctx);
            if ($distResp) {
                $dj = json_decode($distResp, true);
                if (isset($dj['miles']) && $dj['miles'] !== null) $miles = (float)$dj['miles'];
            }
        } catch (Exception $e) {}
        if ($miles === null) $miles = 50.0; // fallback guess
        // Heuristic pricing formulae (rough, for demo only)
        $calc = function($base, $perLb, $perMile) use ($lbs, $miles) {
            $amt = $base + ($perLb * $lbs) + ($perMile * max(0, $miles));
            return round(max(3.95, $amt), 2);
        };
        if ($wantUSPS)  $rates[] = ['carrier' => 'USPS',  'service' => 'Estimate (demo)', 'amount' => $calc(5.95, 0.35, 0.0015), 'estimated' => true];
        if ($wantUPS)   $rates[] = ['carrier' => 'UPS',   'service' => 'Estimate (demo)', 'amount' => $calc(9.25, 0.45, 0.0025), 'estimated' => true];
        if ($wantFedEx) $rates[] = ['carrier' => 'FedEx', 'service' => 'Estimate (demo)', 'amount' => $calc(9.75, 0.50, 0.0028), 'estimated' => true];
    }

    // Save cache
    try {
        Database::execute("REPLACE INTO shipping_rate_cache (cache_key, carrier, from_zip, to_zip, items_hash, rates_json) VALUES (?,?,?,?,?,?)",
            [$cacheKey, $carrierKey, $fromZip, $toZip, $itemsHash, json_encode($rates)]);
    } catch (Exception $e) {}

    Response::success(['rates' => $rates, 'cached' => false]);
} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::exception('shipping_rates error', $e, ['endpoint' => 'shipping_rates']);
    }
    Response::serverError('Server error');
}
