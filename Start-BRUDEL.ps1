param([int]$Port = 8000, [string]$PhpPath = '', [switch]$NoWorker)
$ErrorActionPreference = 'Stop'
$project = Join-Path $PSScriptRoot 'app'
if (!$PhpPath) {
    $found = Get-Command php -ErrorAction SilentlyContinue
    if ($found) { $PhpPath = $found.Source }
    else { $PhpPath = 'C:\laragon\bin\php\php-8.4.25-Win32-vs17-x64\php.exe' }
}
if (!(Test-Path -LiteralPath $PhpPath)) { throw 'PHP tidak ditemukan. Jalankan dengan -PhpPath lokasi-php.exe.' }
if (!(Test-Path -LiteralPath (Join-Path $project 'vendor\autoload.php'))) { throw 'Jalankan composer install di folder app terlebih dahulu.' }
Push-Location -LiteralPath $project
$workers = @()
try {
    & $PhpPath artisan migrate --force
    if (!$NoWorker) {
        # Notifikasi dikirim melalui queue; scheduler melepas undangan tim kedaluwarsa.
        $workers += Start-Process -FilePath $PhpPath -ArgumentList 'artisan', 'queue:work', '--sleep=3', '--tries=3', '--timeout=60' -WorkingDirectory $project -NoNewWindow -PassThru
        $workers += Start-Process -FilePath $PhpPath -ArgumentList 'artisan', 'schedule:work' -WorkingDirectory $project -NoNewWindow -PassThru
    }
    & $PhpPath artisan serve --host=127.0.0.1 --port=$Port
}
finally {
    foreach ($worker in $workers) { if ($worker -and !$worker.HasExited) { Stop-Process -Id $worker.Id -Force } }
    Pop-Location
}
