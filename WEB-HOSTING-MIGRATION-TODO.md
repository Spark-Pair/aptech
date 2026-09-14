# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

Documentation index: `docs/README-WEB-HOSTING-MIGRATION.md`.

## Locked
- [x] Same UI/UX; modular/reusable architecture; no browser realtime/polling requirement; users may refresh for latest attendance.
- [x] Production Laravel = MySQL on Hostinger-compatible shared hosting.
- [x] After Local Agent cutover, hosted Laravel never directly accesses ZKTeco/private LAN.

## Completed foundation
- [x] Architecture/database/ZKTeco/AJAX audit.
- [x] Versioned heartbeat/sync API, agent model, hashed revocable bearer credentials, device binding, validation/throttling.
- [x] Server transport batch idempotency + acknowledgement + heartbeat/last-sync/error health fields.
- [x] Existing `AttendanceImporter` remains authoritative hosted business logic.
- [x] API/security contract documented; auth/validation/idempotency test coverage added.

## MySQL / shared hosting
- [x] Static compatibility audit confirmed by real MySQL 8.0.46 execution on disposable `aptech_test` database.
- [x] MySQL clean-test/existing-data verification checklist documented and linked to result logging.
- [x] Clean migrate/seed on disposable MySQL completed; expanded automated CRUD/auth/HR/attendance/report/status regression passes: 22 tests, 119 assertions.
- [x] Dedicated `phpunit.mysql.xml` keeps the existing SQLite test configuration intact and targets the disposable MySQL test database without storing its password.
- [x] Hostinger deployment/update/rollback target documented.
- [~] Actual Hostinger verification: PHP 8.2.33 and required PDO/MySQL/XML/mbstring/openssl/fileinfo extensions confirmed; Laravel 10.48.28 runs in production mode with debug off, HTTPS APP_URL, file cache/session and sync queue. Live project currently sits under `public_html` with an internal rewrite to `public/`; sensitive-path checks return `.env` 403, `composer.json` 404 and `.git/config` 403, but preferred document-root isolation review remains.
- [x] Hostinger database connectivity verified against MariaDB 11.8.9 at `127.0.0.1`.
- [x] Isolated `web-hosting-sync` staging checkout created outside live `public_html`; Composer production dependencies install successfully on Hostinger.
- [x] Target MariaDB schema has all nine `web-hosting-sync` migrations applied.
- [x] Production SQLite -> MariaDB transfer completed from a protected final SQLite snapshot: 1 user, 1 shift, 2 employees and 47 attendances copied transactionally and verified.
- [x] MariaDB integrity verification passed: zero orphan employee-shift links, zero orphan attendances and zero duplicate employee/date attendance rows; attendance range 2025-05-19 through 2026-09-08.
- [x] Live Laravel cut over to MariaDB/MySQL with production caches rebuilt; all nine migrations report Ran and live Laravel verifies 1 user, 2 employees and 47 attendances.
- [x] Rollback artifacts retained outside webroot, including final pre-MySQL SQLite and environment snapshots.

## Local Sync Agent
- [x] Windows-first PHP CLI MVP; external gitignored config and configurable device/API parameters.
- [x] ZKTeco sockets only on office PC; outbound HTTPS only.
- [x] Durable agent-only SQLite queue/checkpoint, UUID transport idempotency, capped retries/pending replay.
- [x] Heartbeat, safe rotating diagnostics and acknowledged timestamp.
- [x] Windows Task Scheduler helpers now support one continuous `--run` worker with overlap prevention, no execution ceiling and restart-on-failure.
- [x] Startup installer now registers the worker at Windows boot under LocalSystem/SYSTEM with no interactive login or terminal window required; elevation is required at install time.
- [x] Status helper reports task identity/trigger/runtime details and recent agent logs; uninstall preserves config/state/logs.
- [x] Prerequisite checker validates PHP 8.1+, sockets, curl, pdo_sqlite, sqlite3 and Composer autoload.
- [x] Setup/security/recovery guide + expanded physical failure/replay/checkpoint test plan and result log.
- [x] Physical user creation, multi-user attendance persistence and continuous polling through Hostinger MariaDB passed.
- [~] New boot-time SYSTEM deployment mode implemented but requires physical reboot/no-login verification on Windows.
- [~] Timestamp/device-log identity: automated same-timestamp/backfill coverage exists; exact physical edge verification remains.
- [ ] Build/refine non-technical client installer + one-time provisioning flow after startup mode is physically verified. Installer must not embed long-lived API tokens.

