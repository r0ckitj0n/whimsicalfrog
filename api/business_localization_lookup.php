<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::methodNotAllowed('Method not allowed');
}

AuthHelper::requireAdmin(403, 'Admin access required');

function wf_fetch_json(string $url, int $timeoutSeconds = 6): ?array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'header' => "Accept: application/json\r\nUser-Agent: WhimsicalFrog/1.0\r\n"
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false || $raw === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function wf_country_defaults(string $countryCode): array
{
    $upper = strtoupper(trim($countryCode));
    if ($upper === 'US') {
        return ['currency' => 'USD', 'locale' => 'en-US'];
    }
    return ['currency' => 'USD', 'locale' => 'en-' . ($upper ?: 'US')];
}

function wf_locale_currency_for_country(string $countryCode): array
{
    $defaults = wf_country_defaults($countryCode);

    $json = wf_fetch_json('https://cdn.jsdelivr.net/npm/country-locale-map/countries.json');
    if (!is_array($json)) {
        return $defaults;
    }

    $target = strtoupper(trim($countryCode));
    foreach ($json as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (strtoupper((string) ($row['alpha2'] ?? '')) !== $target) {
            continue;
        }

        $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
        $locale = str_replace('_', '-', trim((string) ($row['default_locale'] ?? '')));

        return [
            'currency' => ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency)) ? $currency : $defaults['currency'],
            'locale' => ($locale !== '') ? $locale : $defaults['locale'],
        ];
    }

    return $defaults;
}

$postalCode = trim((string) ($_GET['postal_code'] ?? ''));
$countryCode = strtoupper(trim((string) ($_GET['country_code'] ?? 'US')));

if ($postalCode === '') {
    Response::error('postal_code is required', null, 400);
}

if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
    $countryCode = 'US';
}

$zipData = wf_fetch_json('https://api.zippopotam.us/' . rawurlencode(strtolower($countryCode)) . '/' . rawurlencode($postalCode));
$place = (is_array($zipData['places'] ?? null) && !empty($zipData['places'][0]) && is_array($zipData['places'][0]))
    ? $zipData['places'][0]
    : null;

$resolvedCountry = strtoupper((string) ($zipData['country abbreviation'] ?? $countryCode));
$latitude = isset($place['latitude']) ? (float) $place['latitude'] : null;
$longitude = isset($place['longitude']) ? (float) $place['longitude'] : null;

$timezone = null;
$dstEnabled = true;

if ($latitude !== null && $longitude !== null) {
    $tzData = wf_fetch_json('https://timeapi.io/api/TimeZone/coordinate?latitude=' . rawurlencode((string) $latitude) . '&longitude=' . rawurlencode((string) $longitude));
    if (is_array($tzData)) {
        $timezone = trim((string) ($tzData['timeZone'] ?? '')) ?: null;
        if (array_key_exists('hasDayLightSaving', $tzData)) {
            $dstEnabled = (bool) $tzData['hasDayLightSaving'];
        }
    }
}

$locCur = wf_locale_currency_for_country($resolvedCountry ?: $countryCode);

Response::success([
    'data' => [
        'business_timezone' => $timezone ?: 'America/New_York',
        'business_dst_enabled' => $dstEnabled,
        'business_currency' => $locCur['currency'],
        'business_locale' => $locCur['locale'],
        'business_country' => $resolvedCountry ?: $countryCode,
        'business_city' => (string) ($place['place name'] ?? ''),
        'business_state' => (string) ($place['state abbreviation'] ?? ($place['state'] ?? '')),
        'source' => [
            'postal_lookup' => is_array($zipData),
            'timezone_lookup' => !empty($timezone),
        ],
    ]
]);
