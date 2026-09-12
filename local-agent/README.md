# Local Attendance Sync Agent

Windows-first PHP CLI agent that runs on the same LAN as the ZKTeco device and sends attendance to the hosted Laravel API over outbound HTTPS.

The agent's SQLite file is only a local durable queue/checkpoint. The production Laravel application uses MySQL.

## Setup

1. On the hosted Laravel app, run `php artisan attendance:agent-provision "Office Agent" zk-office-1`.
2. Copy `config.example.json` to `config.json` and paste the one-time token plus hosted URL/device settings. `config.json` is gitignored.
3. On the office PC, ensure PHP CLI has `sockets`, `curl`, `pdo_sqlite` and `sqlite3` available and Composer dependencies are installed.
4. Test manually with `php local-agent/agent.php` while connected to the same LAN as the ZKTeco device.
5. After the manual test succeeds, run PowerShell as an account allowed to create scheduled tasks and execute `local-agent/install-task.ps1`. It schedules a one-cycle sync every minute and prevents overlapping instances.
6. To remove only the scheduled task, run `local-agent/uninstall-task.ps1`. Local config/state is intentionally retained.

## Behavior

- Reads ZKTeco locally through port 4370.
- Sends only outbound HTTPS requests to `/api/v1/attendance-agent/*`.
- Queues unsent batches in local SQLite and retries with capped exponential backoff.
- Uses stable UUID batch IDs so server replay is idempotent.
- Saves the last server-acknowledged attendance timestamp as its checkpoint.
- Sends a heartbeat and writes rotating diagnostics to `local-agent/logs/`.

## Security

Never expose ZKTeco port 4370 to the public internet. Never commit `config.json`, `state.sqlite`, or logs. The hosted token can be rotated by running the provisioning command again for the same `device_identifier` and updating the local config.

## Current staged-migration note

The old hosted ZKTeco path remains available until this agent is verified with the physical device, API and MySQL. It must not be removed before end-to-end verification.
