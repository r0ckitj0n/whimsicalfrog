# Routine: Codebase Hygiene & Orphan Management

This routine ensures the repository remains clean, performant, and free of unreferenced "rogue" files.

## Schedule
- **On Completion of Major Refactor:** Immediately run the scanner.
- **Weekly Audit:** Perform a full site-wide hygiene scan every Friday.

## Procedure

### 1. Run the Scanner
Execute the custom hygiene script to identify unreferenced files (PHP, TS, JS, CSS, etc.).
```bash
node scripts/repo_hygiene.mjs
```

### 2. Review Findings
- Review `orphans_to_archive.json`.
- Check `orphan_progress.json` for the `@type` of references found.
- **False Orphans:** If a file is actually needed (dynamic reference), add it to `scripts/orphan_whitelist.json`.

### 3. Archival
Move confirmed orphans to a timestamped backup directory.
```bash
# Example command
BACKUP_DIR="backups/archive_$(date +%Y%m%d)"
mkdir -p "$BACKUP_DIR"
# Then move confirmed files listed in orphans_to_archive.json into $BACKUP_DIR
```

### 4. Build Verification
Always verify the integrity of the remaining code.
```bash
npm run build
npx tsc --noEmit
```

## Reference Standards
- **Exclusion Dirs:** `node_modules`, `dist`, `.agent`, `.git`, `logs`, `backups`.
- **Target Extensions:** `.php`, `.ts`, `.tsx`, `.js`, `.cjs`, `.xjs`, `.css`, `.json`, `.log`, `.mjs`, `.py`, `.md`.

## Whitelist Management
The whitelist (`scripts/orphan_whitelist.json`) is the single source of truth for intentional orphans.
- **Requirement:** Every entry must have a comment/reason prefix.
