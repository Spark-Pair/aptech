# Attendance Agent Status UI Integration

Keep the current UI/UX. Do not add a separate visual system.

Prepared backend: `AttendanceAgentStatusController` returns each configured agent's active state, computed online state, last heartbeat, last successful sync and safe last error. Online currently means active with a heartbeat within the previous 3 minutes, which accommodates the one-minute scheduled agent cadence.

Integration requirements:
- Expose status only behind the application's existing authenticated web access; do not expose the admin/status listing through the agent bearer-token API.
- Place status in the existing attendance/operations area using current cards/badges/typography.
- Poll JSON asynchronously (recommended ~30 seconds while the relevant view is visible) and stop polling when the view is no longer mounted/visible.
- Update only the status region; never reload the whole page.
- Show Online/Offline, last heartbeat, last successful sync and a concise safe error if present.
- Never render bearer tokens, token hashes, stack traces or private configuration.
- Keep legacy `Sync Now` behavior until Local Agent cutover; then repoint/rename only after approval and regression testing.

The exact Blade placement/route should be chosen after reviewing the latest operations/attendance view on this branch to avoid disrupting current UI.
