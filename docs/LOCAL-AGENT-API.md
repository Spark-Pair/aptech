# Local Attendance Agent API Contract

Base path: `/api/v1/attendance-agent`

Authentication: `Authorization: Bearer <provisioned-token>`. The server stores only a SHA-256 token hash. The credential is bound to one `device_identifier` and may be revoked by setting the agent inactive or rotated with the provisioning command.

## POST /heartbeat

Body: `{}`

Success: HTTP 200 with `message` and `server_time`. Updates `last_heartbeat_at`.

## POST /sync

Body example:

```json
{
  "batch_id": "stable-uuid-generated-by-agent",
  "device_identifier": "zk-office-1",
  "logs": [
    {"id": 12, "timestamp": "2026-09-12 09:01:10", "type": 0},
    {"id": 12, "timestamp": "2026-09-12 17:05:22", "type": 1}
  ]
}
```

Rules:
- 1-1000 logs per batch.
- `id` is the device employee/user identifier expected by the existing AttendanceImporter.
- timestamp format is `Y-m-d H:i:s`.
- type is currently one of `0,1,4,5`, matching the existing attendance import rules.
- `batch_id` must remain unchanged when retrying the same batch.
- same agent + same batch ID returns the previously stored acknowledgement and does not import twice.
- a credential cannot submit another device identifier.

Success acknowledgement includes `duplicate`, `batch_id`, `accepted`, `skipped`, and `updated_days`.

Expected failures: 401 invalid/revoked credential, 403 wrong device binding, 422 validation failure, 429 throttled, 5xx temporary server failure. The Local Agent retains/retries batches for transport/server failures; credentials/configuration errors should be diagnosed rather than bypassed.
