#!/usr/bin/env bash
set -euo pipefail

# Quarantine duplicate/backup files to backups/duplicates/, preserving relative paths
# Patterns handled:
#  - Files with trailing space+digits (e.g., foo 2.php, style 10.json, bar 123.js)
#  - Files ending in .bak
# Exclusions: backups/, node_modules/, vendor/, dist/, .git/

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$REPO_ROOT"

DEST_ROOT="backups/duplicates"
mkdir -p "$DEST_ROOT"

# Build find command with exclusions
# Note: use -print0 to handle spaces/newlines safely

moved=0
find . \
  \( -path './backups/*' -o -path './node_modules/*' -o -path './vendor/*' -o -path './dist/*' -o -path './.git/*' \) -prune -o \
  -type f \
  \( \
    -name '*.bak' -o \
    -name '*.bak.*' -o \
    -name '* [0-9]' -o \
    -name '* [0-9][0-9]' -o \
    -name '* [0-9][0-9][0-9]' -o \
    -name '* [0-9][0-9][0-9][0-9]' -o \
    -name '* [0-9][0-9][0-9][0-9][0-9]' -o \
    -name '* [0-9]*' \
  \) \
  -print0 | while IFS= read -r -d '' src; do
  rel="${src#./}"
  dest="$DEST_ROOT/$rel"
  dest_dir="$(dirname "$dest")"
  mkdir -p "$dest_dir"
  if command -v git >/dev/null 2>&1; then
    git mv -f "$rel" "$dest" 2>/dev/null || mv -f "$rel" "$dest"
  else
    mv -f "$rel" "$dest"
  fi
  echo "Moved: $rel -> $dest"
  moved=$((moved+1))
done

echo "Total moved: $moved"
