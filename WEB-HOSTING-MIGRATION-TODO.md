# Web Hosting Migration & Local Attendance Sync Roadmap

> **Working branch:** `web-hosting-sync`
> **Base branch:** `main`
> **Rule:** All work here stays on `web-hosting-sync`. Do **not** merge into `main`/`master` until implementation is properly tested and explicitly approved.

## Mandatory Workflow

**Before starting or continuing any task, read this document first.** After completing, changing, discovering, or testing work, update this document. Detailed audit findings live in `WEB-HOSTING-AUDIT.md`.

Status: `[ ]` Pending · `[~]` In progress · `[x]` Implemented/static-verified · `[!]` Blocked / attention

## Non-Negotiable Rules

- [x] Preserve existing UI/UX; this is not a redesign.
- [x] Keep familiar user flows unless a technical change is required and approved.
- [x] Keep architecture modular, reusable and maintainable; avoid duplicated logic.
- [x] Prefer reusable services/components/helpers and clear module boundaries.
- [x] Keep a realtime/responsive feel wherever practical.
- [x] Avoid unnecessary full-page refreshes; use the existing AJAX/fetch architecture and improve it where appropriate.
- [x] Preserve proper loading/success/validation/error states for async actions.
- [x] Do not introduce VPS-only dependencies into the production web app.
- [x] Target normal shared-web-hosting constraints.
- [x] Security, authorization, validation, data integrity and duplicate prevention are mandatory for sync APIs.

## Goal 1 — Audit Existing Application

- [x] Architecture/database/attendance/AJAX audit completed; see `WEB-HOSTING-AUDIT.md`.
- [x] Current ZKTeco flow mapped and direct private-LAN/socket dependency identified.
- [x] Existing reusable AJAX and attendance-import infrastructure identified.

## Goal 2 — SQLite Development → MySQL Production

- [x] Database configuration is environment-driven and `.env.example` describes MySQL.
- [~] Static migration/MySQL compatibility audit is positive; real MySQL execution remains required.
- [x] No SQLite-only raw SQL identified in audited attendance path.
- [ ] Run clean migrations on empty MySQL.
- [ ] Run/re-run seeders on MySQL and verify intended repeat behavior.
- [ ] Define existing-data migration/import plan if data must be retained.
- [ ] Verify CRUD/auth/HR/employees/shifts/attendance/reports against MySQL.

## Goal 3 — Shared Web Hosting Compatibility

- [~] Architecture is shared-hosting suitable once physical device access is moved to Local Agent.
- [ ] Document public web-root, permissions, required PHP extensions and deployment/update/rollback commands.
- [ ] Prepare/verify production `.env` (`APP_ENV=production`, `APP_DEBUG=false`, MySQL, HTTPS URL).
- [x] Current file cache/session + sync queue need no persistent worker.
- [x] No active scheduler tasks currently require cron.

## Goal 4 — Decouple ZKTeco From Hosting Server

- [x] Architecture selected: Local Agent owns ZKTeco LAN access and pushes outbound HTTPS.
- [x] Hosted `AttendanceImporter` remains authoritative normalization/import logic.
- [x] Versioned server-side API contract foundation implemented without removing legacy device flow.
- [ ] Build Local Agent and verify replacement end-to-end before disabling hosted socket path.
- [ ] After successful replacement testing, make hosted app fully independent of PHP sockets/private LAN.

## Goal 5 — Secure Attendance Sync API

- [x] Added `/api/v1/attendance-agent/heartbeat` and `/api/v1/attendance-agent/sync` endpoints.
- [x] Added `AttendanceSyncAgent` provisioning model/table.
- [x] Independent bearer credential per device; only SHA-256 token hash stored server-side.
- [x] Added `attendance:agent-provision {name} {device_identifier}` command; plaintext token shown only when provisioned/rotated.
- [x] Added dedicated bearer-token authentication middleware with active/revoked check.
- [x] Added server-side FormRequest validation and max 1000 logs per batch.
- [x] Device identifier is bound to the authenticated credential.
- [x] Added batch idempotency table with unique `(attendance_sync_agent_id, batch_id)` constraint.
- [x] Added explicit duplicate/accepted/skipped/updated-day acknowledgements.
- [x] Existing API throttling plus endpoint throttle remains enabled.
- [x] Heartbeat, last successful sync and safe last-error fields are recorded without plaintext credentials.
- [~] Original timestamps and employee IDs are preserved through existing importer; richer source/punch-level audit persistence can be added if required.
- [ ] Runtime-test API auth, validation, idempotency and importer behavior.

## Goal 6 — Local Sync Agent

Target: lightweight Windows PC/mini-PC agent on same LAN as ZKTeco.

