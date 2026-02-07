#!/bin/bash
set -euo pipefail

# auto_deploy_on_push.sh
# Git pre-push helper:
# - Auto-deploys to live when a tracked branch is pushed.
#
# Environment overrides:
#   WF_AUTO_DEPLOY_ON_PUSH=1          # enable/disable
#   WF_AUTO_DEPLOY_BRANCHES=main      # comma-separated branch names
#   WF_AUTO_DEPLOY_MODE=lite          # lite|full|dist-only|env-only
#   WF_AUTO_DEPLOY_DRY_RUN=1          # print actions only
#   WF_AUTO_DEPLOY_SKIP_BUILD=0       # pass --skip-build to deploy.sh when 1

REMOTE_NAME="${1:-origin}"
REMOTE_URL="${2:-}"
DEPLOY_ENABLED="${WF_AUTO_DEPLOY_ON_PUSH:-1}"
DEPLOY_BRANCHES="${WF_AUTO_DEPLOY_BRANCHES:-main}"
DEPLOY_MODE="${WF_AUTO_DEPLOY_MODE:-lite}"
DRY_RUN="${WF_AUTO_DEPLOY_DRY_RUN:-0}"
SKIP_BUILD="${WF_AUTO_DEPLOY_SKIP_BUILD:-0}"

log() {
  printf '[auto-deploy] %s\n' "$1"
}

if [ "$DEPLOY_ENABLED" != "1" ]; then
  log "Auto-deploy disabled (WF_AUTO_DEPLOY_ON_PUSH=$DEPLOY_ENABLED)."
  exit 0
fi

CURRENT_BRANCH="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
if [ -z "$CURRENT_BRANCH" ]; then
  log "Detached HEAD; skipping deploy."
  exit 0
fi

is_tracked_branch=0
IFS=',' read -r -a BRANCH_LIST <<< "$DEPLOY_BRANCHES"
for branch in "${BRANCH_LIST[@]}"; do
  branch_trimmed="$(echo "$branch" | tr -d '[:space:]')"
  if [ "$CURRENT_BRANCH" = "$branch_trimmed" ]; then
    is_tracked_branch=1
    break
  fi
done

if [ "$is_tracked_branch" -ne 1 ]; then
  log "Branch '$CURRENT_BRANCH' not in deploy set ($DEPLOY_BRANCHES); skipping."
  exit 0
fi

refs_source() {
  if [ -n "${WF_PUSH_REFS_FILE:-}" ] && [ -f "${WF_PUSH_REFS_FILE}" ]; then
    cat "${WF_PUSH_REFS_FILE}"
  else
    cat
  fi
}

pushing_tracked_branch=0
while read -r local_ref local_sha remote_ref remote_sha; do
  [ -z "${local_ref:-}" ] && continue
  if [ "${local_sha:-}" = "0000000000000000000000000000000000000000" ]; then
    continue
  fi
  case "$remote_ref" in
    "refs/heads/$CURRENT_BRANCH")
      pushing_tracked_branch=1
      ;;
  esac
done < <(refs_source)

if [ "$pushing_tracked_branch" -ne 1 ]; then
  log "Push does not update refs/heads/$CURRENT_BRANCH; skipping deploy."
  exit 0
fi

DEPLOY_ARGS=()
case "$DEPLOY_MODE" in
  lite) DEPLOY_ARGS+=(--lite) ;;
  full) DEPLOY_ARGS+=(--full) ;;
  dist-only) DEPLOY_ARGS+=(--dist-only) ;;
  env-only) DEPLOY_ARGS+=(--env-only) ;;
  *)
    log "Unknown WF_AUTO_DEPLOY_MODE='$DEPLOY_MODE'. Use lite|full|dist-only|env-only."
    exit 1
    ;;
esac

if [ "$SKIP_BUILD" = "1" ]; then
  DEPLOY_ARGS+=(--skip-build)
fi

log "Auto-deploying for branch '$CURRENT_BRANCH' (remote: $REMOTE_NAME, url: $REMOTE_URL)"
log "Command: ./scripts/deploy.sh ${DEPLOY_ARGS[*]}"

if [ "$DRY_RUN" = "1" ]; then
  log "Dry run enabled; skipping deploy."
  exit 0
fi

./scripts/deploy.sh "${DEPLOY_ARGS[@]}"
log "Deploy completed."
