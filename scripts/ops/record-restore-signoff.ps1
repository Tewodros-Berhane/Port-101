param(
    [string] $Workspace,
    [switch] $JsonOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $repoRoot

$arguments = @('artisan', 'ops:recovery:signoff', '--write')

if (-not [string]::IsNullOrWhiteSpace($Workspace)) {
    $arguments += "--workspace=$Workspace"
}

if ($JsonOnly) {
    $arguments += '--json'
}

& php @arguments
exit $LASTEXITCODE
