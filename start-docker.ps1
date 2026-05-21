# Build and start Pet Pantry with Docker Compose
Set-Location $PSScriptRoot

Write-Host "Starting Pet Pantry (Docker on port 8080)..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker Compose failed. Is Docker Desktop running?" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 5
$code8080 = curl.exe -s -o NUL -w "%{http_code}" http://localhost:8080/login

Write-Host ""
Write-Host "Docker app login:  http://localhost:8080/login  (HTTP $code8080)" -ForegroundColor Green
Write-Host "phpMyAdmin:        http://localhost:8081" -ForegroundColor Green

$port80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($port80) {
    $proc = Get-Process -Id $port80.OwningProcess -ErrorAction SilentlyContinue
    if ($proc -and $proc.ProcessName -match 'httpd') {
        Write-Host ""
        Write-Host "XAMPP uses port 80 — http://localhost/login shows Apache 404 until you run:" -ForegroundColor Yellow
        Write-Host "  Right-click PowerShell -> Run as Administrator" -ForegroundColor Yellow
        Write-Host "  .\scripts\install-xampp-proxy.ps1" -ForegroundColor Yellow
        Write-Host "  Then restart Apache in XAMPP Control Panel." -ForegroundColor Yellow
    }
}

Write-Host ""
Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
