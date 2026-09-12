# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

Documentation index: `docs/README-WEB-HOSTING-MIGRATION.md`.

## Locked
- [x] Same UI/UX; modular/reusable architecture; realtime/AJAX feel; no unnecessary reloads.
- [x] Production Laravel = MySQL on Hostinger-compatible shared hosting.
- [x] After cutover, hosted Laravel never directly accesses ZKTeco/private LAN.

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
- [~] Actual Hostinger verification in progress: PHP 8.2.33 and required PDO/MySQL/XML/mbstring/openssl/fileinfo extensions confirmed; Laravel 10.48.28 runs in production mode with debug off, HTTPS APP_URL, file cache/session and sync queue. Live project currently sits under `public_html` with an internal rewrite to `public/`, so final document-root/security review remains.
- [x] Hostinger database connectivity verified against MariaDB 11.8.9 at `127.0.0.1`; target database was initially empty.
- [x] Isolated `web-hosting-sync` staging checkout created outside live `public_html`; Composer production dependencies install successfully on Hostinger.
- [x] Target MariaDB schema now has all nine `web-hosting-sync` migrations applied; the two Local Sync Agent migrations were applied from the isolated staging checkout without switching the live app away from SQLite.
- [~] SQLite -> MySQL migration is required: live production SQLite contains real data (1 user, 2 employees, 1 shift, 47 attendances). Source schema/data inspection and safe importer/transfer verification remain before cutover.

## Local Sync Agent
- [x] Windows-first PHP CLI MVP; external gitignored config and configurable device/API parameters.
- [x] ZKTeco sockets only on office PC; outbound HTTPS only.
- [x] Durable agent-only SQLite queue/checkpoint, UUID transport idempotency, capped retries/pending replay.
- [x] Heartbeat, safe rotating diagnostics and acknowledged timestamp.
- [x] Windows Task Scheduler install/uninstall helpers; prerequisite gate, overlap prevention, execution ceiling.
- [x] Prerequisite checker validates PHP 8.1+, sockets, curl, pdo_sqlite, sqlite3 and Composer autoload.
- [x] Setup/security/recovery guide + expanded physical failure/replay/checkpoint test plan and result log.
- [~] Timestamp checkpoint/device-log identity provisional until same-timestamp/backfill behavior verified; separate from transport idempotency.
- [ ] Execute physical ZKTeco test plan and record actual response shape/results.
- [ ] Refine non-technical installer after real-device test.

## UI/status
- [x] Existing AJAX layer retained; no SPA rewrite.
- [x] Server status controller provides active/online/heartbeat/sync/error data.
- [x] Authenticated status route is inside the existing `auth` route group and separately throttled.
- [x] Operations page has a no-redesign Local Sync Agent status region while legacy direct-device action remains available during staged migration.
- [x] Status is fetched once when Operations is loaded/entered through AJAX; continuous browser polling is intentionally disabled to avoid unnecessary shared-hosting traffic.
- [x] Existing portal AJAX layer escapes server status values and reloads status when Operations is entered through AJAX navigation.
- [x] Server-side runtime coverage verifies status auth, Operations status hook, online/offline health, error fields and token-hash non-disclosure on SQLite and MySQL.
- [x] Browser verification passed: no background status request repeats while remaining on Operations; navigating away and back through AJAX triggers exactly one fresh status request.

## Cutover/testing
- [x] Windows SQLite regression is deterministic after throttle-isolation fix: two consecutive runs each passed 22 tests / 119 assertions on 2026-09-12 (12.36s and 11.13s).
- [x] Windows MySQL 8.0.46 regression is green after throttle-isolation fix on disposable `aptech_test`: 22 tests / 119 assertions on 2026-09-12 (14.004s).
- [x] Attendance Agent API runtime checks pass, including successful batch replay/idempotency.
- [x] Successful sync replay/idempotency test has explicit employee fixture, uses machine `empid`, verifies one sync batch and imported attendance.
- [ ] End-to-end Agent -> real ZKTeco -> API -> MySQL.
- [x] UI regression for Local Sync Agent status: server-side coverage plus browser on-demand/AJAX navigation behavior passed; continuous polling absent.
- [x] Staged-cutover rule documented: Local Agent existence alone never removes legacy direct-device code.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Final security review, backup/rollback, explicit approval, then merge.

## Progress — 2026-09-12
- [x] `web-hosting-sync` isolated; main/master untouched.
- [x] Audit + secure API + Local Agent foundation completed.
- [x] Agent diagnostics, hardened Windows scheduling, prerequisites, API/security docs, MySQL checklist, Hostinger guide, recovery/checkpoint test plan, result log and docs index added.
- [x] Local Sync Agent status wired into existing Operations UI through authenticated endpoint.
- [x] Attendance Agent API/idempotency runtime coverage passes.
- [x] Employee detail and monthly Early/Late presentation regression coverage aligned with current UI without redesigning production views.
- [x] Disposable MySQL 8.0.46 connection verified through PDO/Laravel; all nine migrations and seeding completed cleanly.
- [x] Dedicated MySQL PHPUnit configuration added; it does not store the database password.
- [x] Expanded status regression added: unauthenticated access, Operations status hook, online/offline calculation, health/error payload and token-hash non-disclosure.
- [x] Browser confirmed initial Operations status request returns HTTP 200 and renders the no-agent state.
- [x] User rejected continuous browser polling for shared-hosting load; 30-second timer removed. Status now fetches only when Operations loads/is entered.
- [x] Repeated local runs exposed cache-backed route-throttle counters leaking between PHPUnit processes (`login` and legacy `fetchLogs` could return 429 before functional assertions).
- [x] Test base disables only Laravel `ThrottleRequests` middleware during automated functional tests; production route throttles remain configured and unchanged.
- [x] Post-fix SQLite verification completed twice consecutively: both runs 22/22 tests, 119 assertions.
- [x] Post-fix MySQL 8.0.46 verification completed: 22/22 tests, 119 assertions using `phpunit.mysql.xml`.
- [x] Browser on-demand status verification completed: no automatic repeat while idle; one fresh request when returning to Operations through AJAX navigation.
- [x] Actual Hostinger runtime verified: PHP 8.2.33, required extensions present, Laravel 10.48.28, Composer 2.9.8, production/debug/driver settings suitable for shared hosting.
- [x] Hostinger MariaDB 11.8.9 connection verified; isolated `web-hosting-sync` checkout installed successfully and all nine target migrations are now `Ran` in the staging MariaDB database.
- [x] Real production SQLite data confirmed and retained; live app remains on SQLite during staging migration work.
- [x] Legacy hosted ZKTeco path intentionally preserved.
- [~] Next: inspect production SQLite source schema/tables, implement and verify safe SQLite -> MariaDB transfer, complete document-root/HTTPS security checks, then physical ZKTeco end-to-end cutover testing.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
