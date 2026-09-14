$ErrorActionPreference = "Stop"

$taskName = "Aptech Attendance Sync Agent"
$task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if ($null -eq $task) {
    Write-Host "Attendance Sync Agent task is not installed."
    exit 1
}

$info = Get-ScheduledTaskInfo -TaskName $taskName
$triggerType = if ($task.Triggers.Count -gt 0) { $task.Triggers[0].CimClass.CimClassName } else { "None" }

[PSCustomObject]@{
    TaskName       = $task.TaskName
    State          = $task.State
    RunAs          = $task.Principal.UserId
    LogonType      = $task.Principal.LogonType
    RunLevel       = $task.Principal.RunLevel
    TriggerType    = $triggerType
    LastRunTime    = $info.LastRunTime
    LastTaskResult = $info.LastTaskResult
    NextRunTime    = $info.NextRunTime
    Execute        = $task.Actions[0].Execute
    Arguments      = $task.Actions[0].Arguments
    WorkingDir     = $task.Actions[0].WorkingDirectory
} | Format-List

$logPath = Join-Path $PSScriptRoot "logs\agent.log"
if (Test-Path $logPath) {
    Write-Host "Recent Local Agent log:"
    Get-Content $logPath -Tail 10
} else {
    Write-Host "No Local Agent log exists yet."
}
