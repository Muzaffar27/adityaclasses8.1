$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
$workerScript = Join-Path $workspace 'scripts\questions\app-worker.mjs'
$existing = Get-CimInstance Win32_Process -Filter "Name='node.exe'" | Where-Object { $_.CommandLine -like "*$workerScript*" -and $_.CommandLine -like '*--watch*' } | Select-Object -First 1
if ($existing) {
    Write-Output "Local question-bank worker already running (PID $($existing.ProcessId))."
    exit 0
}
$logDir = Join-Path $workspace 'storage\logs'
New-Item -ItemType Directory -Path $logDir -Force | Out-Null
$nodePath = (Get-Command node -ErrorAction Stop).Source
$workerProcess = Start-Process -FilePath $nodePath -ArgumentList @($workerScript, '--watch') -WorkingDirectory $workspace -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDir 'question-bank-worker.log') -RedirectStandardError (Join-Path $logDir 'question-bank-worker-errors.log') -PassThru
Write-Output "Started local question-bank worker (PID $($workerProcess.Id))."
