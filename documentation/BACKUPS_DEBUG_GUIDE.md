# BACKUPS_DEBUG_GUIDE

This guide explains where debug-related artifacts now live, how to temporarily restore them for testing, and how to archive them again to keep the live tree clean.

## Purpose
- **Declutter live site structure** by removing ad-hoc debug utilities from top-level paths.
- **Preserve history and access** by keeping them under `documentation/debug-archive/` rather than deleting.

## Where things are now
- **Archived debug files**: `documentation/debug-archive/`
  - Examples: `debug-payment-modal.js`, `debug-room-main.html`, `debug-shipping*.js|php`, `debug-api-response.js`, `debug_dashboard.php`
- **Archived API debug endpoint**: `documentation/debug-archive/api/debug_session.php`
- **Curl helpers** (not debug, but moved for organization): `scripts/curl_*.sh`

## Using curl helpers
Run from the `scripts/` folder (BASE already set to `http://localhost:8080`):
```bash
bash scripts/curl_get_items.sh
bash scripts/curl_get_items.sh "T-Shirts"
```

## Temporarily restoring debug artifacts
If you need to use a debug file in its original location, choose one of the options below.

### Option A: Temporary symlink (recommended)
- Keeps the repo clean and easy to revert; avoid committing symlinks.
- Example (restore a root HTML/JS page):
```bash
mkdir -p debug
ln -s ../documentation/debug-archive/debug-room-main.html debug/debug-room-main.html
```
- Example (restore API endpoint under `api/`):
```bash
ln -s ../documentation/debug-archive/api/debug_session.php api/debug_session.php
```
- When done:
```bash
rm debug/debug-room-main.html
rm api/debug_session.php
rmdir debug 2>/dev/null || true
```

### Option B: Move into place for an active session (commit-free)
- Handy if you need the exact path and symlinks are undesirable.
```bash
git restore --source=HEAD --staged --worktree -- documentation/debug-archive/debug-room-main.html
mkdir -p debug
cp documentation/debug-archive/debug-room-main.html debug/
# Use the file, then remove
rm debug/debug-room-main.html
```

### Option C: Move with git (history preserved, but creates commits)
- Only do this on a throwaway branch or revert after testing.
```bash
git checkout -b chore/temporary-debug
git mv documentation/debug-archive/debug-room-main.html debug/
# test ...
# re-archive when finished
git mv debug/debug-room-main.html documentation/debug-archive/
git commit -m "chore(debug): temporarily restore debug-room-main.html for testing"
```

## Re-archiving after use
- Ensure no restored debug files remain in live paths before committing.
- Preferred: remove symlinks or move files back into `documentation/debug-archive/` as shown above.

## Notes and conventions
- **Live tree cleanliness**: keep `api/`, root, and `sections/` free of debug-only assets.
- **Archive policy**: debug artifacts live under `documentation/debug-archive/` and `documentation/debug-archive/api/`.
- **Documentation**: This guide lives at `documentation/BACKUPS_DEBUG_GUIDE.md`.
- **Organization preferences**: Scripts belong under `scripts/`; long-term unused SQL into `backups/sql/`.

If you want a small in-folder README inside `documentation/debug-archive/`, we can add one, but the main guide should remain here for discoverability.
