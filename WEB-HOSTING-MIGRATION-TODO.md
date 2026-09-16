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
- [ ] Deploy explicit Local Agent assignment UI/controller commits after `5d6da1b` to Hostinger.
- [ ] In Branches & Devices, explicitly assign `.7` / `2222` to the same Local Agent as `.15` / `zk-office-1`.
- [ ] Run Windows `php agent.php --once` and verify heartbeat returns both `.7:4370` and `.15:4370`, both user rosters sync, and both devices get independent last-contact timestamps.
- [ ] Run `php agent.php --run`, make fresh punches on both physical devices, manually refresh the web app, and verify correct branch/device attendance.
- [ ] Put the updated multi-device Local Agent on the Zorin demo machine when switching back to Zorin.
- [ ] Run automated regression after the multi-device changes on SQLite and isolated Hostinger MariaDB.

## Current known issues / pending verification
- [x] Empty device-user snapshots are valid server input (`af9edee`); production now includes this change.
- [x] Production code/migration through `5d6da1b` deployed and optimized on 2026-09-16.
- [ ] Explicit Local Agent assignment UI/controller commits after `5d6da1b` still need production deployment/browser test.
- [ ] Fresh attendance punches previously received a server HTTP 422 after the timezone fix; inspect the exact dead-letter payload before changing future-time tolerance.
- [ ] Old missing attendance remains unresolved; compare local acknowledged/dead-letter state with production before any replay/reset.
- [x] Known truly future-dated uid50/51 rows are intentionally skipped locally.
- [ ] Latest shifts/leaves/attendance/operations modal UI refactor is deployed with the `5d6da1b` pull but still needs browser verification.
- [ ] Add/complete automated CRUD + authorization + multi-device heartbeat tests.
- [ ] Verify separate Local Agents for separate physical networks before general multi-site rollout.
- [ ] Build/refine non-technical one-click installer + one-time provisioning flow.
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
