#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
TIMEFRAME="${1:-7d}"

curl -s -S "$BASE/api/analytics_tracker.php?action=get_analytics_report&timeframe=$TIMEFRAME" | jq .
