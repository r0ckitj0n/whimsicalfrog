# Database Helper Adoption Guide

This document summarizes how database access is standardized across the WhimsicalFrog codebase using the centralized `Database` helper, and notes pragmatic exceptions where direct PDO remains appropriate.

## Goals

- Consistent, secure, and maintainable DB access
- Eliminate ad-hoc `$pdo->prepare/execute` in API and admin pages
- Centralize connection handling, transactions, and error reporting

## Primary APIs

- `Database::getInstance()`
- `Database::queryOne($sql, [$params])`
- `Database::queryAll($sql, [$params])`
- `Database::execute($sql, [$params])` (returns affected rows)
- `Database::beginTransaction()`, `Database::commit()`, `Database::rollBack()`
- `Database::lastInsertId()`
- `Database::createConnection($host, $db, $user, $pass, $port?, $socket?, $options?)` (for multi-connection/remote tools)

## Migration Patterns

- Replace:
  - `$pdo = Database::getInstance();` followed by `$pdo->prepare(...)` with `Database::queryOne/queryAll/execute(...)`.
  - `$stmt = $pdo->query('SELECT ...')` with `Database::queryAll('SELECT ...')` or `Database::queryOne(...)`.
  - `$stmt->fetchColumn()` with `Database::queryOne(...)[column] ?? null`.
  - Delete/Update/Insert with `Database::execute(...)` and `Database::lastInsertId()` as needed.
- Preserve request/response semantics and logging (`Logger::exception`, `error_log` where appropriate).
- Do not pass PDO objects through functions; call the singleton internally.

## Exceptions (keep direct PDO)

Some scripts require features that are cleaner with direct PDO or must target multiple databases dynamically:

- `api/database_maintenance.php`
  - Uses `SHOW CREATE TABLE`, `SHOW TABLES`, streaming backups/exports, and `$pdo->quote()`.
  - Retain direct PDO for low-level control and performance.

- `admin/db_manager.php` (CLI) and `admin/db_web_manager.php` (web)
  - Tools connect to selectable environments via `Database::createConnection(...)`.
  - Execute arbitrary SQL entered by an operator; small helper closures operate over that specific PDO instance.

- `admin/db_status.php`
  - Reads from current and live DBs; uses `Database::createConnection(...)` for live.
  - Aggregate status queries via that PDO connection are fine.

- Admin Secrets (`/admin/?section=secrets`)
  - Uses a separate `secret_db()` store and helpers; intentional separation from primary DB helper.

## Already Migrated (examples)

- API endpoints like `api/login.php`, `api/inventory.php`, `api/run_image_analysis.php`, `api/checkout_pricing.php`, `api/update-inventory-field.php`, `api/get_marketing_data.php`, `api/get_room_coordinates.php`, `api/next-order-item-id.php`, `api/upload_background.php` now use `Database::*` helpers exclusively.
- Admin pages like Customers (`/admin/?section=customers`) and User Manager (Customers) leverage `Database::*` for local DB reads; parts of `admin/db_api.php` were updated accordingly.

> Note: Legacy admin entrypoints under `admin/admin_*.php` were removed. Use the canonical router: `/admin/?section=<name>`.

## What Not To Change (for now)

- `api/css_generator.php` is deprecated and returns early; legacy code below is not executed.
- `api/ai_providers.php` maintains an internal `getPDO()` for model capability checks; most DB reads already use `Database::*`. Further refactors should be weighed against provider integration complexity.
- `api/business_settings_helper.php` defines a private static `getPDO()` for caching but uses `Database::*` for queries. This is safe to keep, or can be cleaned up later.

## Coding Conventions

- Always `require_once __DIR__ . '/config.php';` (or appropriate relative path) at top of scripts that hit DB.
- Use parameterized queries for all dynamic inputs.
- Prefer `Database::queryOne` for single-row lookups and presence checks.
- Use `Database::execute` for mutations; check affected rows when you need confirmation.
- Keep error handling consistent; return structured JSON in APIs.

## Quick Checklist for Refactors

- [ ] Removed `$pdo->prepare/execute` in favor of `Database::*`
- [ ] Preserved HTTP status codes and JSON envelopes
- [ ] Converted `fetchColumn()` to `queryOne()[col]`
- [ ] Replaced inserts with `Database::execute` + `Database::lastInsertId()` as needed
- [ ] Avoided passing `$pdo` to internal helpers; use singleton inside
- [ ] Left maintenance/multi-connection tools on direct PDO

## Contact

If in doubt about a script, prefer `Database::*`. If a tool needs multi-connection support or raw SQL streaming, keep direct PDO and document why in the file header.
