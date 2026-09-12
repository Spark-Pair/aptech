# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read this document before work; update it after work. Never merge to main/master until tested and explicitly approved.

Status: `[ ]` pending · `[~]` implemented/in testing · `[x]` completed/static verified

## Locked requirements
- [x] Existing UI/UX remains familiar; no redesign.
- [x] Modular/reusable architecture; no duplicated integration/business logic.
- [x] Realtime/responsive feel; reuse existing AJAX/fetch layer and avoid unnecessary page refreshes.
- [x] Production Laravel uses MySQL and targets Hostinger-style shared hosting.
- [x] Hosted Laravel must not require direct ZKTeco/private-LAN access after cutover.

## Completed foundation
- [x] Existing architecture/database/ZKTeco/AJAX audit completed (`WEB-HOSTING-AUDIT.md`).
- [x] Versioned API: `/api/v1/attendance-agent/heartbeat` and `/sync`.
- [x] Agent provisioning model/table and one-time `attendance:agent-provision` token command.
- [x] SHA-256 token storage, active/revoked authentication, device binding, validation and throttling.
- [x] Batch idempotency table/unique constraint and explicit sync acknowledgement.
- [x] Health fields: heartbeat, last successful sync and safe error state.
- [x] Existing `AttendanceImporter` remains authoritative server-side attendance normalization.
- [x] Initial API security/validation feature tests added.

## MySQL / shared hosting
- [~] Static migration compatibility audit positive; real MySQL run required.
- [ ] Clean migrate + seed on MySQL and repeat-safety check.
- [ ] Full CRUD/auth/HR/attendance/report regression against MySQL.
- [ ] Document Hostinger web-root, permissions, PHP extensions, production `.env`, deploy/update/rollback.
- [ ] Configure/test real Hostinger MySQL and HTTPS in staging before production cutover.

## Local Sync Agent
- [x] Runtime decision for MVP: Windows-first PHP CLI using the existing ZKTeco PHP package; one-cycle execution suitable for Windows Task Scheduler.
- [x] Added `local-agent/` with external config and secrets gitignored.
- [x] Local configurable device IP/port/timeout and hosted API URL/token/device ID.
- [x] Local ZKTeco reader uses sockets only on the office PC, never the hosting server.
- [x] Outbound HTTPS API client with bearer token; insecure HTTP rejected by default.
- [x] Durable local SQLite state/queue (agent-only, not production application DB).
- [x] Stable UUID batch IDs + server idempotency for safe replay.
- [x] Exponential capped retry backoff and pending-batch replay before new reads.
- [x] Local acknowledged timestamp checkpoint.
- [x] Heartbeat implemented.
- [~] Auto-start approach selected for MVP: Windows Task Scheduler every minute; installer/service wrapper still pending after real-device verification.
- [ ] Add structured rotating local diagnostics/log file.
- [ ] Test real ZKTeco response shape against reader/normalizer.
- [ ] Test internet loss, API loss, device loss, duplicate replay and PC restart recovery.
- [ ] Package installation/configuration flow for non-technical deployment.

## UI / status
- [x] Existing reusable AJAX layer retained; no SPA rewrite.
- [~] Backend status data exists.
- [ ] Surface online/offline, heartbeat, last sync and safe errors in existing UI using existing visual language and AJAX.

## Tests / cutover
- [ ] Execute full automated suite in a checked-out runtime.
- [ ] Add successful sync + duplicate replay tests with employee fixtures.
- [ ] End-to-end Local Agent -> ZKTeco -> API -> MySQL test.
- [ ] Regression-test existing UI/UX and asynchronous behavior.
- [ ] Only after replacement is proven, remove/disable hosted web-request dependency on ZKTeco sockets/private LAN.
- [ ] Final security review, backup/rollback verification and explicit approval.
- [ ] Merge to main/master only after explicit approval.

## Progress log — 2026-09-12
- [x] Created isolated `web-hosting-sync`; main/master untouched.
- [x] Completed audit and secure server API foundation.
- [x] Added Windows-first Local Sync Agent foundation with durable queue/checkpoint, HTTPS client, heartbeat, local ZKTeco reader and retry/idempotency strategy.
- [x] Legacy hosted ZKTeco path remains intact intentionally for staged migration.
- [~] Next: improve automated sync/idempotency tests and agent diagnostics, then expose sync health in the unchanged UI. Real MySQL/Hostinger and physical ZKTeco verification require the relevant runtime/environment.

## Target
```text
ZKTeco LAN -> Windows Local Agent -> outbound HTTPS -> Laravel API -> AttendanceImporter -> MySQL -> existing AJAX UI
```
