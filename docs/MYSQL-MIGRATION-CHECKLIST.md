# MySQL Migration Verification Checklist

Production Laravel must use MySQL. SQLite may remain for local tests/development and the Local Agent's private retry queue only.

## Empty database verification
1. Create a disposable MySQL database.
2. Configure `.env` with `DB_CONNECTION=mysql` and its credentials.
3. Run `php artisan optimize:clear`.
4. Run `php artisan migrate:fresh --seed` only on the disposable test database.
5. Run `php artisan test`.
6. Exercise login, employees, shifts, attendance import/reporting and AJAX forms/navigation.

## Existing SQLite data
Do not copy the SQLite file into production and do not convert it blindly. Before migration, take an immutable backup, count records per business table, export/transform in dependency order, import into an empty migrated MySQL schema, then compare counts and key totals/dates. Preserve IDs where relationships depend on them. Re-run application-level validation/report checks after import.

A dedicated importer should be written only after the actual source SQLite data that must be retained is confirmed; production migration should not assume seed/demo data equals real data.

## Seeder warning
The current seeder must be reviewed against production-user policy before it is used on production. Seeding is for controlled setup/testing unless explicitly approved.
