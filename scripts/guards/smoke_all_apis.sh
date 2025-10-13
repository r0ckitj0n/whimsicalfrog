#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"

say() { printf "\n=== %s ===\n" "$*"; }

say "help_tooltips get pages=settings"
"$(dirname "$0")/curl_help_tooltips.sh" get settings || true

say "help_tooltips get_pages"
"$(dirname "$0")/curl_help_tooltips.sh" get_pages || true

say "sales get_active_sales (WF-GEN-001A)"
"$(dirname "$0")/curl_sales_active.sh" WF-GEN-001A || true

say "analytics report (7d)"
"$(dirname "$0")/curl_analytics_report.sh" 7d || true

say "rooms list (get_rooms)"
"$(dirname "$0")/curl_get_rooms.sh" || true

say "brand voice (get_active)"
"$(dirname "$0")/curl_brand_voice.sh" get_active || true

say "items list (all)"
"$(dirname "$0")/curl_get_items.sh" || true

say "item details (WF-GEN-001A)"
"$(dirname "$0")/curl_get_item_details.sh" WF-GEN-001A || true

say "categories list"
"$(dirname "$0")/curl_get_categories.sh" || true

say "room data"
"$(dirname "$0")/curl_room_data.sh" || true

say "checkout pricing (zip 30301, USPS)"
"$(dirname "$0")/curl_checkout_pricing.sh" 30301 USPS || true

say "room categories (room 1)"
"$(dirname "$0")/curl_room_categories.sh" 1 || true

say "room coordinates (room1)"
"$(dirname "$0")/curl_room_coordinates.sh" room1 || true

say "ai models (all providers)"
"$(dirname "$0")/curl_ai_models.sh" all || true

say "backgrounds (room1 active)"
"$(dirname "$0")/curl_backgrounds.sh" room1 true || true

say "pricing explanation (sample text)"
"$(dirname "$0")/curl_pricing_explanation.sh" || true

say "ai model capabilities (current)"
"$(dirname "$0")/curl_ai_capabilities.sh" get_current || true

say "email config (debug=0)"
"$(dirname "$0")/curl_email_config.sh" 0 || true

say "marketing suggestion (WF-AR-001)"
"$(dirname "$0")/curl_marketing_suggestion.sh" WF-AR-001 || true
