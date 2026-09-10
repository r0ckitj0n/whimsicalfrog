#!/usr/bin/env bash
# Pull live room backgrounds into the local checkout so agents stop treating
# stale cartoon fallbacks as source-of-truth.
#
# Usage:
#   bash scripts/pull_live_backgrounds.sh
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

if [[ -z "$HOST" || -z "$USER" || -z "$PASS" ]]; then
  echo "WF_DEPLOY_HOST/USER/PASS required" >&2
  exit 1
fi

mkdir -p images/backgrounds

echo "Pulling live images/backgrounds (only-newer) into local checkout..."
lftp -u "$USER","$PASS" "sftp://$HOST" <<EOF
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:max-retries 2
set net:timeout 30
cd $REMOTE_PATH
mirror --verbose --no-perms --only-newer --overwrite images/backgrounds images/backgrounds
bye
EOF

echo "Pull complete. Local backgrounds now prefer live realistic assets."
