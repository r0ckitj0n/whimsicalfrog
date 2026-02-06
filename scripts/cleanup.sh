#!/usr/bin/env bash
set -euo pipefail

# Purge regenerable log files for local/dev workflows.
# - Truncates "active" logs in place (safe with open file descriptors)
# - Removes rotated/compressed log archives
# - Skips non-log artifacts (for example screenshots)
#
# Usage:
#   ./scripts/cleanup.sh
#   ./scripts/cleanup.sh --dry-run

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$ROOT_DIR/logs"
DRY_RUN=0

if [[ "${1:-}" == "--dry-run" ]]; then
  DRY_RUN=1
fi

print_action() {
  local action="$1"
  local target="$2"
  printf '[cleanup.sh] %s %s\n' "$action" "$target"
}

truncate_file() {
  local file="$1"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    print_action "would truncate" "$file"
    return 0
  fi
  : > "$file"
  print_action "truncated" "$file"
}

delete_file() {
  local file="$1"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    print_action "would delete" "$file"
    return 0
  fi
  rm -f "$file"
  print_action "deleted" "$file"
}

purge_tree_logs() {
  local base_dir="$1"
  [[ -d "$base_dir" ]] || return 0

  while IFS= read -r -d '' file; do
    truncate_file "$file"
  done < <(
    find "$base_dir" -type f \
      \( -name '*.log' -o -name '*.out' -o -name '*.err' \) \
      ! -name '*.log.gz' \
      ! -name '*.log.*' \
      -print0
  )

  while IFS= read -r -d '' file; do
    delete_file "$file"
  done < <(
    find "$base_dir" -type f \
      \( -name '*.log.gz' -o -name '*.log.[0-9]*' -o -name '*.log.*.gz' \) \
      -print0
  )
}

purge_root_logs() {
  local root_file
  local root_logs=(
    "$ROOT_DIR/autostart.log"
    "$ROOT_DIR/deploy_debug_output.log"
  )

  for root_file in "${root_logs[@]}"; do
    [[ -f "$root_file" ]] || continue
    truncate_file "$root_file"
  done
}

print_action "starting cleanup in" "$ROOT_DIR"
purge_tree_logs "$LOG_DIR"
purge_root_logs
print_action "cleanup complete" "$ROOT_DIR"
