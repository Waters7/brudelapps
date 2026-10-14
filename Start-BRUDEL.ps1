param([int]$Port = 8000, [string]$PhpPath = '')
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
try { & $PhpPath artisan serve --host=127.0.0.1 --port=$Port }
finally { Pop-Location }
