#!/usr/bin/env bash
set -euo pipefail
PRJ="$(cd "$(dirname "$0")/../.." && pwd)"
JS_REPORT="/tmp/wf_import_mismatch_js.txt"
CSS_REPORT="/tmp/wf_import_mismatch_css.txt"
: > "$JS_REPORT"
: > "$CSS_REPORT"

norm() {
  python3 - "$1" <<'PY'
import os,sys
p=sys.argv[1]
print(os.path.normpath(p))
PY
}

resolve_path() {
  local base="$1" ref="$2"
  if [[ "$ref" == /* ]]; then
    printf "%s\n" "$(norm "$PRJ$ref")"
  else
    printf "%s\n" "$(norm "$base/$ref")"
  fi
}

scan_js() {
  while IFS= read -r -d '' f; do
    local dir="$(dirname "$f")"
    # Extract simple quoted paths ending with .js that start with ./, ../, or /src
    { rg -o --no-line-number --no-heading "['\"][./][^'\"\n]*\\.js['\"]" "$f" || true; } | sed -E "s/^['\"]|['\"]$//g" | while read -r ref; do
      case "$ref" in http*|/@vite/*|/@id/*) continue;; esac
      abs="$(resolve_path "$dir" "$ref")"
      if [[ -f "$abs" ]]; then continue; fi
      bn="$(basename "$abs")"; dir2="$(dirname "$abs")"
      alt1="$dir2/${bn//-/_}"; alt2="$dir2/${bn//_/-}"
      if [[ -f "$alt1" ]]; then
        printf "SUGGEST JS  | %s | %s -> %s\n" "${f#$PRJ/}" "$ref" "${alt1#$PRJ/}" >> "$JS_REPORT"
      elif [[ -f "$alt2" ]]; then
        printf "SUGGEST JS  | %s | %s -> %s\n" "${f#$PRJ/}" "$ref" "${alt2#$PRJ/}" >> "$JS_REPORT"
      else
        printf "MISSING JS  | %s | %s (no candidate)\n" "${f#$PRJ/}" "$ref" >> "$JS_REPORT"
      fi
    done
  done < <(find "$PRJ/src" -type f -name "*.js" -print0)
}

scan_css() {
  while IFS= read -r -d '' f; do
    local dir="$(dirname "$f")"
    { rg -o --no-line-number --no-heading "@import\\s+url\\(['\"][./][^'\"\n]*\\.css['\"]\)" "$f" || true; } | sed -E "s/^@import\\s+url\(['\"]|['\"]\)$//g" | while read -r ref; do
      abs="$(resolve_path "$dir" "$ref")"
      if [[ -f "$abs" ]]; then continue; fi
      bn="$(basename "$abs")"; dir2="$(dirname "$abs")"
      alt1="$dir2/${bn//-/_}"; alt2="$dir2/${bn//_/-}"
      if [[ -f "$alt1" ]]; then
        printf "SUGGEST CSS | %s | %s -> %s\n" "${f#$PRJ/}" "$ref" "${alt1#$PRJ/}" >> "$CSS_REPORT"
      elif [[ -f "$alt2" ]]; then
        printf "SUGGEST CSS | %s | %s -> %s\n" "${f#$PRJ/}" "$ref" "${alt2#$PRJ/}" >> "$CSS_REPORT"
      else
        printf "MISSING CSS | %s | %s (no candidate)\n" "${f#$PRJ/}" "$ref" >> "$CSS_REPORT"
      fi
    done
  done < <(find "$PRJ/src/styles" -type f -name "*.css" -print0)
}

scan_js
scan_css

printf '%s\n' '--- JS REPORT ---'
sed -n '1,400p' "$JS_REPORT" || true
printf '%s\n' '--- CSS REPORT ---'
sed -n '1,400p' "$CSS_REPORT" || true
