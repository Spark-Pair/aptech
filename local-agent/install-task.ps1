param([string]$PhpPath = "php.exe")
$ErrorActionPreference = "Stop"
$agent = Join-Path $PSScriptRoot "agent.php"
if (!(Test-Path (Join-Path $PSScriptRoot "config.json"))) { throw "Create local-agent/config.json before installing the task." }
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument ('"' + $agent + '"') -WorkingDirectory $PSScriptRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName "Aptech Attendance Sync Agent" -Action $action -Trigger $trigger -Settings $settings -Description "Syncs local ZKTeco attendance to the hosted Laravel API." -Force
Write-Host "Attendance Sync Agent scheduled successfully."
