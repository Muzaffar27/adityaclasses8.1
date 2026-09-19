$ErrorActionPreference = 'Stop'

$volumeName = 'adityaclasses_app_public'
$helperName = "adityaclasses-public-sync-$PID"
$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stageRoot = [IO.Path]::GetFullPath((Join-Path $tempRoot $helperName))

docker volume inspect $volumeName *> $null
if ($LASTEXITCODE -ne 0) {
    throw "Docker volume '$volumeName' does not exist. Start the local Docker environment first."
}

if (-not $stageRoot.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Refusing to use a staging directory outside the system temporary directory.'
}

try {
    New-Item -ItemType Directory -Path $stageRoot -Force | Out-Null
    Copy-Item -LiteralPath public -Destination $stageRoot -Recurse -Force

    docker create --name $helperName --mount "source=$volumeName,target=/target" adityaclasses-local sh -lc "sleep 300" *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Failed to create the public-assets helper container.' }

    docker start $helperName *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Failed to start the public-assets helper container.' }

    docker cp "$stageRoot\public\." "${helperName}:/target"
    if ($LASTEXITCODE -ne 0) { throw 'Failed to synchronize public assets to Docker.' }

    Write-Host 'Local Docker public assets synchronized.'
}
finally {
    docker rm -f $helperName *> $null
    if (Test-Path -LiteralPath $stageRoot) {
        Remove-Item -LiteralPath $stageRoot -Recurse -Force
    }
}
