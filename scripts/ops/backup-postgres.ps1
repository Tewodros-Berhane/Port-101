param(
    [string] $DbName = $env:DB_DATABASE,
    [string] $DbUser = $env:DB_USERNAME,
    [string] $DbPassword = $env:DB_PASSWORD,
    [string] $DbHost = $(if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }),
    [string] $DbPort = $(if ($env:DB_PORT) { $env:DB_PORT } else { '5432' }),
    [string] $OutputDir = "storage/app/backups/database"
)

if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser)) {
    throw "DbName/DB_DATABASE and DbUser/DB_USERNAME must be provided."
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$outputPath = Join-Path $OutputDir "$DbName-$timestamp.dump"

$env:PGPASSWORD = $DbPassword

& pg_dump `
    --format=custom `
    --no-owner `
    --no-privileges `
    --host $DbHost `
    --port $DbPort `
    --username $DbUser `
    --file $outputPath `
    $DbName

if ($LASTEXITCODE -ne 0) {
    throw "pg_dump failed with exit code $LASTEXITCODE."
}

Write-Output $outputPath
