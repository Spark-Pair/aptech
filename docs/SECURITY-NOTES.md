# Attendance Sync Security Notes

- Independent random agent credential per device installation; server stores SHA-256 token hash only.
- Agent API requires bearer authentication, active credential, device binding, validation and throttling.
- HTTPS is required for production agent API traffic.
- ZKTeco port 4370 stays LAN-only; no public NAT/port forwarding.
- `config.json`, local state DB and logs are gitignored.
- Logger must not include Authorization headers/tokens and defensively redacts bearer-like text.
- Admin/status listing stays behind normal authenticated web access and never exposes token hashes.
- Revocation: mark agent inactive. Rotation: provision same device identifier again, then update local token.
- Server batch IDs protect transport retries; physical device-log identity/checkpoint behavior is separately verified.
- Before production verify rate limits, revoked token, wrong-device binding, validation, duplicate replay, HTTPS, device checkpoint behavior and local log redaction in the real environment.
