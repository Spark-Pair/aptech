# Local Attendance Sync Agent

Windows-first PHP CLI agent on the same LAN as ZKTeco. It sends attendance to hosted Laravel over outbound HTTPS. Agent SQLite is only a durable local queue/checkpoint; production Laravel uses MySQL.

## Setup
1. Hosted app: `php artisan attendance:agent-provision "Office Agent" zk-office-1`.
2. Copy `config.example.json` to `config.json`, paste the one-time token and configure URL/device. This file is gitignored.
3. Office PC: run `php local-agent/check-requirements.php` (PHP 8.1+, sockets, curl, pdo_sqlite, sqlite3 and Composer autoload).
4. Manually test `php local-agent/agent.php --once` on the ZKTeco LAN.
5. From an elevated PowerShell/CMD, run `local-agent/install-task.ps1`. It rechecks requirements, validates one real cycle, registers `agent.php --run` as a Windows startup task running under LocalSystem, prevents duplicate instances, removes the execution time limit, configures restart-on-failure, and starts the worker immediately.
6. `local-agent/status-task.ps1` shows task state, SYSTEM identity, trigger, last result, executable/arguments and latest agent log lines.
7. `local-agent/uninstall-task.ps1` removes only the task and preserves local config/state/logs.

## Background operation
Production Windows mode is one long-running Task Scheduler process, not a new scheduled process every minute. Windows starts it at system startup under the built-in `SYSTEM` service account, so no interactive user login and no visible PowerShell/CMD window is required. The agent's own `--run` loop performs device polling using `poll_interval_seconds` (default 5 seconds). `MultipleInstances=IgnoreNew` prevents overlapping scheduled instances. If the worker exits unexpectedly, Task Scheduler retries it up to three times at one-minute intervals. The task has no execution time limit.

The installer must be run as Administrator because creating a boot-time SYSTEM task requires elevation. It resolves PHP to an absolute path before registration so the background task does not depend on a user's PATH. The Local Agent folder, PHP executable, config/state/log directories, LAN route to the ZKTeco and outbound HTTPS must remain accessible to LocalSystem. Verify the installed SYSTEM task physically on each client PC before considering deployment complete.

## Remote/client deployment direction
The current repository scripts are the engineering/developer deployment path. A non-technical client installer is a separate packaging step: it should bundle or install the required PHP runtime/extensions and agent files, accept a one-time provisioning code instead of exposing the long-lived API token, obtain device/API configuration over HTTPS, install the startup SYSTEM task, perform a health check, and support clean uninstall/upgrade while preserving local state. Do not hard-code production bearer tokens or client-specific secrets into a distributable installer.

Until that installer/provisioning flow is implemented and tested, remote installation requires either an administrator on the client PC to perform the documented setup or one-time authorized remote desktop access. The repository scripts must not be presented as a finished one-click customer installer.

## Behavior
Local port 4370 read; no public forwarding. Outbound HTTPS API only. Each normal cycle syncs safe device users before attendance, so missing Laravel employees can be created before punches import. Device user sync sends only `userid` and `name`; it discards ZKTeco password, card/security fields, biometric data, role and device-internal uid. Device rows are validated before batching so malformed, unsupported or clearly future-dated machine rows cannot poison valid rows. ZKTeco timestamps are interpreted as device local wall-clock timestamps using `device_timezone` from `config.json` (`Asia/Karachi` for Pakistan). Durable unsent queue, dead-letter storage for permanent payload failures, capped retry for transient failures, stable UUID transport idempotency, acknowledged record fingerprints, heartbeat and rotating diagnostics.

Commands:
- `php local-agent/agent.php --once` runs exactly one diagnostic cycle.
- `php local-agent/agent.php --run` runs continuously with `poll_interval_seconds` between cycles.
- `php local-agent/agent.php --cleanup-dry-run` reports cleanup diagnostics without deleting anything.
- `powershell -ExecutionPolicy Bypass -File local-agent/install-task.ps1` installs/starts the boot-time background worker using `php.exe` from PATH (run elevated).
- `powershell -ExecutionPolicy Bypass -File local-agent/install-task.ps1 -PhpPath "C:\php-8.2\php.exe"` installs/starts it with an explicit PHP executable (run elevated).
- `powershell -ExecutionPolicy Bypass -File local-agent/status-task.ps1` inspects the installed worker.
- `powershell -ExecutionPolicy Bypass -File local-agent/uninstall-task.ps1` removes the scheduled task without deleting local state.

The installed Rats/ZKTeco library exposes full attendance reads and bulk `clearAttendance()` only. It does not expose per-record attendance deletion. Real device cleanup is disabled by default. The physically reviewed dry-run had 7 device rows: 5 ACKed, 0 pending and 2 unresolved future-dated rows, so cleanup correctly remained ineligible. Do not enable bulk device cleanup while any row is unresolved/pending/dead-letter/ambiguous.

## Physical verification status
The real production path has been verified through Hostinger MariaDB: device users `userid=1`/`Hasan` and `userid=2`/`Hassan` were automatically created as Laravel employees `empid=1` and `empid=2`; attendance was stored for both employees. Continuous `--run` polling was physically observed with repeated full device reads, no duplicate re-posting (`new rows=0` on unchanged history), no pending batches, and typical 10-row cycle durations around one second with occasional slower cycles. The previous at-logon scheduled worker was also physically observed running successfully. The new boot-time LocalSystem task still requires a reboot/no-login physical verification on Windows before that specific deployment mode is marked passed. Browser realtime updates are intentionally out of scope; users refresh the web app when they want the latest saved data.

## Security
Never expose port 4370 publicly. Never commit `config.json`, `state.sqlite` or logs. Rotate token by provisioning same device identifier again and updating local config.

## Staged migration
Legacy hosted ZKTeco stays until the remaining physical edge cases and cutover review pass. Exact same-timestamp and delayed/backfilled punches are covered by automated fingerprint tests but still require physical-device verification. Real device bulk deletion also remains disabled pending a separately approved safe threshold policy.
