# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

Documentation index: `docs/README-WEB-HOSTING-MIGRATION.md`.

## Locked
- [x] Same UI/UX; modular/reusable architecture; no browser realtime/polling requirement; users may refresh for latest attendance.
- [x] Production Laravel = MySQL on Hostinger-compatible shared hosting.
- [x] After Local Agent cutover, hosted Laravel never directly accesses ZKTeco/private LAN.
- [x] Multi-branch identity must never assume a ZKTeco `userid` is globally unique across machines/branches.

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
- [~] Actual Hostinger verification: PHP 8.2.33 and required extensions confirmed; Laravel 10.48.28 runs in production mode with debug off, HTTPS APP_URL, file cache/session and sync queue. Live project currently sits under `public_html` with an internal rewrite to `public/`; sensitive-path checks passed, but preferred document-root isolation review remains.
- [x] Hostinger database connectivity verified against MariaDB 11.8.9 at `127.0.0.1`.
- [x] Isolated `web-hosting-sync` staging checkout created outside live `public_html`; Composer production dependencies install successfully on Hostinger.
- [x] Production SQLite -> MariaDB transfer and integrity verification completed; rollback artifacts retained outside webroot.
- [!] Do not deploy the new branch/device-identity migrations to production until current branch-safe regression is completed.

## Local Sync Agent
- [x] Windows-first PHP CLI MVP; external gitignored config and configurable device/API parameters.
- [x] ZKTeco sockets only on office PC; outbound HTTPS only.
- [x] Durable agent-only SQLite queue/checkpoint, UUID transport idempotency, capped retries/pending replay.
- [x] Heartbeat, safe rotating diagnostics and acknowledged timestamp.
- [x] Windows Task Scheduler helpers support one continuous `--run` worker with overlap prevention, no execution ceiling and restart-on-failure.
- [x] Startup installer registers the worker at Windows boot under LocalSystem/SYSTEM with no interactive login or terminal window required; elevation is required at install time.
- [x] Status helper reports task identity/trigger/runtime details and recent agent logs; uninstall preserves config/state/logs.
- [x] Prerequisite checker validates PHP 8.1+, sockets, curl, pdo_sqlite, sqlite3 and Composer autoload.
- [x] Physical user creation, multi-user attendance persistence and continuous polling through Hostinger MariaDB passed.
- [~] New boot-time SYSTEM deployment mode implemented but requires physical reboot/no-login verification on Windows.
- [~] Timestamp/device-log identity: automated same-timestamp/backfill coverage exists; exact physical edge verification remains.
- [ ] Build/refine non-technical client installer + one-time provisioning flow after startup mode is physically verified. Installer must not embed long-lived API tokens.

## Multi-branch / device identity
- [x] Branch model and branch ownership on attendance agents/attendance rows added; historical rows migrate to `Main Branch`.
- [x] Attendance uniqueness changed to branch + employee + date.
- [x] Added `attendance_device_users`: identity is now `(attendance_sync_agent_id, device_user_id) -> employee` rather than global `device_user_id -> empid`.
- [x] Device user sync now creates/reuses an agent-scoped mapping and preserves manually maintained employee business fields.
- [x] Attendance API translates device user IDs through the authenticated agent mapping before invoking `AttendanceImporter`; unmapped device IDs are skipped instead of being attached to a coincidentally matching company employee ID.
- [x] Same device user ID on two agents/branches is covered by regression code and maps to separate employees unless explicitly linked later.
- [x] Legacy first mapping can safely claim an existing employee with matching empid only while that device user ID has not already been mapped by another agent.
- [x] Attendance page supports All Branches / specific branch filtering and shows branch on rows.
- [x] Existing report/export controller already carries branch filters and branch columns; Local Sync Agent status now includes branch name.
- [x] Legacy direct-device fetch is pinned to Main Branch during staged transition so it cannot create unassigned attendance.
- [ ] Run full SQLite regression locally/CI for the new identity migration and tests.
- [ ] Run MySQL regression before Hostinger deployment.
- [ ] Physically verify two-agent/same-device-user-ID behavior before production multi-branch use.

## UI/status
- [x] Existing AJAX layer retained; no SPA rewrite.
- [x] Server status controller provides active/online/heartbeat/sync/error data and now branch identity.
- [x] Operations status remains one fetch on entry/re-entry; continuous browser polling is intentionally disabled.
- [x] Browser realtime/WebSocket/SSE attendance updates explicitly dropped from scope; normal refresh is acceptable.

## Cutover/testing
- [x] Previous Windows SQLite and MySQL regressions passed before multi-branch identity work.
- [x] Attendance Agent API runtime checks previously passed, including successful batch replay/idempotency.
- [x] Local Agent poison-batch handling, retry classification, device timezone handling, durable fingerprints, ACK tracking and cleanup dry-run implemented.
- [x] Installed Rats/ZKTeco library inspected: full attendance reads and bulk `clearAttendance()` only; no per-record delete API found.
- [x] End-to-end real path physically passed: ZKTeco -> Windows Local Agent -> HTTPS -> Hostinger Laravel API -> AttendanceImporter -> MariaDB.
- [x] Production physical test auto-created device users and persisted attendance; continuous polling/dedup physically observed.
- [x] Cleanup dry-run physically reviewed; destructive cleanup remains disabled while unresolved rows exist.
- [x] At-logon background scheduled worker physically ran successfully before startup-mode change.
- [ ] Reinstall latest startup/SYSTEM task and verify after Windows reboot before login; confirm no visible terminal and fresh agent log/heartbeat.
- [ ] Physically verify exact same-timestamp and delayed/backfilled older punches.
- [ ] Approve/implement any destructive device bulk-clear threshold separately; never clear while unresolved/pending/dead-letter/ambiguous rows exist.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Final document-root review, physical-device verification, explicit approval, then merge.

## Progress — 2026-09-14
- [x] Real background `--run` task installed and observed Running on Windows with PHP 8.2.28.
- [x] User confirmed browser realtime is not required; no WebSocket/SSE/browser polling work will be added.
- [x] Startup deployment changed from `AtLogOn` current-user execution to `AtStartup` + built-in `SYSTEM` service-account execution, non-interactive and highest privilege.
- [x] Client deployment direction documented: future one-click installer should provision using a one-time code, not ship long-lived API credentials.
- [x] Multi-branch collision review found global ZKTeco userid assumption unsafe; branch-safe agent/device-user mapping layer implemented before production migration.
- [x] Attendance filters/status presentation updated for branch awareness without redesigning the UI.
- [~] Code is committed only to `web-hosting-sync`; `main`/`master` remain untouched. Runtime regression for this latest identity layer is still required before Hostinger migration.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> authenticated Agent/Branch -> Device User Mapping -> Employee -> AttendanceImporter -> MySQL -> existing AJAX UI (manual refresh)
```
