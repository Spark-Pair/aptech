# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

Documentation index: `docs/README-WEB-HOSTING-MIGRATION.md`.

## Locked architecture
- [x] Same UI/UX direction; modular/reusable architecture; no browser realtime/polling requirement; users refresh for latest attendance.
- [x] Production Laravel uses MySQL/MariaDB on Hostinger shared hosting.
- [x] ZKTeco sockets exist only on a Local Agent PC on the device LAN; hosted Laravel uses outbound HTTPS from the agent and never reaches the private LAN directly.
- [x] Multi-branch identity never assumes ZKTeco `userid` is globally unique. Identity is agent/device scoped.
- [x] Company users manage branches, devices, branch assignment and device connection settings in the web app. Local `config.json` is not the operational source of truth for device IP/port after provisioning.
- [x] `main`/`master` remain untouched until explicit approval.

## Hosting / database
- [x] Hostinger PHP 8.2.33, Laravel 10.48.28, MariaDB 11.8.9, HTTPS production configuration verified.
- [x] Isolated Hostinger MariaDB test DB/check-out exists; production DB is never used for destructive regression tests.
- [x] Branch/device-user/employee-sequence migrations pass cleanly on Hostinger MariaDB.
- [x] Allocator hardening regression: 58 tests / 239 assertions / 0 failures on Hostinger MariaDB.
- [x] Verified production SQL backup created outside webroot before allocator deployment.
- [x] Production checkout updated to `952191b` and allocator migration `2026_09_14_150000` deployed successfully; production sequence initialized correctly.
- [~] Live project remains under `public_html` with internal rewrite to `public/`; sensitive-path checks passed, preferred document-root isolation review remains.
- [ ] Deploy/test the new app-managed device connection settings migration after local regression passes.

## Local Sync Agent
- [x] Windows PHP CLI agent, durable SQLite queue/checkpoint, UUID batch idempotency, retry/dead-letter handling, heartbeat, rotating diagnostics and safe ACK history.
- [x] Continuous Task Scheduler worker uses `--run`, LocalSystem/SYSTEM, AtStartup, highest privilege, overlap prevention, no execution ceiling and restart-on-failure.
- [x] Existing task physically observed `Running` as SYSTEM with boot trigger.
- [x] Agent safely rejects clearly future-dated device rows.
- [x] Device users sync before attendance; unmapped attendance receives retryable `device_users_not_synced` instead of being acknowledged/lost.
- [x] Heartbeat now returns server-managed device connection settings and Local Agent applies current IP/port/timeout before every device read. This allows IP changes from the web app without editing the Windows config file.
- [ ] Re-run local SQLite automated regression after latest device-management changes.
- [ ] Pull latest branch on Windows and verify remote-config change from old device IP to current `192.168.100.16` without editing `config.json`.
- [ ] Reboot Windows and verify SYSTEM task starts before login, has LAN access and sends a fresh heartbeat/sync with no visible terminal.
- [ ] Build/refine non-technical one-click installer + one-time provisioning flow. Long-lived bearer tokens must never be embedded in a distributable installer.

## Branches / devices / identity
- [x] `branches` model/table; historical attendance/agents assigned to `Main Branch`.
- [x] Attendance uniqueness is `(branch_id, empid, date)`.
- [x] `attendance_device_users` maps `(attendance_sync_agent_id, device_user_id) -> employee`.
- [x] Same device user ID on different agents can map independently without cross-branch collision.
- [x] Row-lock-backed global employee-ID allocator prevents duplicate employee allocation/orphans under competing agent requests and advances past manually created empids.
- [x] Attendance/report UI supports All Branches and selected branch, including branch columns/exports.
- [x] Added authenticated **Settings -> Branches & Devices** management page.
- [x] Users can create/edit/activate/deactivate branches.
- [x] Users can create/edit devices, assign/reassign branch, and manage device name, stable identifier, LAN IP, port, timezone, timeout and active state.
- [x] New device provisioning creates a strong bearer credential and displays it only once; stored server credential remains hashed.
- [x] Credential rotation is explicit and warns that the existing Local Agent will stop authenticating until reprovisioned.
- [ ] Add/complete automated CRUD + authorization + heartbeat remote-config tests for the new management UI/API behavior.
- [ ] Physically verify two agents/two branches including the same ZKTeco `userid` on both devices.

## Existing physical verification
- [x] End-to-end ZKTeco -> Windows Local Agent -> HTTPS -> Laravel -> MariaDB passed previously.
- [x] Device users `1/Hasan` and `2/Hassan` were previously auto-created and attendance persisted in the earlier physical test.
- [x] Continuous polling/dedup previously observed with unchanged history producing no duplicate posts.
- [x] Cleanup dry-run reviewed; destructive device cleanup remains disabled.
- [x] ZKTeco library supports full attendance read and bulk `clearAttendance()` only; no per-record deletion was found.
- [ ] Physically verify exact same-timestamp and delayed/backfilled older punches.
- [ ] Never bulk-clear device attendance while unresolved/pending/dead-letter/ambiguous rows exist.

## Current production/testing state — 2026-09-15
- [x] Production branch/device identity + allocator schema is already deployed; the old roadmap warning saying it was not deployed was stale and has been corrected.
- [x] Production app currently has `Main Branch`; production employee/attendance/device-mapping data was intentionally empty immediately after allocator migration.
- [x] Windows Local Agent task is currently confirmed running as SYSTEM/AtStartup.
- [!] Latest physical log showed the agent could not connect because the ZKTeco LAN IP changed from `192.168.100.19` to `192.168.100.16`.
- [x] User explicitly rejected manually editing the Local Agent for operational IP changes; app-managed branches/devices/settings are now the required final design.
- [x] App-managed branch/device configuration implementation committed on `web-hosting-sync` in `2acb703`.
- [ ] Run automated regression for `2acb703` on SQLite and isolated Hostinger MariaDB.
- [ ] Deploy `2acb703` + new migration to production only after regression passes, then set the existing Office device IP to `192.168.100.16` in the app and verify the running agent picks it up automatically.
- [ ] Final physical multi-branch/startup/edge-case verification, document-root review, explicit approval, then merge.

## Target
```text
Company -> Branches -> Devices
                     |
ZKTeco LAN <- Windows Local Agent <- HTTPS server-managed device config
     |
     +-> users/punches -> authenticated Agent/Branch -> Device User Mapping -> Employee -> AttendanceImporter -> MariaDB -> existing AJAX UI
```
