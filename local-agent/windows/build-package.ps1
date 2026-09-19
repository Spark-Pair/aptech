param(
    [Parameter(Mandatory=$true)][string]$PhpRuntimeDir,
    [string]$OutputDir = ".\dist\local-agent"
)

$ErrorActionPreference = "Stop"
$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
$agentSource = Join-Path $repoRoot "local-agent"
$vendorSource = Join-Path $repoRoot "vendor"

if (-not (Test-Path (Join-Path $PhpRuntimeDir "php.exe"))) {
    throw "PhpRuntimeDir must contain php.exe."
}
if (-not (Test-Path (Join-Path $vendorSource "autoload.php"))) {
    throw "Run composer install on the build machine before packaging."
}

$requiredExtensions = @("curl", "openssl", "pdo_sqlite", "sqlite3", "sockets")
$php = Join-Path $PhpRuntimeDir "php.exe"
$modules = & $php -m
foreach ($extension in $requiredExtensions) {
    if ($modules -notcontains $extension) {
        throw "Bundled PHP runtime is missing required extension: $extension"
    }
}

$autoload = Join-Path $vendorSource "autoload.php"
$autoloadPhp = $autoload.Replace("\\", "/").Replace("'", "\\'")
$zkCheck = & $php -r "require '$autoloadPhp'; exit(class_exists('Rats\\Zkteco\\Lib\\ZKTeco') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) {
    throw "Composer dependencies do not contain Rats\\Zkteco\\Lib\\ZKTeco. Run composer install before packaging."
}

$out = Join-Path $repoRoot $OutputDir
if (Test-Path $out) { Remove-Item $out -Recurse -Force }
New-Item -ItemType Directory -Force -Path $out | Out-Null

Copy-Item (Join-Path $agentSource "agent.php") $out
Copy-Item (Join-Path $agentSource "src") $out -Recurse
Copy-Item (Join-Path $agentSource "config.example.json") $out
Copy-Item (Join-Path $agentSource "windows") $out -Recurse
Copy-Item $vendorSource (Join-Path $out "vendor") -Recurse
Copy-Item $PhpRuntimeDir (Join-Path $out "runtime") -Recurse

$zip = "$out.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path (Join-Path $out "*") -DestinationPath $zip -CompressionLevel Optimal

Write-Host "Self-contained Local Agent package created:"
Write-Host $zip

$isccCandidates = @(
    "$env:ProgramFiles(x86)\\Inno Setup 6\\ISCC.exe",
    "$env:ProgramFiles\\Inno Setup 6\\ISCC.exe"
) | Where-Object { $_ -and (Test-Path $_) }

if ($isccCandidates.Count -gt 0) {
    $iscc = $isccCandidates[0]
    $iss = Join-Path $PSScriptRoot "installer.iss"
    & $iscc $iss
    if ($LASTEXITCODE -ne 0) { throw "Inno Setup failed to build the Windows installer." }
    Write-Host "Windows EXE installer created:"
    Write-Host (Join-Path $repoRoot "dist\\AptechAttendanceAgentSetup.exe")
} else {
    Write-Warning "Inno Setup 6 was not found. ZIP was created, but EXE was not compiled. Install Inno Setup 6 and run this builder again."
}
