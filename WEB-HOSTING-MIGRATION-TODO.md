# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

## Locked
- [x] Same UI/UX; modular/reusable architecture; realtime/AJAX feel; no unnecessary reloads.
- [x] Production Laravel = MySQL on Hostinger-compatible shared hosting.
- [x] After cutover, hosted Laravel never directly accesses ZKTeco/private LAN.

## Completed foundation
- [x] Architecture/database/ZKTeco/AJAX audit (`WEB-HOSTING-AUDIT.md`).
- [x] Versioned heartbeat/sync API, agent model, hashed revocable bearer credentials, device binding, validation/throttling.
- [x] Server batch idempotency + acknowledgement + heartbeat/last-sync/error health fields.
- [x] Existing `AttendanceImporter` remains authoritative hosted business logic.
- [x] API contract documented in `docs/LOCAL-AGENT-API.md`.
- [x] Initial API tests plus successful-batch replay/idempotency coverage added (execution pending).

## MySQL / shared hosting
- [~] Static compatibility audit positive; real MySQL execution required.
- [ ] Clean migrate/seed on MySQL; full CRUD/auth/HR/attendance/report regression.
- [x] Hostinger deployment target documented in `docs/HOSTINGER-DEPLOYMENT.md`.
- [ ] Verify target hosting PHP/extensions/document-root and staging MySQL/HTTPS.

## Local Sync Agent
- [x] Windows-first PHP CLI MVP, external gitignored config, configurable device/API parameters.
- [x] ZKTeco sockets only on office PC; outbound HTTPS API only.
- [x] Durable agent-only SQLite queue/checkpoint, UUID idempotency, capped exponential retries and pending replay.
- [x] Heartbeat, acknowledged timestamp, safe rotating diagnostics.
- [x] PowerShell Task Scheduler install/uninstall helpers with one-minute schedule and overlap prevention.
- [x] Setup/security/recovery README.
- [ ] Verify actual physical ZKTeco response shape.
- [ ] Test internet/API/device loss, duplicate replay and PC restart recovery.
- [ ] Refine non-technical installer after real-device test.

## UI/status
- [x] Existing AJAX layer retained; no SPA rewrite.
- [x] Added reusable server status controller calculating active/online state from heartbeat and returning heartbeat/sync/error fields.
- [ ] Wire status controller into authenticated existing web UI and poll asynchronously without changing visual language.

## Cutover/testing
- [ ] Execute full automated suite in checked-out runtime.
- [ ] End-to-end Agent -> real ZKTeco -> API -> MySQL.
- [ ] UI regression and async stale-data checks.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Security review, backup/rollback, explicit approval, then merge.

## Progress — 2026-09-12
- [x] `web-hosting-sync` isolated; main/master untouched.
- [x] Audit + secure API + Local Agent foundation completed.
- [x] Agent diagnostics, automatic Windows scheduling helpers, API contract and Hostinger deployment guide added.
- [x] API idempotency coverage expanded.
- [x] Server-side agent status endpoint logic added for upcoming AJAX UI integration.
- [x] Legacy hosted ZKTeco path intentionally preserved for staged migration.
- [~] Next: connect agent status to authenticated existing UI, then runtime/environment verification.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
