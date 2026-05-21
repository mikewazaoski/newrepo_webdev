# Build and start Pet Pantry with Docker Compose
Set-Location $PSScriptRoot

# Apache/XAMPP on port 80 causes 404 — offer one-click fix
$apacheOn80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue |
    Where-Object { (Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue).ProcessName -eq 'httpd' }
if ($apacheOn80) {
    Write-Host "WARNING: Apache is using port 80 (XAMPP). http://localhost/login will NOT work." -ForegroundColor Red
    Write-Host "  Fix: double-click Run-Fix-As-Admin.bat in this folder (requires Admin / UAC)." -ForegroundColor Yellow
    Write-Host "  Or use: http://localhost:8080/login`n" -ForegroundColor Yellow
}

Write-Host "Building and starting Pet Pantry..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker Compose failed. Is Docker Desktop running?" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 5
$code80 = curl.exe -s -o NUL -w "%{http_code}" http://localhost/login 2>$null

Write-Host ""
if ($code80 -eq "200") {
    Write-Host "Pet Pantry:  http://localhost/" -ForegroundColor Green
    Write-Host "Login:       http://localhost/login" -ForegroundColor Green
} else {
    Write-Host "Login:       http://localhost:8080/login" -ForegroundColor Green
    Write-Host "(Port 80 not responding — check: docker compose ps)" -ForegroundColor Yellow
}
Write-Host "phpMyAdmin:  http://localhost:8081" -ForegroundColor Green
Write-Host "MySQL:       127.0.0.1:3307 (pets_user / pets_password)" -ForegroundColor Green
Write-Host ""
Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
