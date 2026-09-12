# Attendance Agent Status UI Integration

Keep current UI/UX; no separate visual system.

Prepared `AttendanceAgentStatusController` returns active, computed online, last heartbeat, last successful sync and safe last error. Online currently means active + heartbeat within 3 minutes, accommodating the one-minute agent schedule.

Requirements:
- Status listing only behind existing authenticated web access; never expose admin listing through agent bearer API.
- Use current attendance/operations cards, badges and typography.
- Poll asynchronously around every 30 seconds only while relevant view is visible/mounted.
- Update only status region; never full-page reload.
- Show Online/Offline, last heartbeat, last successful sync, concise safe error.
- Never expose bearer token/hash, stack trace or private config.
- Keep legacy Sync Now behavior until Local Agent cutover.
- Review latest Blade/routes before wiring to avoid disrupting current UI.

Implementation is deliberately paused at this boundary until the latest operations Blade/routes are fetched from the updated working branch; this avoids overwriting UI changes with a stale copy.
