param(
    [Parameter(Mandatory = $true)]
    [string] $InputFile,
    [string] $DestinationRoot = "."
)

if (-not (Test-Path $InputFile)) {
    throw "Archive file not found: $InputFile"
}

Expand-Archive -Path $InputFile -DestinationPath $DestinationRoot -Force

Write-Output "Storage archive restored into $DestinationRoot."
