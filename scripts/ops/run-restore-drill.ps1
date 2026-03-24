param(
    [string] $DbName,
    [string] $DbUser,
    [string] $DbPassword,
    [string] $DbHost,
    [string] $DbPort,
    [string] $DrillDbName,
    [switch] $KeepDatabase,
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

if ([string]::IsNullOrWhiteSpace($DbName)) {
    $DbName = Resolve-Value -Names @('DB_DATABASE', 'DB_NAME')
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

if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser)) {
    throw "DB_DATABASE/DB_NAME and DB_USERNAME/DB_USER must be available via parameters, environment, or .env."
}

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$suffix = ([Guid]::NewGuid().ToString('N')).Substring(0, 8)
$workspace = Join-Path $repoRoot "storage/app/restore-drills/$timestamp-$suffix"
$backupDatabaseDir = Join-Path $workspace "backups/database"
$backupStorageDir = Join-Path $workspace "backups/storage"
$restoreRoot = Join-Path $workspace "restore-root"
$logsDir = Join-Path $workspace "logs"

New-Item -ItemType Directory -Force -Path $backupDatabaseDir, $backupStorageDir, $restoreRoot, $logsDir | Out-Null

$dbSlug = ($DbName.ToLower() -replace '[^a-z0-9_]', '_')
if ($dbSlug.Length -gt 40) {
    $dbSlug = $dbSlug.Substring(0, 40)
}

if ([string]::IsNullOrWhiteSpace($DrillDbName)) {
    $DrillDbName = "${dbSlug}_restore_${suffix}"
}

$backupPostgresScript = Join-Path $PSScriptRoot "backup-postgres.ps1"
$backupStorageScript = Join-Path $PSScriptRoot "backup-storage.ps1"
$restorePostgresScript = Join-Path $PSScriptRoot "restore-postgres.ps1"
$restoreStorageScript = Join-Path $PSScriptRoot "restore-storage.ps1"

$dbDumpPath = $null
$storageArchivePath = $null
$drillFailure = $null
$overrideKeys = @(
    'DB_CONNECTION',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_HOST',
    'DB_PORT',
    'LOCAL_FILESYSTEM_ROOT',
    'PUBLIC_FILESYSTEM_ROOT',
    'BACKUP_ATTACHMENTS_DISK',
    'BACKUP_DATABASE_DUMP_DIR',
    'BACKUP_STORAGE_ARCHIVE_DIR'
)
$previousValues = @{}

try {
    Write-Output "Creating disposable restore drill workspace at $workspace"

    $dbDumpPath = (& $backupPostgresScript `
        -DbName $DbName `
        -DbUser $DbUser `
        -DbPassword $DbPassword `
        -DbHost $DbHost `
        -DbPort $DbPort `
        -OutputDir $backupDatabaseDir | Select-Object -Last 1).Trim()

    $storageArchivePath = (& $backupStorageScript `
        -OutputDir $backupStorageDir | Select-Object -Last 1).Trim()

    if (-not (Test-Path $dbDumpPath)) {
        throw "Database backup artifact was not created."
    }

    if (-not (Test-Path $storageArchivePath)) {
        throw "Storage backup artifact was not created."
    }

    $env:PGPASSWORD = $DbPassword

    & createdb `
        --host $DbHost `
        --port $DbPort `
        --username $DbUser `
        $DrillDbName

    if ($LASTEXITCODE -ne 0) {
        throw "createdb failed with exit code $LASTEXITCODE."
    }

    & $restorePostgresScript `
        -InputFile $dbDumpPath `
        -DbName $DrillDbName `
        -DbUser $DbUser `
        -DbPassword $DbPassword `
        -DbHost $DbHost `
        -DbPort $DbPort | Out-Null

    & $restoreStorageScript `
        -InputFile $storageArchivePath `
        -DestinationRoot $restoreRoot | Out-Null

    foreach ($key in $overrideKeys) {
        $previousValues[$key] = [Environment]::GetEnvironmentVariable($key)
    }

    $overrideMap = @{
        'DB_CONNECTION' = 'pgsql'
        'DB_DATABASE' = $DrillDbName
        'DB_USERNAME' = $DbUser
        'DB_PASSWORD' = $DbPassword
        'DB_HOST' = $DbHost
        'DB_PORT' = $DbPort
        'LOCAL_FILESYSTEM_ROOT' = (Join-Path $restoreRoot "storage/app/private")
        'PUBLIC_FILESYSTEM_ROOT' = (Join-Path $restoreRoot "storage/app/public")
        'BACKUP_ATTACHMENTS_DISK' = 'local'
        'BACKUP_DATABASE_DUMP_DIR' = $backupDatabaseDir
        'BACKUP_STORAGE_ARCHIVE_DIR' = $backupStorageDir
    }

    foreach ($entry in $overrideMap.GetEnumerator()) {
        [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value)
    }

    & php artisan optimize:clear | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan optimize:clear failed."
    }

    & php artisan migrate --force | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan migrate --force failed for the restore drill database."
    }

    & php artisan platform:operations:heartbeat | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan platform:operations:heartbeat failed for the restore drill database."
    }

    $recoveryOutput = (& php artisan ops:recovery:smoke-check --json 2>&1 | Out-String).Trim()
    Set-Content -Path (Join-Path $logsDir "recovery-smoke-check.json") -Value $recoveryOutput
    if ($LASTEXITCODE -ne 0) {
        throw "Recovery smoke check failed for the restore drill database."
    }

    $deployOutput = (& php artisan ops:deploy:smoke-check --json --require-heartbeat 2>&1 | Out-String).Trim()
    Set-Content -Path (Join-Path $logsDir "deploy-smoke-check.json") -Value $deployOutput
    if ($LASTEXITCODE -ne 0) {
        throw "Deployment smoke check failed for the restore drill database."
    }

    Write-Output "Restore drill completed successfully."
    Write-Output "Drill database: $DrillDbName"
    Write-Output "Workspace: $workspace"
    Write-Output "Recovery result: $(Join-Path $logsDir 'recovery-smoke-check.json')"
    Write-Output "Deploy result: $(Join-Path $logsDir 'deploy-smoke-check.json')"
}
catch {
    $drillFailure = $_.Exception.Message
    Write-Warning $drillFailure
    Write-Warning "Restore drill workspace retained at $workspace"
    Write-Warning "Inspect logs under $(Join-Path $logsDir '')"
    throw
}
finally {
    foreach ($key in $overrideKeys) {
        [Environment]::SetEnvironmentVariable($key, $previousValues[$key])
    }

    if (-not $KeepDatabase) {
        try {
            & dropdb `
                --if-exists `
                --host $DbHost `
                --port $DbPort `
                --username $DbUser `
                $DrillDbName | Out-Null
        } catch {
            Write-Warning "Failed to drop disposable restore drill database $DrillDbName."
        }
    }

    if ($CleanupArtifacts) {
        Remove-Item -Recurse -Force $workspace -ErrorAction SilentlyContinue
    }
}
