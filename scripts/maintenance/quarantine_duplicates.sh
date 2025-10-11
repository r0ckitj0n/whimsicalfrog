#!/usr/bin/env bash
# Quarantine duplicate/backup files into backups/duplicates/ preserving paths.
# Safe to run multiple times; idempotent for already-moved files.
# Intended to run locally in pre-commit and manually for repo cleanup.

set -euo pipefail

# Repo root
REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BACKUP_DIR="$REPO_ROOT/backups/duplicates"
mkdir -p "$BACKUP_DIR"

# Flags
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --dry-run|--plan) DRY_RUN=1 ;;
  esac
done

# Glob patterns to quarantine (space-separated find -name patterns)
# - Files with trailing " 2" or " 3" before extension
# - Common backup suffixes
# - Editor temporary files
PATTERNS=(
  "* 2.*" "* 3.*" "*.bak" "*.backup" "*.orig" "*.old" "*~"
)

# Excludes
EXCLUDES=(
  "$REPO_ROOT/backups" "$REPO_ROOT/node_modules" "$REPO_ROOT/dist" "$REPO_ROOT/vendor" "$REPO_ROOT/.git"
)

moved_any=0
moved_list=()
planned_count=0
actual_count=0

is_excluded() { local p="$1"; for e in "${EXCLUDES[@]}"; do [[ "$p" == "$e"* ]] && return 0; done; return 1; }

# Build find command dynamically
mapfile -t found < <(
  (
    IFS=$'\n'
    for pat in "${PATTERNS[@]}"; do
      find "$REPO_ROOT" -type f -name "$pat" 2>/dev/null
    done
  ) | sort -u
)

for abs in "${found[@]}"; do
  [[ -z "$abs" ]] && continue
  is_excluded "$abs" && continue
  # Skip files already under backups/duplicates
  [[ "$abs" == "$BACKUP_DIR"* ]] && continue

  rel="${abs#$REPO_ROOT/}"
  dest="$BACKUP_DIR/$rel"
  dest_dir="$(dirname "$dest")"
  mkdir -p "$dest_dir"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    planned_count=$((planned_count+1))
    moved_any=1
    moved_list+=("PLAN: $rel -> ${dest#$REPO_ROOT/}")
    continue
  fi

  # Prefer git mv if file is tracked to preserve history
  if git -C "$REPO_ROOT" ls-files --error-unmatch "$rel" >/dev/null 2>&1; then
    if git -C "$REPO_ROOT" mv -f "$rel" "$dest" >/dev/null 2>&1; then
      actual_count=$((actual_count+1))
    else
      mv -f "$abs" "$dest" && actual_count=$((actual_count+1))
    fi
  else
    mv -f "$abs" "$dest" && actual_count=$((actual_count+1))
  fi
  moved_any=1
  moved_list+=("$rel -> ${dest#$REPO_ROOT/}")

done

# Stage changes if any
if [[ "$moved_any" -eq 1 ]]; then
  if [[ "$DRY_RUN" -eq 0 ]]; then
    git -C "$REPO_ROOT" add -A "$BACKUP_DIR" >/dev/null 2>&1 || true
    # Remove any now-missing paths from the index (in case mv fallback happened)
    git -C "$REPO_ROOT" add -A >/dev/null 2>&1 || true
  fi

  printf "Quarantined duplicate/backup files to backups/duplicates/:\n" >&2
  for m in "${moved_list[@]}"; do printf " - %s\n" "$m" >&2; done
  if [[ "$DRY_RUN" -eq 1 ]]; then
    printf "Quarantine summary: planned=%d moved=0\n" "$planned_count" >&2
  else
    printf "Quarantine summary: planned=%d moved=%d\n" "$planned_count" "$actual_count" >&2
  fi
fi

exit 0
