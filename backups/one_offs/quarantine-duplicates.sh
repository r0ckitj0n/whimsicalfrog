#!/usr/bin/env sh
set -eu

# Quarantine files/dirs with trailing space-number patterns into backups/duplicates_<timestamp>/
# POSIX-compatible implementation (no arrays/mapfile).

ROOT_DIR=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT_DIR"

TIMESTAMP=$(date +%Y-%m-%dT%H-%M-%S-%3NZ 2>/dev/null || date +%Y-%m-%dT%H-%M-%SZ)
QUAR_DIR="backups/duplicates_${TIMESTAMP}"
mkdir -p "$QUAR_DIR"

info() { printf "\033[0;32m[info]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[warn]\033[0m %s\n" "$*"; }

quarantine_path() {
  path="$1"
  dest="$QUAR_DIR/${path#./}"
  mkdir -p "$(dirname "$dest")"
  printf "%s" "$dest"
}

canonical_path() {
  path="$1"
  dir=$(dirname "$path")
  base=$(basename "$path")
  # If it has an extension, strip trailing " <digits>" from the name portion
  case "$base" in
    *.*)
      name=${base%.*}
      ext=${base##*.}
      if printf '%s' "$name" | sed -E 's/ [0-9]+$//' | grep -q "."; then :; fi
      new_name=$(printf '%s' "$name" | sed -E 's/ [0-9]+$//')
      if [ "$new_name" != "$name" ]; then
        printf "%s/%s.%s" "$dir" "$new_name" "$ext"
        return 0
      fi
      ;;
  esac
  # No extension case: strip trailing " <digits>"
  new_base=$(printf '%s' "$base" | sed -E 's/ [0-9]+$//')
  if [ "$new_base" != "$base" ]; then
    printf "%s/%s" "$dir" "$new_base"
  else
    printf ""
  fi
}

moved_count=0

info "Scanning for duplicate-suffixed files..."
find -E . -type f \
  \( -regex '.*/[^/]+ [0-9]+\.[^/]*' -o -regex '.*/[^/]+ [0-9]+' \) \
  ! -path './backups/*' \
  ! -path './node_modules/*' \
  ! -path './dist/*' \
  -print 2>/dev/null | while IFS= read -r f; do
  # Skip backups area
  case "$f" in
    ./backups/*) continue ;;
  esac
  [ -e "$f" ] || continue
  canon=$(canonical_path "$f")
  dest=$(quarantine_path "$f")
  if [ -n "$canon" ] && [ -f "$canon" ]; then
    if cmp -s "$f" "$canon"; then
      info "Moving identical duplicate: $f -> $dest"
    else
      warn "Non-identical duplicate (keeping canonical, quarantining duplicate): $f"
    fi
  else
    warn "Orphan duplicate (no canonical exists): $f"
  fi
  if git mv -f "$f" "$dest" 2>/dev/null || mv -f "$f" "$dest" 2>/dev/null; then
    moved_count=$((moved_count + 1))
  fi
done

info "Scanning for duplicate-suffixed directories (depth-first)..."
find -E . -depth -type d -regex '.*/[^/]+ [0-9]+' \
  ! -path './backups/*' \
  ! -path './node_modules/*' \
  ! -path './dist/*' \
  -print 2>/dev/null | while IFS= read -r d; do
  case "$d" in
    ./backups/*) continue ;;
  esac
  [ -e "$d" ] || continue
  dest=$(quarantine_path "$d")
  info "Quarantining duplicate-suffixed directory: $d -> $dest"
  if git mv -f "$d" "$dest" 2>/dev/null || mv -f "$d" "$dest" 2>/dev/null; then
    moved_count=$((moved_count + 1))
  fi
done

info "Quarantine completed. Moved: $moved_count items."
info "Backup location: $QUAR_DIR"
exit 0
