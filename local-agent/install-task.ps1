param([string]$PhpPath = "php.exe")
$ErrorActionPreference = "Stop"

$taskName = "Aptech Attendance Sync Agent"
$agent = Join-Path $PSScriptRoot "agent.php"
$config = Join-Path $PSScriptRoot "config.json"
$requirements = Join-Path $PSScriptRoot "check-requirements.php"

if (!(Test-Path $config)) {
    throw "Create local-agent/config.json before installing the task."
}

# Resolve PHP now so Task Scheduler does not depend on a future PATH value.
$phpCommand = Get-Command $PhpPath -ErrorAction Stop
$resolvedPhpPath = $phpCommand.Source
if ([string]::IsNullOrWhiteSpace($resolvedPhpPath)) {
    $resolvedPhpPath = $phpCommand.Path
}
if ([string]::IsNullOrWhiteSpace($resolvedPhpPath)) {
    throw "Unable to resolve PHP executable: $PhpPath"
}

& $resolvedPhpPath $requirements
if ($LASTEXITCODE -ne 0) {
    throw "Local Agent requirements check failed."
}

# Validate one real cycle before registering an always-on worker. This exits
# without touching the scheduled task if device/API configuration is broken.
& $resolvedPhpPath $agent --once
if ($LASTEXITCODE -ne 0) {
    throw "Local Agent one-cycle validation failed. Fix the error before installing the background task."
}

$action = New-ScheduledTaskAction `
    -Execute $resolvedPhpPath `
    -Argument ('"' + $agent + '" --run') `
    -WorkingDirectory $PSScriptRoot

# Start automatically when the current Windows user signs in. --run owns the
# five-second polling loop, so Task Scheduler must not launch a new process
# every minute.
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME

$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Runs the local ZKTeco attendance sync worker continuously and sends confirmed data to the hosted Laravel API over HTTPS." `
    -Force | Out-Null

# Start it immediately; the logon trigger handles subsequent Windows sessions.
Start-ScheduledTask -TaskName $taskName
Start-Sleep -Seconds 2

$task = Get-ScheduledTask -TaskName $taskName
$info = Get-ScheduledTaskInfo -TaskName $taskName

Write-Host "Attendance Sync Agent installed and started."
Write-Host "PHP: $resolvedPhpPath"
Write-Host "Task state: $($task.State)"
Write-Host "Last result: $($info.LastTaskResult)"
Write-Host "The worker will start automatically at logon and run local-agent/agent.php --run in the background."
Write-Host "Use local-agent/status-task.ps1 to inspect it and local-agent/uninstall-task.ps1 to remove it."
