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

function wf_timezone_options(): array
{
    $json = wf_fetch_json('https://raw.githubusercontent.com/dmfilipenko/timezones.json/master/timezones.json');
    $zones = [];

    if (is_array($json)) {
        foreach ($json as $row) {
            if (!is_array($row) || empty($row['utc']) || !is_array($row['utc'])) {
                continue;
            }
            foreach ($row['utc'] as $iana) {
                $iana = trim((string) $iana);
                if ($iana === '' || strpos($iana, '/') === false || strpos($iana, 'Etc/') === 0) {
                    continue;
                }
                $zones[$iana] = $iana;
            }
        }
    }

    if (empty($zones)) {
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            if (strpos($tz, 'Etc/') === 0) {
                continue;
            }
            $zones[$tz] = $tz;
        }
    }

    ksort($zones, SORT_NATURAL | SORT_FLAG_CASE);

    $options = [];
    foreach ($zones as $tz) {
        $options[] = ['value' => $tz, 'label' => $tz];
    }

    return $options;
}

function wf_currency_locale_options(): array
{
    $json = wf_fetch_json('https://cdn.jsdelivr.net/npm/country-locale-map/countries.json');
    $currencies = [];
    $locales = [];

    if (is_array($json)) {
        foreach ($json as $row) {
            if (!is_array($row)) {
                continue;
            }

            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            if ($currency !== '' && preg_match('/^[A-Z]{3}$/', $currency)) {
                $currencies[$currency] = $currency;
            }

            $defaultLocale = trim((string) ($row['default_locale'] ?? ''));
            if ($defaultLocale !== '') {
                $locale = str_replace('_', '-', $defaultLocale);
                $locales[$locale] = $locale;
            }

            if (!empty($row['locales']) && is_array($row['locales'])) {
                foreach ($row['locales'] as $loc) {
                    $locale = str_replace('_', '-', trim((string) $loc));
                    if ($locale !== '') {
                        $locales[$locale] = $locale;
                    }
                }
            }
        }
    }

    if (empty($currencies)) {
        $currencies = ['USD' => 'USD'];
    }
    if (empty($locales)) {
        $locales = ['en-US' => 'en-US'];
    }

    ksort($currencies, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($locales, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'currencies' => array_map(fn($code) => ['value' => $code, 'label' => $code], array_values($currencies)),
        'locales' => array_map(fn($locale) => ['value' => $locale, 'label' => $locale], array_values($locales)),
    ];
}

try {
    $timezones = wf_timezone_options();
    $currencyLocale = wf_currency_locale_options();

    Response::success([
        'data' => [
            'timezones' => $timezones,
            'currencies' => $currencyLocale['currencies'],
            'locales' => $currencyLocale['locales'],
        ]
    ]);
} catch (Throwable $e) {
    Response::serverError('Failed to load localization options', ['error' => $e->getMessage()]);
}
