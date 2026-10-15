#!/usr/bin/env bash
# Sync newer content FROM live → local (dev / Cloud Agent).
#
# Live is the source of truth for uploaded media and any on-server hotfixes.
# This mirror is additive / newer-only: it never deletes local files and only
# overwrites a local path when the remote mtime is newer.
#
# Required for network sync:
#   WF_DEPLOY_HOST, WF_DEPLOY_USER, WF_DEPLOY_PASS
# Optional:
#   WF_DEPLOY_PATH   - remote site root (default /)
#
# Usage:
#   scripts/cloud/sync_from_live.sh                 # images only (default)
#   scripts/cloud/sync_from_live.sh --images
#   scripts/cloud/sync_from_live.sh --code
#   scripts/cloud/sync_from_live.sh --all
#   scripts/cloud/sync_from_live.sh --images --soft  # no-op (exit 0) if creds missing
#   scripts/cloud/sync_from_live.sh --dry-run
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

log() { printf '[sync-live] %s\n' "$*"; }
warn() { printf '[sync-live][warn] %s\n' "$*" >&2; }
die() { printf '[sync-live][error] %s\n' "$*" >&2; exit 1; }

if [[ -f "$ROOT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$ROOT_DIR/.env"
  set +a
fi

DO_IMAGES=0
DO_CODE=0
SOFT=0
DRY_RUN=0
EXPLICIT=0

for arg in "$@"; do
  case "$arg" in
    --images) DO_IMAGES=1; EXPLICIT=1 ;;
    --code) DO_CODE=1; EXPLICIT=1 ;;
    --all) DO_IMAGES=1; DO_CODE=1; EXPLICIT=1 ;;
    --soft) SOFT=1 ;;
    --dry-run) DRY_RUN=1 ;;
    -h|--help) grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown argument: $arg" ;;
  esac
done

# Default: images only (safe for every Cloud Agent boot).
if [[ "$EXPLICIT" -eq 0 ]]; then
  DO_IMAGES=1
fi

HOST="${WF_DEPLOY_HOST:-}"
USER="${WF_DEPLOY_USER:-}"
PASS="${WF_DEPLOY_PASS:-}"
REMOTE_PATH="${WF_DEPLOY_PATH:-/}"
if [[ "$REMOTE_PATH" != "/" ]]; then
  REMOTE_PATH="${REMOTE_PATH%/}"
fi

if [[ -z "$HOST" || -z "$USER" || -z "$PASS" ]]; then
  msg="WF_DEPLOY_HOST/USER/PASS not set; cannot sync from live"
  if [[ "$SOFT" -eq 1 ]]; then
    warn "$msg (soft mode — skipping)"
    exit 0
  fi
  die "$msg"
fi

if ! command -v lftp >/dev/null 2>&1; then
  msg="lftp is required for live file sync"
  if [[ "$SOFT" -eq 1 ]]; then
    warn "$msg (soft mode — skipping)"
    exit 0
  fi
  die "$msg"
fi

STATE_DIR="$ROOT_DIR/.local/state"
mkdir -p "$STATE_DIR" "$ROOT_DIR/logs"
TS="$(date -u +%Y%m%dT%H%M%SZ)"
LOG_FILE="$ROOT_DIR/logs/sync_from_live_${TS}.log"
REPORT="$STATE_DIR/sync_from_live_last.json"

count_files() {
  local dir="$1"
  if [[ -d "$dir" ]]; then
    find "$dir" -type f 2>/dev/null | wc -l | tr -d ' '
  else
    echo 0
  fi
}

