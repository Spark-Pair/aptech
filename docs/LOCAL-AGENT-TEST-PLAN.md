# Local Agent End-to-End Test Plan

Run before disabling legacy hosted ZKTeco access.

## Preconditions
- Staging Laravel uses MySQL + HTTPS.
- Dedicated test agent/device token provisioned.
- Office PC is on same LAN as ZKTeco and `check-requirements.php` passes.
- `config.json` remains uncommitted.

## Cases
1. Heartbeat succeeds and server records it.
2. Device read succeeds; capture/anonymize the raw row keys/shape for compatibility verification.
3. Known employee IN/OUT reaches MySQL once and produces expected attendance day.
4. Re-run with no new punches: no duplicate effect.
5. Create two legitimate punches as close together as practical; verify timestamp checkpoint does not skip same-time records. If device can backfill older rows, test that too. If unsafe, replace timestamp checkpoint with punch fingerprints/cursor before production.
6. Disconnect internet: new batch stays queued; restore internet: acknowledged exactly once.
7. Temporarily stop API: same queue/recovery behavior.
8. Disconnect ZKTeco while internet works: heartbeat can succeed, read fails safely, hosted UI stays usable.
9. Restart Windows with queued batch: scheduled task replays successfully.
10. Replay same `batch_id`: API returns duplicate and does not re-import.
11. Rotate token: old token rejected; new token succeeds.
12. Inactivate agent: API rejects it.
13. Verify logs are useful and contain no bearer token.
14. Verify UI status online/offline transitions asynchronously without full-page refresh.

Record date, staging URL/environment, MySQL version, PHP version, ZKTeco model/firmware if available, observed row shape, each result and any corrective commit before cutover.
