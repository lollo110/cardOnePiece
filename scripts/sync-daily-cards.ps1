$ErrorActionPreference = 'Stop'

$projectDir = Split-Path -Parent $PSScriptRoot
$logDir = Join-Path $projectDir 'var\log'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$logFile = Join-Path $logDir "card-sync-$timestamp.log"
$mysqlHost = '127.0.0.1'
$mysqlPort = 3306
$laragonDir = 'C:\laragon'
$laragonPath = Join-Path $laragonDir 'laragon.exe'
$mysqldPath = Get-ChildItem -LiteralPath (Join-Path $laragonDir 'bin\mysql') -Recurse -Filter 'mysqld.exe' -ErrorAction SilentlyContinue |
    Sort-Object -Property FullName -Descending |
    Select-Object -First 1 -ExpandProperty FullName
$mysqldLogPath = 'C:\laragon\data\mysql-8.4\mysqld.log'
$startupTimeoutSeconds = 120

New-Item -ItemType Directory -Force -Path $logDir | Out-Null
Set-Location $projectDir

function Write-SyncLog {
    param([string] $Message)

    $line = '[{0}] {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -LiteralPath $logFile -Value $line
}

function Test-TcpPort {
    param(
        [string] $HostName,
        [int] $Port,
        [int] $TimeoutMilliseconds = 1000
    )

    $client = [System.Net.Sockets.TcpClient]::new()

    try {
        $connect = $client.BeginConnect($HostName, $Port, $null, $null)

        if (-not $connect.AsyncWaitHandle.WaitOne($TimeoutMilliseconds, $false)) {
            return $false
        }

        $client.EndConnect($connect)

        return $client.Connected
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Wait-MySql {
    param([int] $TimeoutSeconds)

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        if (Test-TcpPort -HostName $mysqlHost -Port $mysqlPort) {
            return $true
        }

        Start-Sleep -Seconds 2
    }

    return $false
}

function Ensure-MySqlReady {
    if (Test-TcpPort -HostName $mysqlHost -Port $mysqlPort) {
        Write-SyncLog "MySQL is already reachable on ${mysqlHost}:${mysqlPort}."

        return
    }

    Write-SyncLog "MySQL is not reachable on ${mysqlHost}:${mysqlPort}; trying to start Laragon/MySQL."

    if ((Test-Path -LiteralPath $laragonPath) -and -not (Get-Process -Name 'laragon' -ErrorAction SilentlyContinue)) {
        Write-SyncLog "Starting Laragon: $laragonPath"
        Start-Process -FilePath $laragonPath -WindowStyle Hidden
    }

    if (Wait-MySql -TimeoutSeconds 30) {
        Write-SyncLog "MySQL became reachable after starting Laragon."

        return
    }

    if ($mysqldPath -and (Test-Path -LiteralPath $mysqldPath) -and -not (Get-Process -Name 'mysqld' -ErrorAction SilentlyContinue) -and -not (Test-TcpPort -HostName $mysqlHost -Port $mysqlPort)) {
        Write-SyncLog "Starting MySQL directly: $mysqldPath"
        Start-Process -FilePath $mysqldPath -ArgumentList "--log-error=$mysqldLogPath" -WindowStyle Hidden
    } elseif (Get-Process -Name 'mysqld' -ErrorAction SilentlyContinue) {
        Write-SyncLog "MySQL process is already running; waiting for ${mysqlHost}:${mysqlPort}."
    }

    if (-not (Wait-MySql -TimeoutSeconds $startupTimeoutSeconds)) {
        Write-SyncLog "MySQL did not become reachable within $startupTimeoutSeconds seconds. Sync aborted."
        exit 1
    }

    Write-SyncLog "MySQL is reachable; continuing sync."
}

Ensure-MySqlReady

$syncOutput = & php bin/console app:cards:sync-daily --no-interaction 2>&1
$syncExitCode = $LASTEXITCODE

if ($syncOutput) {
    $syncOutput | ForEach-Object {
        Add-Content -LiteralPath $logFile -Value $_
    }
}

exit $syncExitCode
