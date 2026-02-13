#!/bin/bash
set -euo pipefail

# push_and_deploy.sh
# Fallback workflow for local terminal use:
#   1) Push to GitHub
#   2) If push succeeds, run local deploy
#
# This is useful when GitHub Actions is unavailable or you want a manual fallback.
#
# Usage:
#   ./scripts/push_and_deploy.sh
#   ./scripts/push_and_deploy.sh --remote origin --branch main --mode lite
#   ./scripts/push_and_deploy.sh --mode full --skip-build
#   ./scripts/push_and_deploy.sh --dry-run

REMOTE="origin"
BRANCH=""
DEPLOY_MODE="lite"
SKIP_BUILD=0
DRY_RUN=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --remote)
      REMOTE="${2:-origin}"
      shift 2
      ;;
    --branch)
      BRANCH="${2:-}"
      shift 2
      ;;
    --mode)
      DEPLOY_MODE="${2:-lite}"
      shift 2
      ;;
    --skip-build)
      SKIP_BUILD=1
      shift
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -h|--help)
      sed -n '1,30p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *)
      echo "[push+deploy] Unknown argument: $1" >&2
      exit 2
      ;;
  esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

if [[ -z "$BRANCH" ]]; then
  BRANCH="$(git branch --show-current)"
fi

DEPLOY_ARGS=()
case "$DEPLOY_MODE" in
  lite) DEPLOY_ARGS+=(--lite) ;;
  code-only) DEPLOY_ARGS+=(--lite --code-only) ;;
  full) DEPLOY_ARGS+=(--full) ;;
  dist-only) DEPLOY_ARGS+=(--dist-only) ;;
  env-only) DEPLOY_ARGS+=(--env-only) ;;
  *)
    echo "[push+deploy] Invalid --mode '$DEPLOY_MODE'. Use lite|code-only|full|dist-only|env-only." >&2
    exit 2
    ;;
esac

if [[ "$SKIP_BUILD" = "1" ]]; then
  DEPLOY_ARGS+=(--skip-build)
fi

echo "[push+deploy] Remote: $REMOTE"
echo "[push+deploy] Branch: $BRANCH"
echo "[push+deploy] Deploy command: ./scripts/deploy.sh ${DEPLOY_ARGS[*]}"

if [[ "$DRY_RUN" = "1" ]]; then
  echo "[push+deploy] Dry run: would run git push --follow-tags $REMOTE $BRANCH"
  echo "[push+deploy] Dry run: would run ./scripts/deploy.sh ${DEPLOY_ARGS[*]}"
  exit 0
fi

echo "[push+deploy] Pushing to GitHub..."
git push --follow-tags "$REMOTE" "$BRANCH"

echo "[push+deploy] Push succeeded. Deploying to live..."
./scripts/deploy.sh "${DEPLOY_ARGS[@]}"

echo "[push+deploy] Done."
