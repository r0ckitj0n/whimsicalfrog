#!/usr/bin/env bash
# Security-only live deploy.
#
# Live has precedence for normal content. This mode force-uploads ONLY the
# allowlisted auth/session security files and does not mirror, delete, or
# touch images/dist/database/.env.
#
# Usage:
#   bash scripts/deploy_security_only.sh
#   bash scripts/deploy_security_only.sh --dry-run
#   WF_DRY_RUN=1 bash scripts/deploy_security_only.sh
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

ENV_FILE="${ROOT_DIR}/.env"
if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "$ENV_FILE"
  set +a
fi

ALLOWLIST_FILE="${WF_SECURITY_ALLOWLIST_FILE:-${ROOT_DIR}/scripts/deploy/security_allowlist.txt}"
HOST="${WF_DEPLOY_HOST:-}"
USER="${WF_DEPLOY_USER:-}"
PASS="${WF_DEPLOY_PASS:-}"
DRY_RUN="${WF_DRY_RUN:-0}"
PUBLIC_BASE="${WF_PUBLIC_BASE:-}"
DEPLOY_BASE_URL="${DEPLOY_BASE_URL:-${WF_DEPLOY_BASE_URL:-}}"
BASE_URL="${DEPLOY_BASE_URL}${PUBLIC_BASE}"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

while [[ $# -gt 0 ]]; do
  case "$1" in
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -h|--help)
      cat <<'EOF'
Security-only live deploy.

Live has precedence for normal content. This mode force-uploads ONLY the
allowlisted auth/session security files and does not mirror, delete, or
touch images/dist/database/.env.

Usage:
  bash scripts/deploy_security_only.sh
  bash scripts/deploy_security_only.sh --dry-run
  WF_DRY_RUN=1 bash scripts/deploy_security_only.sh
EOF
      exit 0
      ;;
    *)
      echo -e "${RED}Unknown argument: $1${NC}" >&2
      exit 2
      ;;
  esac
done

require_var() {
  local key="$1"
  if [[ -z "${!key:-}" ]]; then
    echo -e "${RED}Error: $key must be set (in environment or .env).${NC}" >&2
    exit 1
  fi
}

require_var WF_DEPLOY_HOST
require_var WF_DEPLOY_USER
require_var WF_DEPLOY_PASS

if [[ ! -f "$ALLOWLIST_FILE" ]]; then
  echo -e "${RED}Missing allowlist: $ALLOWLIST_FILE${NC}" >&2
  exit 1
fi

mapfile -t ALLOWED_FILES < <(
  grep -E -v '^\s*(#|$)' "$ALLOWLIST_FILE" | sed 's/[[:space:]]*$//'
)

if [[ ${#ALLOWED_FILES[@]} -eq 0 ]]; then
  echo -e "${RED}Allowlist is empty: $ALLOWLIST_FILE${NC}" >&2
  exit 1
fi

MISSING=0
for rel in "${ALLOWED_FILES[@]}"; do
  if [[ "$rel" == /* || "$rel" == *..* ]]; then
    echo -e "${RED}Refusing unsafe allowlist path: $rel${NC}" >&2
    MISSING=1
    continue
  fi
  # Never allowlist live-owned / secret paths (even if someone edits the list).
  case "$rel" in
    .env|.env.*|config/secret.key|config/my.cnf|private/*|*/private/*|images/*|uploads/*|sessions/*|backups/*|logs/*|vendor/*|node_modules/*|.git/*)
      echo -e "${RED}Refusing protected path in security allowlist: $rel${NC}" >&2
      MISSING=1
      continue
      ;;
  esac
  if [[ ! -f "$ROOT_DIR/$rel" ]]; then
    echo -e "${RED}Missing local file from allowlist: $rel${NC}" >&2
    MISSING=1
  fi
done
if [[ "$MISSING" -ne 0 ]]; then
  exit 1
fi

echo -e "${GREEN}🔒 Security-only deploy (live precedence for everything else)${NC}"
echo -e "  • Allowlist: ${ALLOWLIST_FILE}"
echo -e "  • Files: ${#ALLOWED_FILES[@]}"
echo -e "  • Target: ${HOST}"
echo -e "  • Never touches: images/, uploads/, .env, config/secret.key, private/, sessions/, backups/, dist/"
if [[ "$DRY_RUN" = "1" ]]; then
  echo -e "${YELLOW}DRY-RUN: no SFTP uploads will be performed${NC}"
fi

# Unique parent dirs for allowlisted files (relative to SFTP chroot / site root).
PARENT_DIRS=()
for rel in "${ALLOWED_FILES[@]}"; do
  remote_dir="$(dirname "$rel")"
  if [[ "$remote_dir" == "." ]]; then
    continue
  fi
  skip=0
  for existing in "${PARENT_DIRS[@]:-}"; do
    if [[ "$existing" == "$remote_dir" ]]; then
      skip=1
      break
    fi
  done
  if [[ "$skip" -eq 0 ]]; then
    PARENT_DIRS+=("$remote_dir")
  fi
done

LFTP_FILE="$(mktemp -t wf-security-deploy.XXXXXX)"
cleanup() { rm -f "$LFTP_FILE"; }
trap cleanup EXIT

{
  echo "set sftp:auto-confirm yes"
  echo "set ssl:verify-certificate no"
  # IONOS/1&1 SFTP returns "mkdir: Access failed: Failure" when the directory
  # already exists. That is normal for api/ and includes/ — do not abort.
  echo "set cmd:fail-exit no"
  echo "open sftp://${USER}:${PASS}@${HOST}"
  for remote_dir in "${PARENT_DIRS[@]:-}"; do
    echo "mkdir -p ${remote_dir}"
  done
  # Puts must still fail the job if an upload fails.
  echo "set cmd:fail-exit yes"
  for rel in "${ALLOWED_FILES[@]}"; do
    echo "put ${rel} -o ${rel}"
  done
  echo "bye"
} > "$LFTP_FILE"

echo -e "${GREEN}Uploading allowlisted security files (force overwrite, no deletes)...${NC}"
for rel in "${ALLOWED_FILES[@]}"; do
  echo "  • $rel"
done

if [[ "$DRY_RUN" = "1" ]]; then
  echo -e "${YELLOW}DRY-RUN: Skipping lftp upload${NC}"
else
  mkdir -p logs
  if ! lftp -f "$LFTP_FILE"; then
    echo -e "${RED}Security-only upload failed.${NC}" >&2
    exit 1
  fi
fi

echo -e "${GREEN}🔍 Spot-checking live endpoints...${NC}"
if [[ -z "$DEPLOY_BASE_URL" ]]; then
  echo -e "${YELLOW}Skipping HTTP spot-check (set DEPLOY_BASE_URL or WF_DEPLOY_BASE_URL to enable).${NC}"
else
  WHOAMI_CODE="$(curl -sS -o /dev/null -w "%{http_code}" "${BASE_URL}/api/whoami.php" || true)"
  HOME_CODE="$(curl -sS -o /dev/null -w "%{http_code}" "${BASE_URL}/" || true)"
  echo "  • /api/whoami.php -> HTTP ${WHOAMI_CODE}"
  echo "  • / -> HTTP ${HOME_CODE}"

  if [[ "$WHOAMI_CODE" != "200" && "$WHOAMI_CODE" != "401" && "$WHOAMI_CODE" != "403" ]]; then
    echo -e "${YELLOW}⚠️  Unexpected whoami status (${WHOAMI_CODE}); review live auth after deploy.${NC}"
  fi
fi

echo -e "${GREEN}✅ Security-only deploy complete. Live content outside the allowlist was not touched.${NC}"
