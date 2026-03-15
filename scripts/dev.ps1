param(
    [ValidateSet('start', 'stop', 'status')]
    [string] $Action = 'start'
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$appPath = Join-Path $repoRoot 'app'
$storagePath = Join-Path $appPath 'storage'
$runtimePath = Join-Path $storagePath 'app\local-dev'
$logPath = Join-Path $storagePath 'logs\local-dev'
$stateFile = Join-Path $runtimePath 'state.json'
$webHost = '127.0.0.1'
$webPort = 8001
$viteHost = '127.0.0.1'
$vitePort = 5173
$dashboardUrl = "http://$webHost`:$webPort/dashboard"

function Write-Step {
    param([string] $Message)

    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Fail {
    param([string] $Message)

    Write-Host "ERROR: $Message" -ForegroundColor Red
    exit 1
}

function Test-Command {
    param([string] $Name)

    return $null -ne (Get-Command $Name -ErrorAction SilentlyContinue)
}

function Ensure-Directory {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        New-Item -ItemType Directory -Path $Path -Force | Out-Null
    }
}

function Get-PortListener {
    param([int] $Port)

    return Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1
}

function Wait-ForPort {
    param(
        [int] $Port,
        [int] $TimeoutSeconds = 25
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        if (Get-PortListener -Port $Port) {
            return $true
        }

        Start-Sleep -Milliseconds 500
    }

    return $false
}

function Wait-ForHttpOk {
    param(
        [string] $Url,
        [int] $TimeoutSeconds = 25
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -UseBasicParsing -Uri $Url -TimeoutSec 5

            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) {
                return $true
            }
        } catch {
            Start-Sleep -Milliseconds 500
        }
    }

    return $false
}

function Get-State {
    if (-not (Test-Path $stateFile)) {
        return $null
    }

    return Get-Content -Raw $stateFile | ConvertFrom-Json
}

function Save-State {
    param(
        [int[]] $LaravelPids,
        [int[]] $VitePids
    )

    Ensure-Directory -Path $runtimePath

    [pscustomobject]@{
        web = @{
            pids = @($LaravelPids | Select-Object -Unique)
            host = $webHost
            port = $webPort
            url  = $dashboardUrl
        }
        vite = @{
            pids = @($VitePids | Select-Object -Unique)
            host = $viteHost
            port = $vitePort
            url  = "http://$viteHost`:$vitePort/"
        }
        started_at = (Get-Date).ToString('o')
    } | ConvertTo-Json -Depth 5 | Set-Content -Path $stateFile -Encoding UTF8
}

function Remove-State {
    if (Test-Path $stateFile) {
        Remove-Item $stateFile -Force
    }
}

function Get-AliveProcess {
    param([int] $ProcessId)

    return Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
}

function Stop-StateProcesses {
    $state = Get-State

    if (-not $state) {
        Write-Host 'Aucun environnement local pilote par scripts/dev.ps1.' -ForegroundColor Yellow
        return
    }

    foreach ($entry in @($state.web, $state.vite)) {
        $entryPids = @()

        if ($entry.PSObject.Properties.Name -contains 'pids') {
            $entryPids = @($entry.pids)
        } elseif ($entry.PSObject.Properties.Name -contains 'pid') {
            $entryPids = @($entry.pid)
        }

        foreach ($processId in $entryPids) {
            $process = Get-AliveProcess -ProcessId $processId

            if ($process) {
                Stop-Process -Id $processId -Force
                Write-Host "Processus arrete: PID $processId" -ForegroundColor Green
            }
        }
    }

    Remove-State
}

function Ensure-Prerequisites {
    Write-Step 'Verification des prerequis'

    foreach ($command in @('php', 'composer', 'npm.cmd')) {
        if (-not (Test-Command -Name $command)) {
            Fail "Commande introuvable: $command"
        }
    }

    if (-not (Test-Path (Join-Path $appPath 'artisan'))) {
        Fail "Application Laravel introuvable dans $appPath"
    }

    if (-not (Test-Path (Join-Path $appPath 'vendor\autoload.php'))) {
        Fail 'Dependances PHP absentes. Lancez composer install dans app/.'
    }

    if (-not (Test-Path (Join-Path $appPath 'node_modules'))) {
        Fail 'Dependances Node absentes. Lancez npm install dans app/.'
    }

    Ensure-Directory -Path $runtimePath
    Ensure-Directory -Path $logPath
}

function Ensure-Environment {
    Write-Step 'Verification de .env et de la base locale'

    $envFile = Join-Path $appPath '.env'
    $envExampleFile = Join-Path $appPath '.env.example'

    if (-not (Test-Path $envFile)) {
        Copy-Item $envExampleFile $envFile
        Write-Host '.env cree depuis .env.example' -ForegroundColor Green
    }

    $envContent = Get-Content -Raw $envFile

    if ($envContent -notmatch '(?m)^APP_KEY=.+') {
        & php artisan key:generate --ansi --no-interaction | Out-Host
    }

    if ($envContent -match '(?m)^DB_CONNECTION=sqlite\s*$') {
        $sqliteFile = Join-Path $appPath 'database\database.sqlite'

        if (-not (Test-Path $sqliteFile)) {
            New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
            Write-Host 'database/database.sqlite cree' -ForegroundColor Green
        }
    }
}

