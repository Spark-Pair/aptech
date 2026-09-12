param([string]$PhpPath = "php.exe")
$ErrorActionPreference = "Stop"
$agent = Join-Path $PSScriptRoot "agent.php"
$config = Join-Path $PSScriptRoot "config.json"
if (!(Test-Path $config)) { throw "Create local-agent/config.json before installing the task." }
& $PhpPath (Join-Path $PSScriptRoot "check-requirements.php")
if ($LASTEXITCODE -ne 0) { throw "Local Agent requirements check failed." }
$action = New-ScheduledTaskAction -Execute $PhpPath -Argument ('"' + $agent + '"') -WorkingDirectory $PSScriptRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
Register-ScheduledTask -TaskName "Aptech Attendance Sync Agent" -Action $action -Trigger $trigger -Settings $settings -Description "Syncs local ZKTeco attendance to the hosted Laravel API." -Force
Write-Host "Attendance Sync Agent scheduled successfully."
