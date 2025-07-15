# WhimsicalFrog Codebase Audit – Phase 1 Report

*Generated: 2025-07-15 14:21 EDT*

## 1. Methodology

1. Listed every file in the repository with `find` + `stat` (see `audit_raw.txt`).
2. Applied a first-pass set of heuristics to flag likely garbage:
   • macOS metadata files (`.DS_Store`).
   • Backup/temporary extensions (`*.bak`, `*.old`, `*~`, etc.).
   • Log files outside `logs/`.
   • Obvious backup/experimental directories (`*/old`, `*/tmp`, etc.).
   • Duplicate images by SHA-1 checksum.
3. Searched for unreferenced CSS (none found in first pass).

## 2. Candidates Identified So Far

| Path | Reason | Proposed backup destination |
|------|--------|-----------------------------|
| `./sections/.DS_Store` | macOS metadata | `backups/metadata/sections/.DS_Store` |
| `./.DS_Store` | macOS metadata | `backups/metadata/.DS_Store` |
| `./css/.DS_Store` | macOS metadata | `backups/metadata/css/.DS_Store` |
| `./images/.DS_Store` | macOS metadata | `backups/metadata/images/.DS_Store` |
| `./images/items/.DS_Store` | macOS metadata | `backups/metadata/images/items/.DS_Store` |
| `./js/.DS_Store` | macOS metadata | `backups/metadata/js/.DS_Store` |
| `./backups/.DS_Store` | macOS metadata | `backups/metadata/backups/.DS_Store` |
| `./backups/css/.DS_Store` | macOS metadata | `backups/metadata/backups/css/.DS_Store` |

_No duplicate images, stray logs, unreferenced CSS/JS/PHP files, or orphaned images were detected in the deeper scan._

## 3. Next Steps

1. Continue scanning for:
   • Unreferenced JavaScript and PHP includes.
   • Orphaned images not referenced in HTML/PHP/CSS.
   • Any lingering backup or temp files with other extensions.
2. Update this report with additional findings.
3. After your approval, move listed files to the specified locations under `backups/`.

---
_Feel free to comment directly in this document or instruct me to proceed with cleanup for any/all items._
