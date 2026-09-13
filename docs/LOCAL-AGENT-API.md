# Local Attendance Agent API Contract

Base: `/api/v1/attendance-agent`. Auth: `Authorization: Bearer <provisioned-token>`. Server stores only SHA-256 token hash and binds it to one `device_identifier`.

## POST /heartbeat
Body `{}`. HTTP 200 updates heartbeat and returns server time.

## POST /users
```json
{"device_identifier":"zk-office-1","users":[{"userid":"1","name":"Hasan"}]}
```
Rules: same bearer auth and device binding as attendance sync; up to 1000 users per request; `userid` is the ZKTeco business user ID and maps to `employees.empid`; `name` is optional and defaults to `Device User {userid}` when blank. The sync is create/link-only: it creates missing employees and does not overwrite manually maintained employee fields or delete/deactivate employees absent from the device.

Success returns `created`, `existing`, `skipped`. The Local Agent deliberately does not transmit ZKTeco `password`, biometric data, `cardno`, `role` or device-internal `uid`.

## POST /sync
```json
{"batch_id":"stable-uuid","device_identifier":"zk-office-1","logs":[{"id":12,"timestamp":"2026-09-12 09:01:10","type":0}]}
```
Rules: 1-1000 logs; timestamp `Y-m-d H:i:s`; type `0,1,4,5`; retry the same payload with the same `batch_id`. Same agent + batch ID returns stored acknowledgement instead of importing again. Wrong device binding is forbidden.

Success returns `duplicate`, `batch_id`, `accepted`, `skipped`, `updated_days`. Typical failures: 401 credential/revocation, 403 device binding, 422 validation, 429 throttling, 5xx temporary server error.

Transport idempotency does not by itself prove physical-device cursor safety; see `docs/IMPLEMENTATION-NOTES.md` and physical test plan before cutover.