# Write an lftp script that mirrors one remote tree → local with live precedence.
run_mirror() {
  local remote_rel="$1"
  local local_rel="$2"
  local label="$3"

  mkdir -p "$ROOT_DIR/$local_rel"
  log "Pulling newer live → local: ${remote_rel}/ ($label)"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "DRY-RUN: would mirror ${REMOTE_PATH%/}/${remote_rel} → ${ROOT_DIR}/${local_rel}"
    return 0
  fi

  local script
  script="$(mktemp)"
  # Credentials stay in a temp file deleted immediately after; matches deploy_*.sh pattern.
  cat >"$script" <<EOF
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 45
set net:max-retries 3
set net:reconnect-interval-base 5
set cmd:fail-exit yes
open sftp://${USER}:${PASS}@${HOST}
cd ${REMOTE_PATH}
mirror --only-newer --continue --no-perms --verbose \\
  --exclude-glob .git/ \\
  --exclude-glob node_modules/ \\
  --exclude-glob vendor/ \\
  --exclude-glob backups/ \\
  --exclude-glob logs/ \\
  --exclude-glob sessions/ \\
  --exclude-glob .local/ \\
  --exclude-glob .env \\
  --exclude-glob .env.* \\
  --exclude-glob hot \\
  ${remote_rel} ${ROOT_DIR}/${local_rel}
bye
EOF

  if ! lftp -f "$script" >>"$LOG_FILE" 2>&1; then
    rm -f "$script"
    warn "Mirror failed for ${remote_rel} (see ${LOG_FILE})"
    if [[ "$SOFT" -eq 1 ]]; then
      return 0
    fi
    return 1
  fi
  rm -f "$script"
  return 0
}

# Newer-only pull for a few top-level entry files (no delete).
run_entry_files() {
  log "Pulling newer top-level entry files"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    log "DRY-RUN: would pull router.php index.html vite-proxy.php policy.php privacy.php terms.php"
    return 0
  fi

  local script
  script="$(mktemp)"
  cat >"$script" <<EOF
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 45
set net:max-retries 3
set cmd:fail-exit no
open sftp://${USER}:${PASS}@${HOST}
cd ${REMOTE_PATH}
# mirror of "." with includes is awkward; use per-file get + newer check via mirror of single names
$(for f in router.php index.html vite-proxy.php policy.php privacy.php terms.php; do
  printf 'lcd %s\n' "$ROOT_DIR"
  # Use mirror --only-newer with a single-file include from remote root.
  printf 'mirror --only-newer --continue --no-perms --verbose --include %s --exclude * . %s || true\n' "$f" "$ROOT_DIR"
done)
bye
EOF

  if ! lftp -f "$script" >>"$LOG_FILE" 2>&1; then
    rm -f "$script"
    warn "Top-level entry sync reported errors (see ${LOG_FILE})"
    if [[ "$SOFT" -eq 1 ]]; then
      return 0
    fi
    return 1
  fi
  rm -f "$script"
  return 0
}

BEFORE_IMAGES="$(count_files "$ROOT_DIR/images")"
STATUS="ok"
FAILURES=0

if [[ "$DO_IMAGES" -eq 1 ]]; then
  if ! run_mirror "images" "images" "media assets"; then
    STATUS="partial"
    FAILURES=$((FAILURES + 1))
  fi
fi

if [[ "$DO_CODE" -eq 1 ]]; then
  # Code trees that may contain live hotfixes newer than the git checkout.
  # Never pull dist/ (built artifacts) or vendor/node_modules.
  for tree in api includes src functions config scripts; do
    if ! run_mirror "$tree" "$tree" "code tree"; then
      STATUS="partial"
      FAILURES=$((FAILURES + 1))
    fi
  done
  if ! run_entry_files; then
    STATUS="partial"
    FAILURES=$((FAILURES + 1))
  fi
fi

AFTER_IMAGES="$(count_files "$ROOT_DIR/images")"
ITEMS_AFTER="$(count_files "$ROOT_DIR/images/items")"

python3 - <<PY
import json
from pathlib import Path
Path(${REPORT@Q}).write_text(json.dumps({
  "timestamp_utc": ${TS@Q},
  "status": ${STATUS@Q},
  "failures": ${FAILURES},
  "images_files_before": int(${BEFORE_IMAGES}),
  "images_files_after": int(${AFTER_IMAGES}),
  "images_items_after": int(${ITEMS_AFTER}),
  "did_images": bool(${DO_IMAGES}),
  "did_code": bool(${DO_CODE}),
  "dry_run": bool(${DRY_RUN}),
  "log_file": ${LOG_FILE@Q},
}, indent=2) + "\n", encoding="utf-8")
PY

log "Done (status=${STATUS}). images files: ${BEFORE_IMAGES} → ${AFTER_IMAGES} (items=${ITEMS_AFTER})"
log "Report: ${REPORT}"
if [[ "$FAILURES" -gt 0 && "$SOFT" -eq 0 ]]; then
  exit 1
fi
exit 0
