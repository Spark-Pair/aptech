# Local Attendance Sync Agent

Windows-first PHP CLI agent on the same LAN as ZKTeco. It pushes attendance to Laravel over outbound HTTPS. Uses local SQLite only for durable agent state; production Laravel remains MySQL.

Copy `config.example.json` to `config.json`, provision a token with `php artisan attendance:agent-provision "Office Agent" zk-office-1`, then run `php local-agent/agent.php`. For the MVP, schedule this command every minute with Windows Task Scheduler. Never expose ZKTeco port 4370 publicly.
