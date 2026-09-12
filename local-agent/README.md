# Local Attendance Sync Agent

Windows-first PHP CLI agent on the same LAN as ZKTeco. It sends attendance to hosted Laravel over outbound HTTPS. Agent SQLite is only a durable local queue/checkpoint; production Laravel uses MySQL.

## Setup
1. Hosted app: `php artisan attendance:agent-provision "Office Agent" zk-office-1`.
2. Copy `config.example.json` to `config.json`, paste the one-time token and configure URL/device. This file is gitignored.
3. Office PC: run `php local-agent/check-requirements.php`. It verifies PHP 8.1+, sockets, curl, pdo_sqlite and sqlite3.
4. Ensure Composer dependencies are installed, then manually test `php local-agent/agent.php` on the ZKTeco LAN.
5. After manual success, run `local-agent/install-task.ps1` in PowerShell with permission to create scheduled tasks. It runs every minute and prevents overlapping instances.
6. `local-agent/uninstall-task.ps1` removes only the task and preserves local config/state.

## Behavior
- Local ZKTeco port 4370 read; no public port forwarding.
- Outbound HTTPS `/api/v1/attendance-agent/*` only.
- Durable unsent queue, capped exponential retry, stable UUID idempotency, acknowledged timestamp checkpoint.
- Heartbeat plus rotating diagnostics under `local-agent/logs/`.

## Security
Never expose port 4370 publicly. Never commit `config.json`, `state.sqlite` or logs. Rotate a token by provisioning the same device identifier again and updating local config.

## Staged migration
The legacy hosted ZKTeco path stays until physical-device + API + MySQL end-to-end tests pass. The timestamp checkpoint is provisional until the physical device is checked for same-timestamp or delayed/backfilled rows; see `docs/IMPLEMENTATION-NOTES.md`.
