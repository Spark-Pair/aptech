# Implementation Notes

## 2026-09-12 Local Agent checkpoint strategy

The MVP currently checkpoints by the greatest server-acknowledged device timestamp. This is simple and prevents repeated upload of old logs. During physical-device testing we must confirm whether the target ZKTeco can emit multiple legitimate records with the exact same timestamp or later expose delayed/backfilled records older than the checkpoint. If either behavior occurs, replace timestamp-only filtering with a durable per-punch fingerprint/cursor strategy before production cutover.

Server-side `(agent, batch_id)` idempotency already protects transport retries, but it is intentionally separate from device-log identity. Do not mark physical-device duplicate safety fully verified until this edge case is tested.
