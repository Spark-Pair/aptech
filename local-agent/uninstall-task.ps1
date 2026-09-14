$ErrorActionPreference = "Stop"
$taskName = "Aptech Attendance Sync Agent"
$task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($null -eq $task) {
    Write-Host "Attendance Sync Agent task is not installed."
    exit 0
}

# Stop the long-running --run worker before unregistering the task. This does
# not touch config.json, state.sqlite or logs.
if ($task.State -eq "Running") {
    Stop-ScheduledTask -TaskName $taskName
    Start-Sleep -Milliseconds 500
}

Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
Write-Host "Attendance Sync Agent scheduled task stopped and removed. Local config/state/log files were left untouched."
