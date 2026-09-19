# Web Hosting Migration & Local Attendance Sync Roadmap

> Working branch: `web-hosting-sync` | Base: `main`
> Rule: read before work, update after work. Never merge to main/master until tested and explicitly approved.

Documentation index: `docs/README-WEB-HOSTING-MIGRATION.md`.

## Locked architecture
- [x] Preserve existing Ace/Bootstrap UI direction; modular/reusable; no browser polling/WebSocket/SSE requirement.
- [x] Production Laravel uses MySQL/MariaDB on Hostinger shared hosting.
- [x] Hosted Laravel never connects directly to private ZKTeco LAN sockets.
- [x] Company users manage branches, devices, branch assignment, device IP, port, timezone, timeout and Local Agent assignment in the web app.
- [x] Device IP/port and Local Agent assignment are server-managed after provisioning; normal operation never requires editing local config.
- [x] One Local Agent process manages multiple ZKTeco devices assigned to it on the same LAN/Wi-Fi.
- [x] Each device remains independently branch-scoped and device-user identity remains device-scoped.
- [x] No device IP, branch ID or Local Agent assignment is hardcoded in application logic.
- [x] `main`/`master` remain untouched until explicit approval.

## Verified baseline
- [x] Windows SQLite regression: 58 tests / 239 assertions / 0 failures.
- [x] Isolated Hostinger MariaDB regression: 58 tests / 239 assertions / 0 failures.
- [x] Production branch/device identity + allocator schema deployed.
- [x] Production device connection settings migration deployed as Batch 3.
- [x] Verified production SQL backup exists outside webroot.
- [x] Remote device configuration was physically consumed by the Local Agent and sync resumed after an IP change without editing local config.
- [x] Employee AJAX edit/viewport fixes deployed through `0702180`.
- [x] Server-side configured-device timezone import fix deployed through `4fcbf45`.

## Multi-device Local Agent — 2026-09-16
- [x] Added `local_agent_key` grouping migration so multiple device records can share one Local Agent credential without losing per-device identity.
- [x] Heartbeat returns an assigned `devices` collection while retaining the legacy single `device` field for backward compatibility.
- [x] Users/attendance API authorization resolves submitted `device_identifier` only inside the authenticated Local Agent group.
- [x] Local Agent loops through all assigned devices each cycle and applies each device's server-managed IP/port/timeout.
- [x] Failure of one physical device is isolated so other assigned devices continue syncing.
- [x] Local SQLite fingerprints and user-roster state are scoped per additional device while preserving legacy fingerprints for the originally provisioned device.
- [x] Credential rotation applies to every device in that Local Agent group.
- [x] Production migration/server changes through `5d6da1b` deployed to Hostinger; migration `2026_09_16_120000_group_devices_by_local_agent` ran successfully.
- [x] Windows Local Agent updated to `5d6da1b`; `--once` verified new runner with `Devices=1, connected=1` for `zk-office-1`.
- [x] Production diagnosis confirmed `zk-office-1` is `agent-1` while never-connected `2222` is `agent-2`.
- [x] Removed implicit "most recently active" Local Agent assignment. Device create/update now uses explicit web-selected `local_agent_key`; existing assignment is selected in UI.
- [x] Branch/device/credential forms use normal full-page submission (`data-no-ajax`) to avoid global AJAX interception issues.
- [x] Explicit Local Agent assignment UI/controller commits through `e31016d` deployed to Hostinger and browser-visible.
- [x] Shared-token migration `2026_09_16_160000_allow_shared_local_agent_token_hash.php` is confirmed applied on Hostinger; production reported nothing pending after deployment through `99d34ad`.
- [x] Production heartbeat now returns both assigned devices to the same Local Agent; physical test showed `Devices=2`.
- [x] Windows background agent verified heartbeat with two assigned devices and fault isolation: unavailable `2222` did not block `zk-office-1`; observed `Devices=2, connected=1`.
- [x] Fresh punch on available `zk-office-1` verified end-to-end: device rows increased 33→34, `new rows=1`, pending=0, and attendance appeared after manual web refresh. Second physical device remains unavailable for a two-online-device test.
- [ ] Put the updated multi-device Local Agent on the Zorin demo machine when switching back to Zorin.
- [x] Windows SQLite regression after latest multi-device/standalone-agent changes: 63 tests / 248 assertions / 0 failures.
- [ ] Re-run isolated Hostinger MariaDB regression after the latest multi-device/standalone-agent changes.

