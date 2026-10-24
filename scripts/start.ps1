$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath (Split-Path -Parent $PSScriptRoot)
if (!(Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
if (!(Test-Path -LiteralPath 'vendor/autoload.php')) { Write-Host 'Run composer install first.'; exit 1 }
php artisan serve --host=127.0.0.1 --port=8000
