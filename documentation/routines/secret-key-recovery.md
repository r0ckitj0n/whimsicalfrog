# Secret encryption key recovery

Encrypted values in the `secrets` table are sealed with the filesystem key at `config/secret.key` (32 random bytes, gitignored, never deployed).

## Symptom

Checkout or admin features fail with:

`Payment configuration error: Square access token secret is unreadable (re-enter the access token in Square settings)`

Admin → System Secrets may also show **Unreadable — re-enter** for SMTP / AI keys.

## Cause

`config/secret.key` was replaced or deleted while ciphertext remained in MySQL. A new key cannot decrypt older rows. Re-saving a secret encrypts it with the *current* key (that secret becomes readable again; others stay broken until re-entered or the original key is restored).

## Recovery (preferred when the old key is available)

1. Stop deploys that touch `config/`.
2. Restore the previous `config/secret.key` from a known-good backup (same host, same era as the ciphertext).
3. Confirm with `GET /api/secrets.php?action=health` (admin session or `admin_token`): `unreadable` should be `[]`.
4. Run Square **Test Connection**.

## Recovery (when the old key is gone)

Re-enter each unreadable secret under the **current** live key:

1. **Square** — Developer Dashboard → Applications → Production → Access token. Paste into Admin → Square Configuration → Access Token → Save → Test Connection.
2. **SMTP** — Admin email settings (username + password).
3. **AI keys** — Admin AI settings for each provider marked unreadable.
4. Optionally delete orphaned unreadable rows after replacements are confirmed.

Do **not** use **Rotate Keys** until every secret is readable again. Rotation now aborts if any value fails to decrypt.

## Prevention

- `config/secret.key` is excluded from agent-safe and full deploys.
- The secret store refuses to mint a new key file when the `secrets` table already has rows.
- Maintenance/cron token helpers will not overwrite an unreadable token (that path previously amplified key loss).
