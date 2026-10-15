param(
    [int]$Port = 8000, [string]$PhpPath = '', [switch]$NoWorker,
    # Trial lokal di PostgreSQL (database brudel_trial); -ResetData mengosongkan database lalu mengisi TrialSeeder.
    [switch]$PostgresTrial, [switch]$ResetData,
    [string]$PgBin = 'C:\laragon\bin\postgresql\postgresql-17.11\bin', [string]$PgData = 'C:\laragon\data\postgresql-17'
)
$ErrorActionPreference = 'Stop'
$project = Join-Path $PSScriptRoot 'app'
if (!$PhpPath) {
    $found = Get-Command php -ErrorAction SilentlyContinue
    if ($found) { $PhpPath = $found.Source }
    else { $PhpPath = 'C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64\php.exe' }
}
if (!(Test-Path -LiteralPath $PhpPath)) { throw 'PHP tidak ditemukan. Jalankan dengan -PhpPath lokasi-php.exe.' }
if (!(Test-Path -LiteralPath (Join-Path $project 'vendor\autoload.php'))) { throw 'Jalankan composer install di folder app terlebih dahulu.' }
$saved = @{}
if ($PostgresTrial) {
    if (!(Test-Path -LiteralPath (Join-Path $PgBin 'pg_ctl.exe'))) { throw 'PostgreSQL tidak ditemukan. Jalankan dengan -PgBin folder-bin-postgresql.' }
    & (Join-Path $PgBin 'pg_isready.exe') -h 127.0.0.1 -p 5432 | Out-Null
    if ($LASTEXITCODE -ne 0) { & (Join-Path $PgBin 'pg_ctl.exe') -D $PgData -l (Join-Path $PgData 'server.log') -w start }
    # pdo_pgsql dimuat per proses (termasuk proses anak artisan serve) tanpa mengubah php.ini.
    $ini = Join-Path $env:TEMP 'brudel-pgsql-ini'
    New-Item -ItemType Directory -Force -Path $ini | Out-Null
    Set-Content -LiteralPath (Join-Path $ini 'pgsql.ini') -Value 'extension=pdo_pgsql' -Encoding ascii
    $trial = @{ PHP_INI_SCAN_DIR = $ini; DB_CONNECTION = 'pgsql'; DB_HOST = '127.0.0.1'; DB_PORT = '5432'; DB_DATABASE = 'brudel_trial'; DB_USERNAME = 'brudel_trial'; DB_PASSWORD = 'BrudelTrial!2026' }
    foreach ($name in $trial.Keys) {
        $saved[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
        [Environment]::SetEnvironmentVariable($name, $trial[$name], 'Process')
    }
}
Push-Location -LiteralPath $project
$workers = @()
try {
    if ($ResetData) { & $PhpPath artisan migrate:fresh --force --seeder=TrialSeeder }
    else { & $PhpPath artisan migrate --force }
    if (!$NoWorker) {
        # Notifikasi dikirim melalui queue; scheduler melepas undangan tim kedaluwarsa.
        $workers += Start-Process -FilePath $PhpPath -ArgumentList 'artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=60' -WorkingDirectory $project -NoNewWindow -PassThru
        $workers += Start-Process -FilePath $PhpPath -ArgumentList 'artisan', 'schedule:work' -WorkingDirectory $project -NoNewWindow -PassThru
    }
    & $PhpPath artisan serve --host=127.0.0.1 --port=$Port
}
finally {
    foreach ($worker in $workers) { if ($worker -and !$worker.HasExited) { Stop-Process -Id $worker.Id -Force } }
    foreach ($name in $saved.Keys) { [Environment]::SetEnvironmentVariable($name, $saved[$name], 'Process') }
    Pop-Location
}
