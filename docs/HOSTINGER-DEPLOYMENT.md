# Hostinger Shared Hosting Deployment Target

Execute first on staging/test hosting. Do not merge to main until roadmap tests pass.

## Runtime
PHP 8.1+ for Laravel 10. Verify PDO/MySQL, mbstring, openssl, tokenizer, xml, ctype, json/fileinfo and dependency-required extensions. Hosted attendance will not need `sockets` after Local Agent cutover. HTTPS is mandatory for agent traffic.

## Production env
Use `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL` and `DB_CONNECTION=mysql` with Hostinger DB credentials. Never commit secrets or agent tokens.

## Web root/filesystem
Preferred document root is Laravel `public/` only. `storage/` and `bootstrap/cache/` must be writable by PHP. Keep `.env`, source internals and storage out of public exposure where hosting layout allows.

## First staging deployment
```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Do not automatically seed production until production-user/data policy is reviewed.

## Update/rollback
Back up DB/files, deploy tested revision, install production dependencies, migrate, rebuild caches, smoke test. Keep previous known-good code. Never blindly rollback a DB migration after production writes; use migration-specific recovery. Legacy direct ZKTeco remains until Local Agent verification passes.

## Verify
Login/auth; employees/shifts/attendance/reports; AJAX behavior; agent auth/heartbeat; office-PC HTTPS reachability; attendance arrives once through retry/replay; no direct hosting-to-LAN requirement.
