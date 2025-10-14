# Settings Page Tooltip Proposals

**Purpose**: Replace generic "Hover or click to see what it adjusts" tooltips with specific, helpful, funny, and snarky descriptions.

**Style Guide**:
- Explain what the modal/tool actually does
- Written for someone new to running a business
- Elementary + helpful + snarky
- No obvious leading statements ("Opens...", "Saves...", etc.)
- Get straight to the useful information

---

## Business & Store Settings

### `accountSettingsBtn` / `action:open-account-settings`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Your admin profile—name, email, password. The "it's me" settings. Change carefully if you share this login.

### `businessInfoBtn` / `action:open-business-info`
**Current**: Your store name, contact email, phone, address, and hours. What shows on receipts and footer.
**Proposed**: ✅ Already good!

### `websiteConfigBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Site-wide settings like timezone, currency, and default language. The boring-but-essential foundation stuff.

### `systemConfigBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Technical knobs: debug mode, cache settings, performance tweaks. For when you're feeling brave or desperate.

---

## Product & Catalog

### `categoriesBtn` / `action:open-categories`
**Current**: Organize products into groups (e.g., "Art," "Decor"). Makes browsing easier than one giant pile.
**Proposed**: ✅ Already good!

### `attributesBtn` / `action:open-attributes`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Global options like Size, Color, Material. Define once, reuse everywhere—consistency without the copy-paste marathon.

### `roomsBtn`
**Current**: Name and describe each virtual room in your store. Think of it as interior design for your website.
**Proposed**: ✅ Already good!

### `roomCategoryLinksBtn` / `room-category-btn` / `action:open-room-category-links`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Decide which product categories appear in which rooms. Traffic control so shoppers find the fun stuff naturally.

---

## Visual & Design

### `backgroundManagerBtn` / `action:open-background-manager`
**Current**: Upload and assign room backgrounds. Ambiance matters; this is where you set the vibe.
**Proposed**: ✅ Already good!

### `room-mapper-btn` / `action:open-room-map-editor`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Draw clickable hotspots on room images. Point shoppers to products visually—like a treasure map, but for commerce.

### `areaItemMapperBtn` / `action:open-area-item-mapper`
**Current**: Link hotspots to specific products. Click a lamp in the photo, see the lamp for sale. Magic.
**Proposed**: ✅ Already good!

### `global-css-btn` / `action:open-css-catalog`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Site-wide styling tweaks. Colors, fonts, spacing—polish without hiring a designer. Preview before committing or regret it visually.

### `cssRulesBtn` / `action:open-css-rules`
**Current**: Advanced CSS rules. More power, more risk. Preview before saving or you'll redecorate in Comic Sans.
**Proposed**: ✅ Already good!

---

## Email & Communications

### `emailConfigBtn` / `action:open-email-settings`
**Current**: Configure how order confirmations and receipts get sent. Test it or customers won't get emails.
**Proposed**: ✅ Already good!

### `emailHistoryBtn` / `action:open-email-history`
**Current**: Every email the system sent, who opened it, and delivery status. Your paper trail.
**Proposed**: ✅ Already good!

### `templateManagerBtn` / `action:open-template-manager`
**Current**: Customize order confirmations, receipts, and marketing emails with your brand voice.
**Proposed**: ✅ Already good!

### `action:open-customer-messages`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Inbox for customer inquiries and support messages. Where "I have a question" meets "here's an answer."

---

## Payments & Checkout

### `squareSettingsBtn` / `action:open-square-settings`
**Current**: Enter API keys and test connections. Get this right or checkout breaks—no pressure.
**Proposed**: ✅ Already good!

### `shippingSettingsBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Carrier API keys (USPS, UPS, FedEx) and distance calculation. Real-time rates beat guesswork—customers appreciate honesty.

### `receiptMessagesBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Custom messages that appear on receipts and order confirmations. Add personality, policies, or "thanks for not haggling."

---

## AI & Automation

### `aiSettingsBtn` / `action:open-ai-settings`
**Current**: Pick your AI provider (OpenAI, Anthropic) and set limits. Let robots help write product descriptions.
**Proposed**: ✅ Already good!

