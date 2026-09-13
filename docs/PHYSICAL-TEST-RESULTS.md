# Physical / Staging Verification Results

Status: **PARTIALLY EXECUTED**. Environment-dependent roadmap items stay pending unless actual physical results are listed below.

Record when testing begins: date/environment; Hostinger/staging PHP + MySQL versions; ZKTeco model/firmware; anonymized device attendance row shape; automated test result; MySQL migrate/seed result; heartbeat/sync result; offline/retry/restart/transport-idempotency result; device checkpoint/same-timestamp/backfill result; UI regression result; corrective commits; final pass/fail decision.

## Physical results reported 2026-09-13

Physically passed:
- Windows PHP prerequisites passed on PHP 8.2.28.
- PHP extensions/capabilities verified: sockets, curl, pdo_sqlite, sqlite3 and Composer autoload.
- Windows PC `192.168.100.13` can reach ZKTeco `192.168.100.19:4370`; `Test-NetConnection` returned `TcpTestSucceeded : True`.
- Local ZKTeco PHP library connected to the real attendance machine.
- Local Agent read actual attendance rows from the device.
- Windows Local Agent reached the production HTTPS heartbeat API.
- Production Operations UI showed `Office Agent` as Online for device `zk-office-1`.
- ZKTeco machine clock problem was identified: old rows were approximately seven days in the future.
- ZKTeco machine date/time was corrected.
- A new real punch was observed with correct local timestamp `2026-09-13 14:18:42`.

Issue physically observed:
- Stale future-dated rows remain stored in ZKTeco memory, including `2026-09-20 13:03:04` and `2026-09-20 13:25:49`.
- The Local Agent included stale future rows and the valid `2026-09-13 14:18:42` row in the same sync attempt.
- Laravel correctly returned HTTP 422 for the future timestamp.
- The invalid future row poisoned the whole outgoing batch, preventing the valid row from syncing.

Automated verification after code fix:
- `vendor\bin\phpunit --configuration phpunit.xml --filter LocalAgentTest`: 10 tests, 18 assertions, passed.
- `vendor\bin\phpunit --configuration phpunit.xml --filter AttendanceAgentApiTest`: 7 tests, 15 assertions, passed.
- `vendor\bin\phpunit --configuration phpunit.xml`: 33 tests, 140 assertions, passed.

Not yet physically passed:
- Corrected valid attendance arriving in production MariaDB after the Local Agent poison-batch fix.
- Exact same-timestamp punches.
- Delayed/backfilled older punches appearing after a newer checkpoint.
- Physical outage, restart and replay scenarios not explicitly listed as passed above.
