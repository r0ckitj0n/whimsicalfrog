#!/usr/bin/env bash
set -euo pipefail

# Guard against legacy DB-driven CSS patterns.
# Fails the build if prohibited patterns are detected outside allowed locations.

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT_DIR"

# Common excludes
EXCLUDES=(
  "./node_modules"
  "./vendor"
  "./dist"
  "./.git"
  "./docs"
  "./reports"
  "./api/_deprecated_css_endpoints"
  "./scripts" # avoid self-matching guard scripts
  "./.github"
)

# Only scan likely source files to avoid false positives from manifests/caches
INCLUDES=(
  "*.php" "*.phtml" "*.html" "*.htm"
  "*.js" "*.mjs" "*.cjs" "*.ts" "*.tsx" "*.jsx"
  "*.css" "*.scss"
)

failures=()

# Helper: run grep with excludes
grep_repo() {
  local pattern="$1"; shift || true
  local extra_excludes=("$@")
  local args=( -RInE --color=never )
  # Include only code-like files
  for inc in "${INCLUDES[@]}"; do
    args+=( --include="$inc" )
  done
  # Always include base excludes
  for p in "${EXCLUDES[@]}"; do
    if [[ -d "$p" ]]; then
      args+=( --exclude-dir="$p" )
    elif [[ -f "$p" ]]; then
      args+=( --exclude="$p" )
    fi
  done
  # Optionally include extra excludes if provided
  if (( ${#extra_excludes[@]} > 0 )); then
    for p in "${extra_excludes[@]}"; do
      if [[ -d "$p" ]]; then
        args+=( --exclude-dir="$p" )
      elif [[ -f "$p" ]]; then
        args+=( --exclude="$p" )
      fi
    done
  fi
  # Use POSIX grep available on runners
  grep "${args[@]}" -- "$pattern" . || true
}

# 1) Disallow references to legacy tables outside allowed places
matches=$(grep_repo '\b(css_variables|global_css_rules)\b' './scripts' './.github')
if [[ -n "$matches" ]]; then
  failures+=("Found references to legacy tables css_variables/global_css_rules:\n$matches")
fi

# 2) Disallow references to deprecated endpoints outside their dir and scripts
matches=$(grep_repo '(_deprecated_css_endpoints|css_generator\.php|global_css_rules\.php)' './scripts/dev/check_legacy_css.sh')
if [[ -n "$matches" ]]; then
  failures+=("Found references to deprecated CSS endpoints:\n$matches")
fi

# 3) Disallow UI component custom_css usage outside server guard file (exclude this script)
matches=$(grep_repo '\bcustom_css\b' './api/website_config.php' './scripts/dev/check_legacy_css.sh')
if [[ -n "$matches" ]]; then
  failures+=("Found references to custom_css outside api/website_config.php:\n$matches")
fi

if (( ${#failures[@]} > 0 )); then
  echo 'Legacy CSS guard failed:' >&2
  for msg in "${failures[@]}"; do
    echo -e "\n- $msg" >&2
  done
  exit 1
fi

echo 'Legacy CSS guard passed.'