### `aiToolsBtn` / `action:open-ai-tools`
**Current**: Hover or click to see what it adjusts.
**Proposed**: AI-powered helpers for product descriptions, SEO copy, and marketing text. You edit, robots draft—teamwork without the awkward meetings.

---

## Dashboard & Layout

### `dashboardConfigBtn` / `action:open-dashboard-config`
**Current**: Arrange widgets and cards. Customize what you see first thing every morning.
**Proposed**: ✅ Already good!

---

## System & Maintenance

### `databaseMaintenanceBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Optimize tables and clear old logs. Routine maintenance so the site doesn't slow down like a browser with 47 tabs.

### `database-tables-btn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Peek at raw database tables. For the curious or desperate—tread carefully, backup first.

### `dbSchemaAuditBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Scan your database structure for issues, missing indexes, or orphaned columns. The "is this thing healthy?" checkup.

### `fileExplorerbtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: All uploaded images and documents. Organize, rename, delete—keep the chaos tidy before it becomes archaeological.

### `systemCleanupBtn`
**Current**: Clear cobwebs so pages load faster. A spa day for your server.
**Proposed**: ✅ Already good!

### `healthDiagnosticsBtn` / `action:run-health-check`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Run system health checks—database, files, permissions, API connections. Green means go, red means fix it before customers notice.

### `loggingStatusBtn` / `action:open-logging-status`
**Current**: Hover or click to see what it adjusts.
**Proposed**: View system logs and error reports. Where the site confesses what went wrong and when.

### `secretsManagerBtn` / `action:open-secrets-modal`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Encrypted storage for API keys and sensitive credentials. Keep secrets secret—rotate keys when paranoia strikes.

### `devStatusBtn` / `action:open-dev-status`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Development environment status—Vite server, build info, debug flags. For when you're wearing the developer hat.

---

## Deployment & Repository

### `deployManagerBtn` / `action:open-deploy-manager`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Deploy code changes to production. The "make it live" button—test first, deploy second, panic never (ideally).

### `repoCleanupBtn` / `action:open-repo-cleanup`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Clean up old files, unused assets, and code cruft. Digital spring cleaning for your repository.

---

## Analytics & Reports

### `analyticsBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Traffic stats, conversion rates, and visitor behavior. See who's browsing, what they're clicking, and where they bail.

### `businessReportsBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Sales reports, revenue summaries, and financial snapshots. The "how's business actually doing?" dashboard.

### `costBreakdownBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Product cost analysis and profit margins. Know what you're actually making after materials, shipping, and existential dread.

---

## Specialized Tools

### `addressDiagBtn`
**Current**: Fill in the details, save it, and it joins the collection.
**Proposed**: Test address validation and shipping calculations. Debug why "123 Main St" won't geocode or calculate rates.

### `userManagerBtn`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Manage admin users, roles, and permissions. Who gets access to what—trust, but verify.

### `action:scan-item-images`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Scan for missing product images or broken image links. Find the gaps before customers do.

---

## Action Buttons (Generic Patterns)

### `action:move-up` / `action:move-down`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Reorder list items. Nudge things up or down until the sequence makes sense.

### `action:email-history-next` / `action:email-history-prev`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Page through email history. Because 500 emails don't fit on one screen.

### `action:email-history-toggle-json`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Switch between friendly view and raw JSON. For when you need to see the actual data structure.

### `action:logging-open-file`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Open the full log file. Dive deep into errors, warnings, and the occasional mystery.

### `action:secrets-rotate`
**Current**: Hover or click to see what it adjusts.
**Proposed**: Generate new encryption keys. Rotate secrets when security paranoia strikes or compliance demands it.

### `action:prevent-submit`
**Current**: Hover or click to see what it adjusts.
**Proposed**: (This is a form attribute, not a button—skip tooltip)

---

## Review Notes

- **Already Good**: 10 tooltips are already specific and helpful
- **Need Updates**: ~40 tooltips need replacement
- **Patterns Identified**: Many generic tooltips are on specialized admin tools that need context-specific descriptions

**Next Steps**:
1. Review these proposals
2. Approve, modify, or reject each one
3. I'll update the curation script and apply the changes
