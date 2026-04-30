$ErrorActionPreference = 'Stop'

$projectDir = Split-Path -Parent $PSScriptRoot
$logDir = Join-Path $projectDir 'var\log'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $logDir "card-sync-$timestamp.log"

New-Item -ItemType Directory -Force -Path $logDir | Out-Null
Set-Location $projectDir

& php bin/console app:cards:sync-daily --no-interaction *> $logFile
exit $LASTEXITCODE
