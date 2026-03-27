param(
    [string] $BaseUrl,
    [string] $ApiToken,
    [string] $SummaryFile,
    [int] $Vus = 10,
    [string] $Duration = "60s",
    [switch] $SkipValidation
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $repoRoot

function Get-DotEnvValue {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Key
    )

    $envFile = Join-Path $repoRoot ".env"

    if (-not (Test-Path $envFile)) {
        return $null
    }

    $line = Get-Content $envFile | Where-Object { $_ -match "^${Key}=" } | Select-Object -Last 1

    if (-not $line) {
        return $null
    }

    $value = ($line -split '=', 2)[1].Trim()

    if (
        ($value.StartsWith('"') -and $value.EndsWith('"')) -or
        ($value.StartsWith("'") -and $value.EndsWith("'"))
    ) {
        $value = $value.Substring(1, $value.Length - 2)
    }

    return $value
}

if (-not (Get-Command k6 -ErrorAction SilentlyContinue)) {
    throw "k6 is required on PATH to run the API load harness."
}

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $BaseUrl = [Environment]::GetEnvironmentVariable('BASE_URL')
}
if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
    $BaseUrl = Get-DotEnvValue -Key 'APP_URL'
}
if ([string]::IsNullOrWhiteSpace($ApiToken)) {
    $ApiToken = [Environment]::GetEnvironmentVariable('API_TOKEN')
}

$outputDir = Join-Path $repoRoot "storage/app/load-tests"
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null

if ([string]::IsNullOrWhiteSpace($SummaryFile)) {
    $SummaryFile = Join-Path $outputDir ("api-smoke-{0}.json" -f (Get-Date -Format "yyyyMMdd-HHmmss"))
}

$env:BASE_URL = $BaseUrl
$env:API_TOKEN = $ApiToken
$env:K6_VUS = $Vus
$env:K6_DURATION = $Duration
$env:K6_WEB_DASHBOARD = 'false'

& k6 run `
    --summary-export $SummaryFile `
    (Join-Path $PSScriptRoot "k6-api-smoke.js")

if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Output "k6 summary written to $SummaryFile"

if (-not $SkipValidation) {
    & php artisan ops:performance:validate-load $SummaryFile --write

    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}
