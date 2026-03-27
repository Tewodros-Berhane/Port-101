param(
    [string] $Email,
    [string] $Company,
    [string] $TokenName = "ops-load-test",
    [string] $Abilities = "*",
    [switch] $JsonOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $repoRoot

$arguments = @(
    'artisan',
    'ops:load-test:token',
    "--name=$TokenName",
    "--abilities=$Abilities"
)

if (-not [string]::IsNullOrWhiteSpace($Company)) {
    $arguments += "--company=$Company"
}

if (-not [string]::IsNullOrWhiteSpace($Email)) {
    $arguments += $Email
}

if ($JsonOnly) {
    $arguments += '--json'
}

& php @arguments
exit $LASTEXITCODE
