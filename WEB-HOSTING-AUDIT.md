# Web Hosting / MySQL / Attendance Sync Audit

Date: 2026-09-12
Branch: `web-hosting-sync`

This audit supports `WEB-HOSTING-MIGRATION-TODO.md`. No production/main merge is implied by these findings.

## Current Architecture

The project is Laravel 10 / PHP 8.1+ and already uses Laravel's environment-driven database configuration. `.env.example` currently defaults to MySQL, file cache/session, and synchronous queues. The current production attendance sync, however, still asks the hosted Laravel process to open a socket directly to the ZKTeco LAN device.

Current attendance device flow:

```text
Authenticated browser
  -> POST /fetchLogs
  -> AttendanceController::fetchLogs()
  -> ZKTecoService::connect()
  -> PHP sockets -> configured private ZKTeco IP:4370
  -> getAttendanceLogs()
  -> AttendanceImporter::import()
  -> attendances table
```

This direct-device portion is incompatible with the target shared-hosting architecture because a public shared-hosting server cannot normally route to an office private IP such as `192.168.100.125`.

## Database Audit

### Positive findings

- Database connection is environment-driven through normal Laravel configuration.
- `.env.example` already describes a MySQL connection.
- Core migrations use Laravel Schema Builder rather than raw SQLite SQL.
- `employees.empid` is unique.
- `attendances` has a unique `(empid, date)` constraint, which provides useful day-level duplicate protection.
- `attendances.empid` references `employees.empid` with cascade delete.
- Shift assignment uses a nullable constrained foreign key and `nullOnDelete()`.
- Attendance writes use a DB transaction.
- `AttendanceImporter` uses `lockForUpdate()` on the employee row, which is useful on MySQL for serializing concurrent imports.

### Risks / items requiring verification

- A clean migration must still be executed against a real MySQL instance before marking MySQL compatibility complete.
- Existing SQLite production/data contents, if any must be retained, need an explicit data migration/import plan rather than copying the SQLite file.
- MySQL timezone/session behavior must be verified for attendance timestamps.
- Seeders must be tested for repeatability/uniqueness on MySQL.
- All report/calendar queries still need runtime verification against MySQL even though no immediate SQLite-only SQL was identified in the audited core attendance path.

## ZKTeco / Attendance Audit

`ZKTecoService` requires the PHP `sockets` extension and creates the ZKTeco client with configured device IP/port. This is the key server-level/LAN dependency that must move out of hosted Laravel.

`AttendanceImporter` is already a good reusable server-side domain boundary. It validates supported punch types, validates timestamps, ignores unknown employees/pre-joining-date logs, groups records by employee/date, retains earliest IN/latest OUT, writes in a transaction, and avoids unnecessary writes when records are unchanged.

The target refactor should therefore preserve `AttendanceImporter` as the authoritative hosted import/domain service while replacing the source of its logs:

```text
CURRENT:
ZKTecoService -> AttendanceImporter

TARGET:
Local Agent -> HTTPS Sync API -> AttendanceImporter
```

The browser should no longer cause the hosted PHP process to contact the physical device directly.

## Shared Hosting Audit

Positive current choices:

- `QUEUE_CONNECTION=sync` means core behavior does not currently require a persistent queue worker.
- File cache and file sessions are compatible with a single shared-hosting deployment when storage permissions are correct.
- No active Laravel scheduler jobs were found in `App\Console\Kernel`; there is currently no mandatory scheduler/cron dependency.

Required work:

- Remove direct ZKTeco socket connectivity from hosted production flow.
- Add deployment documentation for Hostinger document root/public directory, PHP version/extensions, writable directories, production cache commands, HTTPS, and MySQL settings.
- Ensure production `.env` uses `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, MySQL credentials, and appropriate log level.
- Do not introduce Supervisor/PM2/root/persistent-worker requirements for essential hosted functionality.

## AJAX / Realtime-feel Audit

The app already has a reusable AJAX layer in `public/hr/js/portal.js` rather than being fully page-refresh based. It currently:

- intercepts normal forms unless `data-no-ajax` is present;
- sends JSON/XHR-style requests with CSRF support;
- handles validation/error toast messages;
- disables the submit button while busy;
- asynchronously reloads/replaces only `#main-content`;
- supports AJAX pagination and selected links;
- supports browser history/popstate.

Several controllers already return JSON when requested, including attendance updates, attendance sync, CSV import, attendance generation, and leave creation.

This means the target should **extend and harden the existing AJAX architecture**, not replace the UI or introduce a new frontend framework. Some successful actions currently return `refresh: true`, causing an AJAX fetch and replacement of the entire `#main-content`; later work can make high-frequency operations update smaller sections where doing so remains maintainable.

## UI/UX Audit Decision

No redesign is required. Existing Blade structure, visual styling, navigation, forms, tables, and interaction patterns should remain familiar. Infrastructure changes must not alter the visual product unless a small status/error element is necessary for device-agent visibility.

## Modularity Audit

Positive boundaries already present:

- `ZKTecoService` isolates physical-device access.
- `AttendanceImporter` isolates attendance normalization/import rules.
- `AttendanceReport`, `AttendanceCalendar`, and `AttendanceCsvReader` separate additional attendance responsibilities.
- Controllers already delegate significant attendance logic to services.

Refactor direction:

- Keep physical ZKTeco access exclusively in the Local Sync Agent after migration.
- Keep hosted attendance normalization/import logic server-side.
- Add dedicated API request validation/authentication instead of putting agent-specific logic in web controllers.
- Add dedicated agent/device model(s) for credentials, heartbeat and sync state.
- Keep API credentials out of plaintext storage/logs.

## Key Compatibility Risks

1. Direct private-LAN device access from shared hosting — **must be removed**.
2. PHP `sockets` dependency in hosted attendance sync — **must no longer be required for production web sync**.
3. Real MySQL migration/runtime behavior — **must be tested before merge**.
4. Existing SQLite data migration — **requires explicit plan if existing data must be preserved**.
5. Retry/idempotency — day-level uniqueness helps, but Local Agent/API need request/log-level idempotency and acknowledgements for safe offline retries.
6. Device/agent authentication — must be independent and revocable; browser/session authentication is not sufficient for an unattended Local Agent.
7. Shared-hosting execution limits — sync requests should be bounded/batched and should not rely on long-running PHP requests.

## Recommended Implementation Sequence

1. Establish/verify MySQL-compatible production configuration and migrations.
2. Add server-side device/agent persistence and secure token authentication.
3. Add versioned attendance sync/heartbeat API using the existing `AttendanceImporter` domain logic.
4. Add API tests for auth, validation, duplicate/retry behavior and authorization.
5. Build Local Sync Agent with local ZKTeco access, durable state/queue and HTTPS push.
6. Replace hosted `fetchLogs` direct-device behavior with agent-aware status/sync behavior while preserving existing UI.
7. Add heartbeat/last-sync/error status to the existing UI using current visual patterns and AJAX infrastructure.
8. Test MySQL + shared hosting + real device/agent end-to-end.
9. Only after explicit approval, prepare merge into `main`.

## Audit Result

The application is a good candidate for shared hosting after the device connectivity is decoupled. The existing service separation and AJAX layer reduce the amount of risky refactoring required. No immediate need for a UI rewrite or SPA migration was found.
