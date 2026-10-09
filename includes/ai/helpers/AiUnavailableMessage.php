<?php
/**
 * User-facing copy when item photo analysis cannot run.
 * The photo upload and item save must keep working either way.
 */

function wf_ai_unavailable_user_message($exceptionMessage = '', $photoConfirmedSaved = true): string
{
    $technical = (string) $exceptionMessage;
    $keyUnreadable = (bool) preg_match('/cannot be decrypted/i', $technical);
    $keyMissing = (bool) preg_match('/API key is not saved|API key missing/i', $technical);
    $billing = (bool) preg_match('/insufficient_quota|billing|exceeded your current quota|HTTP 429\b/i', $technical);

    if ($keyUnreadable) {
        $action = 'The saved OpenAI and Google API keys are on the server but can no longer be read. Open Admin, then AI Settings, paste each key again, and save. Do not generate a new encryption key.';
    } elseif ($keyMissing) {
        $action = 'No working API key is saved. Open Admin, then AI Settings, paste the OpenAI API key, and save.';
    } elseif ($billing) {
        $action = 'OpenAI refused the request because of billing or quota. Check the OpenAI account plan and billing, then try Generate again.';
    } else {
        $action = '';
    }

    if ($photoConfirmedSaved) {
        $base = "AI auto-fill isn't available right now. Your photo is already saved. Fill in the Name and Category, then click Create.";
    } else {
        $base = "AI auto-fill isn't available right now, and the photo could not be confirmed as saved. Check the image gallery, then fill in the Name and Category and click Create.";
    }

    return $action !== '' ? $base . ' ' . $action : $base;
}
