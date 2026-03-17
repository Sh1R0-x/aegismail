$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$appRoot = Join-Path $repoRoot 'app'
$e2eDatabase = Join-Path $appRoot 'database\e2e.sqlite'
$envFile = Join-Path $appRoot '.env'
$envExample = Join-Path $appRoot '.env.example'

if (-not (Test-Path $envFile)) {
    Copy-Item $envExample $envFile
}

if (-not (Test-Path $e2eDatabase)) {
    New-Item -ItemType File -Path $e2eDatabase -Force | Out-Null
}

$env:APP_URL = 'http://127.0.0.1:8001'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $e2eDatabase
$env:QUEUE_CONNECTION = 'database'
$env:CACHE_STORE = 'database'
$env:SESSION_DRIVER = 'database'
$env:MAIL_GATEWAY_DRIVER = 'stub'

Set-Location $appRoot

$appKeyLine = Select-String -Path $envFile -Pattern '^APP_KEY=' | Select-Object -First 1
if ($null -eq $appKeyLine -or [string]::IsNullOrWhiteSpace(($appKeyLine.Line -replace '^APP_KEY=', ''))) {
    php artisan key:generate --ansi --force
}

php artisan migrate:fresh --seed --seeder=SmokeTestSeeder --force --ansi
npm run build
php artisan serve --host=127.0.0.1 --port=8001 --no-reload
