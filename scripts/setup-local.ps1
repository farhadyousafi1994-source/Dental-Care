# Run from PowerShell: .\scripts\setup-local.ps1
# Only migrates the database after an explicit confirmation. Never uses migrate:fresh.
$ErrorActionPreference = 'Stop'
$Root = Split-Path $PSScriptRoot -Parent
function Run-Step([string]$Program, [string[]]$Arguments) {
    & $Program @Arguments
    if ($LASTEXITCODE -ne 0) { throw "$Program failed. Resolve the error above before continuing." }
}
foreach ($tool in @('php', 'composer', 'node', 'npm')) {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { throw "$tool is missing from PATH. Restart your terminal after installing it." }
}
Push-Location $Root
try {
    if (-not (Test-Path 'backend/.env')) {
        Copy-Item 'backend/.env.example' 'backend/.env'
        Write-Host 'Created backend/.env. Configure MySQL and CMS_ADMIN_EMAIL / CMS_ADMIN_PASSWORD before continuing.'
        Write-Host 'Create website_cms in phpMyAdmin using utf8mb4_unicode_ci. Do not paste passwords into chat.'
        if ((Read-Host 'Press Enter after editing .env, or type cancel') -eq 'cancel') { return }
    }
    Push-Location 'backend'
    try {
        Run-Step 'composer' @('install','--no-interaction')
        $Key = Get-Content '.env' | Where-Object { $_ -match '^APP_KEY=.+$' }
        if (-not $Key) { Run-Step 'php' @('artisan','key:generate') }
        Run-Step 'php' @('artisan','config:clear')
        $Answer = Read-Host 'Apply migrations and seed defaults to the configured LOCAL database? Type MIGRATE'
        if ($Answer -ne 'MIGRATE') { Write-Host 'Database changes skipped.'; return }
        Run-Step 'php' @('artisan','migrate','--seed')
        if (-not (Test-Path 'public/storage')) { Run-Step 'php' @('artisan','storage:link') }
        Run-Step 'php' @('artisan','cms:doctor')
    } finally { Pop-Location }
    Push-Location 'frontend'
    try { Run-Step 'npm' @('ci'); Run-Step 'npm' @('run','build') } finally { Pop-Location }
    Write-Host 'Setup complete. Start Laravel and the Vue app in separate terminals (see docs/LOCAL_SETUP.md).'
} finally { Pop-Location }
