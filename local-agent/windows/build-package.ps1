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
