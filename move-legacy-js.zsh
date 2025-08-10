#!/bin/zsh
# Save as: move-legacy-js.zsh (or just paste directly into your terminal)
# Purpose: Move unused legacy JS to backups with clear, timestamped logs.

ts() { date '+%Y-%m-%d %H:%M:%S'; }
log() { echo "[$(ts)] $*"; }

BACKUP_DIR="backups/unused-2025-08-09/js"
TARGETS=(
  "js/bundle.js"
  "js/wf-unified.js"
  "js/comprehensive-missing-functions.js"
  "js/recovered"
  "js/room-modal-manager.js"
  "js/room-modal-manager-direct.js"
  "js/simple-room-modal.js"
  "js/simple-room-modal.js.disabled"
  "js/cart-system.js"
)

log "BEGIN legacy JS backup/move"
log "PWD: $PWD"
log "User: $(whoami) | Shell: $SHELL"
command -v git >/dev/null 2>&1 && log "Git: $(git --version)" || log "Git: not available"

log "Ensuring backup dir: $BACKUP_DIR"
mkdir -p "$BACKUP_DIR" && log "Backup dir ready"

print_status() {
  log "Listing js/ (top-level):"
  ls -la js | sed 's/^/    /'
  log "Listing $BACKUP_DIR:"
  ls -la "$BACKUP_DIR" | sed 's/^/    /' || true
  if command -v git >/dev/null 2>&1; then
    log "Git status (short):"
    git status -s | sed 's/^/    /' || true
  fi
}

use_git_mv() {
  local SRC="$1"
  if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git mv "$SRC" "$BACKUP_DIR/" 2>/dev/null
    return $?
  fi
  return 1
}

move_path() {
  local SRC="$1"
  log "----"
  log "START move: $SRC"
  if [ -e "$SRC" ]; then
    SIZE=$(du -sh "$SRC" 2>/dev/null | awk '{print $1}')
    log "Found: $SRC (size: ${SIZE:-?})"
    if use_git_mv "$SRC"; then
      log "git mv succeeded: $SRC -> $BACKUP_DIR/"
    else
      log "git mv not used/failed; falling back to mv"
      mv "$SRC" "$BACKUP_DIR/"
      log "mv succeeded: $SRC -> $BACKUP_DIR/"
    fi
    log "Verifying destination:"
    ls -la "$BACKUP_DIR/$(basename "$SRC")" | sed 's/^/    /' || log "WARNING: could not list moved item"
  else
    log "SKIP (not found): $SRC"
  fi
  log "END move: $SRC"
}

log "Initial status snapshot"
print_status

for T in "${TARGETS[@]}"; do
  move_path "$T"
done

log "Final status snapshot"
print_status

log "DONE legacy JS backup/move"