## Current known issues / pending verification
- [x] Empty device-user snapshots are valid server input (`af9edee`); production now includes this change.
- [x] Production code/migration through `5d6da1b` deployed and optimized on 2026-09-16.
- [x] Explicit Local Agent assignment UI is deployed/browser-visible; first assignment attempt exposed the legacy unique `token_hash` DB constraint and the migration fix is committed.
- [x] Retry acknowledgement checkpoint now uses the queued batch's `device_identifier`, preventing multi-device replay from updating the wrong device checkpoint.
- [x] Fresh live attendance no longer reproduces the previous HTTP 422: 2026-09-19 physical punch synced successfully with pending=0.
- [x] Historical dead-letter inspection completed: three 2026-09-15 `zk-office-1` type=1 punches (17:41:49, 17:44:20, 17:54:51) were old HTTP 422 entries. Production already held a later checkout at 21:41:07 Asia/Karachi; importer reconciliation returned `days=0, skipped=0`, so no production attendance change or replay/reset was required. Dead-letter rows are retained as audit history.
- [x] Known truly future-dated uid50/51 rows are intentionally skipped locally.
- [ ] Latest shifts/leaves/attendance/operations modal UI refactor is deployed with the `5d6da1b` pull but still needs browser verification.
- [ ] Add/complete automated CRUD + authorization + multi-device heartbeat tests.
- [ ] Verify separate Local Agents for separate physical networks before general multi-site rollout.
- [x] Added self-contained Windows packaging/install/uninstall flow: bundled PHP runtime + dependencies, `%ProgramData%` install, startup Scheduled Task, and preservation of local replay state on uninstall.
- [x] New Local Agent installs require only HTTPS portal URL + Local Agent access token locally; device IP/port/branch/assignment remain server-managed.
- [ ] Build the distributable package with a verified Windows PHP runtime and browser/client-test the installer on a clean Windows PC.
- [ ] Preferred production document-root isolation review remains.

## Safety
- [x] Destructive device cleanup remains disabled.
- [x] Never bulk-clear ZKTeco attendance while unresolved/pending/dead-letter/ambiguous rows exist.
- [x] Do not reset/delete Local Agent `state.sqlite` to force a replay.
- [x] Do not expose bearer credentials in source control or screenshots.

## Target
```text
Company
  -> Branches
      -> Devices (.7:4370, .15:4370, ...)
           ^
           | same LAN/Wi-Fi
      One Local Agent PC
           |
           +-> heartbeat gets assigned devices
           +-> device A users/punches -> HTTPS -> device A / branch A
           +-> device B users/punches -> HTTPS -> device B / branch B
           +-> one device offline does not stop the others
```


## Finalization work — 2026-09-19
- [x] Local Agent boot supports standalone `local-agent/vendor/autoload.php` with parent-project fallback for development.
- [x] Local Agent no longer requires a locally hardcoded bootstrap device identifier/IP/port; heartbeat assignment is authoritative after provisioning.
- [x] Added Windows one-click install/uninstall scripts and self-contained package builder.
- [x] Windows installer registers `Aptech Attendance Sync Agent` at system startup and starts it immediately.
- [x] Installer/uninstaller never automatically deletes `state.sqlite` or replay history.
- [x] Shared-token migration and same-agent assignment confirmed on Hostinger; heartbeat returns `Devices=2`. Only one physical device was available, so observed `connected=1` is expected; `connected=2` remains a later hardware-availability check.
- [x] Fresh punch on `zk-office-1` verified end-to-end and appeared in web attendance after refresh. Repeat on `2222` when that physical device is available.
- [x] Historical dead-letter/future-timestamp reconciliation completed without state reset or direct DB edits; retained old dead-letter rows for audit history.
- [ ] Build/test self-contained Windows ZIP on a clean client PC. Builder now verifies required PHP extensions and `Rats\\Zkteco\\Lib\\ZKTeco`; reinstall preserves existing config/state. Current development Scheduled Task is already running successfully.
- [ ] Final production smoke test and latest isolated Hostinger MariaDB regression. Windows suite is green at 63 tests / 248 assertions / 0 failures. Only after explicit user approval may `web-hosting-sync` be merged to main/master.

- [x] 2026-09-19 physical live-sync verification: background Scheduled Task running as a single PHP process; server returned two assigned devices; one available device connected; fresh punch synced and appeared in production web UI.
- [x] Per-device server-managed timezone is now applied during Local Agent normalization; automated coverage added for provisioning-only config, two assigned devices, timezone handling, shared-token heartbeat and group authorization.
