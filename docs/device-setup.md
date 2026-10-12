# Lukla Operations — Device Setup Guide

How to get this app running on a machine that has never seen it: a second laptop, a
teammate's workstation, or a fresh OS install. Follow the steps in order — the app
stores sessions, cache, and queued jobs in PostgreSQL, so it will not boot until the
database exists and migrations have run.

## Stack

Pulled from `composer.json` and `package.json`:

| Component | Version | Source |
|---|---|---|
| PHP | ^8.2 | `composer.json:9` |
| Laravel | ^12.0 | `composer.json:11` |
| Filament | ~5.0 | `composer.json:10` |
| spatie/laravel-permission | ^6.0 | `composer.json:13` |
| Vite | ^7.0 | `package.json:15` |
| Tailwind CSS | ^4.0 | `package.json:14` |

## Prerequisites

| Tool | Minimum | Notes |
|---|---|---|
| PHP | 8.2 | Must have the extensions below |
| Composer | 2.x | |
| Node.js | 20.19+ or 22.12+ | Vite 7 will not run on older releases |
| PostgreSQL | 14 | Any recent version; the app uses `pdo_pgsql` |

### Required PHP extensions

Enable these in `php.ini` by uncommenting the matching `extension=` line, then restart
the web server or CLI:

```
pdo_pgsql   mbstring   openssl   fileinfo   gd   intl   zip   bcmath   sodium
```

`pdo_pgsql` is the one that gets missed. Without it every database command fails with
`could not find driver`.

## 1. Clone and install PHP dependencies

```bash
git clone <repository-url>
cd lukla-operations
composer install
```

## 2. Create the PostgreSQL role and database

Do this before anything touches `.env` or migrations.

The app connects as `lukla_app`, not as the `postgres` superuser. That split is
deliberate and should not be flattened into superuser credentials.

```bash
psql -U postgres -c "CREATE ROLE lukla_app LOGIN PASSWORD 'choose-a-password';"
psql -U postgres -c "CREATE DATABASE lukla_operations OWNER lukla_app;"
```

`psql` is frequently absent from `PATH` on Windows. Either open the SQL shell that
ships with PostgreSQL, use pgAdmin's Query Tool, or call the binary directly:

```powershell
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres
```

Keep the password you set here; step 3 needs it.

## 3. Configure the environment

```bash
copy .env.example .env          # macOS / Linux: cp .env.example .env
php artisan key:generate
```

Open `.env` and set the password from step 2:

```dotenv
DB_PASSWORD=choose-a-password
```

The remaining database values in `.env.example` already suit a default local install
(`127.0.0.1`, port `5432`, database `lukla_operations`, user `lukla_app`).

## 4. Migrate and seed

```bash
php artisan migrate --seed
```

This creates all 34 tables and seeds the eight RBAC roles plus the demo accounts listed
further down.

## 5. Build the frontend

```bash
npm install
npm run build
```

This step is required, not optional. `public/build` is gitignored, so a fresh clone has
no compiled assets and the UI will render unstyled. Filament publishes its own assets
automatically through the `post-autoload-dump` Composer hook.

## 6. Run the app

```bash
php artisan serve
```

On Windows you can also double-click `serve.bat`, which starts the same server and warns
you if the PostgreSQL service is down.

For the full development loop — HTTP server, queue worker, log tailer, and Vite in one
terminal — use:

```bash
composer dev
```

### Panels

| Panel | URL | Audience |
|---|---|---|
| Admin | http://127.0.0.1:8000/admin | Internal roles |
| Portal | http://127.0.0.1:8000/portal | Maklon clients |

## Demo accounts

Seeded by `DatabaseSeeder`. Every account uses the password `password`.

| Role | Email |
|---|---|
| Owner / Eksekutif | `owner_eksekutif@lukla.test` |
| Tim Sample | `tim_sample@lukla.test` |
| Gudang | `gudang@lukla.test` |
| Kepala Produksi | `kepala_produksi@lukla.test` |
| Penjahit | `penjahit@lukla.test` |
| QC Inspector | `qc_inspector@lukla.test` |
| Finance & Payroll | `finance_payroll@lukla.test` |
| Klien Maklon | `klien@lukla.test` |

The first seven sign in at `/admin`. The client account signs in at `/portal` and is
scoped to its own `client_id`.

These are development credentials only. Do not seed them into a production database.

## Faster path

Steps 3 through 5 are wrapped in a single Composer script:

```bash
composer setup
```

It copies `.env`, generates the app key, migrates, and builds assets. Two gaps to know
about: it does **not** create the PostgreSQL role and database (step 2 still applies),
and it does **not** seed. Run:

```bash
# after step 2
composer setup
php artisan db:seed
```

## Troubleshooting

**`could not find driver`**
`pdo_pgsql` is not enabled in the `php.ini` the CLI is actually reading. Confirm with
`php -m | findstr pgsql` (Windows) or `php -m | grep pgsql`. Note that XAMPP and a
standalone PHP install each have their own `php.ini` — edit the one `php --ini` reports.

**`SQLSTATE[08006] connection refused`**
The PostgreSQL service is not running. On Windows: `net start postgresql-x64-18` from an
Administrator terminal. On macOS: `brew services start postgresql`. On Linux:
`sudo systemctl start postgresql`.

**`password authentication failed for user "lukla_app"`**
`DB_PASSWORD` in `.env` does not match the password set in step 2. Passwords are
case-sensitive; re-run `CREATE ROLE` with `ALTER ROLE lukla_app PASSWORD '...'` if unsure.

**`database "lukla_operations" does not exist`**
Step 2 was skipped, or `DB_DATABASE` in `.env` points somewhere else.

**Unstyled pages, or `Vite manifest not found`**
`npm run build` has not run. For active frontend work use `npm run dev` instead and keep
it running alongside `php artisan serve`.

**Filament assets return 404**
Run `php artisan filament:upgrade`, then hard-refresh the browser.

**`The stream or file could not be opened` under storage**
Missing write permissions on `storage/` and `bootstrap/cache/`. On macOS or Linux:
`chmod -R 775 storage bootstrap/cache`. Windows clones do not normally hit this.

**Stale config after editing `.env`**
`php artisan config:clear`. Cached config ignores new `.env` values.

## Related documents

- `docs/lukla-operations-engine.md` — business requirements and scope
- `docs/Lukla Operations Engine — SRS Walkthrough (1).md` — functional requirements, RBAC matrix
