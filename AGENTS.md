# AGENTS.md - Whimsical Frog Project Context

## 1. Project Identity & Environment
- **Project Name:** Whimsical Frog
- **Developer:** Jon Graves
- **Live Site:** https://whimsicalfrog.us
- **Dev Site (Vite):** http://localhost:5176
- **Dev Site (Backend):** http://localhost:8080
- **Database:** MySQL (Single Source of Truth). Do NOT use SQLite.
- **Legacy Backup:** `/Volumes/Media/~Archives/Websites/WhimsicalFrog`
- **Codex File Names:** Keep this file named `AGENTS.md` and the ignore file named `.codexignore` so Codex loads them.

## 2. Tech Stack (Strict)
- **Frontend:** React 18, Vite ^7.0, TypeScript (Strict), Tailwind CSS (Primary).
- **Backend:** PHP (API in `/api`, logic in `/includes`), MySQL, Drizzle ORM (tooling).
- **Package Manager:** npm / Composer.

## 3. Critical Architecture Protocols

### The "Shared Types" Protocol
1. **Centralized Storage:** All API/data interfaces MUST reside in `src/types/` (e.g., `inventory.ts`, `orders.ts`).
2. **Workflow:** Define the request/response interface in `src/types/` FIRST. Import this into both the frontend hook and PHP endpoint.
3. **Prohibition:** Never define API response shapes inside component or hook files.

### The "Conductor" Pattern
- **App.tsx:** Composition layer only. No inline logic.
- **Component Limit:** React components > 300 lines must be refactored into `src/components/` or `src/hooks/`.
- **Modals:** Must reside in `src/components/modals/`. Avoid prop-drilling > 2 levels; use `AppContext`.

### Database & Error Handling
- **Transparency Mandate:** Silent `catch` blocks are prohibited. Database/API errors must propagate to the user.
- **Naming:** Tables = plural snake_case (`order_items`). Columns = snake_case (`is_active`). Foreign Keys = `[table_singular]_id`.
- **Currency:** Use `DECIMAL(10,2)` or `INT` (cents). Never `FLOAT`.

## 4. Design & Styling (Tailwind Priority)
- **Styling:** Use Tailwind utility classes. Custom CSS is restricted to complex animations only.
- **Z-Index:** Use approved tokens (e.g., `var(--wf-z-modal)`) from `main.css`. No raw integers.
- **Buttons:** Follow `buttons-hover.css` logic: Primary Green (`--brand-primary`) ↔ Secondary Orange (`--brand-secondary`) on hover.
- **Tooltips:** Do not hardcode titles. Use `useTooltips.ts` and `data-help-id` to fetch content from the `help_tooltips` table.

## 5. Repository Hygiene
- **Clean as You Code:** Run `scripts/repo_hygiene.mjs` before marking tasks complete.
- **Knowledge Catalog:** Query `agent_knowledge_catalog` before starting complex tasks. Index new tasks using `scripts/catalog-task.php`.
- **Verification:** Verify all changes via browser preview or `curl`.

## 6. Local Admin Auth Probe (Testing Only)
- **Purpose:** For localhost/dev UI testing, establish an admin session without manual login form entry.
- **Endpoint:** `/api/auth_redirect_probe.php`
- **Usage (dev/local only):** `http://localhost:5176/api/auth_redirect_probe.php?token=wf_probe_2025_09&next=shop`
- **Behavior:** Sets auth cookies for a local admin user, then redirects (e.g., to `/shop` or `whoami`).
- **Safety Rule:** Use only on localhost/dev. Do not use this flow on production domains.
- **Token Source:** Token defaults to `wf_probe_2025_09` unless overridden by `WF_AUTH_PROBE_TOKEN`.
