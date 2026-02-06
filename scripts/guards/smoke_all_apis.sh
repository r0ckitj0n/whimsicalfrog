#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${WF_SMOKE_BASE_URL:-http://localhost:8080}"
FAILURES=0

say() {
  printf "\n=== %s ===\n" "$*"
}

probe() {
  local path="$1"
  local expected="${2:-200}"
  local url="${BASE_URL}${path}"
  local code

  code="$(curl -sS -o /tmp/wf_smoke_api.json -w '%{http_code}' "$url" || true)"
  if [[ "$code" != "$expected" ]]; then
    echo "FAIL ${path} (expected ${expected}, got ${code})"
    FAILURES=$((FAILURES + 1))
    return
  fi

  echo "OK ${path} (${code})"
}

say "Core API endpoint health"
probe "/api/bootstrap.php?path=%2F"
probe "/api/get_rooms.php"
probe "/api/get_items.php"
probe "/api/health_items.php"
probe "/api/health_backgrounds.php"
probe "/api/room_status.php"
probe "/api/db_tools.php?action=status"

say "Auth probe redirect"
probe "/api/auth_redirect_probe.php?token=wf_probe_2025_09&next=whoami" 302

if [[ "$FAILURES" -gt 0 ]]; then
  echo
  echo "API smoke failed with ${FAILURES} failing endpoint(s)."
  exit 1
fi

echo
echo "API smoke passed."
