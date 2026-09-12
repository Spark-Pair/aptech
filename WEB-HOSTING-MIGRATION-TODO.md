# Web Hosting Migration & Local Attendance Sync Roadmap

> **Working branch:** `web-hosting-sync`
> **Base branch:** `main`
> **Rule:** All work described in this document must be developed and tested on `web-hosting-sync`. Do **not** merge into `main`/`master` until the complete implementation has been properly tested and explicitly approved.

## Mandatory Workflow

**Before starting or continuing any task in this roadmap, read this document first.**

After completing, changing, discovering, or testing work, update this document so it remains the source of truth for project status, decisions, pending work, and test results.

Status legend: `[ ]` Pending · `[~]` In progress · `[x]` Completed & verified · `[!]` Blocked / needs attention

## Non-Negotiable Product & Engineering Rules

- [ ] Preserve the existing UI/UX. This project is an infrastructure/architecture improvement, not a visual redesign.
- [ ] Existing user flows and familiar behavior should remain the same unless a change is technically required and approved.
- [ ] Keep the application modular, maintainable, and reusable. Avoid duplicated business logic, UI logic, and integration code.
- [ ] Prefer reusable services, components/partials, helpers, and clear module boundaries.
- [ ] The application should feel realtime/responsive wherever practical.
- [ ] Normal actions must not require unnecessary full-page refreshes. Use AJAX/fetch/XHR-style requests for forms, sync operations, filters, status updates, CRUD actions, and other suitable interactions.
- [ ] Provide proper loading, success, empty, validation, and error states for asynchronous actions.
- [ ] Avoid introducing VPS-only dependencies into the production web application.
- [ ] Production must be compatible with normal shared web hosting constraints.
- [ ] Security, validation, authorization, data integrity, and duplicate prevention are mandatory for sync APIs.

## Goal 1 — Audit Existing Application

- [ ] Audit routes, controllers, models, services, migrations, jobs/commands, frontend JavaScript, and attendance workflow.
- [ ] Identify SQLite-specific SQL, schema behavior, date handling, indexes, foreign keys, and assumptions.
- [ ] Identify code requiring server-level PHP extensions or long-running processes.
- [ ] Map current ZKTeco attendance import flow end-to-end.
- [ ] Identify pages/actions currently causing unnecessary full-page reloads.
- [ ] Record compatibility risks before modifying production behavior.

## Goal 2 — Database: SQLite Development → MySQL Production

- [ ] Keep Laravel database configuration environment-driven.
- [ ] Production must use MySQL; production must not depend on SQLite.
- [ ] Development/testing may continue to use SQLite where useful, but code/migrations must remain MySQL-compatible.
- [ ] Audit and correct migrations for MySQL compatibility.
- [ ] Audit column types, defaults, nullable fields, timestamps, indexes, unique constraints, and foreign keys.
- [ ] Remove/replace SQLite-specific queries or behavior.
- [ ] Test a clean MySQL migration from an empty database.
- [ ] Test seeders on MySQL and ensure they can be safely run as intended.
- [ ] Define a safe migration/import approach for existing production data if required.
- [ ] Verify CRUD, authentication, HR, employees, shifts, attendance, reports, and other database-backed modules against MySQL.

## Goal 3 — Shared Web Hosting Compatibility

- [ ] Ensure Laravel works correctly when deployed on Hostinger-style shared hosting.
- [ ] Ensure the public web root / `public` directory setup is documented and safe.
- [ ] Verify required PHP version and standard extensions.
- [ ] Configure writable `storage` and `bootstrap/cache` paths correctly.
- [ ] Ensure production configuration uses `APP_ENV=production` and `APP_DEBUG=false`.
- [ ] Ensure cache/session/logging configuration is shared-hosting compatible.
- [ ] Do not make core functionality depend on Supervisor, PM2, root access, persistent workers, or direct LAN access from the hosting server.
- [ ] Use hosting cron only where scheduled server work is genuinely required and supported.
- [ ] Document production deployment/update commands and rollback considerations.

## Goal 4 — Decouple ZKTeco From Hosting Server

Current device communication must not require the public hosting server to connect directly to a private LAN IP such as `192.168.100.125:4370`.

- [ ] Separate local ZKTeco communication from hosted Laravel application responsibilities.
- [ ] Preserve/reuse proven ZKTeco parsing/integration logic where practical.
- [ ] Hosted application must continue working even when an attendance device/agent is offline.
- [ ] Remove production web-request dependency on PHP socket access to the local attendance device.
- [ ] Define clear contracts between device agent and hosted API.

## Goal 5 — Secure Attendance Sync API

- [ ] Add a dedicated, versionable attendance/device sync API.
- [ ] Add device/agent registration or provisioning model.
- [ ] Give each installation/device an independent revocable credential/token.
- [ ] Never store plaintext secrets where hashing or another safer mechanism is appropriate.
- [ ] Authenticate and authorize every agent request.
- [ ] Validate every incoming payload server-side.
- [ ] Support efficient batch attendance uploads.
- [ ] Implement idempotency/duplicate prevention so retries cannot create duplicate punches.
- [ ] Preserve original device timestamps and relevant source/device identifiers.
- [ ] Return explicit acknowledgements so the agent knows what was accepted/rejected.
- [ ] Add safe request limits and meaningful error responses.
- [ ] Record sync health/status without logging credentials or sensitive secrets.

## Goal 6 — Local Sync Agent

Target: a lightweight agent running on a Windows PC/mini-PC on the same LAN as the ZKTeco device.

