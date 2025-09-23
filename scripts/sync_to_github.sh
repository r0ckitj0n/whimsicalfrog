#!/usr/bin/env bash
# Sync this repository to a GitHub remote.
#
# Usage:
#   scripts/sync_to_github.sh --repo https://github.com/<user>/<repo>.git [--branch main] [--remote origin] [--update-remote] [--no-verify]
#   scripts/sync_to_github.sh --repo <user>/<repo> --create-repo [--private|--public] [--branch main] [--remote origin] [--no-verify]
#
# Notes:
# - Run from the project root (where .git resides or will be created).
# - Safe to re-run; it will initialize git if needed, set the branch, add the remote, and push.
# - It will NOT overwrite an existing remote unless you pass --update-remote.

set -euo pipefail

# ---- defaults ----
REPO=""
BRANCH="main"
REMOTE="origin"
UPDATE_REMOTE=false
CREATE_REPO=false
VISIBILITY="private" # default when creating via gh
DESCRIPTION=""
NO_VERIFY=false

# ---- arg parsing ----
while [[ $# -gt 0 ]]; do
  case "$1" in
    --repo)
      REPO="${2:-}"; shift 2 ;;
    --branch)
      BRANCH="${2:-main}"; shift 2 ;;
    --remote)
      REMOTE="${2:-origin}"; shift 2 ;;
    --update-remote)
      UPDATE_REMOTE=true; shift ;;
    --create-repo)
      CREATE_REPO=true; shift ;;
    --private)
      VISIBILITY="private"; shift ;;
    --public)
      VISIBILITY="public"; shift ;;
    --description)
      DESCRIPTION="${2:-}"; shift 2 ;;
    --no-verify)
      NO_VERIFY=true; shift ;;
    -h|--help)
      grep -E "^#( |$)" "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *)
      echo "Unknown argument: $1" >&2; exit 1 ;;
  esac
done

if [[ -z "$REPO" ]]; then
  # Try to auto-detect from existing remote (default: "$REMOTE")
  if git remote get-url "$REMOTE" >/dev/null 2>&1; then
    REPO="$(git remote get-url "$REMOTE")"
    echo "[INFO] Using existing remote $REMOTE -> $REPO"
  else
    echo "Error: --repo <git@github.com:USER/REPO.git|https://github.com/USER/REPO.git> is required (no existing remote '$REMOTE' found)" >&2
    exit 1
  fi
fi

# ---- helpers ----
info()  { printf "\033[1;34m[INFO]\033[0m %s\n" "$*"; }
success(){ printf "\033[1;32m[SUCCESS]\033[0m %s\n" "$*"; }
warn()  { printf "\033[1;33m[WARN]\033[0m %s\n" "$*"; }
err()   { printf "\033[1;31m[ERROR]\033[0m %s\n" "$*"; }

# ---- ensure git repo ----
if [[ ! -d .git ]]; then
  info "Initializing new git repository..."
  git init
  # Set default branch before first commit (for newer git avoids 'master')
  if git symbolic-ref -q HEAD >/dev/null 2>&1; then
    :
  else
    git symbolic-ref HEAD "refs/heads/${BRANCH}" || true
  fi
  info "Creating initial commit..."
  git add -A
  if [[ "$NO_VERIFY" == true ]]; then
    git commit -m "Initial commit" --no-verify
  else
    git commit -m "Initial commit"
  fi
else
  info ".git exists; using existing repository"
fi

# Ensure branch exists and is checked out
CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$CURRENT_BRANCH" != "$BRANCH" ]]; then
  if git show-ref --verify --quiet "refs/heads/${BRANCH}"; then
    info "Checking out existing branch ${BRANCH}..."
    git checkout "$BRANCH"
  else
    info "Creating and switching to branch ${BRANCH}..."
    git checkout -b "$BRANCH"
  fi
fi

