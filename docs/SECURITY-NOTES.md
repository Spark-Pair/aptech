# Attendance Sync Security Notes

- Agent credential is random and independent per device installation.
- Hosted database stores only SHA-256 token hash; plaintext is shown at provisioning/rotation time only.
- Agent API requires bearer authentication, active credential, device binding, validation and throttling.
- Agent refuses non-HTTPS API URL unless an explicit local-development override is introduced/configured.
- ZKTeco port 4370 remains LAN-only; no public NAT/port forwarding is part of the design.
- `config.json`, local state DB and logs are gitignored.
- Logs should never include Authorization headers/tokens; logger also redacts bearer-like text defensively.
- Admin/status listing must stay behind normal authenticated web access and must never expose token hashes.
- Revocation: mark agent inactive. Rotation: provision same device identifier again, then replace local token.
- Before production, test rate limits, revoked token behavior, wrong-device binding, validation, duplicate replay and HTTPS from the actual office network.
