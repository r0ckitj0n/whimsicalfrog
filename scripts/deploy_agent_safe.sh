#!/usr/bin/env bash
# Agent-safe live deploy (backup-first, non-destructive to live data).
#
# Standing process for Cursor / Codex agents after shipping code changes:
#   1) Snapshot live targets (and optionally trigger live backup APIs) BEFORE upload
#   2) Upload only intentional code/build artifacts
#   3) NEVER delete or overwrite live-owned data directories
#   4) Never run a whole-tree mirror --delete
#
# Usage:
#   bash scripts/deploy_agent_safe.sh --frontend
#   bash scripts/deploy_agent_safe.sh --code
#   bash scripts/deploy_agent_safe.sh --paths api/foo.php src/bar.tsx
#   bash scripts/deploy_agent_safe.sh --frontend --dry-run
#   bash scripts/deploy_agent_safe.sh --frontend --skip-build
#   bash scripts/deploy_agent_safe.sh --frontend --skip-db-backup
#
# Required env (or .env): WF_DEPLOY_HOST, WF_DEPLOY_USER, WF_DEPLOY_PASS
# Optional: WF_DEPLOY_PATH (default /), WF_DEPLOY_BASE_URL, WF_ADMIN_TOKEN
#
# Never touches on remote:
#   .env, images/, backups/ (except writing new pre-deploy metadata), sessions/,
#   logs/, data/, node_modules/, .git/, vendor/
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ -f "$ROOT_DIR/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  . "$ROOT_DIR/.env"
  set +a
fi

HOST="${WF_DEPLOY_HOST:-}"
USER="${WF_DEPLOY_USER:-}"
PASS="${WF_DEPLOY_PASS:-}"
REMOTE_PATH="${WF_DEPLOY_PATH:-/}"
BASE_URL="${WF_DEPLOY_BASE_URL:-}"
ADMIN_TOKEN="${WF_ADMIN_TOKEN:-${WF_DEPLOY_ADMIN_TOKEN:-}}"

MODE=""
DRY_RUN=0
SKIP_BUILD=0
SKIP_DB_BACKUP=0
PATH_ARGS=()

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

usage() {
  sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --frontend) MODE="frontend"; shift ;;
    --code) MODE="code"; shift ;;
    --paths)
      MODE="paths"
      shift
      while [[ $# -gt 0 && "$1" != --* ]]; do
        PATH_ARGS+=("$1")
        shift
      done
      ;;
    --dry-run) DRY_RUN=1; shift ;;
    --skip-build) SKIP_BUILD=1; shift ;;
    --skip-db-backup) SKIP_DB_BACKUP=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *)
      echo -e "${RED}Unknown argument: $1${NC}" >&2
      usage >&2
      exit 2
      ;;
  esac
done

if [[ -z "$MODE" ]]; then
  echo -e "${RED}Choose a mode: --frontend | --code | --paths <files...>${NC}" >&2
  exit 2
fi

require_var() {
  local key="$1"
  if [[ -z "${!key:-}" ]]; then
    echo -e "${RED}Error: $key must be set (environment or .env).${NC}" >&2
    exit 1
  fi
}

require_var HOST
require_var USER
require_var PASS

TS="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="$ROOT_DIR/backups/pre-deploy/$TS"
mkdir -p "$BACKUP_DIR/live" "$BACKUP_DIR/meta" "$ROOT_DIR/logs"
MANIFEST="$BACKUP_DIR/meta/manifest.txt"
REPORT="$BACKUP_DIR/meta/report.txt"
LFTP_LOG="$ROOT_DIR/logs/deploy_agent_safe_${TS}.log"
: >"$REPORT"
: >"$MANIFEST"

log() { printf '%s\n' "$*" | tee -a "$REPORT"; }
logc() { echo -e "$1$2${NC}" | tee -a "$REPORT"; }

