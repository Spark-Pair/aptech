# Hostinger Shared Hosting Deployment Target

This is the target checklist; execute it first on staging/test hosting. Do not merge to main until the roadmap tests pass.

## Runtime
- PHP 8.1+ (matching Laravel 10 requirements).
- Required web app extensions should include PDO/MySQL, mbstring, openssl, tokenizer, xml, ctype, json and fileinfo as required by Laravel/dependencies.
- The hosted app no longer needs PHP `sockets` for attendance after Local Agent cutover.
- HTTPS is mandatory for Local Agent API traffic.

## Production environment

Use `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`, and MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

Never commit production secrets or the Local Agent bearer token.

## Filesystem/web root

Preferred deployment exposes only Laravel's `public/` directory as the domain document root. `storage/` and `bootstrap/cache/` must be writable by the PHP/web process. Keep `.env`, application source, vendor internals and storage outside public exposure where the hosting layout allows it.

## First staging deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Do not run the seeder automatically on production until its intended production data policy has been reviewed.

## Update

Put the app into maintenance mode when a migration/change requires it, back up DB/files, deploy tested code, run `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, clear/rebuild caches, smoke test, then leave maintenance mode.

## Rollback

Before deployment keep a database backup and the previous known-good code revision. Code can be returned to the previous revision, but database migrations must not be blindly rolled back after production writes; use a migration-specific rollback/recovery plan. Keep the old direct-ZKTeco flow until Local Agent end-to-end verification is complete.

## Verification
- Login/auth works.
- Employees/shifts/attendance/reports work on MySQL.
- AJAX navigation/forms behave the same as before.
- `/api/v1/attendance-agent/heartbeat` rejects missing/invalid token and accepts a provisioned agent.
- Local Agent can reach the HTTPS API from the office PC.
- Device punches arrive once only despite retry/replay.
