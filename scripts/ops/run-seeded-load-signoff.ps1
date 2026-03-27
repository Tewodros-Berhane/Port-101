param(
    [string] $SourceDbName,
    [string] $DbUser,
    [string] $DbPassword,
    [string] $DbHost,
    [string] $DbPort,
    [string] $ListenHost = "127.0.0.1",
    [int] $Port = 8011,
    [string] $Company = "demo-company-workflow",
    [string] $TokenName = "ops-load-test",
    [int] $Vus = 4,
    [string] $Duration = "30s",
    [switch] $KeepSourceDatabase,
    [switch] $KeepServerLogs,
    [string] $K6Bin,
    [string] $ValidationProfile = "rehearsal"
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

function Get-ResponseBody {
    param(
        [Parameter(Mandatory = $true)]
        [System.Exception] $Exception
    )

    $response = $Exception.Response

    if (-not $response) {
        return ''
    }

    try {
        if ($response -is [System.Net.HttpWebResponse]) {
            $stream = $response.GetResponseStream()

            if ($stream) {
                $reader = New-Object System.IO.StreamReader($stream)
                return $reader.ReadToEnd()
            }
        }

        if ($response.Content) {
            return $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        }
    } catch {
        return ''
    }

    return ''
}

function Assert-ApiEndpoint {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Url,
        [Parameter(Mandatory = $true)]
        [hashtable] $Headers,
        [Parameter(Mandatory = $true)]
        [string] $Label
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -Headers $Headers -UseBasicParsing -TimeoutSec 10

        if ($response.StatusCode -ne 200) {
            throw "Preflight [$Label] returned status $($response.StatusCode)."
        }
    } catch {
        $body = ''
        $status = $null

        if ($_.Exception.Response) {
            try {
                $status = [int] $_.Exception.Response.StatusCode
            } catch {
                $status = $null
            }

            $body = Get-ResponseBody -Exception $_.Exception
        }

        $detail = if ($status) { "status $status" } else { 'request failure' }
        $bodyPreview = if ([string]::IsNullOrWhiteSpace($body)) {
            ''
        } else {
            " Body: $($body.Substring(0, [Math]::Min($body.Length, 400)))"
        }

        throw "Preflight [$Label] failed with $detail.$bodyPreview"
    }
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
    $SourceDbName = "port101_load_source_$suffix"
}

$loadTestRoot = Join-Path $repoRoot "storage/app/load-tests"
$loadSignoffRoot = Join-Path $repoRoot "storage/app/load-signoffs"
$logsRoot = Join-Path $repoRoot "storage/app/load-test-logs"
New-Item -ItemType Directory -Force -Path $loadTestRoot, $loadSignoffRoot, $logsRoot | Out-Null

