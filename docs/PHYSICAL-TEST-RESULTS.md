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

## Physical Local Agent cycle on 2026-09-13

One controlled Local Agent cycle was run against the physical ZKTeco device.

Passed:
- Invalid future rows uid `50` (`2026-09-20 13:03:04`) and uid `51` (`2026-09-20 13:25:49`) were detected by the pre-batch filter and skipped with `reason=future_timestamp`.

Failed / bug found:
- Valid uid `52` (`2026-09-13 14:18:42`) was incorrectly classified as `future_timestamp`.
- The agent log timestamp was UTC (`2026-09-13T09:46:15+00:00`), while the ZKTeco row was Pakistan local wall-clock time. The row was about 27 minutes old in `Asia/Karachi`, not future.
- Production attendance sync is still not physically passed.

Automated verification after timezone fix:
- `vendor\bin\phpunit --configuration phpunit.xml --filter LocalAgentTest`: 11 tests, 23 assertions, passed.
- `vendor\bin\phpunit --configuration phpunit.xml --filter AttendanceAgentApiTest`: 8 tests, 19 assertions, passed.
- `vendor\bin\phpunit --configuration phpunit.xml`: 35 tests, 147 assertions, passed.

## Attendance transport passed before production cleanup

Physically verified:
- Stale future rows uid `50` and uid `51` were skipped.
- Valid uid `52` timestamp `2026-09-13 14:18:42` synced through Windows Local Agent -> HTTPS -> Hostinger Laravel API -> `AttendanceImporter` -> MariaDB.
- Production attendance count increased from 47 to 48.
- The accepted sync batch reported `accepted_count=1`.
- Production MariaDB insert was verified.

After that verification, production business/test data was intentionally cleaned. Current production business counts were reported as: users `1`, attendance sync agents `1`, attendance sync batches `0`, attendances `0`, employees `0`, shifts `0`. The existing Office Agent remains the agent credential to use for the next test.

## Physical device user read on 2026-09-13

Physically verified:
- A real ZKTeco `getUser()` call succeeded.
- The current physical device returned one user with `userid=1` and `name=Hasan`.

Security note:
- The raw device API also exposes security-sensitive fields. The Local Agent deliberately discards those fields and only sends `userid` and `name` for user sync.

New feature implemented, not yet physically verified:
- Automatic non-destructive Device User -> Employee synchronization.
- Expected first production verification after pulling this change: device `userid=1`, `name=Hasan` creates one Laravel employee with `empid=1`, `name=Hasan`.
- Automated verification after user-sync implementation: `vendor\bin\phpunit --configuration phpunit.xml` passed with 45 tests and 196 assertions.

Not yet physically passed:
- Automatic user creation on production.
- Exact same-timestamp punches.
- Delayed/backfilled older punches appearing after a newer checkpoint.
- Physical outage, restart and replay scenarios not explicitly listed as passed above.
