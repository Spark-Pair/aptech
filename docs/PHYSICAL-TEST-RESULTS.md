# Physical / Staging Verification Results

Status: **PARTIALLY EXECUTED**. Environment-dependent roadmap items stay pending unless actual physical results are listed below.

## Physical results reported 2026-09-13

Physically passed:
- Windows PHP prerequisites passed on PHP 8.2.28.
- Windows PC can reach the real ZKTeco device on port 4370 and the PHP library connects successfully.
- Windows Local Agent reached the production HTTPS heartbeat/sync API.
- Production Operations UI showed the provisioned Office Agent online.
- A ZKTeco clock problem was identified and the machine date/time was corrected.
- Stale future-dated rows remained in device memory and are correctly rejected before batching.

## Attendance transport

Physically verified:
- Stale future rows uid `50` and uid `51` were skipped.
- Valid uid `52` timestamp `2026-09-13 14:18:42` synced through Windows Local Agent -> HTTPS -> Hostinger Laravel API -> `AttendanceImporter` -> MariaDB.
- Production attendance count increased from 47 to 48 in that controlled test and the accepted sync batch reported `accepted_count=1`.
- Production MariaDB insert was verified.

After that verification, production business/test data was intentionally cleaned while retaining the login user and Office Agent credential.

## Device user synchronization

Physically verified:
- Real ZKTeco `getUser()` calls succeeded.
- Local Agent sends only the safe `userid` and `name` fields; device password/card/role/uid/biometric data are not sent by the user-sync payload.
- Production later showed two automatically created employees from the physical device: `userid=1`, `name=Hasan` -> Laravel `empid=1`, and `userid=2`, `name=Hassan` -> Laravel `empid=2`.
- Both employees used the expected generated `device_1` / `device_2` usernames and non-destructive business defaults.

## Continuous Local Agent

Physically verified:
- `php local-agent/agent.php --run` operated continuously against the real device.
- The device returned full historical attendance on every poll, while the Local Agent fingerprint state prevented unchanged historical rows from being re-posted (`new rows=0` on unchanged cycles).
- Repeated cycles showed `pending batches=0`.
- With 10 device rows, typical observed cycle duration was roughly 0.86-0.97 seconds, with occasional slower cycles around 1.6-2.3 seconds. The configured sleep remains 5 seconds between cycles.
- Production MariaDB showed attendance for both physical device employees. Multiple punches aggregate into the existing employee/business-date attendance model rather than creating one database attendance row per raw punch.

## Cleanup dry-run

Physically verified dry-run output:
- device rows: `7`
- ACKed rows: `5`
- pending rows: `0`
- unresolved rows: `2`
- cleanup eligible rows: `0`
- capability: `bulk_clear_all_only`
- cleanup enabled: `false`

The two unresolved rows correspond to stale future-dated device attendance. The safety gate therefore correctly prevented cleanup eligibility.

Static library/device capability finding:
- Attendance reads use the full attendance log request.
- The installed Rats/ZKTeco library exposes bulk `clearAttendance()` / clear-all only.
- No per-record or delete-through-position attendance cleanup API was found.

Safety gate:
- Real device attendance deletion remains disabled.
- Never bulk-clear while any device row is pending, unresolved, dead-lettered or otherwise ambiguous.
- Exact rolling 24/48-hour retention on the physical device cannot be implemented with the currently available clear-all-only API; recent ACK metadata can be retained safely in Local Agent SQLite instead.

## Background Windows worker

Implemented after the successful continuous physical test:
- `local-agent/install-task.ps1` now installs the actual continuous `agent.php --run` worker instead of launching a one-cycle process every minute.
- The installer resolves PHP to an absolute path, runs the prerequisite checker, validates one real `--once` cycle before installation, registers an at-logon task, prevents overlapping instances, removes the execution time limit, configures restart-on-failure and starts the task immediately.
- `local-agent/status-task.ps1` reports task state/result/action and recent Local Agent logs.
- `local-agent/uninstall-task.ps1` stops a running worker before unregistering the task while preserving config/state/logs.
- This scheduled-task installation itself still requires physical Windows verification after pulling the implementation.

## Automated verification history

- Poison-row fix: Local Agent 10 tests / 18 assertions; API 7 / 15; full suite 33 / 140.
- Device timezone fix: Local Agent 11 / 23; API 8 / 19; full suite 35 / 147.
- Device user sync: full suite 45 tests / 196 assertions.
- Continuous-agent hardening: full suite 55 tests / 221 assertions.

The background-task PowerShell changes were made through the GitHub connector and have not yet been executed by the test runner in this session; physical Windows installation/status verification is the next gate.

## Still pending physical verification

- Exact same-timestamp punches on the physical device.
- Delayed/backfilled older punches on the physical device.
- Explicit internet outage, response-loss/idempotent replay and restart recovery scenarios not already demonstrated.
- Windows background scheduled-task installation/reboot-or-logon persistence after the latest task changes.
- Any real ZKTeco attendance deletion or bulk clear.
