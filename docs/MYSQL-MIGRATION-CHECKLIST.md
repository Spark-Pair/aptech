# MySQL Migration Verification Checklist

Production Laravel uses MySQL. SQLite may remain for local development/tests and Local Agent private queue only.

## Disposable DB
1. Create disposable MySQL DB and set `.env` to `DB_CONNECTION=mysql`.
2. `php artisan optimize:clear`.
3. On disposable DB only: `php artisan migrate:fresh --seed`.
4. `php artisan test`.
5. Exercise login, employees, shifts, attendance import/reporting and AJAX forms/navigation.
6. Record actual results in `docs/PHYSICAL-TEST-RESULTS.md` (or a linked staging result entry).

## Existing SQLite data
Never copy SQLite file as production DB or blindly convert it. Take immutable backup, inventory/count business tables, migrate into an empty Laravel-created MySQL schema in dependency order, preserve IDs where relationships require them, compare counts/key totals/dates, then run application/report checks.

Write a dedicated importer only after confirming the actual SQLite source data that must be retained. Do not assume demo/seed data represents production.

## Seeder
Review current seeder against production-user policy before any production run. Seeding is controlled setup/testing unless explicitly approved.
