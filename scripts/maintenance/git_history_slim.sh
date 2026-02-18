#!/usr/bin/env bash
set -euo pipefail

# One-time git history slimming helper.
# Default is analyze-only; rewrite requires explicit confirmation.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STATE_DIR="$ROOT_DIR/.local/state/git-history"
MODE="analyze"
CONFIRM_FLAG=0
SKIP_BACKUP=0

usage() {
  cat <<'EOF'
Usage:
  scripts/maintenance/git_history_slim.sh [--analyze]
  scripts/maintenance/git_history_slim.sh --rewrite --i-understand-history-rewrite [--skip-bundle-backup]

Behavior:
  --analyze  Show current object sizes and biggest blobs.
  --rewrite  Remove generated paths from full git history (destructive rewrite).

Safety:
  - Rewrite requires a clean working tree.
  - Creates a full pre-rewrite git bundle by default.
  - Requires git-filter-repo to be installed.
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --analyze)
      MODE="analyze"
      shift
      ;;
    --rewrite)
      MODE="rewrite"
      shift
      ;;
    --i-understand-history-rewrite)
      CONFIRM_FLAG=1
      shift
      ;;
    --skip-bundle-backup)
      SKIP_BACKUP=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

cd "$ROOT_DIR"

analyze() {
  echo "=== Repository Object Stats ==="
  git count-objects -vH
  echo
  echo "=== Largest blobs (top 25) ==="
  git rev-list --objects --all \
    | git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize) %(rest)' \
    | awk '$1=="blob" {print $3"\t"$2"\t"$4}' \
    | sort -nr \
    | awk 'NR<=25 {mb=$1/1024/1024; printf "%8.2f MB\t%s\n", mb, $3}'
  echo
  cat <<'EOF'
Recommended rewrite target paths:
  logs/
  dist/
  node_modules/
  .cache/
  backups/live_sync/
  backups/local_pre_restore/
  backups/sql/
  backups/*.sql*
  backups/*.tar*
  backups/*.tgz
  backups/*.zip
EOF
}

rewrite() {
  if [[ "$CONFIRM_FLAG" -ne 1 ]]; then
    echo "Refusing rewrite without --i-understand-history-rewrite" >&2
    exit 1
  fi

  if [[ -n "$(git status --porcelain)" ]]; then
    echo "Working tree is not clean. Commit/stash changes before rewrite." >&2
    exit 1
  fi

  if ! command -v git-filter-repo >/dev/null 2>&1; then
    echo "git-filter-repo is required. Install with: brew install git-filter-repo" >&2
    exit 1
  fi

  local ts
  ts="$(date +%Y%m%d_%H%M%S)"
  mkdir -p "$STATE_DIR"

  if [[ "$SKIP_BACKUP" -ne 1 ]]; then
    local bundle="$STATE_DIR/pre-rewrite-${ts}.bundle"
    echo "Creating rollback bundle: $bundle"
    git bundle create "$bundle" --all
  fi

  local safety_branch="codex/pre-history-slim-${ts}"
  git branch "$safety_branch" >/dev/null 2>&1 || true
  echo "Safety branch created: $safety_branch"

  echo "Running git-filter-repo rewrite..."
  git filter-repo --force --invert-paths \
    --path logs \
    --path dist \
    --path node_modules \
    --path .cache \
    --path backups/live_sync \
    --path backups/local_pre_restore \
    --path backups/sql \
    --path-glob 'backups/*.sql' \
    --path-glob 'backups/*.sql.gz' \
    --path-glob 'backups/*.tar' \
    --path-glob 'backups/*.tar.gz' \
    --path-glob 'backups/*.tgz' \
    --path-glob 'backups/*.zip'

  git reflog expire --expire=now --all
  git gc --prune=now --aggressive

  echo
  echo "Rewrite complete."
  echo "Next steps:"
  echo "  1) Validate project state and run tests."
  echo "  2) Coordinate with collaborators."
  echo "  3) Force-push safely:"
  echo "     git push --force-with-lease --all"
  echo "     git push --force-with-lease --tags"
}

if [[ "$MODE" == "rewrite" ]]; then
  rewrite
else
  analyze
fi
