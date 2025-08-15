#!/usr/bin/env bash
set -euo pipefail

# Scan for PHP debug artifacts across the repository (excluding backups, vendor, node_modules, tests)
# Default flags on:
#  - var_dump(
#  - print_r(..., true)
#  - error_reporting(E_ALL)
# Optional: -e to include die()/exit() checks
# Modes: -s strict (exit non-zero on findings), default is warn-only

ROOT_DIR="$(cd "$(dirname "$0")"/../.. && pwd)"
cd "$ROOT_DIR"

STRICT=false
INCLUDE_EXITS=false
while getopts ":se" opt; do
  case $opt in
    s) STRICT=true ;;
    e) INCLUDE_EXITS=true ;;
    *) ;;
  esac
done

EXCLUDES=(
  --exclude-dir=backups
  --exclude-dir=node_modules
  --exclude-dir=vendor
  --exclude-dir=tests
)

# Optional allowlist file: each line is an ERE applied to the file path (e.g., ./scripts/foo.php)
ALLOWLIST_FILE="$ROOT_DIR/scripts/php/debug-allowlist.txt"
ALLOWLIST_RE=""
if [ -f "$ALLOWLIST_FILE" ]; then
  # Join non-empty, non-comment lines with |
  ALLOWLIST_RE=$(grep -v '^[[:space:]]*#' "$ALLOWLIST_FILE" | sed '/^[[:space:]]*$/d' | sed 's/[[:space:]]*$//' | paste -sd'|' - || true)
fi

# Search helper: pattern, header
search_and_report() {
  local pattern="$1"
  local header="$2"
  # Use `|| true` so grep non-match doesn't terminate the script under -e
  local results
  results=$(grep -RInE "${EXCLUDES[@]}" --include='*.php' -e "$pattern" -- . || true)
  # Apply allowlist filtering against file path portion before first colon
  if [ -n "$ALLOWLIST_RE" ] && [ -n "$results" ]; then
    # Escape for awk
    local _re="$ALLOWLIST_RE"
    _re=${_re//\\/\\\\}
    _re=${_re//\'/\\\'}
    results=$(printf "%s\n" "$results" | awk -v re="$_re" '{p=$0; sub(/:.*/, "", p); if (re!="" && p ~ re) next; print}')
  fi
  # Normalize empty
  if [ -z "$results" ]; then
    return 1
  fi
  # Count lines robustly
  local count
  count=$(printf "%s\n" "$results" | sed '/^$/d' | wc -l | tr -d ' ')
  echo "--- ${header}: ${count} occurrence(s) ---"
  echo "$results"
  echo ""
  return 0
}

FOUND=0

# Core debug artifacts
SEARCH_VAR_DUMP='var_dump\('
SEARCH_PRINT_R='print_r\(.*\,\s*true\)'
SEARCH_ERR_ALL='error_reporting\s*\(\s*E_ALL\s*\)'

if search_and_report "$SEARCH_VAR_DUMP" "var_dump( usages"; then FOUND=1; fi
if search_and_report "$SEARCH_PRINT_R" "print_r(..., true) usages"; then FOUND=1; fi
if search_and_report "$SEARCH_ERR_ALL" "error_reporting(E_ALL) usages"; then FOUND=1; fi

# Optional exits/die checks
if [ "$INCLUDE_EXITS" = true ]; then
  # Avoid ERE \b; approximate word boundary by requiring non-word or start
  # This matches occurrences where die( or exit( are not part of a longer identifier
  SEARCH_EXITS='(^|[^[:alnum:]_])(die\(|exit\()'
  if search_and_report "$SEARCH_EXITS" "die()/exit() usages"; then FOUND=1; fi
fi

if [ "$FOUND" -eq 1 ]; then
  if [ "$STRICT" = true ]; then
    echo "ERROR: PHP debug artifacts detected (strict mode). Please remove them."
    exit 1
  else
    echo "WARN: PHP debug artifacts detected. Review and clean up when appropriate."
    exit 0
  fi
else
  echo "OK: No PHP debug artifacts found."
fi
