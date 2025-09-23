#!/usr/bin/env bash
set -euo pipefail
FILE="api/area_mappings.php"
BACKUP="${FILE}.bak.$(date +%Y%m%d-%H%M%S)"
cp -v "$FILE" "$BACKUP"

# 1) Replace wf_normalize_room_number function
perl -0777 -pe 
