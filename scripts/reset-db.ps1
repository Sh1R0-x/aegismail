<#
.SYNOPSIS
    Remet la base SQLite locale a zero et reapplique toutes les migrations.

.DESCRIPTION
    Ce script supprime la base SQLite locale (database.sqlite), la recree vide,
    et relance toutes les migrations depuis zero. Il n'affecte ni la base de test
    en memoire (phpunit.xml → :memory:), ni la base e2e (database/e2e.sqlite).

    A utiliser quand :
    - Le schema local est desaligne avec le code (colonnes manquantes, etc.)
    - On souhaite repartir d'une base propre sans donnees
    - Apres un pull qui ajoute de nouvelles migrations

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1

.EXAMPLE
    # Avec seeding optionnel
    powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1 -Seed
#>

param(
    [switch] $Seed
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$appPath = Join-Path $repoRoot 'app'
$sqliteFile = Join-Path $appPath 'database\database.sqlite'

function Write-Step {
    param([string] $Message)
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Fail {
    param([string] $Message)
    Write-Host "ERROR: $Message" -ForegroundColor Red
    exit 1
}

# Verify we're not accidentally targeting a non-SQLite env
$envFile = Join-Path $appPath '.env'
if (Test-Path $envFile) {
    $envContent = Get-Content $envFile -Raw
    if ($envContent -notmatch '(?m)^DB_CONNECTION=sqlite') {
        Fail "DB_CONNECTION n'est pas sqlite dans .env. Ce script ne doit pas etre utilise avec une autre base."
    }
}

Push-Location $appPath

try {
    Write-Step 'Suppression de la base SQLite locale'
    if (Test-Path $sqliteFile) {
        Remove-Item $sqliteFile -Force
        Write-Host "  Supprime: $sqliteFile" -ForegroundColor Yellow
    }
    else {
        Write-Host "  Fichier absent, rien a supprimer." -ForegroundColor Gray
    }

    Write-Step 'Creation du fichier SQLite vide'
    New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
    Write-Host "  Cree: $sqliteFile" -ForegroundColor Green

    Write-Step 'Application de toutes les migrations'
    & php artisan migrate --no-interaction --ansi
    if ($LASTEXITCODE -ne 0) {
        Fail "Les migrations ont echoue (code de sortie: $LASTEXITCODE)."
    }

    if ($Seed) {
        Write-Step 'Execution des seeders'
        & php artisan db:seed --no-interaction --ansi
        if ($LASTEXITCODE -ne 0) {
            Fail "Le seeding a echoue (code de sortie: $LASTEXITCODE)."
        }
    }

    Write-Step 'Verification du schema'
    $status = & php artisan migrate:status --no-ansi 2>&1
    $pending = $status | Select-String 'Pending'
    if ($pending) {
        Fail "Des migrations sont encore en attente apres le reset. Verifiez les fichiers de migration."
    }

    Write-Host ""
    Write-Host "Base locale reinitalisee avec succes." -ForegroundColor Green
    Write-Host "Toutes les migrations sont appliquees. La base est vide et prete." -ForegroundColor Green
    if (-not $Seed) {
        Write-Host "Conseil: ajoutez -Seed pour pre-remplir les donnees de base." -ForegroundColor Gray
    }
}
finally {
    Pop-Location
}
