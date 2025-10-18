#!/usr/bin/env bash

set -euo pipefail

# Locate repository root (two levels up from scripts/dev/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

if ! command -v rsync >/dev/null 2>&1; then
  echo "This script requires 'rsync'. Please install it and rerun." >&2
  exit 1
fi

readonly TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"

# Required items for bootstrapping a new project with WhimsicalFrog guardrails
REQUIRED_ITEMS=(
  ".githooks"
  "scripts/maintenance"
  "scripts/guard-templates-css.mjs"
  "scripts/server_monitor.sh"
  "scripts/start_servers.sh"
  "scripts/restart_servers.sh"
  "documentation/PROJECT_GUARDRAILS_STARTER.md"
  "documentation/WF_ENGINEERING_GUIDELINES.md"
  "includes/vite_helper.php"
  "includes/logger.php"
  "includes/secret_store.php"
  "partials/header.php"
  "partials/footer.php"
  "package.json"
  "package-lock.json"
  "composer.json"
  "composer.lock"
  "vite.config.js"
  "postcss.config.cjs"
  ".eslintignore"
  ".stylelintignore"
  ".lintstagedignore"
  "eslint.config.js"
  ".stylelintrc.json"
  ".github/workflows"
)

print_header() {
  echo "=========================================================="
  echo "WhimsicalFrog Guardrail Bootstrap"
  echo "Source repository: ${PROJECT_ROOT}"
  echo "=========================================================="
  echo
}

list_items() {
  echo "The following items will be copied to your target project:"
  for item in "${REQUIRED_ITEMS[@]}"; do
    printf ' - %s\n' "${item}"
  done
  echo
}

prompt_destination() {
  local input_path dest
  while true; do
    read -r -p "Enter the destination project directory: " input_path
    if [ -z "${input_path}" ]; then
      echo "Destination path cannot be empty."
      continue
    fi
    dest="${input_path/#\~/${HOME}}"
    if [ ! -d "${dest}" ]; then
      read -r -p "Directory does not exist. Create it? [y/N]: " create_choice
      create_choice=${create_choice:-N}
      if [[ "${create_choice}" =~ ^[Yy]$ ]]; then
        mkdir -p "${dest}"
      else
        continue
      fi
    fi
    dest="$(cd "${dest}" && pwd)"
    if [ "${dest}" = "${PROJECT_ROOT}" ]; then
      echo "Destination cannot be the current project root." >&2
      continue
    fi
    DESTINATION_ROOT="${dest}"
    break
  done
}

confirm_items() {
  echo "Destination: ${DESTINATION_ROOT}"
  read -r -p "Proceed with copying the guardrail files? [y/N]: " confirm
  confirm=${confirm:-N}
  if [[ ! "${confirm}" =~ ^[Yy]$ ]]; then
    echo "Aborted by user." >&2
    exit 1
  fi
}

backup_existing() {
  local target="$1"
  local backup_target="${target}.backup_${TIMESTAMP}"
  mkdir -p "$(dirname "${backup_target}")"
  mv "$1" "${backup_target}"
  echo "  Existing copy moved to ${backup_target}"
}

handle_existing_path() {
  local rel_path="$1"
  local full_path="$2"
  local choice

  while true; do
    read -r -p "${rel_path} exists in destination. [s]kip/[o]verwrite/[b]ackup? (s/o/b) [s]: " choice
    choice=${choice:-s}
    case "${choice}" in
      s|S)
        echo "  Skipping ${rel_path}"
        return 1
        ;;
      o|O)
        echo "  Overwriting ${rel_path}"
        return 0
        ;;
      b|B)
        backup_existing "${full_path}"
        return 2
        ;;
      *)
        echo "  Invalid choice."
        ;;
    esac
  done
}

copy_directory() {
  local src_dir="$1"
  local dest_dir="$2"
  mkdir -p "${dest_dir}"
  rsync -a "${src_dir}/" "${dest_dir}/"
}

copy_file() {
  local src_file="$1"
  local dest_file="$2"
  mkdir -p "$(dirname "${dest_file}")"
  rsync -a "${src_file}" "${dest_file}"
}

copy_item() {
  local rel_path="$1"
  local src_path="${PROJECT_ROOT}/${rel_path}"
  local dest_path="${DESTINATION_ROOT}/${rel_path}"

  if [ ! -e "${src_path}" ]; then
    echo "WARNING: ${rel_path} does not exist in source; skipping." >&2
    return
  fi

  if [ -e "${dest_path}" ]; then
    local result
    result=$(handle_existing_path "${rel_path}" "${dest_path}" || true)
    case "${result}" in
      1)
        return
        ;;
      2)
        ;;
      0)
        if [ -d "${src_path}" ]; then
          copy_directory "${src_path}" "${dest_path}"
        else
          copy_file "${src_path}" "${dest_path}"
        fi
        echo "  Copied ${rel_path}"
        return
        ;;
    esac
  fi

  if [ -d "${src_path}" ]; then
    copy_directory "${src_path}" "${dest_path}"
  else
    copy_file "${src_path}" "${dest_path}"
  fi
  echo "  Copied ${rel_path}"
}

add_additional_items() {
  local extras
  read -r -p "Add extra relative paths to copy (space-separated, or leave blank): " extras || true
  if [ -n "${extras:-}" ]; then
    for extra in ${extras}; do
      REQUIRED_ITEMS+=("${extra}")
    done
  fi
}

print_header
list_items
add_additional_items
prompt_destination
confirm_items

echo "Copying guardrail assets..."
for item in "${REQUIRED_ITEMS[@]}"; do
  copy_item "${item}"
done

cat <<"EOF"

Done!

Next steps in the new project:
  - git init && git config core.hooksPath .githooks
  - npm install (and composer install if applicable)
  - npm run dev -- --host (Vite) and start backend server on :8080

EOF
