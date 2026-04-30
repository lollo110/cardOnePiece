$ErrorActionPreference = 'Stop'

$runAfter = [TimeSpan]::Parse('10:00:00')

if ((Get-Date).TimeOfDay -lt $runAfter) {
    exit 0
}

$scriptPath = Join-Path $PSScriptRoot 'sync-daily-cards.ps1'

& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $scriptPath
exit $LASTEXITCODE
