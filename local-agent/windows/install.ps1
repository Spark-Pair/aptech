param(
    [string]$ApiBaseUrl,
    [string]$AccessToken
)

$ErrorActionPreference = "Stop"

$taskName = "Aptech Attendance Sync Agent"

function Require-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Run install.cmd as Administrator."
    }
}

Require-Admin

$source = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $source "runtime\php.exe"
$vendor = Join-Path $source "vendor\autoload.php"
if (-not (Test-Path $runtime)) { throw "This package is missing its bundled PHP runtime." }
if (-not (Test-Path $vendor)) { throw "This package is missing its bundled Composer dependencies." }

$apiBase = if ([string]::IsNullOrWhiteSpace($ApiBaseUrl)) { (Read-Host "Hosted HR Portal URL (example: https://company.example.com)").TrimEnd("/") } else { $ApiBaseUrl.TrimEnd("/") }
if ($apiBase -notmatch '^https://') { throw "The hosted portal URL must use HTTPS." }
$token = if ([string]::IsNullOrWhiteSpace($AccessToken)) { (Read-Host "Local Agent access token").Trim() } else { $AccessToken.Trim() }
if ([string]::IsNullOrWhiteSpace($token)) { throw "Local Agent access token is required." }

$installDir = Join-Path $env:ProgramData "AptechAttendanceAgent"
New-Item -ItemType Directory -Force -Path $installDir | Out-Null

$existingConfig = Join-Path $installDir "config.json"
$hasExistingConfig = Test-Path $existingConfig
Get-ChildItem $source -Force | Where-Object { $_.Name -notin @("windows", "config.json", "state.sqlite", "logs") } | ForEach-Object {
    Copy-Item $_.FullName -Destination $installDir -Recurse -Force
}

$config = @{
    api_base_url = $apiBase
    api_token = $token
    device_timezone = "Asia/Karachi"
    poll_interval_seconds = 5
    user_sync_interval_seconds = 60
    batch_size = 500
    future_skew_seconds = 300
    device_cleanup_enabled = $false
    local_ack_history_days = 14
    local_ack_history_keep = 5000
    http_timeout = 20
    retry_base_seconds = 30
    retry_max_seconds = 1800
}
# Always write the credentials supplied to this installer. Device/branch settings
# remain server-managed; only local runtime settings and the access credential live here.
$configJson = $config | ConvertTo-Json
[System.IO.File]::WriteAllText($existingConfig, $configJson, (New-Object System.Text.UTF8Encoding($false)))
if ($hasExistingConfig) {
    Write-Host "Existing Local Agent config refreshed with the installer credentials."
}

$runner = Join-Path $installDir "run-agent.cmd"
@"
@echo off
cd /d "$installDir"
"$installDir\runtime\php.exe" "$installDir\agent.php" --run
"@ | Set-Content -Path $runner -Encoding ASCII

# Register the background agent through the Windows ScheduledTasks API so failures
# are terminating errors instead of being silently ignored by schtasks.exe.
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction Stop
}

$action = New-ScheduledTaskAction -Execute $runtime -Argument ('"' + (Join-Path $installDir "agent.php") + '" --run') -WorkingDirectory $installDir
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force -ErrorAction Stop | Out-Null

$registeredTask = Get-ScheduledTask -TaskName $taskName -ErrorAction Stop
if (-not $registeredTask) {
    throw "Failed to register the Local Agent Scheduled Task."
}

Start-ScheduledTask -TaskName $taskName -ErrorAction Stop
Start-Sleep -Seconds 2

$startedTask = Get-ScheduledTask -TaskName $taskName -ErrorAction Stop
if ($startedTask.State -notin @("Running", "Ready")) {
    throw "Local Agent Scheduled Task was registered but did not start correctly. State: $($startedTask.State)"
}

Write-Host ""
Write-Host "Local Agent installed and started successfully."
Write-Host "Device IPs, ports, branches and device assignments are managed from the web portal."
Write-Host "Install directory: $installDir"
