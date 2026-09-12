# Web Hosting Migration & Local Attendance Sync Roadmap

> **Working branch:** `web-hosting-sync`
> **Base branch:** `main`
> **Rule:** All work here stays on `web-hosting-sync`. Do **not** merge into `main`/`master` until implementation is properly tested and explicitly approved.

## Mandatory Workflow

**Before starting or continuing any task, read this document first.** After completing, changing, discovering, or testing work, update this document. Detailed audit findings live in `WEB-HOSTING-AUDIT.md`.

Status: `[ ]` Pending · `[~]` In progress · `[x]` Completed & verified · `[!]` Blocked / attention

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

- [x] Audit routes, controllers, models, services, migrations, scheduler and frontend JavaScript relevant to the migration.
- [x] Audit core database path for SQLite-specific assumptions and MySQL compatibility risks.
- [x] Identify server-level/long-running dependencies: direct ZKTeco access requires PHP sockets; no active scheduler job currently requires cron.
- [x] Map current ZKTeco flow: browser -> `/fetchLogs` -> `AttendanceController` -> `ZKTecoService` -> LAN device -> `AttendanceImporter` -> DB.
- [x] Audit AJAX behavior: reusable `public/hr/js/portal.js` already intercepts suitable forms/links and replaces `#main-content` asynchronously.
- [x] Record compatibility risks in `WEB-HOSTING-AUDIT.md`.

## Goal 2 — SQLite Development → MySQL Production

- [x] Confirm database configuration is environment-driven and `.env.example` already describes MySQL.
- [ ] Production must use MySQL; production must not depend on SQLite.
- [ ] Keep development/testing SQLite-compatible where useful while ensuring MySQL compatibility.
- [~] Audit/correct migrations for MySQL compatibility. Static audit is positive; real MySQL migration test remains required.
- [~] Verify types/defaults/nullability/timestamps/indexes/foreign keys on real MySQL.
- [x] No SQLite-only raw SQL identified in the audited core attendance path.
- [ ] Run clean migrations on an empty MySQL database.
- [ ] Test seeders on MySQL and verify safe intended repeat behavior.
- [ ] Define safe existing SQLite-data migration/import plan if existing data must be retained.
- [ ] Verify CRUD/auth/HR/employees/shifts/attendance/reports against MySQL.

## Goal 3 — Shared Web Hosting Compatibility

- [~] Laravel architecture is suitable for Hostinger-style hosting after direct LAN/ZKTeco dependency is removed.
- [ ] Document safe `public` web-root setup.
- [ ] Verify required PHP version/extensions on target hosting.
- [ ] Document writable `storage` and `bootstrap/cache` requirements.
- [ ] Prepare production configuration (`APP_ENV=production`, `APP_DEBUG=false`, correct URL/logging).
- [x] Current file cache/session and synchronous queue configuration do not require persistent workers.
- [x] No active Laravel scheduler tasks currently require cron.
- [ ] Document deployment/update/rollback commands.

## Goal 4 — Decouple ZKTeco From Hosting Server

- [~] Architecture decided: Local Agent owns physical ZKTeco access; hosted Laravel receives HTTPS sync data.
- [x] Preserve/reuse `AttendanceImporter` as authoritative hosted normalization/import logic.
- [ ] Ensure hosted app remains functional while device/agent is offline.
- [ ] Remove production web-request dependency on PHP sockets/private LAN.
- [ ] Implement clear Local Agent <-> API contract.

## Goal 5 — Secure Attendance Sync API

- [ ] Add versioned device/attendance sync API.
- [ ] Add device/agent provisioning model.
- [ ] Independent revocable credential/token per installation/device.
- [ ] Do not store plaintext secrets where hashing/safer storage is appropriate.
- [ ] Authenticate/authorize every agent request.
- [ ] Validate payloads server-side.
- [ ] Support bounded batch uploads.
- [ ] Add request/log-level idempotency so retries cannot duplicate punches.
- [ ] Preserve original device timestamp/source identifiers.
- [ ] Return explicit accepted/rejected acknowledgements.
- [ ] Add rate/request limits and useful safe errors.
- [ ] Record heartbeat/sync health without exposing secrets.

## Goal 6 — Local Sync Agent

Target: lightweight Windows PC/mini-PC agent on same LAN as ZKTeco.

- [ ] Decide/document runtime and packaging.
- [ ] Configurable ZKTeco IP/port; current expected device is around `192.168.100.125:4370`.
- [ ] Read attendance locally.
- [ ] Push outbound over HTTPS; no router port forwarding required.
- [ ] Durable local checkpoint/state and retry queue.
- [ ] Backoff/retry safely without duplicates.
- [ ] Recover after PC/network/device/API restart/outage.
- [ ] Configurable API URL, credential, device IP/port, sync interval.
- [ ] Protect stored credentials and safe diagnostics/logging.
- [ ] Auto-start/package so daily manual launch is unnecessary.
- [ ] Test offline -> online and restart recovery.

