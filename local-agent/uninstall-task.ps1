$ErrorActionPreference = "Stop"
$taskName = "Aptech Attendance Sync Agent"
$task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($null -eq $task) { Write-Host "Attendance Sync Agent task is not installed."; exit 0 }
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
Write-Host "Attendance Sync Agent scheduled task removed. Local config/state files were left untouched."
