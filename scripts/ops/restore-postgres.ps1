param(
    [Parameter(Mandatory = $true)]
    [string] $InputFile,
    [string] $DbName = $env:DB_DATABASE,
    [string] $DbUser = $env:DB_USERNAME,
    [string] $DbPassword = $env:DB_PASSWORD,
    [string] $DbHost = $(if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }),
    [string] $DbPort = $(if ($env:DB_PORT) { $env:DB_PORT } else { '5432' })
)

if (-not (Test-Path $InputFile)) {
    throw "Dump file not found: $InputFile"
}

if ([string]::IsNullOrWhiteSpace($DbName) -or [string]::IsNullOrWhiteSpace($DbUser)) {
    throw "DbName/DB_DATABASE and DbUser/DB_USERNAME must be provided."
}

$env:PGPASSWORD = $DbPassword

& pg_restore `
    --clean `
    --if-exists `
    --no-owner `
    --no-privileges `
    --host $DbHost `
    --port $DbPort `
    --username $DbUser `
    --dbname $DbName `
    $InputFile

if ($LASTEXITCODE -ne 0) {
    throw "pg_restore failed with exit code $LASTEXITCODE."
}

Write-Output "Restore completed into $DbName."
