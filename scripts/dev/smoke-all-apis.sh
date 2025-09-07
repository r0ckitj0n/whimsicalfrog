#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"

say() { printf "\n=== %s ===\n" "$*"; }

say "help_tooltips get pages=settings"
"$(dirname "$0")/curl-help-tooltips.sh" get settings || true

say "help_tooltips get_pages"
"$(dirname "$0")/curl-help-tooltips.sh" get_pages || true

say "sales get_active_sales (WF-GEN-001A)"
"$(dirname "$0")/curl-sales-active.sh" WF-GEN-001A || true

say "analytics report (7d)"
"$(dirname "$0")/curl-analytics-report.sh" 7d || true

say "rooms list (get_rooms)"
"$(dirname "$0")/curl-get-rooms.sh" || true

say "brand voice (get_active)"
"$(dirname "$0")/curl-brand-voice.sh" get_active || true

say "items list (all)"
"$(dirname "$0")/curl-get-items.sh" || true

say "item details (WF-GEN-001A)"
"$(dirname "$0")/curl-get-item-details.sh" WF-GEN-001A || true

say "categories list"
"$(dirname "$0")/curl-get-categories.sh" || true

say "room data"
"$(dirname "$0")/curl-room-data.sh" || true

say "checkout pricing (zip 30301, USPS)"
"$(dirname "$0")/curl-checkout-pricing.sh" 30301 USPS || true
