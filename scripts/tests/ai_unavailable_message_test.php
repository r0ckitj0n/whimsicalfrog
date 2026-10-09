<?php
/**
 * Asserts the add-item AI failure copy stays non-blocking and specific.
 * Run: php scripts/tests/ai_unavailable_message_test.php
 */

require_once __DIR__ . '/../../includes/ai/helpers/AiUnavailableMessage.php';

$failures = 0;

function expect_contains($label, $haystack, $needle)
{
    global $failures;
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL {$label}: missing [{$needle}]\n  got: {$haystack}\n");
        $failures++;
        return;
    }
    fwrite(STDOUT, "ok {$label}\n");
}

function expect_absent($label, $haystack, $needle)
{
    global $failures;
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL {$label}: unexpectedly contains [{$needle}]\n  got: {$haystack}\n");
        $failures++;
        return;
    }
    fwrite(STDOUT, "ok {$label}\n");
}

$decrypt = wf_ai_unavailable_user_message(
    "Primary provider 'openai' failed: The saved OpenAI API key is stored but cannot be decrypted.",
    true
);
expect_contains('decrypt keeps photo saved', $decrypt, 'Your photo is already saved');
expect_contains('decrypt tells Jon to re-save', $decrypt, 'paste each key again');
expect_contains('decrypt forbids a new key', $decrypt, 'Do not generate a new encryption key');
expect_contains('decrypt names Create', $decrypt, 'click Create');

$missing = wf_ai_unavailable_user_message('The OpenAI API key is not saved.', true);
expect_contains('missing key asks to add it', $missing, 'paste the OpenAI API key');
expect_absent('missing key does not claim decrypt', $missing, 'can no longer be read');

$billing = wf_ai_unavailable_user_message('API error (HTTP 429): insufficient_quota', true);
expect_contains('billing names quota', $billing, 'billing or quota');
expect_contains('billing keeps create', $billing, 'click Create');
expect_absent('billing does not ask to paste a key', $billing, 'paste each key again');

$generic = wf_ai_unavailable_user_message('model_not_found', true);
expect_contains('generic still allows create', $generic, 'click Create');
expect_absent('generic does not invent a key step', $generic, 'paste');

$unsaved = wf_ai_unavailable_user_message('cannot be decrypted', false);
expect_absent('unsaved photo is not claimed saved', $unsaved, 'Your photo is already saved');
expect_contains('unsaved photo still explains the key', $unsaved, 'can no longer be read');

if ($failures > 0) {
    fwrite(STDERR, "{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "all assertions passed\n");
