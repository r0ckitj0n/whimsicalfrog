#!/bin/bash
set -euo pipefail

# install_git_hooks.sh
# Installs tracked local Git hooks for this repository.
# Usage: ./scripts/install_git_hooks.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

if [ ! -d ".git" ]; then
  echo "[hooks] Error: .git directory not found. Run this from the repository."
  exit 1
fi

if [ ! -x "$ROOT_DIR/scripts/commit_mode_sync.sh" ]; then
  echo "[hooks] Making scripts/commit_mode_sync.sh executable..."
  chmod +x "$ROOT_DIR/scripts/commit_mode_sync.sh"
fi

if [ ! -x "$ROOT_DIR/scripts/release_track_on_push.sh" ]; then
  echo "[hooks] Making scripts/release_track_on_push.sh executable..."
  chmod +x "$ROOT_DIR/scripts/release_track_on_push.sh"
fi

PRE_COMMIT_HOOK_PATH="$ROOT_DIR/.git/hooks/pre-commit"
PRE_PUSH_HOOK_PATH="$ROOT_DIR/.git/hooks/pre-push"

cat > "$PRE_COMMIT_HOOK_PATH" <<'EOF'
#!/bin/sh
set -e

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null)
if [ -z "$REPO_ROOT" ]; then
  echo "[pre-commit] Unable to determine repository root."
  exit 1
fi

exec "$REPO_ROOT/scripts/commit_mode_sync.sh"
EOF

chmod +x "$PRE_COMMIT_HOOK_PATH"

cat > "$PRE_PUSH_HOOK_PATH" <<'EOF'
#!/bin/sh
set -e

REPO_ROOT=$(git rev-parse --show-toplevel 2>/dev/null)
if [ -z "$REPO_ROOT" ]; then
  echo "[pre-push] Unable to determine repository root."
  exit 1
fi

cd "$REPO_ROOT"

# Capture push refs once so multiple scripts can read them.
WF_PUSH_REFS_FILE="$(mktemp -t wf-pre-push-refs.XXXXXX)"
trap 'rm -f "$WF_PUSH_REFS_FILE"' EXIT INT TERM
cat > "$WF_PUSH_REFS_FILE"
export WF_PUSH_REFS_FILE

echo "[pre-push] Running repo hygiene..."
node "$REPO_ROOT/scripts/repo_hygiene.mjs"

echo "[pre-push] Running release tracking..."
exec "$REPO_ROOT/scripts/release_track_on_push.sh" "$@"
EOF

chmod +x "$PRE_PUSH_HOOK_PATH"
git config push.followTags true

echo "[hooks] Installed pre-commit hook at .git/hooks/pre-commit"
echo "[hooks] Hook will run scripts/commit_mode_sync.sh on every commit."
echo "[hooks] Installed pre-push hook at .git/hooks/pre-push"
echo "[hooks] Hook runs repo_hygiene.mjs and release tracking on pushes."
echo "[hooks] Set git config push.followTags=true for this repository."