function Ensure-PortsAvailable {
    Write-Step 'Verification des ports'

    foreach ($port in @($webPort, $vitePort)) {
        $listener = Get-PortListener -Port $port

        if ($listener) {
            Fail "Le port $port est deja utilise par le PID $($listener.OwningProcess)."
        }
    }
}

function Start-Services {
    Write-Step 'Migrations Laravel'
    & php artisan migrate --ansi --no-interaction | Out-Host

    Write-Step 'Demarrage du serveur Laravel'
    $laravelProcess = Start-Process php -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8001', '--no-reload' -WorkingDirectory $appPath -RedirectStandardOutput (Join-Path $logPath 'laravel.out.log') -RedirectStandardError (Join-Path $logPath 'laravel.err.log') -PassThru

    if (-not (Wait-ForPort -Port $webPort)) {
        Stop-Process -Id $laravelProcess.Id -Force -ErrorAction SilentlyContinue
        Fail 'Le serveur Laravel n a pas demarre a temps.'
    }

    Write-Step 'Demarrage de Vite'
    $viteProcess = Start-Process npm.cmd -ArgumentList 'run', 'dev', '--', '--host', '127.0.0.1', '--port', '5173' -WorkingDirectory $appPath -RedirectStandardOutput (Join-Path $logPath 'vite.out.log') -RedirectStandardError (Join-Path $logPath 'vite.err.log') -PassThru

    if (-not (Wait-ForPort -Port $vitePort)) {
        Stop-Process -Id $laravelProcess.Id -Force -ErrorAction SilentlyContinue
        Stop-Process -Id $viteProcess.Id -Force -ErrorAction SilentlyContinue
        Fail 'Vite n a pas demarre a temps.'
    }

    if (-not (Wait-ForHttpOk -Url $dashboardUrl)) {
        Stop-Process -Id $laravelProcess.Id -Force -ErrorAction SilentlyContinue
        Stop-Process -Id $viteProcess.Id -Force -ErrorAction SilentlyContinue
        Fail "L URL $dashboardUrl ne repond pas correctement."
    }

    $webListener = Get-PortListener -Port $webPort
    $viteListener = Get-PortListener -Port $vitePort

    Save-State -LaravelPids @($laravelProcess.Id, $webListener.OwningProcess) -VitePids @($viteProcess.Id, $viteListener.OwningProcess)

    Write-Host ''
    Write-Host "Environnement local demarre." -ForegroundColor Green
    Write-Host "URL: $dashboardUrl" -ForegroundColor Green
    Write-Host "Laravel log: $(Join-Path $logPath 'laravel.out.log')"
    Write-Host "Vite log: $(Join-Path $logPath 'vite.out.log')"
    Write-Host "Arret propre: powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action stop"
}

function Show-Status {
    $state = Get-State

    if (-not $state) {
        Write-Host 'Statut: arrete' -ForegroundColor Yellow
        return
    }

    $webPortActive = Get-PortListener -Port $state.web.port
    $vitePortActive = Get-PortListener -Port $state.vite.port

    if ($webPortActive -and $vitePortActive) {
        $webPids = if ($state.web.PSObject.Properties.Name -contains 'pids') { @($state.web.pids) } else { @($state.web.pid) }
        $vitePids = if ($state.vite.PSObject.Properties.Name -contains 'pids') { @($state.vite.pids) } else { @($state.vite.pid) }

        Write-Host 'Statut: demarre' -ForegroundColor Green
        Write-Host "URL: $($state.web.url)"
        Write-Host "Laravel PID(s): $($webPids -join ', ')"
        Write-Host "Vite PID(s): $($vitePids -join ', ')"
        return
    }

    Write-Host 'Statut: incoherent (PID manquants ou processus arretes)' -ForegroundColor Yellow
    if ($webPortActive) {
        Write-Host "Laravel ecoute encore sur le port $($state.web.port)"
    }
    if ($vitePortActive) {
        Write-Host "Vite ecoute encore sur le port $($state.vite.port)"
    }
}

switch ($Action) {
    'start' {
        $state = Get-State

        if ($state) {
            $webPortActive = Get-PortListener -Port $state.web.port
            $vitePortActive = Get-PortListener -Port $state.vite.port

            if ($webPortActive -and $vitePortActive) {
                Write-Host "Environnement deja demarre: $($state.web.url)" -ForegroundColor Green
                exit 0
            }

            Remove-State
        }

        Ensure-Prerequisites
        Ensure-Environment
        Ensure-PortsAvailable
        Start-Services
    }
    'stop' {
        Stop-StateProcesses
    }
    'status' {
        Show-Status
    }
}
