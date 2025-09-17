#!/usr/bin/env bash
# WhimsicalFrog release orchestrator
#
# Purpose: One command to build, push to GitHub, and deploy to live.
#
# Usage examples:
#   scripts/release.sh --message "feat: update room modal"              # build (prod), commit, push, deploy
#   scripts/release.sh --no-deploy --message "chore: build only"         # skip deploy
#   scripts/release.sh --repo https://github.com/USER/REPO.git            # set/push to specified repo
#   scripts/release.sh --branch main --remote origin                      # customize branch/remote
#   scripts/release.sh --dry-run                                          # print steps without executing
#   scripts/release.sh --prod --scan-secrets                              # pass through to build.sh
#
# Flags:
#   --no-build        Skip build.sh
#   --no-push         Skip GitHub push
#   --no-deploy       Skip deploy
#   --message MSG     Commit message (default: chore: release)
#   --repo URL|slug   GitHub repo URL or slug (owner/name) for sync script
#   --branch NAME     Branch to push (default: main)
#   --remote NAME     Remote name (default: origin)
#   --dry-run         Show what would run without executing
#   --ci, --prod, --scan-secrets, --skip-node, --skip-php, --verbose  # forwarded to build.sh (note: --prod is default)
#
set -euo pipefail
IFS=$'\n\t'

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Defaults
DO_BUILD=true
DO_PUSH=true
DO_DEPLOY=true
DO_TAG=true
DO_GH_RELEASE=true
TAG_PREFIX="deploy"
COMMIT_MESSAGE="chore: release $(date '+%Y-%m-%d %H:%M:%S %Z')"
REPO=""
BRANCH="main"
REMOTE="origin"
DRY_RUN=false

# Build flags passthrough (default to --prod)
BUILD_ARGS=("--prod")

# Arg parsing
while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-build) DO_BUILD=false; shift ;;
    --no-push) DO_PUSH=false; shift ;;
    --no-deploy) DO_DEPLOY=false; shift ;;
    --no-tag) DO_TAG=false; shift ;;
    --no-gh-release) DO_GH_RELEASE=false; shift ;;
    --tag-prefix) TAG_PREFIX="${2:-$TAG_PREFIX}"; shift 2 ;;
    --message) COMMIT_MESSAGE="${2:-$COMMIT_MESSAGE}"; shift 2 ;;
    --repo) REPO="${2:-}"; shift 2 ;;
    --branch) BRANCH="${2:-$BRANCH}"; shift 2 ;;
    --remote) REMOTE="${2:-$REMOTE}"; shift 2 ;;
    --dry-run) DRY_RUN=true; shift ;;
    --ci|--prod|--scan-secrets|--skip-node|--skip-php|--verbose)
      BUILD_ARGS+=("$1"); shift ;;
    -h|--help)
      grep -E "^#( |$)" "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *)
      echo "Unknown argument: $1" >&2; exit 2 ;;
  esac
done

run() {
  if [ "$DRY_RUN" = true ]; then
    echo "+ $*"
  else
    eval "$@"
  fi
}

section() {
  echo -e "\n============================================================"
  echo -e "== $*"
  echo -e "============================================================\n"
}

# 1) Build
if [ "$DO_BUILD" = true ]; then
  section "Build: running ./build.sh ${BUILD_ARGS[*]:-}"
  if [ ! -x "$ROOT_DIR/build.sh" ]; then
    echo "ERROR: build.sh not found or not executable at repo root" >&2
    exit 1
  fi
  if [ "$DRY_RUN" = true ]; then
    echo "+ "$ROOT_DIR"/build.sh ${BUILD_ARGS[*]:-}"
  else
    "$ROOT_DIR"/build.sh ${BUILD_ARGS[*]:-}
  fi
else
  section "Build: skipped (--no-build)"
fi

# 2) Commit and push to GitHub
if [ "$DO_PUSH" = true ]; then
  section "Git: commit and push"
  # Commit only if changes exist
  if git status --porcelain | grep -q .; then
    if [ "$DRY_RUN" = true ]; then
      echo "+ git add -A"
      echo "+ git commit -m \"$COMMIT_MESSAGE\""
    else
      git add -A
      git commit -m "$COMMIT_MESSAGE" || true
    fi
  else
    echo "No local changes to commit"
  fi

  SYNC_CMD=("scripts/sync_to_github.sh")
  if [ -n "$REPO" ]; then SYNC_CMD+=("--repo" "$REPO"); fi
  SYNC_CMD+=("--branch" "$BRANCH" "--remote" "$REMOTE")

  if [ "$DRY_RUN" = true ]; then
    echo "+ ${SYNC_CMD[*]}"
  else
    "${SYNC_CMD[@]}"
  fi
else
  section "Git: push skipped (--no-push)"
fi

# 3) Tag and GitHub release
if [ "$DO_TAG" = true ]; then
  section "Tag: creating and pushing annotated tag"
  TS_TAG=$(date '+%Y-%m-%d-%H%M')
  TAG_NAME="${TAG_PREFIX}-${TS_TAG}"
  TAG_TITLE="Live deploy $(date '+%Y-%m-%d %H:%M %Z')"
  if [ "$DRY_RUN" = true ]; then
    echo "+ git tag -a \"$TAG_NAME\" -m \"$COMMIT_MESSAGE\""
    echo "+ git push $REMOTE $TAG_NAME"
  else
    git tag -a "$TAG_NAME" -m "$COMMIT_MESSAGE" || true
    git push "$REMOTE" "$TAG_NAME" || true
  fi

  if [ "$DO_GH_RELEASE" = true ]; then
    section "GitHub: creating release for $TAG_NAME"
    if command -v gh >/dev/null 2>&1 && gh auth status >/dev/null 2>&1; then
      NOTES_FILE="$(mktemp -t wf_release_notes.XXXXXX)"
      CURRENT_SHA=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
      {
        echo "Automated release created by scripts/release.sh"
        echo
        echo "Branch: $BRANCH"
        echo "Commit: $CURRENT_SHA"
        if [ "$DO_DEPLOY" = true ]; then
          echo "Deploy: scripts/deploy_full.sh"
        else
          echo "Deploy: skipped"
        fi
        echo
        echo "Build: $( [ "$DO_BUILD" = true ] && echo 'build.sh ran' || echo 'skipped' )"
      } > "$NOTES_FILE"
      if [ "$DRY_RUN" = true ]; then
        echo "+ gh release create \"$TAG_NAME\" --title \"$TAG_TITLE\" --notes-file \"$NOTES_FILE\""
      else
        gh release create "$TAG_NAME" --title "$TAG_TITLE" --notes-file "$NOTES_FILE" || true
      fi
      rm -f "$NOTES_FILE"
    else
      echo "GitHub CLI (gh) not available or not authenticated; skipping GitHub release"
    fi
  fi
else
  section "Tag: skipped (--no-tag)"
fi

# 4) Deploy to live
if [ "$DO_DEPLOY" = true ]; then
  section "Deploy: running scripts/deploy_full.sh"
  if [ "$DRY_RUN" = true ]; then
    echo "+ bash scripts/deploy_full.sh"
  else
    bash scripts/deploy_full.sh
  fi
else
  section "Deploy: skipped (--no-deploy)"
fi

section "All done"
echo "Release flow completed."
