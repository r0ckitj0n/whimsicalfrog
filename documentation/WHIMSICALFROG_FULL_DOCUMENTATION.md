# WhimsicalFrog – Full System Documentation

> Version: July 2025  
> Maintainer: WhimsicalFrog Core Team  
> Contact: dev@whimsicalfrog.com

---

## Table of Contents
1. Project Overview
2. Technology Stack
3. Directory Structure
4. Local Development
5. Build & Deployment
6. Core Architecture
   * Authentication & Sessions
   * E-commerce Workflows
   * Dynamic Background System
   * Email-Campaign System
7. Security Hardening
8. Code Quality & Testing
9. Data Model Reference
10. Troubleshooting & FAQ
11. Contribution Guide

---

## 1. Project Overview
WhimsicalFrog is an artisan e-commerce platform that showcases unique custom crafts.  
Key goals:
* Smooth storefront and checkout experience.
* Powerful admin panel for inventory, orders, reports & marketing.
* Dynamic theming (room/landing backgrounds) driven by DB content.
* Scalable email-marketing tools with audience filtering.

---

## 2. Technology Stack
| Layer | Tech |
|-------|------|
| Front-end | Tailwind-styled CSS, vanilla JS bundles (`js/bundle.js`) |
| Back-end | PHP 8.x (modular functions, PDO) |
| Database | MySQL 8.x |
| Build | PHP scripts for CSS/JS bundling; Composer for PHP deps; npm (eslint/stylelint) for linting |
| Testing | PHPUnit 12.x |
| Deployment | Git → CI (Netlify / Shared Host) using PHP built-in server or Apache |

---

## 3. Directory Structure
```
/                ← PHP entry points (index.php, api/, functions/)
admin/           ← Admin UI pages
css/             ← Bundled & page-specific CSS (built by scripts/bundle-css.php)
js/              ← Bundled & modular JS (built by scripts/bundle-js.php)
images/          ← Public images (products, backgrounds, logos)
includes/        ← Shared helpers (auth.php, functions.php, background_helpers.php)
logs/            ← Access & error logs (protected via .htaccess)
backups/         ← DB & file backups (protected)
documentation/   ← 📚 You are here
scripts/         ← Build / maintenance scripts (bundle-css.php, bundle-js.php)
tests/           ← PHPUnit test suite
```

### Cache-Busting
Static assets are loaded with `filemtime()` query-string versioning (e.g. `bundle.css?v=1678886400`).

---

## 4. Local Development
1. **Clone & Install**  
   ```bash
   git clone git@github.com:whimsicalfrog/site.git
   cd site
   composer install
   npm install # optional – for ESLint/Stylelint
   ```
2. **Configure DB**  
   Copy `api/config.sample.php` → `api/config.php` and set credentials.
3. **Run Server**  
   ```bash
   php -S 127.0.0.1:8000 index.php
   ```
4. **Run Tests**  
   ```bash
   vendor/bin/phpunit --bootstrap tests/bootstrap.php
   ```

---

## 5. Build & Deployment
* **CSS / JS Bundles:**  
  ```bash
  php scripts/bundle-css.php
  php scripts/bundle-js.php
  ```
  Bundles output to `css/bundle.css` and `js/bundle.js`.
* **Deploy:** Commit to `main`; CI builds bundles then pushes to production hosting.
* **Environment Variables:**  
  `WHIMSICALFROG_ENV` (prod|stage|dev), DB creds, SMTP creds.

---

## 6. Core Architecture
### 6.1 Authentication & Sessions
* Sessions started in `index.php`.
* `includes/auth.php` provides `isLoggedIn()`, `isAdmin()`, `getCurrentUser()`.
* Admin pages validate with `isAdmin()` or dev token.

### 6.2 E-commerce Workflows
* **Cart** stored in `$_SESSION['cart']`.
* **Checkout** → `functions/checkout.php` creates order records.
* **Inventory CRUD** in `/admin/admin_inventory.php`.

### 6.3 Dynamic Background System
* Table `backgrounds` (`image_filename`, `webp_filename`, `is_active`, `room_type`).
* `get_active_background($roomType)` returns URL.
* `get_landing_background_path()` (shared helper) guarantees fallback to `images/background_home.webp`.

### 6.4 Email-Campaign System
* Campaigns in `email_campaigns` with `target_audience` (`all`|`customers`|`non-customers`).
* Sending handled in `functions/process_email_campaign.php` (audience SQL switch).
* Sends logged in `email_campaign_sends`.

---

## 7. Security Hardening
* `.htaccess` files in `/admin`, `/backups`, `/documentation`, `/logs` disable directory listing and enforce HTTPS.
* Input sanitization via prepared PDO statements.
* Error reporting disabled in production (`display_errors = 0`).

---

## 8. Code Quality & Testing
* **Linters:** `php-cs-fixer`, `eslint`, `stylelint` with auto-fix tasks.
* **Tests:** see `tests/` – LandingBackgroundTest, EmailCampaignFilterTest (total 5 assertions).
* **CI:** Runs lints + PHPUnit before deploy.

---

## 9. Data Model Reference (key tables)
| Table | Purpose |
|-------|---------|
| `users` | Accounts (`role` = admin/user) |
| `items` | Inventory products |
| `orders` | Customer orders |
| `email_subscribers` | Marketing opt-ins |
| `email_campaigns` | Campaign metadata |
| `email_campaign_sends` | Per-subscriber send log |
| `backgrounds` | Room/landing background images |

---

## 10. Troubleshooting & FAQ
**Blank Landing Page?**  
Check logs for background helper errors; `get_landing_background_path()` falls back automatically.

**CSS Not Updating?**  
Ensure `scripts/bundle-css.php` ran and cache-busting query param updated.

---

## 11. Contribution Guide
1. Fork & create a feature branch.
2. Write unit tests for new PHP logic.
3. Run `npm run lint` & `vendor/bin/phpunit`.
4. Submit PR; CI must pass.

---

© 2025 WhimsicalFrog. All rights reserved.
