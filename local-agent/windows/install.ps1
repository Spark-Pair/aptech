param(
    [string]$ApiBaseUrl,
    [string]$AccessToken
)

$ErrorActionPreference = "Stop"

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
if (-not $hasExistingConfig) {
    $config | ConvertTo-Json | Set-Content -Path $existingConfig -Encoding UTF8
} else {
    Write-Host "Existing Local Agent config preserved."
}

$runner = Join-Path $installDir "run-agent.cmd"
@"
@echo off
cd /d "$installDir"
"$installDir\runtime\php.exe" "$installDir\agent.php" --run
"@ | Set-Content -Path $runner -Encoding ASCII

$taskName = "Aptech Attendance Sync Agent"
schtasks.exe /Delete /TN $taskName /F 2>$null | Out-Null
schtasks.exe /Create /TN $taskName /SC ONSTART /RU SYSTEM /RL HIGHEST /TR ('"' + $runner + '"') /F | Out-Null
schtasks.exe /Run /TN $taskName | Out-Null

Write-Host ""
Write-Host "Local Agent installed and started successfully."
Write-Host "Device IPs, ports, branches and device assignments are managed from the web portal."
Write-Host "Install directory: $installDir"
