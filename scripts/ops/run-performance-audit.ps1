param(
    [string] $OutputFile,
    [switch] $JsonOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $repoRoot

$outputDir = Join-Path $repoRoot "storage/app/performance-audits"
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

if ([string]::IsNullOrWhiteSpace($OutputFile)) {
    $OutputFile = Join-Path $outputDir ("performance-audit-{0}.json" -f (Get-Date -Format "yyyyMMdd-HHmmss"))
}

if (-not $JsonOnly) {
    & php artisan ops:performance:audit

    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

$jsonOutput = (& php artisan ops:performance:audit --json 2>&1 | Out-String).Trim()

if ($LASTEXITCODE -ne 0) {
    Write-Error "php artisan ops:performance:audit --json failed."
    exit $LASTEXITCODE
}

Set-Content -Path $OutputFile -Value $jsonOutput

Write-Output "Performance audit JSON written to $OutputFile"

if ($JsonOnly) {
    Write-Output $jsonOutput
}
