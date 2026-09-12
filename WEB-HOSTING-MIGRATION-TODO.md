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
- [x] Initial API tests plus successful-batch replay/idempotency test coverage added (runtime execution still pending).

## MySQL / shared hosting
- [~] Static compatibility audit positive; real MySQL execution required.
- [ ] Clean migrate/seed on MySQL; full CRUD/auth/HR/attendance/report regression.
- [ ] Document Hostinger web-root, permissions, PHP extensions, `.env`, deploy/update/rollback.
- [ ] Staging Hostinger MySQL + HTTPS verification.

## Local Sync Agent
- [x] Windows-first PHP CLI MVP reuses existing ZKTeco package and runs as a one-cycle task.
- [x] External gitignored config; device/API parameters configurable.
- [x] ZKTeco sockets live only on office PC; API is outbound HTTPS only.
- [x] Durable agent-only SQLite pending queue/checkpoint.
- [x] UUID batches, server idempotency, capped exponential retries, pending replay before new reads.
- [x] Heartbeat and acknowledged-timestamp checkpoint.
- [x] Safe rotating local diagnostics (`agent.log` + previous file), with bearer-like secret redaction.
- [~] MVP auto-start plan: Windows Task Scheduler every minute; installer/service wrapper after physical-device verification.
- [ ] Verify actual ZKTeco response shape against agent normalizer.
- [ ] Test internet/API/device loss, duplicate replay and PC restart recovery.
- [ ] Package non-technical installation/configuration flow.

## UI/status
- [x] Existing reusable AJAX layer retained; no SPA rewrite.
- [~] Backend health/status data exists.
- [ ] Surface online/offline, heartbeat, last sync and safe errors in existing UI with existing visual language/AJAX.

## Cutover/testing
- [ ] Execute full automated suite in checked-out runtime.
- [ ] End-to-end Agent -> real ZKTeco -> API -> MySQL.
- [ ] UI regression and async stale-data checks.
- [ ] Only after replacement proves stable, disable/remove hosted ZKTeco socket/private-LAN dependency.
- [ ] Security review, backup/rollback, explicit approval, then merge.

## Progress — 2026-09-12
- [x] `web-hosting-sync` isolated; main/master untouched.
- [x] Audit + secure API foundation completed.
- [x] Local Agent foundation implemented: LAN reader, HTTPS client, durable queue, checkpoint, retry/idempotency, heartbeat and diagnostics.
- [x] API idempotency test coverage expanded.
- [x] Legacy hosted ZKTeco path intentionally preserved for staged migration.
- [~] Next: device/sync status UI and packaging support. MySQL/Hostinger + physical ZKTeco verification require the corresponding environments.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
