# Tooltip Management Scripts

## Active Scripts (Keep These)

### Core Management
- **`apply_tooltips_v8_manual.php`** - Main script with all 521 hand-authored tooltips. Run this to apply tooltip updates to the database.
- **`make_tooltips_more_snarky.php`** - Enhancement script that makes tooltips more verbose, helpful, and snarky. Already applied, can be run again if needed.

### Quality Assurance
- **`qa_verify_tooltips.php`** - Verifies tooltip quality (length, title repetition, banned phrases). Run before/after changes.
- **`list_remaining_tooltips.php`** - Shows coverage gaps between database and apply script.

### Export/Documentation
- **`generate_tooltips_audit_md.php`** - Generates full audit markdown from database.
- **`export_all_tooltips.php`** - Exports all active tooltips to a list file.
- **`export_actionable_tooltips.php`** - Exports actionable tooltips (buttons, links, etc.).

### Utilities
- **`check-tooltips.sh`** - Shell script for quick tooltip checks.

## Workflow

1. **Make changes**: Edit `apply_tooltips_v8_manual.php` or run `make_tooltips_more_snarky.php`
2. **Apply**: `php scripts/apply_tooltips_v8_manual.php`
3. **Verify**: `php scripts/qa_verify_tooltips.php`
4. **Document**: `php scripts/generate_tooltips_audit_md.php > documentation/TOOLTIPS_AUDIT_V8_FULL.md`
5. **Refresh browser**: 
   ```javascript
   delete window.__WF_LOADED_TOOLTIPS;
   await window.__wfDebugTooltips?.();
   ```

## Current Status
- **521 tooltips** active and enhanced
- **All pass QA** (no empty, too short, title repetition, or banned phrases)
- **Enhanced for snark** on 2025-10-08 - significantly more verbose and helpful
