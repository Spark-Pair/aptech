# Local Agent End-to-End Test Plan

Run this before disabling the legacy hosted ZKTeco path.

## Preconditions
- Test/staging Laravel uses MySQL and HTTPS.
- Agent provisioned with a dedicated test device identifier/token.
- Office test PC is on same LAN as ZKTeco.
- `config.json` is not committed.

## Cases
1. Manual heartbeat succeeds; web server records heartbeat.
2. Device read succeeds and known employee punch reaches MySQL once.
3. Re-run with no new punches: no duplicate attendance effect.
4. Disconnect internet, create/read new punch, run agent: batch remains queued. Restore internet and verify queued batch is acknowledged exactly once.
5. Stop/disable API temporarily: same queue/recovery behavior.
6. Disconnect ZKTeco but keep internet: heartbeat remains possible, device read fails safely and existing hosted UI remains usable.
7. Restart Windows with a queued batch: scheduled task later replays it successfully.
8. Replay same server `batch_id`: API reports duplicate and does not create a second batch/import.
9. Rotate token: old token rejected; updated local token succeeds.
10. Set agent inactive: API returns unauthorized and no sync occurs.
11. Verify local logs contain useful errors but no bearer token.
12. Verify UI status transitions online/offline based on heartbeat without full-page refresh.

Record test date, environment, device model/firmware if available, result, and any observed ZKTeco log row shape before production cutover.
