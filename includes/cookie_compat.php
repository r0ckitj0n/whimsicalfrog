<?php
// Cookie compatibility helper for PHP < 7.3 (no options array support)
if (!function_exists('wf_setcookie')) {
    function wf_setcookie(string $name, string $value = '', array $options = []) : bool {
        $expires = $options['expires'] ?? 0;
        $path    = $options['path'] ?? '/';
        $domain  = $options['domain'] ?? '';
        $secure  = !empty($options['secure']);
        $httponly= !empty($options['httponly']);
        $samesite= $options['samesite'] ?? '';

        // PHP >= 7.3 supports options array
        if (PHP_VERSION_ID >= 70300) {
            // Normalize SameSite capitalization
            if (is_string($samesite) && $samesite !== '') {
                $samesite = ucfirst(strtolower($samesite));
                $options['samesite'] = $samesite;
            }
            return setcookie($name, $value, $options);
        }

        // For PHP < 7.3, build header manually to support SameSite if provided
        // Note: setcookie() pre-7.3 ignores SameSite; we emulate by header when needed
        if ($samesite !== '') {
            $parts = urlencode($name) . '=' . urlencode($value);
            if ($expires) { $parts .= '; Expires=' . gmdate('D, d-M-Y H:i:s T', is_int($expires) ? $expires : strtotime((string)$expires)); }
            if ($path)    { $parts .= '; Path=' . $path; }
            if ($domain)  { $parts .= '; Domain=' . $domain; }
            if ($secure)  { $parts .= '; Secure'; }
            if ($httponly){ $parts .= '; HttpOnly'; }
            $parts .= '; SameSite=' . ucfirst(strtolower((string)$samesite));
            header('Set-Cookie: ' . $parts, false);
            return true;
        }

        // Fallback: plain setcookie signature
        return setcookie($name, $value, (int)$expires, $path, $domain, $secure, $httponly);
    }
}