## Goal 7 — Device & Sync Status in Existing UI

- [ ] Keep existing visual language; no redesign.
- [ ] Show agent/device status, last heartbeat and last successful sync.
- [ ] Show useful safe sync errors.
- [ ] Keep sync/status interactions asynchronous.
- [ ] Update status without unnecessary full-page refreshes.

## Goal 8 — AJAX / Responsive Experience

- [x] Existing reusable AJAX layer identified in `public/hr/js/portal.js`.
- [x] Existing layer handles CSRF, async forms, validation/errors, busy states, pagination, selected links and browser history.
- [ ] Harden/reuse this layer instead of introducing a new frontend framework.
- [ ] Convert/improve suitable high-frequency actions without changing UI.
- [ ] Prevent double submissions and update only affected sections where maintainable.
- [ ] Preserve sensible browser navigation/history.

## Goal 9 — Modularity & Reusability

- [x] Existing service boundaries identified: `ZKTecoService`, `AttendanceImporter`, `AttendanceReport`, `AttendanceCalendar`, `AttendanceCsvReader`.
- [ ] Keep physical ZKTeco logic exclusively in Local Agent after migration.
- [ ] Keep hosted attendance normalization/import rules server-side.
- [ ] Dedicated API request/authentication layer; avoid agent logic in normal web controllers.
- [ ] Centralize API response/validation patterns.
- [ ] Reuse frontend loading/request/notification patterns.
- [ ] Keep client/device/hosting details environment/config driven.

## Goal 10 — Testing & Verification

No merge to `main`/`master` until completed/reviewed.

- [ ] Existing automated tests pass.
- [ ] Add sync API auth/validation/idempotency/authorization tests.
- [ ] Test clean MySQL migrations and application functionality on MySQL.
- [ ] Test shared-hosting production configuration.
- [ ] Test Local Agent -> real/test ZKTeco -> hosted API.
- [ ] Test internet/device/API outage and recovery.
- [ ] Test agent/PC restart recovery.
- [ ] Test duplicate uploads/retries.
- [ ] Regression-test existing UI/UX and major workflows.
- [ ] Verify async actions do not create stale UI/data issues.
- [ ] Final security/configuration review.

## Goal 11 — Production Deployment

- [ ] Prepare production `.env` requirements without committing secrets.
- [ ] Configure Hostinger MySQL credentials.
- [ ] Deploy tested branch to staging/test hosting where possible.
- [ ] Run migrations safely and verify HTTPS.
- [ ] Install/configure Local Sync Agent.
- [ ] Verify real attendance end-to-end.
- [ ] Verify backups/rollback.
- [ ] Obtain explicit approval before merge.
- [ ] Merge only after final testing and approval.

## Target Architecture

```text
ZKTeco Device (Office LAN)
        | local :4370
        v
Local Sync Agent (Windows / mini-PC)
        | outbound HTTPS
        v
Laravel Sync API on Shared Web Hosting
        |
        v
MySQL Production Database
        |
        v
Existing Laravel Web UI + existing AJAX architecture
```

## Progress Log

### 2026-09-12

- [x] Created `web-hosting-sync` from `main`; main/master untouched.
- [x] Created this roadmap and mandatory read/update workflow.
- [x] Locked requirements: MySQL production, shared hosting, Local Sync Agent, secure sync, unchanged UI/UX, modular/reusable code, AJAX/realtime feel.
- [x] Completed first architecture/database/attendance/AJAX audit and added `WEB-HOSTING-AUDIT.md`.
- [x] Confirmed existing code already has useful reusable `AttendanceImporter` and AJAX infrastructure.
- [x] Confirmed principal hosting blocker: hosted `fetchLogs` currently requires PHP sockets and direct routing to private ZKTeco LAN IP.
- [x] Confirmed no active scheduler jobs/persistent queue worker requirement in current core app.
- [~] Next implementation phase: MySQL verification/configuration + secure device/agent API foundation. Do not remove existing working behavior until replacement path and tests are in place.

## Decisions / Notes

- Production DB: **MySQL**. SQLite may remain for local development/tests only where useful.
- Hosting: **Hostinger-compatible shared web hosting**.
- Connectivity: **Local Agent -> outbound HTTPS -> Laravel**; hosted Laravel must not access office private LAN directly.
- UI/UX: **unchanged/familiar**.
- Frontend: **extend existing AJAX architecture; no unnecessary full-page refreshes and no unnecessary SPA rewrite**.
- Architecture: **modular, reusable, maintainable, configuration-driven**.
- Merge: **test first; explicit approval required before `main`/`master` merge**.
