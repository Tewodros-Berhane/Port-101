param(
    [Parameter(Mandatory = $true)]
    [string] $EvidenceFile,
    [switch] $JsonOnly,
    [switch] $Write
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\\..")
Set-Location $repoRoot

$arguments = @(
    'artisan',
    'ops:backup:signoff',
    $EvidenceFile
)

if ($Write) {
    $arguments += '--write'
}

if ($JsonOnly) {
    $arguments += '--json'
}

& php @arguments
exit $LASTEXITCODE
