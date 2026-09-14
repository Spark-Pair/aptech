param([string]$PhpPath = "php.exe")
$ErrorActionPreference = "Stop"

$taskName = "Aptech Attendance Sync Agent"
$agent = Join-Path $PSScriptRoot "agent.php"
$config = Join-Path $PSScriptRoot "config.json"
$requirements = Join-Path $PSScriptRoot "check-requirements.php"

# Startup/SYSTEM tasks require elevation. Fail clearly instead of silently
# installing a login-dependent task.
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this installer from an elevated PowerShell/CMD (Run as administrator)."
}

if (!(Test-Path $config)) {
    throw "Create local-agent/config.json before installing the task."
}

# Resolve PHP now so the SYSTEM task never depends on a user's PATH.
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

# Validate the current device/API configuration interactively before moving it
# into the non-interactive SYSTEM context.
& $resolvedPhpPath $agent --once
if ($LASTEXITCODE -ne 0) {
    throw "Local Agent one-cycle validation failed. Fix the error before installing the background task."
}

$action = New-ScheduledTaskAction `
    -Execute $resolvedPhpPath `
    -Argument ('"' + $agent + '" --run') `
    -WorkingDirectory $PSScriptRoot

# Boot-time trigger: no Windows user needs to sign in for attendance sync.
$trigger = New-ScheduledTaskTrigger -AtStartup

# Run as LocalSystem, non-interactively, with the highest available privileges.
# ServiceAccount logon means no password is stored by this installer.
$taskPrincipal = New-ScheduledTaskPrincipal `
    -UserId "SYSTEM" `
    -LogonType ServiceAccount `
    -RunLevel Highest

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
    -Principal $taskPrincipal `
    -Settings $settings `
    -Description "Runs the local ZKTeco attendance sync worker continuously at Windows startup and sends confirmed data to the hosted Laravel API over HTTPS." `
    -Force | Out-Null

# Start immediately as SYSTEM; future starts happen automatically at boot.
Start-ScheduledTask -TaskName $taskName
Start-Sleep -Seconds 2

$task = Get-ScheduledTask -TaskName $taskName
$info = Get-ScheduledTaskInfo -TaskName $taskName

Write-Host "Attendance Sync Agent installed and started."
Write-Host "PHP: $resolvedPhpPath"
Write-Host "Task state: $($task.State)"
Write-Host "Run as: $($task.Principal.UserId)"
Write-Host "Trigger: Windows startup"
Write-Host "Last result: $($info.LastTaskResult)"
Write-Host "The worker runs non-interactively in the background; no user login or terminal window is required."
Write-Host "Use local-agent/status-task.ps1 to inspect it and local-agent/uninstall-task.ps1 to remove it."
