param(
    [switch]$Json,
    [switch]$RequireHeartbeat
)

$root = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $root

$artisanArgs = @("ops:deploy:smoke-check")

if ($Json) {
    $artisanArgs += "--json"
}

if ($RequireHeartbeat) {
    $artisanArgs += "--require-heartbeat"
}

& php artisan @artisanArgs

exit $LASTEXITCODE
