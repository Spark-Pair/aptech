# Implementation Notes

## 2026-09-12 — Device checkpoint identity
The MVP checkpoints by greatest server-acknowledged device timestamp. Physical testing must confirm whether the target ZKTeco emits multiple legitimate records with the same timestamp or later exposes delayed/backfilled records older than the checkpoint. If so, replace timestamp-only filtering with a durable per-punch fingerprint/cursor before production cutover.

Server `(agent, batch_id)` idempotency protects transport retries only; it is separate from physical device-log identity.

## 2026-09-12 — Staged cutover
Do not remove current hosted/direct ZKTeco code merely because Local Agent code exists. Cutover requires staging MySQL, automated tests, real-device row-shape verification, outage/retry/restart tests, status UI regression and explicit approval.
