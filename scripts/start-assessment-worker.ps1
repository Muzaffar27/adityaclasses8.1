$ErrorActionPreference = 'Stop'
$workspace = Split-Path -Parent $PSScriptRoot
Push-Location $workspace
try {
    $environment = docker compose exec -T --user www-data app php artisan env
    if ($LASTEXITCODE -ne 0 -or ($environment -join ' ') -notmatch '\[local\]') {
        throw 'This helper requires the running local application container.'
    }
    docker compose exec -T -d --user www-data app sh -c 'exec flock -n /tmp/aditya-assessment-scheduler.lock php artisan schedule:work >> storage/logs/assessment-scheduler.log 2>&1'
    if ($LASTEXITCODE -ne 0) { throw 'Could not start the local assessment scheduler.' }
    Write-Output 'Local scheduler requested. The lock prevents duplicate schedulers. Only explicitly activated student courses are processed.'
} finally { Pop-Location }
