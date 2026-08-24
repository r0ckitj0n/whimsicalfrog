<?php

declare(strict_types=1);

/**
 * Automation token for server-to-server admin actions.
 *
 * Only WF_ADMIN_TOKEN from the environment (or a defined WF_ADMIN_TOKEN constant)
 * is accepted. The public AuthHelper::ADMIN_TOKEN / whimsical_admin_2024 value
 * is never a valid automation credential.
 */
function wf_automation_admin_token_expected(): string
{
    $expected = getenv('WF_ADMIN_TOKEN');
    if (!is_string($expected) || $expected === '') {
        $expected = (defined('WF_ADMIN_TOKEN') && WF_ADMIN_TOKEN) ? (string) WF_ADMIN_TOKEN : '';
    }

    // Never honor the public JS/PHP fallback token, even if it was copied into env.
    if ($expected === '' || $expected === 'whimsical_admin_2024') {
        return '';
    }

    return $expected;
}

function wf_automation_admin_token_valid(string $provided): bool
{
    if ($provided === '') {
        return false;
    }

    $expected = wf_automation_admin_token_expected();
    return $expected !== '' && hash_equals($expected, $provided);
}
