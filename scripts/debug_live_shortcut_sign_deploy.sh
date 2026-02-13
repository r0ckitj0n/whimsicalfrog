#!/usr/bin/env bash
set -euo pipefail

# Debug helper for "deploy saved shortcut sign image" on LIVE.
#
# Usage (read-only, no auth needed):
#   scripts/debug_live_shortcut_sign_deploy.sh --room 0 --mapping 49
#
# Usage (attempt deploy; requires admin cookies from an authenticated browser session):
#   scripts/debug_live_shortcut_sign_deploy.sh --room 0 --mapping 49 --asset 3 --cookie "PHPSESSID=...; wf_admin=..."
#
# Or using a Netscape cookie jar:
#   scripts/debug_live_shortcut_sign_deploy.sh --room 0 --mapping 49 --asset 3 --cookie-jar /tmp/wf.cookies.txt

ROOM=""
MAPPING_ID=""
ASSET_ID=""
COOKIE=""
COOKIE_JAR=""
USER_AGENT=""
BASE_URL="${WF_LIVE_BASE_URL:-https://whimsicalfrog.us}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --room) ROOM="${2:-}"; shift 2;;
    --mapping|--mapping-id) MAPPING_ID="${2:-}"; shift 2;;
    --asset|--asset-id) ASSET_ID="${2:-}"; shift 2;;
    --cookie) COOKIE="${2:-}"; shift 2;;
    --cookie-jar) COOKIE_JAR="${2:-}"; shift 2;;
    --user-agent) USER_AGENT="${2:-}"; shift 2;;
    --base-url) BASE_URL="${2:-}"; shift 2;;
    -h|--help) sed -n '1,120p' "$0"; exit 0;;
    *) echo "Unknown arg: $1" >&2; exit 2;;
  esac
done

if [[ -z "$ROOM" || -z "$MAPPING_ID" ]]; then
  echo "Missing required args: --room and --mapping" >&2
  exit 2
fi

curl_common=( -sS )
if [[ -n "$COOKIE_JAR" ]]; then
  curl_common+=( -b "$COOKIE_JAR" )
elif [[ -n "$COOKIE" ]]; then
  curl_common+=( -H "Cookie: ${COOKIE}" )
fi

echo "Base URL: ${BASE_URL}"
echo "Room: ${ROOM}"
echo "Mapping: ${MAPPING_ID}"

echo
echo "[1/4] Current mapping row (list_room_raw)..."
python3 - <<PY
import json,sys,urllib.request,ssl
url="${BASE_URL}/api/area_mappings.php?action=list_room_raw&room=${ROOM}"
ctx=ssl.create_default_context()
obj=json.loads(urllib.request.urlopen(url,context=ctx).read().decode('utf-8'))
ms=obj.get('data',{}).get('mappings',[])
mid=int("${MAPPING_ID}")
m=next((x for x in ms if int(x.get('id',0))==mid),None)
if not m:
  print("Mapping not found in list_room_raw")
  sys.exit(0)
print({k:m.get(k) for k in ["id","area_selector","mapping_type","content_target","content_image","link_image","is_active","updated_at"]})
PY

echo
echo "[2/4] Current sign assets (get_shortcut_sign_assets)..."
curl "${curl_common[@]}" "${BASE_URL}/api/area_mappings.php?action=get_shortcut_sign_assets&room=${ROOM}&mapping_id=${MAPPING_ID}" | python3 -c 'import json,sys; obj=json.load(sys.stdin); assets=((obj.get("data") or {}).get("assets")) or obj.get("assets") or []; print("count:", len(assets)); [print({k:a.get(k) for k in ["id","source","is_active","image_url","created_at"]}) for a in assets]'

if [[ -z "$ASSET_ID" ]]; then
  echo
  echo "No --asset specified; skipping deploy step."
  exit 0
fi

if [[ -z "$USER_AGENT" ]]; then
  cat >&2 <<'EOF'
Missing required --user-agent for deploy.

WhimsicalFrog sessions are fingerprinted by User-Agent. If you POST with a different UA than the browser that created
the session, the server will destroy the session and log you out (you'll see "Session security violation detected").

Fix: copy your browser UA string and pass it via --user-agent.
EOF
  exit 2
fi

echo
echo "[3/4] Deploy asset via POST set_shortcut_sign_active..."
post_payload=$(python3 - <<PY
import json
print(json.dumps({
  "action":"set_shortcut_sign_active",
  "room":"${ROOM}",
  "mapping_id": int("${MAPPING_ID}"),
  "asset_id": int("${ASSET_ID}")
}))
PY
)
curl "${curl_common[@]}" -A "${USER_AGENT}" -H "Content-Type: application/json" -H "X-Requested-With: XMLHttpRequest" \
  -X POST "${BASE_URL}/api/area_mappings.php" \
  --data "$post_payload" | python3 -m json.tool || true

echo
echo "[4/4] Re-check mapping + assets after deploy..."
python3 - <<PY
import json,sys,urllib.request,ssl
ctx=ssl.create_default_context()
url="${BASE_URL}/api/area_mappings.php?action=list_room_raw&room=${ROOM}"
obj=json.loads(urllib.request.urlopen(url,context=ctx).read().decode('utf-8'))
ms=obj.get('data',{}).get('mappings',[])
mid=int("${MAPPING_ID}")
m=next((x for x in ms if int(x.get('id',0))==mid),None)
if not m:
  print("Mapping not found in list_room_raw")
  sys.exit(0)
print({k:m.get(k) for k in ["id","content_image","link_image","updated_at"]})
PY

curl "${curl_common[@]}" "${BASE_URL}/api/area_mappings.php?action=get_shortcut_sign_assets&room=${ROOM}&mapping_id=${MAPPING_ID}" | python3 - <<'PY'
curl "${curl_common[@]}" "${BASE_URL}/api/area_mappings.php?action=get_shortcut_sign_assets&room=${ROOM}&mapping_id=${MAPPING_ID}" | python3 -c 'import json,sys; obj=json.load(sys.stdin); assets=((obj.get("data") or {}).get("assets")) or obj.get("assets") or []; [print("active:", {k:a.get(k) for k in ["id","source","is_active","image_url"]}) for a in assets if int(a.get("is_active") or 0)==1]'
