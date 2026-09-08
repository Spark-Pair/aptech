# Aptech HR & Attendance Portal

Laravel 10 application with the Ace / Bootstrap 3 interface adapted from `../../sir azeem2/hr`. Theme assets are served locally; no frontend build or CDN is required.

## Run the existing application

```powershell
cd "C:\Software\sir azeem\Sir Azeem\laravel"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

Keep the existing `.env`, database and administrator login. The UI and attendance operations use the existing employees/attendances schema; no database reset or new migration is needed for an existing installation.

For a fresh installation: install Composer dependencies, copy `.env.example` to `.env`, configure the database, run `php artisan key:generate` and `php artisan migrate`. Create an administrator through your existing provisioning process. There are no bundled default credentials.

## Screens and operations

- Dashboard: database-backed monthly totals and paginated employee records.
- Employees: search, department/month filters, add/edit, active/inactive status, monthly detail and print.
- Attendance: month/name/status filters, actual punches, working hours and incomplete-punch indicators.
- Leaves: record approved leave ranges, skipping Sundays and refusing to overwrite punches. This records leave days; it is not an approval workflow.
- Generate attendance: fill missing completed days for currently active employees, starting from their joining date. Existing attendance and leave records stay intact. Sundays are off days.
- CSV upload: header `empid,timestamp,type`; timestamp `YYYY-MM-DD HH:MM:SS`; type 0/4 check-in, 1/5 check-out. Limit 5 MB / 20,000 punches. All rows validate before writes. Unknown employee codes are reported as skipped.
- Device sync: POST with CSRF protection, rate limiting and a cache lock. Re-importing preserves earliest check-in/latest check-out. Device logs are retained.

Working days are recorded present, absent and leave days. Import punches before generating missing days. Physical attendance takes precedence over recorded leave. Salary is the stored monthly salary; payroll deductions and leave approvals are not inferred from template sample data. Daily punch pairing assumes shifts start and finish on the same calendar day; incomplete punches are shown explicitly.

## Configuration

```dotenv
APP_TIMEZONE=Asia/Karachi
ZKTECO_IP=192.168.100.125
ZKTECO_PORT=4370
ZKTECO_TIMEOUT=5
ATTENDANCE_SHIFT_START=
ATTENDANCE_SHIFT_END=
```

Set `ATTENDANCE_SHIFT_START` and `ATTENDANCE_SHIFT_END` to scheduled local times in `HH:MM` format, then run `php artisan config:clear`. Early Min counts whole minutes leaving before the shift end; Late Min counts whole minutes arriving after the shift start, with a minimum of zero and no grace period. These calculations use same-day shifts. Daily values are unavailable for non-present records or missing relevant punches. Monthly columns total available values for the selected month. Unconfigured shift times display a dash instead of an assumed schedule.

Enable PHP `sockets` for hardware sync and connect the application host to the device network. Set the application timezone to the device's local timezone. Set `attendance.off_day` in `config/attendance.php` if the weekly off day differs. Device sync has a recoverable error state when hardware or the extension is unavailable.

Employee passwords are hashed on create/change. Existing passwords are retained when an edit leaves the password blank. Machine codes cannot be changed after creation because they identify historical attendance. Employee accounts remain separate from administrator logins in the existing users table.

## Structure

- `app/Http/Requests`: reusable report and employee validation.
- `app/Services/AttendanceReport.php`: date ranges, aggregated counts and report queries.
- `app/Services/AttendanceImporter.php`: transactional punch normalization and repeat-safe imports.
- `app/Services/AttendanceCalendar.php`: missing-day generation and leave conflict handling.
- `app/Services/ZKTecoService.php`: configurable hardware adapter.
- `resources/views/layouts`, `partials`, `components`: shared Ace shell, navigation, fields, tables, summaries and pagination.
- `public/hr`: selected local theme assets and portal CSS/JS. Source license is included.

## Verification

```powershell
php artisan test
php artisan view:cache
```

PHPUnit forces an isolated in-memory SQLite database. Physical device sync requires a reachable ZKTeco device; automated tests use a mock adapter. Browser checks use a separate review database under ignored `storage/app/ui-review`.
