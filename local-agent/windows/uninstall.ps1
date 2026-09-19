$ErrorActionPreference = "Stop"
$taskName = "Aptech Attendance Sync Agent"
$installDir = Join-Path $env:ProgramData "AptechAttendanceAgent"

schtasks.exe /End /TN $taskName 2>$null | Out-Null
schtasks.exe /Delete /TN $taskName /F 2>$null | Out-Null

Write-Host "Scheduled Local Agent stopped and removed."
Write-Host "Local state was NOT deleted automatically: $installDir"
Write-Host "Keep that folder if attendance replay/recovery may still be required."