- [ ] Decide and document the agent runtime/packaging approach before implementation.
- [ ] Agent connects locally to configurable ZKTeco IP/port (currently expected around `192.168.100.125:4370`).
- [ ] Agent reads attendance punches locally.
- [ ] Agent sends data outbound to hosted Laravel API over HTTPS.
- [ ] No inbound router port-forwarding should be required for normal operation.
- [ ] Persist a local sync checkpoint/state.
- [ ] Maintain a durable local retry queue when internet/API is unavailable.
- [ ] Retry safely with backoff without generating duplicate attendance records.
- [ ] Recover automatically after PC restart, network loss, device outage, or API outage.
- [ ] Provide configurable API URL, token/credential, device IP, device port, and sync interval.
- [ ] Protect stored credentials appropriately.
- [ ] Add useful local logs/diagnostics without exposing secrets.
- [ ] Package/install so normal users do not need to manually launch the agent every day.
- [ ] Test offline → online recovery and restart recovery.

## Goal 7 — Device & Sync Status in Existing UI

**Keep the existing visual language/UI/UX. Do not redesign the application.**

- [ ] Show device/agent status using existing UI patterns.
- [ ] Show last successful sync time.
- [ ] Show last agent heartbeat/check-in where useful.
- [ ] Show useful sync errors without exposing sensitive internals.
- [ ] Keep `Sync Now`/related interactions asynchronous where technically appropriate.
- [ ] Update status without unnecessary full-page refreshes.
- [ ] Provide clear loading/success/failure feedback.

## Goal 8 — AJAX / Responsive App Experience

- [ ] Audit current high-frequency interactions for unnecessary reloads.
- [ ] Introduce a reusable AJAX/request layer rather than duplicating request logic page-by-page.
- [ ] Convert suitable forms/actions to asynchronous requests without changing the familiar UI.
- [ ] Keep CSRF, validation errors, authentication redirects, and authorization correct.
- [ ] Prevent double submissions while requests are in progress.
- [ ] Update only affected UI sections after successful operations.
- [ ] Ensure browser navigation/history remains sensible where relevant.
- [ ] Do not turn pages into fragile client-side code merely to avoid refreshes; progressive, maintainable enhancement is preferred.

## Goal 9 — Modularity & Reusability

- [ ] Keep controllers thin; move reusable business/integration logic into appropriate services/actions.
- [ ] Avoid duplicated attendance normalization/import logic between local and server components.
- [ ] Centralize API response conventions and validation patterns where practical.
- [ ] Reuse frontend request/loading/notification patterns.
- [ ] Keep configuration environment-driven; do not hardcode client/device/hosting details.
- [ ] Maintain clear separation between web application, attendance domain logic, sync API, and local agent.

## Goal 10 — Testing & Verification

No merge to `main`/`master` until these are completed and reviewed.

- [ ] Existing automated tests pass.
- [ ] Add tests for sync API authentication.
- [ ] Add tests for invalid/malformed sync payloads.
- [ ] Add tests for duplicate/idempotent attendance uploads.
- [ ] Add tests for device/agent authorization boundaries.
- [ ] Test MySQL migrations from a fresh database.
- [ ] Test application functionality on MySQL.
- [ ] Test Laravel production configuration on shared hosting.
- [ ] Test Local Agent → real/test ZKTeco → hosted API flow.
- [ ] Test temporary internet loss.
- [ ] Test temporary ZKTeco/device loss.
- [ ] Test agent restart/PC restart recovery.
- [ ] Test API/server temporary failure and retry.
- [ ] Test duplicate uploads/retries.
- [ ] Regression-test existing UI/UX and major application workflows.
- [ ] Verify asynchronous actions do not introduce stale UI/data issues.
- [ ] Perform final security/configuration review.

## Goal 11 — Production Deployment

- [ ] Prepare production `.env` requirements/documentation without committing secrets.
- [ ] Configure Hostinger MySQL database and credentials.
- [ ] Deploy tested `web-hosting-sync` build to staging/test hosting first where possible.
- [ ] Run production migrations safely.
- [ ] Verify HTTPS.
- [ ] Install/configure Local Sync Agent on office network.
- [ ] Verify real attendance records end-to-end.
- [ ] Verify backups and rollback path.
- [ ] Obtain explicit approval before merging this branch into `main`/`master`.
- [ ] Merge only after final testing and approval.

## Architecture Target

```text
ZKTeco Device (Office LAN)
        |
        | Local network :4370
        v
Local Sync Agent (Windows / mini-PC)
        |
        | Outbound HTTPS
        v
Laravel Sync API on Shared Web Hosting
        |
        v
MySQL Production Database
        |
        v
Existing Laravel Web UI
```

## Progress Log

### 2026-09-12

- [x] Created dedicated working branch: `web-hosting-sync` from `main`.
- [x] Established rule that `main`/`master` will not be merged/modified by this project until implementation is properly tested and explicitly approved.
- [x] Created this roadmap/source-of-truth document.
- [x] Recorded requirements: production MySQL, shared-hosting compatibility, Local Sync Agent, secure attendance syncing, unchanged UI/UX, modular/reusable architecture, realtime/responsive feel, and AJAX/no unnecessary page refreshes.
- [ ] Next: read this document, then perform the existing application architecture/database/attendance-flow audit before implementation changes.

## Decisions / Notes

- Production database: **MySQL**.
- SQLite: acceptable for local development/tests where appropriate, but **not the production database**.
- Production hosting target: **shared web hosting (Hostinger-compatible)**.
- Attendance connectivity: **Local Sync Agent pushes outbound HTTPS data to Laravel**; hosted Laravel must not depend on direct access to the office private LAN.
- UI/UX: **preserve existing design and user experience**.
- Frontend behavior: **AJAX/asynchronous updates wherever suitable; avoid unnecessary full-page refreshes**.
- Architecture: **modular, reusable, maintainable, configuration-driven**.
- Merge policy: **test first; merge into `main`/`master` only after explicit approval**.
