#!/bin/bash
set -uo pipefail

# release_track_on_push.sh
# Git pre-push hook helper:
# - Auto-creates an annotated release tag when pushing tracked branches.
# - Intended to provide lightweight "release tracking" without running deploy logic.
#
# Environment overrides:
#   WF_RELEASE_TRACK_ENABLED=0        # disable tracking
#   WF_RELEASE_TRACK_BRANCHES=main    # comma-separated branch names
#   WF_RELEASE_TAG_PREFIX=deploy      # tag prefix
#   WF_RELEASE_TRACK_DRY_RUN=1        # print actions only

REMOTE_NAME="${1:-origin}"
REMOTE_URL="${2:-}"
TRACK_ENABLED="${WF_RELEASE_TRACK_ENABLED:-1}"
TRACK_BRANCHES="${WF_RELEASE_TRACK_BRANCHES:-main}"
TAG_PREFIX="${WF_RELEASE_TAG_PREFIX:-deploy}"
DRY_RUN="${WF_RELEASE_TRACK_DRY_RUN:-0}"

log() {
  printf '[release-track] %s\n' "$1"
}

if [ "$TRACK_ENABLED" != "1" ]; then
  log "Tracking disabled (WF_RELEASE_TRACK_ENABLED=$TRACK_ENABLED)."
  exit 0
fi

CURRENT_BRANCH="$(git symbolic-ref --quiet --short HEAD 2>/dev/null || true)"
if [ -z "$CURRENT_BRANCH" ]; then
  log "Detached HEAD; skipping release tracking."
  exit 0
fi

is_tracked_branch=0
IFS=',' read -r -a BRANCH_LIST <<< "$TRACK_BRANCHES"
for branch in "${BRANCH_LIST[@]}"; do
  branch_trimmed="$(echo "$branch" | tr -d '[:space:]')"
  if [ "$CURRENT_BRANCH" = "$branch_trimmed" ]; then
    is_tracked_branch=1
    break
  fi
done

if [ "$is_tracked_branch" -ne 1 ]; then
  log "Branch '$CURRENT_BRANCH' not in tracked set ($TRACK_BRANCHES); skipping."
  exit 0
fi

# Determine whether this push updates the tracked branch.
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
done

if [ "$pushing_tracked_branch" -ne 1 ]; then
  log "Push does not update refs/heads/$CURRENT_BRANCH; skipping."
  exit 0
fi

HEAD_SHA="$(git rev-parse --verify HEAD)"
if git tag --points-at "$HEAD_SHA" | grep -Eq "^${TAG_PREFIX}-"; then
  log "HEAD already has a ${TAG_PREFIX}-* tag; nothing to do."
  exit 0
fi

timestamp="$(date '+%Y%m%d-%H%M%S')"
tag="${TAG_PREFIX}-${timestamp}"

# Avoid collisions if multiple pushes happen within the same second.
if git rev-parse -q --verify "refs/tags/$tag" >/dev/null 2>&1; then
  tag="${tag}-$(git rev-parse --short "$HEAD_SHA")"
fi

subject="$(git log -1 --pretty=%s "$HEAD_SHA")"
message="Release track on push

Remote: ${REMOTE_NAME}
URL: ${REMOTE_URL}
Branch: ${CURRENT_BRANCH}
Commit: ${HEAD_SHA}
Subject: ${subject}
Created: $(date '+%Y-%m-%d %H:%M:%S %Z')"

if [ "$DRY_RUN" = "1" ]; then
  log "Dry run: would create annotated tag '$tag' on $HEAD_SHA"
  exit 0
fi

if git tag -a "$tag" -m "$message" "$HEAD_SHA"; then
  log "Created release tag: $tag"
  log "Tip: enable auto-tag push with: git config push.followTags true"
else
  # Do not block push if tracking cannot complete.
  log "Warning: failed to create release tag; allowing push to continue."
fi

exit 0