## UI/status
- [x] Existing AJAX layer retained; no SPA rewrite.
- [x] Server status controller provides active/online/heartbeat/sync/error data.
- [x] Authenticated status route is inside the existing `auth` route group and separately throttled.
- [x] Operations page has a no-redesign Local Sync Agent status region while legacy direct-device action remains available during staged migration.
- [x] Status is fetched once when Operations is loaded/entered through AJAX; continuous browser polling is intentionally disabled to avoid unnecessary shared-hosting traffic.
- [x] Existing portal AJAX layer escapes server status values and reloads status when Operations is entered through AJAX navigation.
- [x] Server-side runtime coverage verifies status auth, Operations status hook, online/offline health, error fields and token-hash non-disclosure on SQLite and MySQL.
- [x] Browser verification passed: no background status request repeats while remaining on Operations; navigating away and back through AJAX triggers exactly one fresh status request.
- [x] Post-cutover production browser verification confirms authenticated Operations page renders successfully from the deployed `web-hosting-sync` code.
- [x] Browser realtime/WebSocket/SSE attendance updates explicitly dropped from scope; normal refresh is acceptable.

## Cutover/testing
- [x] Windows SQLite regression is deterministic after throttle-isolation fix: two consecutive runs each passed 22 tests / 119 assertions on 2026-09-12.
- [x] Windows MySQL 8.0.46 regression passed: 22 tests / 119 assertions.
- [x] Attendance Agent API runtime checks pass, including successful batch replay/idempotency.
- [x] Local Agent poison-batch handling filters invalid/malformed/future rows before batching.
- [x] Local Agent API failures distinguish transient, permanent payload and auth/config failures; permanent queued failures move to dead-letter storage.
- [x] Device timestamps are interpreted using configured IANA `device_timezone` (Pakistan deployment: `Asia/Karachi`).
- [x] Automatic non-destructive Device User -> Employee sync sends only `userid` and `name` and creates missing employees by `employees.empid = ZKTeco userid`.
- [x] Local Agent continuous `--run`, diagnostic `--once`, durable record fingerprints, ACK tracking and `--cleanup-dry-run` implemented.
- [x] Installed Rats/ZKTeco library inspected: full attendance reads and bulk `clearAttendance()` only; no per-record delete API found.
- [x] End-to-end real path physically passed: ZKTeco -> Windows Local Agent -> HTTPS -> Hostinger Laravel API -> AttendanceImporter -> MariaDB.
- [x] Production physical test auto-created device users 1/Hasan and 2/Hassan and persisted attendance for both.
- [x] Continuous polling physically observed with unchanged history producing `new rows=0` and `pending batches=0`.
- [x] Cleanup dry-run physically reviewed: 7 device rows, 5 ACKed, 0 pending, 2 unresolved future rows; destructive cleanup correctly remained ineligible/disabled.
- [x] At-logon background scheduled worker physically ran successfully before startup-mode change.
- [ ] Reinstall latest startup/SYSTEM task and verify after Windows reboot before login; confirm no visible terminal and fresh agent log/heartbeat.
- [ ] Physically verify exact same-timestamp and delayed/backfilled older punches.
- [ ] Approve/implement any destructive device bulk-clear threshold separately; never clear while unresolved/pending/dead-letter/ambiguous rows exist.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Final document-root review, physical-device verification, explicit approval, then merge.

## Progress — 2026-09-14
- [x] Real background `--run` task installed and observed Running on Windows with PHP 8.2.28.
- [x] Background cycle detected two new rows from 12 device rows, synced them with zero pending batches, then subsequent cycles correctly reported `new rows=0`.
- [x] User confirmed browser realtime is not required; no WebSocket/SSE/browser polling work will be added.
- [x] Startup deployment changed from `AtLogOn` current-user execution to `AtStartup` + built-in `SYSTEM` service-account execution, non-interactive and highest privilege.
- [x] Installer now requires Administrator elevation and continues to validate prerequisites plus one real cycle before registration.
- [x] Client deployment direction documented: future one-click installer should provision using a one-time code, not ship long-lived API credentials.
- [~] Next physical check: pull latest `web-hosting-sync`, reinstall task elevated, confirm `RunAs=SYSTEM` and boot trigger, reboot PC, do not log in immediately, then verify server heartbeat/attendance or log after login.
- [ ] Non-technical installer/provisioning implementation remains next after startup-mode verification.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI (manual refresh for latest data)
```
