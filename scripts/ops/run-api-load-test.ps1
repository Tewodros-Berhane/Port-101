param(
    [string] $BaseUrl,
    [string] $ApiToken,
    [string] $SummaryFile,
    [int] $Vus = 4,
    [string] $Duration = "60s",
    [switch] $SkipValidation,
    [string] $K6Bin,
    [string] $ValidationProfile = "default"
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

function Resolve-K6Binary {
    param(
        [string] $ExplicitBinary
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitBinary)) {
        if (Test-Path $ExplicitBinary) {
            return (Resolve-Path $ExplicitBinary).Path
        }

        throw "Specified k6 binary [$ExplicitBinary] does not exist."
    }

    $resolved = Get-Command k6 -ErrorAction SilentlyContinue

    if ($resolved) {
        return $resolved.Source
    }

    $candidates = @(
        'C:\Program Files\k6\k6.exe',
        'C:\Program Files (x86)\k6\k6.exe'
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    throw "k6 is required on PATH or available via -K6Bin to run the API load harness."
}

$k6Binary = Resolve-K6Binary -ExplicitBinary $K6Bin

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

& $k6Binary run `
    --summary-export $SummaryFile `
    (Join-Path $PSScriptRoot "k6-api-smoke.js")

if ($LASTEXITCODE -ne 0) {
    throw "k6 failed with exit code $LASTEXITCODE."
}

Write-Output "k6 summary written to $SummaryFile"

if (-not $SkipValidation) {
    & php artisan ops:performance:validate-load $SummaryFile --write "--profile=$ValidationProfile"

    if ($LASTEXITCODE -ne 0) {
        throw "Load-test validation failed with exit code $LASTEXITCODE."
    }
}
