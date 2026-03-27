param(
    [string] $SourceDbName,
    [string] $DbUser,
    [string] $DbPassword,
    [string] $DbHost,
    [string] $DbPort,
    [switch] $KeepSourceDatabase,
    [switch] $KeepRestoreDatabase,
    [switch] $CleanupArtifacts
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

function Resolve-Value {
    param(
        [string[]] $Names,
        [string] $Fallback = ""
    )

    foreach ($name in $Names) {
        $envValue = [Environment]::GetEnvironmentVariable($name)

        if (-not [string]::IsNullOrWhiteSpace($envValue)) {
            return $envValue
        }

        $dotenvValue = Get-DotEnvValue -Key $name

        if (-not [string]::IsNullOrWhiteSpace($dotenvValue)) {
            return $dotenvValue
        }
    }

    return $Fallback
}

if ([string]::IsNullOrWhiteSpace($DbUser)) {
    $DbUser = Resolve-Value -Names @('DB_USERNAME', 'DB_USER')
}
if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    $DbPassword = Resolve-Value -Names @('DB_PASSWORD')
}
if ([string]::IsNullOrWhiteSpace($DbHost)) {
    $DbHost = Resolve-Value -Names @('DB_HOST') -Fallback '127.0.0.1'
}
if ([string]::IsNullOrWhiteSpace($DbPort)) {
    $DbPort = Resolve-Value -Names @('DB_PORT') -Fallback '5432'
}

if ([string]::IsNullOrWhiteSpace($DbUser)) {
    throw "DB_USERNAME/DB_USER must be available via parameters, environment, or .env."
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$suffix = ([Guid]::NewGuid().ToString('N')).Substring(0, 8)

if ([string]::IsNullOrWhiteSpace($SourceDbName)) {
    $SourceDbName = "port101_restore_source_$suffix"
}

$restoreDrillRoot = Join-Path $repoRoot "storage/app/restore-drills"
$existingWorkspaces = @()

if (Test-Path $restoreDrillRoot) {
    $existingWorkspaces = Get-ChildItem -Path $restoreDrillRoot -Directory | Select-Object -ExpandProperty FullName
}

$overrideKeys = @(
    'DB_CONNECTION',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_HOST',
    'DB_PORT'
)
$previousValues = @{}

try {
    $env:PGPASSWORD = $DbPassword

    & dropdb `
        --if-exists `
        --host $DbHost `
        --port $DbPort `
        --username $DbUser `
        $SourceDbName | Out-Null

    & createdb `
        --host $DbHost `
        --port $DbPort `
        --username $DbUser `
        $SourceDbName

    if ($LASTEXITCODE -ne 0) {
        throw "createdb failed with exit code $LASTEXITCODE."
    }

    foreach ($key in $overrideKeys) {
        $previousValues[$key] = [Environment]::GetEnvironmentVariable($key)
    }

    $overrideMap = @{
        'DB_CONNECTION' = 'pgsql'
        'DB_DATABASE' = $SourceDbName
        'DB_USERNAME' = $DbUser
        'DB_PASSWORD' = $DbPassword
        'DB_HOST' = $DbHost
        'DB_PORT' = $DbPort
    }

    foreach ($entry in $overrideMap.GetEnumerator()) {
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value)
    }

    & php artisan config:clear | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan config:clear failed."
    }

    & php artisan migrate --force | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan migrate --force failed for the seeded restore source database."
    }

    & php artisan db:seed --class=DatabaseSeeder | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan db:seed --class=DatabaseSeeder failed for the seeded restore source database."
    }

    & php artisan db:seed --class=DemoCompanyWorkflowSeeder | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan db:seed --class=DemoCompanyWorkflowSeeder failed for the seeded restore source database."
    }
}
finally {
    foreach ($key in $overrideKeys) {
        [Environment]::SetEnvironmentVariable($key, $previousValues[$key])
    }
}

$restoreArguments = @{
    DbName = $SourceDbName
    DbUser = $DbUser
    DbPassword = $DbPassword
    DbHost = $DbHost
    DbPort = $DbPort
}

if ($KeepRestoreDatabase) {
    $restoreArguments.KeepDatabase = $true
}

if ($CleanupArtifacts) {
    $restoreArguments.CleanupArtifacts = $true
}

& (Join-Path $PSScriptRoot 'run-restore-drill.ps1') @restoreArguments
if ($LASTEXITCODE -ne 0) {
    throw "run-restore-drill.ps1 failed."
}

$workspace = $null

if (-not $CleanupArtifacts -and (Test-Path $restoreDrillRoot)) {
    $workspace = Get-ChildItem -Path $restoreDrillRoot -Directory |
        Where-Object { $existingWorkspaces -notcontains $_.FullName } |
        Sort-Object FullName |
        Select-Object -Last 1 -ExpandProperty FullName
}

if (-not [string]::IsNullOrWhiteSpace($workspace)) {
    & (Join-Path $PSScriptRoot 'record-restore-signoff.ps1') -Workspace $workspace
    if ($LASTEXITCODE -ne 0) {
        throw "record-restore-signoff.ps1 failed."
    }

    Write-Output "Seeded restore sign-off completed successfully."
    Write-Output "Source database: $SourceDbName"
    Write-Output "Workspace: $workspace"
} else {
    Write-Warning "Restore drill workspace could not be resolved for sign-off."
}

if (-not $KeepSourceDatabase) {
    & dropdb `
        --if-exists `
        --host $DbHost `
        --port $DbPort `
        --username $DbUser `
        $SourceDbName | Out-Null
}
