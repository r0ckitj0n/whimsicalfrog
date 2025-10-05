<?php
// Distance API: compute driving distance miles with 24h caching
// POST JSON: { from:{address?,city?,state?,zip}, to:{address?,city?,state?,zip}, debug?:true }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/response.php';

function haversineMiles($lat1, $lon1, $lat2, $lon2) {
    $earthRadiusMi = 3958.8;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadiusMi * $c;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        Response::error('Invalid JSON', null, 400);
    }
    $from = $input['from'] ?? [];
    $to = $input['to'] ?? [];
    $debug = !empty($input['debug']);

    // Compose strings for cache key
    $fromStr = trim(($from['address'] ?? '') . ' ' . ($from['city'] ?? '') . ' ' . ($from['state'] ?? '') . ' ' . ($from['zip'] ?? ''));
    $toStr   = trim(($to['address'] ?? '') . ' ' . ($to['city'] ?? '') . ' ' . ($to['state'] ?? '') . ' ' . ($to['zip'] ?? ''));
    if ($fromStr === '' || $toStr === '') {
        Response::error('Missing from/to address', null, 400);
    }

    // Ensure cache table
    try {
        Database::execute("CREATE TABLE IF NOT EXISTS distance_cache (
            cache_key VARCHAR(96) PRIMARY KEY,
            from_addr TEXT,
            to_addr TEXT,
            miles DOUBLE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    $cacheKey = substr(sha1($fromStr . '|' . $toStr), 0, 96);
    $cached = Database::queryOne("SELECT miles, created_at FROM distance_cache WHERE cache_key = ?", [$cacheKey]);
    if ($cached) {
        $age = strtotime('now') - strtotime($cached['created_at']);
        if ($age >= 0 && $age <= 86400) {
            Response::success(['miles' => (float)$cached['miles'], 'cached' => true]);
        }
    }

    $orsKey = (string) BusinessSettings::get('ors_api_key', '');
    $miles = null;

    if ($orsKey) {
        // Very lightweight geocoding and routing via OpenRouteService (free tier)
        $geocode = function ($q) use ($orsKey) {
            $url = 'https://api.openrouteservice.org/geocode/search?api_key=' . urlencode($orsKey) . '&text=' . urlencode($q) . '&size=1';
            $resp = @file_get_contents($url);
            if (!$resp) return null;
            $data = json_decode($resp, true);
            $coords = $data['features'][0]['geometry']['coordinates'] ?? null; // [lon, lat]
            if (!$coords || count($coords) < 2) return null;
            return ['lat' => (float)$coords[1], 'lon' => (float)$coords[0]];
        };
        $fromC = $geocode($fromStr);
        $toC = $geocode($toStr);
        if ($fromC && $toC) {
            // Routing (driving-car) returns distance in meters
            $routeUrl = 'https://api.openrouteservice.org/v2/directions/driving-car?api_key=' . urlencode($orsKey);
            $payload = json_encode(['coordinates' => [[(float)$fromC['lon'], (float)$fromC['lat']], [(float)$toC['lon'], (float)$toC['lat']]]]);
            $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 10]];
            $context = stream_context_create($opts);
            $routeResp = @file_get_contents($routeUrl, false, $context);
            if ($routeResp) {
                $r = json_decode($routeResp, true);
                $meters = $r['features'][0]['properties']['segments'][0]['distance'] ?? null;
                if ($meters !== null) {
                    $miles = (float)$meters * 0.000621371;
                }
            }
        }
    }

    if ($miles === null) {
        // Fallback: geocoding not available; return null miles
        // Frontend should treat null as ineligible unless admin permits fallback
        Response::success(['miles' => null, 'cached' => false]);
    }

    // Cache
    try {
        Database::execute("REPLACE INTO distance_cache (cache_key, from_addr, to_addr, miles) VALUES (?,?,?,?)",
            [$cacheKey, $fromStr, $toStr, $miles]);
    } catch (Exception $e) {}

    Response::success(['miles' => round($miles, 2), 'cached' => false]);
} catch (Throwable $e) {
    if (class_exists('Logger')) {
        Logger::exception('distance error', $e, ['endpoint' => 'distance']);
    }
    Response::serverError('Server error');
}
