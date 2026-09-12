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
- [ ] Verify actual Hostinger PHP/extensions/document-root and staging MySQL/HTTPS.
- [ ] Build SQLite -> MySQL importer only if real production data must be retained, after inspecting source DB.

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
- [x] Existing portal AJAX layer polls status asynchronously every 30 seconds, escapes server values, survives AJAX navigation and stops polling when the status region leaves the DOM.
- [x] Server-side runtime coverage verifies status auth, Operations polling hook, online/offline health, error fields and token-hash non-disclosure on SQLite and MySQL.
- [ ] Browser-test AJAX navigation/poll lifecycle and visual status updates.

## Cutover/testing
- [x] Windows SQLite regression suite is green: 22 passed, 0 failed, 0 skipped, 119 assertions on 2026-09-12.
- [x] Windows MySQL 8.0.46 regression suite is green on disposable `aptech_test`: 22 tests, 119 assertions on 2026-09-12.
- [x] Attendance Agent API runtime checks pass, including successful batch replay/idempotency.
- [x] Successful sync replay/idempotency test has explicit employee fixture, uses machine `empid`, verifies one sync batch and imported attendance.
- [ ] End-to-end Agent -> real ZKTeco -> API -> MySQL.
- [~] UI regression: server-side status/auth/polling-hook coverage passes; browser AJAX navigation/poll lifecycle remains.
- [x] Staged-cutover rule documented: Local Agent existence alone never removes legacy direct-device code.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Final security review, backup/rollback, explicit approval, then merge.

## Progress — 2026-09-12
- [x] `web-hosting-sync` isolated; main/master untouched.
- [x] Audit + secure API + Local Agent foundation completed.
- [x] Agent diagnostics, hardened Windows scheduling, prerequisites, API/security docs, MySQL checklist, Hostinger guide, recovery/checkpoint test plan, result log and docs index added.
- [x] Local Sync Agent status wired into existing Operations UI through authenticated endpoint and 30-second AJAX polling.
- [x] Attendance Agent API/idempotency runtime coverage passes.
- [x] Employee detail and monthly Early/Late presentation regression coverage aligned with current UI without redesigning production views.
- [x] HR tests use report aliases `present_days`/`absent_days`/`working_days`, documented CSV `empid` header, username login, and correct failed-connect cleanup semantics; repeat import asserts a single attendance row.
- [x] Disposable MySQL 8.0.46 connection verified through PDO/Laravel; all nine migrations and seeding completed cleanly (`users=1`, no seeded employees/shifts/attendances).
- [x] Dedicated MySQL PHPUnit configuration added; it does not store the database password.
- [x] Expanded status regression added: unauthenticated access, Operations polling hook, online/offline calculation, health/error payload and token-hash non-disclosure.
- [x] Windows SQLite runtime after status coverage: 22 tests passed, 119 assertions, 5.13s.
- [x] Windows MySQL 8.0.46 runtime after status coverage: 22 tests passed, 119 assertions, 5.050s using `phpunit.mysql.xml`.
- [x] Legacy hosted ZKTeco path intentionally preserved.
- [~] Next: manual browser AJAX navigation/status-poll lifecycle verification, then actual Hostinger staging PHP/extensions/MySQL/HTTPS verification before physical ZKTeco end-to-end cutover testing.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
