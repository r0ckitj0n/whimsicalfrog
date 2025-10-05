<?php
// Shipping Rates API (USPS/UPS/FedEx) with 24h caching and safe fallbacks
// POST JSON: { items:[{sku,qty,weightOz?,dims?}], from:{zip}, to:{zip}, carrier?: 'USPS'|'UPS'|'FedEx', debug?:true }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/response.php';

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

    // Load provider keys from BusinessSettings
    $uspsUserId = (string) BusinessSettings::get('usps_webtools_userid', '');
    $upsKey = (string) BusinessSettings::get('ups_access_key', '');
    $upsSecret = (string) BusinessSettings::get('ups_secret', '');
    $fedexKey = (string) BusinessSettings::get('fedex_key', '');
    $fedexSecret = (string) BusinessSettings::get('fedex_secret', '');

    $rates = [];

    // Without provider keys, we will not call external APIs; return empty so frontend can fallback
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
