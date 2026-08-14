param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\dist')
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$outputRoot = [System.IO.Path]::GetFullPath($OutputDirectory)
$releaseName = 'travel-quote-system'
$stageRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('travel-quote-release-' + [guid]::NewGuid().ToString('N'))
$stageProject = Join-Path $stageRoot $releaseName
$archivePath = Join-Path $outputRoot ($releaseName + '.zip')

New-Item -ItemType Directory -Force -Path $stageProject, $outputRoot | Out-Null

$includedEntries = @(
    'app',
    'bootstrap',
    'config',
    'database',
    'public',
    'resources',
    'routes',
    'scripts',
    'storage',
    'artisan',
    'composer.json',
    'composer.lock',
    '.env.example',
    '.gitattributes',
    '.gitignore',
    'BAOTA_DEPLOYMENT.md',
    'README.md'
)

try {
    foreach ($entry in $includedEntries) {
        $source = Join-Path $projectRoot $entry
        if (-not (Test-Path -LiteralPath $source)) {
            throw "Release entry is missing: $source"
        }

        Copy-Item -LiteralPath $source -Destination $stageProject -Recurse -Force
    }

    Get-ChildItem -LiteralPath (Join-Path $stageProject 'storage') -Recurse -File |
        Where-Object { $_.Name -ne '.gitignore' } |
        Remove-Item -Force

    $sqliteDatabase = Join-Path $stageProject 'database\database.sqlite'
    if (Test-Path -LiteralPath $sqliteDatabase) {
        Remove-Item -LiteralPath $sqliteDatabase -Force
    }

    Get-ChildItem -LiteralPath (Join-Path $stageProject 'bootstrap\cache') -File |
        Where-Object { $_.Name -ne '.gitignore' } |
        Remove-Item -Force

    if (Test-Path -LiteralPath $archivePath) {
        Remove-Item -LiteralPath $archivePath -Force
    }

    & tar.exe -a -c -f $archivePath -C $stageRoot $releaseName
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create release archive with tar.exe (exit code $LASTEXITCODE)."
    }
    Write-Output $archivePath
}
finally {
    if (Test-Path -LiteralPath $stageRoot) {
        Remove-Item -LiteralPath $stageRoot -Recurse -Force
    }
}
