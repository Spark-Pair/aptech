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
- [~] Static compatibility audit positive; real MySQL execution required.
- [x] MySQL clean-test/existing-data verification checklist documented and linked to result logging.
- [ ] Clean migrate/seed on disposable MySQL; full CRUD/auth/HR/attendance/report regression.
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
- [ ] Runtime-test authenticated/unauthenticated status behavior and AJAX navigation/poll lifecycle in browser.

## Cutover/testing
- [~] First automated runtime suite executed on Windows: 16 passed, 3 failed, 1 skipped, 95 assertions. Failures traced to employee edit/update routing and update unique validation; fixes committed. Re-run required.
- [x] Attendance Agent API runtime checks passed for missing token, active heartbeat, inactive agent rejection, wrong device rejection and bounded log validation.
- [~] Successful sync replay/idempotency test is still skipped because its employee/import fixture is not yet implemented.
- [ ] End-to-end Agent -> real ZKTeco -> API -> MySQL.
- [ ] UI regression and async stale-data checks.
- [x] Staged-cutover rule documented: Local Agent existence alone never removes legacy direct-device code.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Final security review, backup/rollback, explicit approval, then merge.

## Progress — 2026-09-12
- [x] `web-hosting-sync` isolated; main/master untouched.
- [x] Audit + secure API + Local Agent foundation completed.
- [x] Agent diagnostics, hardened Windows scheduling, prerequisites, API/security docs, MySQL checklist, Hostinger guide, recovery/checkpoint test plan, result log and docs index added.
- [x] Local Sync Agent status wired into existing Operations UI through authenticated endpoint and 30-second AJAX polling.
- [x] First real `php artisan test` run received: dependencies/autoload/cache clear succeeded; 16 tests passed, 3 failed, 1 skipped.
- [x] Fixed missing employee `edit`/`update` resource routes that caused the two 302 failures.
- [x] Fixed employee update validation so unchanged username correctly ignores the current employee and immutable `empid` remains prohibited on update.
- [x] Legacy hosted ZKTeco path intentionally preserved.
- [~] Next: pull latest `web-hosting-sync` and rerun `php artisan test`. If green except the intentional idempotency skip, implement that fixture/test, then move to clean MySQL verification.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
