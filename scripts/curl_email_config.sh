#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
DEBUG="${1:-0}"

curl -s -S "$BASE/api/get_email_config.php?debug=$DEBUG" | jq .
