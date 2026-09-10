#!/usr/bin/env bash
# Explicitly push room backgrounds to live. Opt-in only.
#
# WHY THIS EXISTS
# Agent checkouts often lack images/backgrounds/realistic/ and only have older
# cartoon background-room*.webp fallbacks. Normal deploys must NEVER overwrite
# live realistic media with those stale files. When we intentionally restore
# realistic assets, use this script with WF_ALLOW_BACKGROUND_PUSH=1.
#
# Usage:
#   WF_ALLOW_BACKGROUND_PUSH=1 bash scripts/push_live_backgrounds.sh
#   WF_ALLOW_BACKGROUND_PUSH=1 bash scripts/push_live_backgrounds.sh --dry-run
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
DRY_RUN=0

if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

if [[ "${WF_ALLOW_BACKGROUND_PUSH:-0}" != "1" ]]; then
  echo "Refusing to push backgrounds without WF_ALLOW_BACKGROUND_PUSH=1" >&2
  echo "This guard exists so agents cannot overwrite live realistic images with stale checkout copies." >&2
  exit 2
fi

if [[ -z "$HOST" || -z "$USER" || -z "$PASS" ]]; then
  echo "WF_DEPLOY_HOST/USER/PASS required" >&2
  exit 1
fi

if [[ ! -d images/backgrounds/realistic ]]; then
  echo "Missing images/backgrounds/realistic — pull from live first" >&2
  exit 1
fi

# Sanity: landing fallback must match realistic frogs (not the old large cartoon).
A_SIZE="$(wc -c < images/backgrounds/background-roomA.webp)"
R_SIZE="$(wc -c < images/backgrounds/realistic/realistic-roomA-frogs.webp)"
if [[ "$A_SIZE" != "$R_SIZE" ]]; then
  echo "Refusing push: background-roomA.webp ($A_SIZE) != realistic-roomA-frogs.webp ($R_SIZE)" >&2
  echo "Copy realistic assets onto the canonical fallbacks before pushing." >&2
  exit 1
fi

echo "Pushing images/backgrounds (realistic + canonical fallbacks) to live..."
# Force overwrite (no --only-newer): live may have newer mtimes on stale cartoon files
# that agents previously uploaded.
CMD="mirror --reverse --verbose --no-perms --overwrite images/backgrounds images/backgrounds"
if [[ "$DRY_RUN" == "1" ]]; then
  echo "DRY-RUN: $CMD"
  exit 0
fi

lftp -u "$USER","$PASS" "sftp://$HOST" <<EOF
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:max-retries 2
set net:timeout 30
cd $REMOTE_PATH
$CMD
bye
EOF

echo "Background push complete."