- [ ] Decide/document runtime and packaging.
- [ ] Configurable ZKTeco IP/port; expected around `192.168.100.125:4370`.
- [ ] Read attendance locally and push to versioned API over HTTPS.
- [ ] Durable checkpoint/retry queue with safe backoff/idempotent batch IDs.
- [ ] Recover after PC/network/device/API restart/outage.
- [ ] Configurable API URL/token/device IP/port/sync interval.
- [ ] Protect credentials, safe diagnostics, auto-start packaging.
- [ ] Test offline -> online and restart recovery.

## Goal 7 — Device & Sync Status in Existing UI

- [ ] Preserve existing visual language.
- [~] Backend status fields now exist (`last_heartbeat_at`, `last_sync_at`, `last_error`, active state).
- [ ] Surface agent/device status asynchronously in existing UI after agent/API runtime verification.

## Goal 8 — AJAX / Responsive Experience

- [x] Existing reusable AJAX layer identified in `public/hr/js/portal.js`.
- [x] It handles CSRF, async forms, validation/errors, busy states, pagination, selected links and history.
- [ ] Harden/reuse it for status/UI work; no SPA rewrite.

## Goal 9 — Modularity & Reusability

- [x] Existing service boundaries preserved.
- [x] Sync API uses dedicated controller, FormRequest, middleware and models rather than normal web controllers.
- [x] Hosted attendance normalization/import remains in `AttendanceImporter`.
- [ ] Keep physical ZKTeco code exclusively in Local Agent after cutover.
- [ ] Centralize additional API response patterns only if repetition develops.

## Goal 10 — Testing & Verification

No merge to `main`/`master` until completed/reviewed.

- [~] Added initial feature tests for missing token, valid heartbeat, revoked/inactive agent, device authorization and bounded payload validation.
- [ ] Execute full automated test suite in a checked-out runtime.
- [ ] Add/execute successful sync + duplicate batch/idempotency tests with employee fixtures.
- [ ] Test clean MySQL migrations and application functionality on MySQL.
- [ ] Test shared-hosting production configuration.
- [ ] Test Local Agent -> real/test ZKTeco -> hosted API.
- [ ] Test internet/device/API outage, retries and restart recovery.
- [ ] Regression-test existing UI/UX and workflows.
- [ ] Final security/configuration review.

## Goal 11 — Production Deployment

- [ ] Prepare production `.env` requirements without secrets.
- [ ] Configure Hostinger MySQL credentials.
- [ ] Deploy tested branch to staging/test hosting where possible.
- [ ] Run migrations safely and verify HTTPS.
- [ ] Provision/install/configure Local Sync Agent.
- [ ] Verify real attendance end-to-end, backups and rollback.
- [ ] Obtain explicit approval before merge.
- [ ] Merge only after final testing and approval.

## Target Architecture

```text
ZKTeco Device (Office LAN)
        | local :4370
        v
Local Sync Agent (Windows / mini-PC)
        | outbound HTTPS + bearer credential + idempotent batches
        v
Laravel /api/v1/attendance-agent/* on Shared Web Hosting
        |
        v
AttendanceImporter -> MySQL
        |
        v
Existing Laravel Web UI + AJAX architecture
```

## Progress Log

### 2026-09-12

- [x] Created `web-hosting-sync` from `main`; main/master untouched.
- [x] Created roadmap/source-of-truth and architecture audit.
- [x] Confirmed principal hosting blocker: hosted ZKTeco socket/private-LAN access.
- [x] Implemented secure attendance-agent API foundation: agent model, hashed/revocable bearer credential middleware, provisioning command, heartbeat, bounded batch sync, device binding, batch idempotency, health fields and initial feature tests.
- [x] Kept legacy hosted ZKTeco flow intact for safe staged migration; no premature cutover.
- [~] Next: implement Local Sync Agent foundation and expand runtime/idempotency tests; separately execute MySQL/Hostinger environment verification when a suitable runtime is available.

## Decisions / Notes

- Production DB: **MySQL**. SQLite may remain for local development/tests only where useful.
- Hosting: **Hostinger-compatible shared web hosting**.
- Connectivity: **Local Agent -> outbound HTTPS -> Laravel**; hosted Laravel must not access office private LAN after cutover.
- Agent auth: independent random bearer token; server stores **SHA-256 hash only**; provisioning/rotation command reveals plaintext once.
- Retry/idempotency: agent supplies stable `batch_id`; server uniquely binds it to agent and returns prior acknowledgement on replay.
- UI/UX: unchanged/familiar; extend existing AJAX architecture.
- Merge: test first; explicit approval required before `main`/`master` merge.
