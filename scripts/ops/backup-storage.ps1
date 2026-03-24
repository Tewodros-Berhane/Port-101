param(
    [string] $OutputDir = "storage/app/backups/storage"
)

$sourcePaths = @(
    "storage/app/private",
    "storage/app/public"
)

$existingPaths = $sourcePaths | Where-Object { Test-Path $_ }

if ($existingPaths.Count -eq 0) {
    throw "No storage paths found to archive."
}

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$archivePath = Join-Path $OutputDir "port-101-storage-$timestamp.zip"

Compress-Archive -Path $existingPaths -DestinationPath $archivePath -Force

Write-Output $archivePath
