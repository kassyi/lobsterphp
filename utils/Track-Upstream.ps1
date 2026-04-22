param(
    [switch]$UpdateJson
)

$ErrorActionPreference = "Stop"

$syncFile = "$PSScriptRoot\..\.upstream-sync.json"
if (-not (Test-Path $syncFile)) {
    Write-Error "Sync file not found: $syncFile"
}

$syncData = Get-Content $syncFile | ConvertFrom-Json
$lastCommit = $syncData.last_synced_commit

Write-Host "Fetching latest changes from upstream (lobsterjs)..." -ForegroundColor Cyan
Push-Location "$PSScriptRoot\..\.upstream\lobsterjs"

git fetch origin main | Out-Null
$latestCommit = git rev-parse origin/main

if ($lastCommit -eq $latestCommit) {
    Write-Host "You are up to date with upstream lobsterjs ($latestCommit)." -ForegroundColor Green
    Pop-Location
    exit 0
}

Write-Host "New upstream changes detected!" -ForegroundColor Yellow
Write-Host "From: $lastCommit"
Write-Host "To:   $latestCommit"
Write-Host "`n--- Commits ---"
git log --oneline "$lastCommit..$latestCommit"

Write-Host "`n--- Changed Files ---"
git diff --name-status "$lastCommit..$latestCommit"

Pop-Location

if ($UpdateJson) {
    $syncData.last_synced_commit = $latestCommit
    $syncData.sync_date = (Get-Date).ToString("yyyy-MM-dd")
    $syncData | ConvertTo-Json | Set-Content $syncFile
    Write-Host "`nUpdated $syncFile with latest commit ($latestCommit)." -ForegroundColor Green
} else {
    Write-Host "`nRun '.\utils\Track-Upstream.ps1 -UpdateJson' after porting changes to mark them as synced." -ForegroundColor Cyan
}