is_protected_path() {
  local rel="${1#./}"
  case "$rel" in
    .env|.env.*|images|images/*|backups|backups/*|sessions|sessions/*|logs|logs/*|data|data/*|node_modules|node_modules/*|.git|.git/*|vendor|vendor/*)
      return 0
      ;;
  esac
  return 1
}

remote_prefix_cmd() {
  if [[ "$REMOTE_PATH" == "/" || -z "$REMOTE_PATH" ]]; then
    echo ""
  else
    echo "cd $REMOTE_PATH"
  fi
}

run_lftp() {
  local body="$1"
  local tmp
  tmp="$(mktemp)"
  cat >"$tmp" <<EOF
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 60
set net:max-retries 3
set cmd:fail-exit yes
$(remote_prefix_cmd)
$body
bye
EOF
  if [[ "$DRY_RUN" == "1" ]]; then
    logc "$YELLOW" "DRY-RUN lftp:"
    echo "lftp -u \$USER,*** sftp://\$HOST" | tee -a "$REPORT"
    tee -a "$REPORT" <"$tmp"
    rm -f "$tmp"
    return 0
  fi
  if ! lftp -u "$USER","$PASS" "sftp://$HOST" <"$tmp" >>"$LFTP_LOG" 2>&1; then
    rm -f "$tmp"
    return 1
  fi
  rm -f "$tmp"
  return 0
}

BACKUP_PATHS=()
case "$MODE" in
  frontend)
    BACKUP_PATHS+=(dist index.html src/components/modals/room/RoomHeader.tsx)
    ;;
  code)
    BACKUP_PATHS+=(api includes src functions config index.html router.php)
    ;;
  paths)
    BACKUP_PATHS+=("${PATH_ARGS[@]}")
    ;;
esac

logc "$GREEN" "==> Pre-deploy backup $TS"
log "Local snapshot dir: $BACKUP_DIR"
{
  echo "ts=$TS"
  echo "mode=$MODE"
  echo "git=$(git rev-parse HEAD 2>/dev/null || echo unknown)"
  echo "branch=$(git branch --show-current 2>/dev/null || echo unknown)"
  echo "remote=$HOST:$REMOTE_PATH"
} >"$BACKUP_DIR/meta/context.txt"

for rel in "${BACKUP_PATHS[@]}"; do
  [[ -z "$rel" ]] && continue
  if is_protected_path "$rel"; then
    echo -e "${RED}Refusing to deploy/backup-overwrite protected path in deploy set: $rel${NC}" >&2
    exit 2
  fi
  echo "$rel" >>"$MANIFEST"
  local_target="$BACKUP_DIR/live/$rel"
  mkdir -p "$(dirname "$local_target")"
  if [[ -d "$ROOT_DIR/$rel" || "$rel" == "dist" || "$rel" == "api" || "$rel" == "includes" || "$rel" == "src" || "$rel" == "functions" || "$rel" == "config" ]]; then
    mkdir -p "$local_target"
    log "Backing up remote directory: $rel"
    run_lftp "mirror --verbose --no-perms $rel $local_target" \
      || logc "$YELLOW" "Warning: incomplete backup for remote dir $rel"
  else
    log "Backing up remote file: $rel"
    run_lftp "get $rel -o $local_target" \
      || logc "$YELLOW" "Warning: remote file missing (will be created): $rel"
  fi
done

if [[ "$SKIP_DB_BACKUP" != "1" && -n "$ADMIN_TOKEN" && -n "$BASE_URL" ]]; then
  log "Triggering live backup APIs..."
  if [[ "$DRY_RUN" == "1" ]]; then
    log "DRY-RUN: would call backup_database.php and backup_website.php"
  else
    if curl -fsS -X POST \
      "${BASE_URL%/}/api/backup_database.php?admin_token=${ADMIN_TOKEN}" \
      -o "$BACKUP_DIR/meta/backup_database_response.json" \
      --max-time 180; then
      logc "$GREEN" "Live DB backup API OK"
    else
      logc "$YELLOW" "Live DB backup API unavailable; continuing with file snapshot"
    fi
    if curl -fsS -X POST \
      "${BASE_URL%/}/api/backup_website.php?admin_token=${ADMIN_TOKEN}" \
      -o "$BACKUP_DIR/meta/backup_website_response.json" \
      --max-time 180; then
      logc "$GREEN" "Live website backup API OK"
    else
      logc "$YELLOW" "Live website backup API unavailable; file snapshot retained"
    fi
  fi
else
  logc "$YELLOW" "Skipping live API backups (need WF_ADMIN_TOKEN + WF_DEPLOY_BASE_URL, or --skip-db-backup)"
fi

log "Recording backup metadata on live under backups/pre-deploy/$TS (additive only)"
run_lftp "mkdir -p backups/pre-deploy/$TS
put $BACKUP_DIR/meta/context.txt -o backups/pre-deploy/$TS/context.txt
put $MANIFEST -o backups/pre-deploy/$TS/manifest.txt" \
  || logc "$YELLOW" "Could not write remote backup metadata (local snapshot still kept)"

if [[ "$MODE" == "frontend" && "$SKIP_BUILD" != "1" ]]; then
  logc "$GREEN" "==> Building production frontend"
  if [[ "$DRY_RUN" == "1" ]]; then
    log "DRY-RUN: npm run build"
  else
    npm run build
  fi
fi

if [[ "$MODE" == "frontend" ]]; then
  if [[ ! -s dist/index.html ]]; then
    echo -e "${RED}Missing dist/index.html${NC}" >&2
    exit 1
  fi
  if [[ ! -s dist/.vite/manifest.json && ! -s dist/manifest.json ]]; then
    echo -e "${RED}Missing Vite manifest under dist/${NC}" >&2
    exit 1
  fi
fi

logc "$GREEN" "==> Uploading (live data dirs protected)"

upload_tree_no_delete() {
  local src="$1"
  local dst="$2"
  log "Mirror NO-DELETE: $src -> remote:$dst"
  run_lftp "mirror --reverse --verbose --no-perms --overwrite \
    --exclude-glob .env \
    --exclude-glob .env.* \
    --exclude-glob images/** \
    --exclude-glob backups/** \
    --exclude-glob sessions/** \
    --exclude-glob logs/** \
    --exclude-glob data/** \
    --exclude-glob node_modules/** \
    --exclude-glob .git/** \
    --exclude-glob vendor/** \
    $src $dst"
}

upload_file() {
  local src="$1"
  local dst="$2"
  if [[ ! -f "$src" ]]; then
    logc "$YELLOW" "Skip missing local file: $src"
    return 0
  fi
  log "Put: $src -> remote:$dst"
  run_lftp "put $src -o $dst"
}

case "$MODE" in
  frontend)
    # Scoped --delete only inside dist/ so stale hashed bundles are cleaned.
    # Never touches images/, .env, backups/, etc.
    log "Syncing dist/ (overwrite + delete only inside dist/)"
    run_lftp "mirror --reverse --verbose --no-perms --overwrite --delete \
      --exclude-glob .env \
      --exclude-glob images/** \
      dist dist"
    upload_file "dist/index.html" "index.html"
    if [[ -f src/components/modals/room/RoomHeader.tsx ]]; then
      run_lftp "mkdir -p src/components/modals/room" || true
      upload_file "src/components/modals/room/RoomHeader.tsx" "src/components/modals/room/RoomHeader.tsx"
    fi
    ;;
  code)
    for d in api includes src functions config; do
      [[ -d "$d" ]] || continue
      upload_tree_no_delete "$d" "$d"
    done
    # Prefer built SPA shell so --code cannot clobber landing/settings boot fixes
    # that only exist after vite rewrites preloads into dist/index.html.
    if [[ -s dist/index.html ]]; then
      upload_file "dist/index.html" "index.html"
      upload_file "dist/index.html" "dist/index.html"
    else
      upload_file "index.html" "index.html"
    fi
    upload_file "router.php" "router.php"
    ;;
  paths)
    if [[ ${#PATH_ARGS[@]} -eq 0 ]]; then
      echo -e "${RED}--paths requires at least one relative path${NC}" >&2
      exit 2
    fi
    for rel in "${PATH_ARGS[@]}"; do
      if is_protected_path "$rel"; then
        echo -e "${RED}Refusing protected path: $rel${NC}" >&2
        exit 2
      fi
      if [[ -d "$rel" ]]; then
        upload_tree_no_delete "$rel" "$rel"
      else
        run_lftp "mkdir -p $(dirname "$rel")" || true
        upload_file "$rel" "$rel"
      fi
    done
    ;;
esac

logc "$GREEN" "==> Verification"
if [[ -n "$BASE_URL" && "$DRY_RUN" != "1" ]]; then
  HOME_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "${BASE_URL%/}/" || true)"
  log "Homepage HTTP $HOME_CODE"
  MANIFEST_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "${BASE_URL%/}/dist/.vite/manifest.json" || true)"
  log "Manifest HTTP $MANIFEST_CODE"
  if [[ "$MODE" == "frontend" && "$MANIFEST_CODE" == "200" ]]; then
    curl -fsS "${BASE_URL%/}/dist/.vite/manifest.json" -o "$BACKUP_DIR/meta/live_manifest_after.json" || true
    python3 - <<'PY' "$BACKUP_DIR/meta/live_manifest_after.json" "$BASE_URL" "$BACKUP_DIR/meta" || true
import json, sys, urllib.request
from pathlib import Path
manifest_path, base, outdir = sys.argv[1:4]
base = base.rstrip("/")
try:
    manifest = json.load(open(manifest_path))
except Exception as e:
    print("manifest read failed", e)
    sys.exit(0)
file = None
for k, v in manifest.items():
    if not isinstance(v, dict):
        continue
    f = v.get("file", "")
    if "RoomModal" in k or "RoomModal" in f:
        file = f
        break
if not file:
    print("RoomModal chunk not found in live manifest")
    sys.exit(0)
body = urllib.request.urlopen(f"{base}/dist/{file}", timeout=30).read().decode("utf-8", "ignore")
Path(outdir, "room_modal_probe.txt").write_text(
    f"file={file}\nhas_back={('Back to Main Room' in body)}\nhas_close_class={('btn-icon--close' in body)}\n"
)
print(f"probed {file}: Back to Main Room={('Back to Main Room' in body)} btn-icon--close={('btn-icon--close' in body)}")
PY
  fi
else
  log "Skipping HTTP verify (missing WF_DEPLOY_BASE_URL or dry-run)"
fi

logc "$GREEN" "==> Done"
log "Local rollback snapshot: $BACKUP_DIR"
log "lftp log: $LFTP_LOG"
echo
echo -e "${GREEN}Agent-safe deploy finished.${NC}"
echo "Pre-deploy backup: $BACKUP_DIR"
echo "Protected live paths were not deleted: images/, backups/, sessions/, logs/, data/, .env"
