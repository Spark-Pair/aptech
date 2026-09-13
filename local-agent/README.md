# Local Attendance Sync Agent

Windows-first PHP CLI agent on the same LAN as ZKTeco. It sends attendance to hosted Laravel over outbound HTTPS. Agent SQLite is only a durable local queue/checkpoint; production Laravel uses MySQL.

## Setup
1. Hosted app: `php artisan attendance:agent-provision "Office Agent" zk-office-1`.
2. Copy `config.example.json` to `config.json`, paste the one-time token and configure URL/device. This file is gitignored.
3. Office PC: run `php local-agent/check-requirements.php` (PHP 8.1+, sockets, curl, pdo_sqlite, sqlite3, Composer autoload).
4. Manually test `php local-agent/agent.php` on the ZKTeco LAN.
5. After manual success, run `local-agent/install-task.ps1` in PowerShell with permission to create scheduled tasks. It rechecks requirements, runs every minute, prevents overlap and caps one run at five minutes.
6. `local-agent/uninstall-task.ps1` removes only the task and preserves local config/state.

## Behavior
Local port 4370 read; no public forwarding. Outbound HTTPS API only. Each normal cycle syncs safe device users before attendance, so missing Laravel employees can be created before punches import. Device user sync sends only `userid` and `name`; it discards ZKTeco password, card/security fields, biometric data, role and device-internal uid. Device rows are validated before batching so malformed, unsupported or clearly future-dated machine rows cannot poison valid rows. ZKTeco timestamps are interpreted as device local wall-clock timestamps using `device_timezone` from `config.json` (`Asia/Karachi` for Pakistan). Durable unsent queue, dead-letter storage for permanent payload failures, capped retry for transient failures, stable UUID transport idempotency, acknowledged timestamp checkpoint, heartbeat and rotating diagnostics.

## Security
Never expose port 4370 publicly. Never commit `config.json`, `state.sqlite` or logs. Rotate token by provisioning same device identifier again and updating local config.

## Staged migration
Legacy hosted ZKTeco stays until physical-device + API + MySQL tests pass. Timestamp/device-log identity is provisional until same-timestamp/delayed rows are tested. See implementation notes/test plan.
