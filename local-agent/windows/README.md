# Windows Local Agent package

The client package is self-contained. A client PC does not need Git, Composer, Node.js, Laravel, MySQL, XAMPP, VS Code, or a separately installed PHP runtime.

## Package contents required

- `agent.php` and `src/`
- `vendor/`
- `runtime/php.exe` with cURL, OpenSSL, PDO SQLite, SQLite3 and sockets enabled
- `windows/install.cmd` and `windows/install.ps1`

Run `windows/install.cmd` as Administrator. The installer asks only for the HTTPS portal URL and one-time Local Agent provisioning token. Device IP, port, branch, timeout and Local Agent assignment remain server-managed.

The installer copies the package to `%ProgramData%\AptechAttendanceAgent`, creates the `Aptech Attendance Sync Agent` startup task, and starts it.

Uninstall deliberately does not delete `state.sqlite`, config, or logs because local attendance replay state must not be destroyed automatically.