$existingSummaries = @(Get-ChildItem -Path $loadTestRoot -Filter "*.json" -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName)
$existingSignoffs = @(Get-ChildItem -Path $loadSignoffRoot -Filter "*.json" -ErrorAction SilentlyContinue | Select-Object -ExpandProperty FullName)
$serverStdout = Join-Path $logsRoot "seeded-load-$timestamp-$suffix.out.log"
$serverStderr = Join-Path $logsRoot "seeded-load-$timestamp-$suffix.err.log"
$overrideKeys = @(
    'DB_CONNECTION',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_HOST',
    'DB_PORT',
    'APP_URL'
)
$previousValues = @{}
$serverProcess = $null
$baseUrl = "http://$ListenHost`:$Port"
$serverScript = Join-Path $repoRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$serverWorkingDirectory = Join-Path $repoRoot 'public'

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
        'APP_URL' = $baseUrl
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
        throw "php artisan migrate --force failed for the seeded load database."
    }

    & php artisan db:seed --class=DatabaseSeeder | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan db:seed --class=DatabaseSeeder failed for the seeded load database."
    }

    & php artisan db:seed --class=DemoCompanyWorkflowSeeder | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan db:seed --class=DemoCompanyWorkflowSeeder failed for the seeded load database."
    }

    $tokenPayload = (& php artisan ops:load-test:token --company=$Company --name=$TokenName --json 2>&1 | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw "php artisan ops:load-test:token failed: $tokenPayload"
    }

    $tokenJson = $tokenPayload | ConvertFrom-Json
    $apiToken = [string] $tokenJson.token

    if ([string]::IsNullOrWhiteSpace($apiToken)) {
        throw "Load-test token command did not return a token."
    }

    $serverProcess = Start-Process `
        -FilePath "php" `
        -WorkingDirectory $serverWorkingDirectory `
        -ArgumentList @('-S', "$ListenHost`:$Port", $serverScript) `
        -PassThru `
        -RedirectStandardOutput $serverStdout `
        -RedirectStandardError $serverStderr

    $deadline = (Get-Date).AddSeconds(60)
    $serverReady = $false

    while ((Get-Date) -lt $deadline) {
        if ($serverProcess.HasExited) {
            break
        }

        try {
            $response = Invoke-WebRequest -Uri "$baseUrl/api/v1/health" -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -eq 200) {
                $serverReady = $true
                break
            }
        } catch {
            Start-Sleep -Seconds 1
        }
    }

    if (-not $serverReady) {
        $stdout = if (Test-Path $serverStdout) { Get-Content $serverStdout -Raw } else { '' }
        $stderr = if (Test-Path $serverStderr) { Get-Content $serverStderr -Raw } else { '' }
        throw "Seeded Laravel server did not become ready. Stdout: $stdout Stderr: $stderr"
    }

    $authHeaders = @{
        'Accept' = 'application/json'
        'Authorization' = "Bearer $apiToken"
    }

    Assert-ApiEndpoint -Url "$baseUrl/api/v1/projects?per_page=1" -Headers $authHeaders -Label 'projects'
    Assert-ApiEndpoint -Url "$baseUrl/api/v1/inventory/stock-balances?per_page=1" -Headers $authHeaders -Label 'inventory stock balances'
    Assert-ApiEndpoint -Url "$baseUrl/api/v1/sales/orders?per_page=1" -Headers $authHeaders -Label 'sales orders'
    Assert-ApiEndpoint -Url "$baseUrl/api/v1/webhooks/endpoints?per_page=1" -Headers $authHeaders -Label 'webhook endpoints'

    & (Join-Path $PSScriptRoot 'run-api-load-test.ps1') `
        -BaseUrl $baseUrl `
        -ApiToken $apiToken `
        -Vus $Vus `
        -Duration $Duration `
        -K6Bin $K6Bin `
        -ValidationProfile $ValidationProfile

    if ($LASTEXITCODE -ne 0) {
        throw "run-api-load-test.ps1 failed."
    }

    $newSummary = Get-ChildItem -Path $loadTestRoot -Filter "*.json" -ErrorAction SilentlyContinue |
        Where-Object { $existingSummaries -notcontains $_.FullName } |
        Sort-Object LastWriteTime |
        Select-Object -Last 1
    $newSignoff = Get-ChildItem -Path $loadSignoffRoot -Filter "*.json" -ErrorAction SilentlyContinue |
        Where-Object { $existingSignoffs -notcontains $_.FullName } |
        Sort-Object LastWriteTime |
        Select-Object -Last 1

    Write-Output "Seeded load sign-off completed successfully."
    Write-Output "Source database: $SourceDbName"
    Write-Output "Base URL: $baseUrl"
    if ($newSummary) {
        Write-Output "Load summary: $($newSummary.FullName)"
    }
    if ($newSignoff) {
        Write-Output "Load sign-off artifact: $($newSignoff.FullName)"
    }
} catch {
    $newSummary = Get-ChildItem -Path $loadTestRoot -Filter "*.json" -ErrorAction SilentlyContinue |
        Where-Object { $existingSummaries -notcontains $_.FullName } |
        Sort-Object LastWriteTime |
        Select-Object -Last 1

    if ($newSummary) {
        Write-Warning "Latest load summary: $($newSummary.FullName)"
    }

    throw
}
finally {
    if ($serverProcess -and -not $serverProcess.HasExited) {
        Stop-Process -Id $serverProcess.Id -Force -ErrorAction SilentlyContinue
    }

    foreach ($key in $overrideKeys) {
        [Environment]::SetEnvironmentVariable($key, $previousValues[$key])
    }

    if (-not $KeepSourceDatabase) {
        & dropdb `
            --if-exists `
            --host $DbHost `
            --port $DbPort `
            --username $DbUser `
            $SourceDbName | Out-Null
    }

    if (-not $KeepServerLogs) {
        Remove-Item $serverStdout, $serverStderr -Force -ErrorAction SilentlyContinue
    }
}