# ---- GitHub CLI assisted repo creation (optional) ----
if [[ "$CREATE_REPO" == true ]]; then
  if ! command -v gh >/dev/null 2>&1; then
    err "GitHub CLI (gh) not found. Install with: brew install gh"
    exit 1
  fi
  info "Checking GitHub CLI authentication..."
  if ! gh auth status >/dev/null 2>&1; then
    err "You are not logged in to GitHub CLI. Run: gh auth login"
    exit 1
  fi
  # If REPO looks like a full URL, extract owner/name for gh, else use as-is
  REPO_SLUG="$REPO"
  if [[ "$REPO" =~ ^https?://github.com/([^/]+/[^/]+)\.git$ ]]; then
    REPO_SLUG="${BASH_REMATCH[1]}"
  elif [[ "$REPO" =~ ^git@github.com:([^/]+/[^/]+)\.git$ ]]; then
    REPO_SLUG="${BASH_REMATCH[1]}"
  fi
  info "Creating GitHub repo $REPO_SLUG ($VISIBILITY) via gh (if it doesn't already exist)..."
  GH_ARGS=("$REPO_SLUG" "--${VISIBILITY}" "--source" "." "--remote" "$REMOTE")
  if [[ -n "$DESCRIPTION" ]]; then GH_ARGS+=("--description" "$DESCRIPTION"); fi
  # gh repo create exits non-zero if the repo exists; we tolerate that and proceed
  if gh repo create "${GH_ARGS[@]}" 2>/dev/null; then
    info "GitHub repository created."
  else
    warn "gh repo create reported a non-success (it may already exist). Continuing..."
  fi
  # Normalize REPO to HTTPS URL for remote configuration if needed
  if [[ ! "$REPO" =~ ^https?:// && ! "$REPO" =~ ^git@github.com: ]]; then
    REPO="https://github.com/${REPO_SLUG}.git"
  fi
fi

# ---- remote setup ----
REMOTE_EXISTS=false
if git remote get-url "$REMOTE" >/dev/null 2>&1; then
  REMOTE_EXISTS=true
fi

if [[ "$REMOTE_EXISTS" == true ]]; then
  EXISTING_URL="$(git remote get-url "$REMOTE")"
  if [[ "$EXISTING_URL" == "$REPO" ]]; then
    info "Remote $REMOTE already points to $REPO"
  else
    if [[ "$UPDATE_REMOTE" == true ]]; then
      warn "Updating remote $REMOTE URL from $EXISTING_URL -> $REPO"
      git remote set-url "$REMOTE" "$REPO"
    else
      err "Remote $REMOTE exists with different URL: $EXISTING_URL"
      err "Re-run with --update-remote to update it."
      exit 1
    fi
  fi
else
  info "Adding remote $REMOTE -> $REPO"
  git remote add "$REMOTE" "$REPO"
fi

# ---- commit current changes (if any) ----
if ! git diff --quiet || ! git diff --cached --quiet; then
  info "Committing local changes..."
  git add -A
  if [[ "$NO_VERIFY" == true ]]; then
    git commit -m "chore: sync to GitHub" --no-verify
  else
    git commit -m "chore: sync to GitHub"
  fi
else
  info "No local changes to commit"
fi

# ---- push ----
info "Fetching remote refs..."
 git fetch "$REMOTE" || true

info "Pushing branch ${BRANCH} to ${REMOTE}..."
# If upstream not set, set it; otherwise push normally. If push fails due to non-fast-forward,
# attempt a pull --rebase and retry push once.
set +e
if git rev-parse --abbrev-ref --symbolic-full-name @{u} >/dev/null 2>&1; then
  git push "$REMOTE" "$BRANCH"
  PUSH_STATUS=$?
else
  git push -u "$REMOTE" "$BRANCH"
  PUSH_STATUS=$?
fi
set -e

if [[ $PUSH_STATUS -ne 0 ]]; then
  warn "Initial push failed. Attempting 'git pull --rebase' from $REMOTE/$BRANCH and retrying..."
  git fetch "$REMOTE" "$BRANCH" || true
  # If branch has an upstream, rebase against it; else explicitly use remote/branch
  set +e
  if git rev-parse --abbrev-ref --symbolic-full-name @{u} >/dev/null 2>&1; then
    git pull --rebase
  else
    git pull --rebase "$REMOTE" "$BRANCH"
  fi
  REBASE_STATUS=$?
  set -e
  if [[ $REBASE_STATUS -ne 0 ]]; then
    err "Rebase failed. Please resolve conflicts and re-run the script."
    exit $PUSH_STATUS
  fi
  info "Retrying push after successful rebase..."
  if git rev-parse --abbrev-ref --symbolic-full-name @{u} >/dev/null 2>&1; then
    git push "$REMOTE" "$BRANCH"
  else
    git push -u "$REMOTE" "$BRANCH"
  fi
fi

success "Sync complete. Remote: $(git remote get-url $REMOTE), Branch: $BRANCH" 

# Optional: Git LFS hint (commented)
# If you plan to track large binary assets, uncomment below lines the first time:
# git lfs install
# git lfs track "images/**/*.{png,jpg,jpeg,webp,svg}"
# git add .gitattributes && git commit -m "chore: track images via LFS" && git push
