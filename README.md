# WhimsicalFrog

Welcome to the WhimsicalFrog codebase! 🎉

All up-to-date documentation now lives in:

```
documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md
```

That single document replaces the numerous legacy markdown files that previously described various subsystems. Those files have been archived for reference but should *not* be relied upon.

Quick links:
* [Full Documentation](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md)
* [Contribution Guide](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md#11-contribution-guide)
* [Local Development Setup](documentation/WHIMSICALFROG_FULL_DOCUMENTATION.md#4-local-development)
* [Admin Tooltip System](documentation/includes/tooltip-system.md)
* [Legacy Scripts Archive](backups/legacy/README.md)

## Front-end Quick Start (Vite)

```bash
# Install JS dependencies
npm install

# Start Vite dev server
npm run dev
```

The dev server runs on [http://localhost:5176](http://localhost:5176) and hot-reloads whenever you edit files under `js/`, `src/`, or `css/`.

Tooltips on admin pages are Vite-managed (styling/loader) but the content is dynamic from the database. See `documentation/includes/tooltip-system.md` for API endpoints and the seeding/audit/export scripts.

To generate the production bundle used in `dist/`, run:

```bash
npm run build
```

## Full Stack Dev Servers (PHP + Vite)

To start both the PHP dev server (http://localhost:8080) and the Vite dev server (http://localhost:5176) together, run:

```bash
./scripts/restart_servers.sh
```

## Quick Commands (wf)

You can also use the `wf` helper for common operations:

```bash
# Start servers with monitoring
./wf start

# Check status
./wf status

# View recent logs
./wf logs

# Stop servers and monitor
./wf stop
```

The `wf` wrapper uses the scripts under `scripts/` and keeps process names consistent.

Enjoy coding! 🐸

## Environment (.env)

- See `.env.example` at the repo root for a template of expected variables.
- PHP loads environment variables from a root `.env` via `config.php` using `loadEnv(__DIR__ . '/.env')`.
- The actual `.env` file is git-ignored; copy `.env.example` to `.env` and fill values locally.
- Vite/dev server reads values from the shell process (e.g., `VITE_DEV_PORT`, `VITE_HMR_PORT`, `PORT`). Optional toggles read by PHP include `WF_VITE_DEV`, `WF_VITE_DISABLE_DEV`, `WF_VITE_ORIGIN`, `WF_PUBLIC_BASE`, `WF_BACKEND_ORIGIN`.


## Database Configuration (Centralized)

See `documentation/CENTRALIZATION_SUMMARY.md` for full details on the centralized database configuration, environment variable keys, and examples.

Minimal usage in PHP scripts:

```php
require_once __DIR__ . '/api/config.php';

// Default connection (auto-detected environment)
$pdo = Database::getInstance();

// Explicit environment connection if needed
$live = wf_get_db_config('live');
$livePdo = Database::createConnection(
    $live['host'],
    $live['db'],
    $live['user'],
    $live['pass'],
    $live['port'] ?? 3306,
    $live['socket'] ?? null
);
```

Environment variables are supported (optionally via a `.env` file). See `.env.example` for the `WF_DB_LOCAL_*` and `WF_DB_LIVE_*` keys.

## Security Hardening

The repository includes defense-in-depth measures to prevent accidental exposure of sensitive files when developing locally or running under Apache:

- Sensitive file extensions are denied via root `.htaccess` using `<FilesMatch>`: `.sql`, `.sqlite`, `.db`, `.env`, `.ini`, `.log`, `.bak`, `.old`, archives (`.zip/.tar/.gz/.7z/.rar`), and `.map`.
- Directory listings are disabled globally with `Options -Indexes`.
- Sensitive directories are blocked early with 403 responses in `.htaccess`: `backups/`, `scripts/`, `.git/`, `.github/`, and `vendor/`. Hidden dotfiles (e.g., `.env`, `.htaccess`) are also blocked except `/.well-known/`.
- Safe security headers are set via `mod_headers`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.
- The PHP dev router `router.php` mirrors these protections because the built-in PHP server ignores `.htaccess`. Requests for sensitive extensions, dotfiles (excluding `/.well-known/`), and protected directories return `403 Forbidden`.
- Database dumps were moved to `backups/sql/` and both `backups/` and `backups/sql/` contain a deny-all `.htaccess`.

Recommendation: Keep SQL dumps and logs outside the web root whenever possible. Root-level `/*.sql` files are git-ignored by default; SQL files under `scripts/` remain allowed for tooling.
