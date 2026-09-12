# Web Hosting Migration Documentation Index

Start every work session with `WEB-HOSTING-MIGRATION-TODO.md`.

- `WEB-HOSTING-AUDIT.md` — initial application/database/ZKTeco/AJAX audit.
- `docs/LOCAL-AGENT-API.md` — Local Agent server API contract.
- `local-agent/README.md` — office PC agent setup/operation.
- `docs/LOCAL-AGENT-TEST-PLAN.md` — physical device/outage/retry/restart/checkpoint verification.
- `docs/PHYSICAL-TEST-RESULTS.md` — record real staging/device results; never claim unexecuted tests passed.
- `docs/IMPLEMENTATION-NOTES.md` — unresolved technical edge cases/staged-cutover rule.
- `docs/MYSQL-MIGRATION-CHECKLIST.md` — MySQL verification and existing-data policy.
- `docs/HOSTINGER-DEPLOYMENT.md` — shared-hosting deployment/update/rollback target.
- `docs/STATUS-UI-INTEGRATION.md` — unchanged-UI asynchronous status integration guardrails.
- `docs/SECURITY-NOTES.md` — sync credential/network/logging/revocation security controls.

Current migration is staged: legacy direct-device behavior remains until Local Agent + MySQL + physical-device path is verified and explicitly approved.
